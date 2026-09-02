<?php
/**
 * Master Admin AJAX endpoint to run a migrations/ script safely.
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/migration_runner_helper.php';

$migrationJsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
    $migrationJsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
}

$migrationJsonEncode = static function ($payload) use ($migrationJsonFlags): string {
    if (!is_array($payload)) {
        $payload = ['success' => false, 'message' => 'Unexpected migration result.'];
    }
    if (isset($payload['output']) && is_string($payload['output']) && strlen($payload['output']) > 200000) {
        $payload['output'] = substr($payload['output'], 0, 200000) . "\n\n[output truncated]";
    }
    $json = json_encode($payload, $migrationJsonFlags);
    if (!is_string($json) || $json === '') {
        return '{"success":false,"message":"Could not encode migration output."}';
    }
    return $json;
};

$migrationJsonExit = static function ($payload, $code = 200) use ($migrationJsonEncode): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code((int) $code);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo $migrationJsonEncode($payload);
    exit;
};

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin'])) {
    $migrationJsonExit(['success' => false, 'message' => 'Unauthorized.'], 403);
}

if (($_SESSION['admin_role'] ?? '') !== 'master_admin') {
    $migrationJsonExit(['success' => false, 'message' => 'Only Master Admin can run migrations.'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $migrationJsonExit(['success' => false, 'message' => 'POST required.'], 405);
}

$token = $_POST['csrf_token'] ?? '';
if ($token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    $migrationJsonExit(['success' => false, 'message' => 'Invalid security token. Refresh the page and try again.'], 403);
}

$filename = $_POST['migration_file'] ?? '';
$command = $_POST['migration_command'] ?? 'install';
$runBy = (string) ($_SESSION['admin'] ?? $_SESSION['admin_username'] ?? 'master_admin');

$responseSent = false;
$ajaxResult = null;

register_shutdown_function(static function () use (&$responseSent, &$ajaxResult, $migrationJsonEncode) {
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

    echo $migrationJsonEncode($ajaxResult);
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
    $migrationJsonExit($ajaxResult);
} catch (InvalidArgumentException $e) {
    $responseSent = true;
    $migrationJsonExit(['success' => false, 'message' => $e->getMessage()], 400);
} catch (Throwable $e) {
    $responseSent = true;
    $migrationJsonExit(['success' => false, 'message' => $e->getMessage()], 500);
}
