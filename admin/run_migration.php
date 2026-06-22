<?php
/**
 * Master Admin AJAX endpoint to run a migrations/ script safely.
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/migration_runner_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

if (($_SESSION['admin_role'] ?? '') !== 'master_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only Master Admin can run migrations.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required.']);
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if ($token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Refresh the page and try again.']);
    exit;
}

$filename = $_POST['migration_file'] ?? '';
$command = $_POST['migration_command'] ?? 'install';
$runBy = (string) ($_SESSION['admin'] ?? $_SESSION['admin_username'] ?? 'master_admin');

$responseSent = false;
$ajaxResult = null;

register_shutdown_function(static function () use (&$responseSent, &$ajaxResult) {
    if ($responseSent) {
        return;
    }

    $buffered = '';
    while (ob_get_level() > 0) {
        $buffered = ob_get_contents() . $buffered;
        ob_end_clean();
    }

    if ($ajaxResult === null) {
        $ajaxResult = [
            'success' => true,
            'message' => 'Migration completed.',
            'filename' => '',
            'output' => $buffered !== '' ? $buffered : 'Migration finished (script exited).',
            'error' => '',
        ];
    } elseif ($buffered !== '' && empty($ajaxResult['output'])) {
        $ajaxResult['output'] = $buffered;
    }

    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode($ajaxResult);
});

ob_start();

try {
    $result = migration_runner_execute($conn, $filename, $runBy, $command);
    $ajaxResult = [
        'success' => $result['success'],
        'message' => $result['success'] ? 'Migration completed.' : 'Migration failed.',
        'filename' => $result['filename'],
        'output' => $result['output'],
        'error' => $result['error'],
    ];
    $responseSent = true;

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    echo json_encode($ajaxResult);
} catch (InvalidArgumentException $e) {
    $responseSent = true;
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    $responseSent = true;
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
