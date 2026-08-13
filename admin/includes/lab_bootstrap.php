<?php
/**
 * Shared bootstrap for Lab Instruments and IT Lab admin pages.
 * Expects $labModule = 'instrument'|'itlab'
 */
require_once __DIR__ . '/../../includes/url_helper.php';
require_once __DIR__ . '/../../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../../includes/admin_assets.php';
session_start();

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/theme_loader.php';
require_once __DIR__ . '/../../includes/session_manager.php';
require_once __DIR__ . '/../../includes/labs_helper.php';

if (empty($labModule) || !labModule($labModule)) {
    $_SESSION['message'] = 'Unknown lab module.';
    $_SESSION['message_type'] = 'danger';
    header('Location: dashboard.php');
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

ensureLabTables($conn);
lab_require_access($conn, $labModule);

$lab = labModule($labModule);
$active_theme = loadActiveTheme($conn);
$adminUser = (string) ($_SESSION['admin'] ?? '');
$isMasterAdmin = (($_SESSION['admin_role'] ?? '') === 'master_admin');
$labCsrf = (string) $_SESSION['csrf_token'];

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? 'success';
unset($_SESSION['message'], $_SESSION['message_type']);

$labCurrent = basename($_SERVER['PHP_SELF']);
$labNav = [
    ['file' => $lab['home'], 'label' => $lab['short'] . ' Home', 'icon' => $lab['icon']],
    ['file' => $lab['stock'], 'label' => !empty($lab['has_parts']) ? 'Systems & parts' : 'Stock Register', 'icon' => $lab['stock_icon']],
    ['file' => $lab['student'], 'label' => 'Student Issue / Return', 'icon' => 'user-graduate'],
    ['file' => $lab['staff'], 'label' => 'Staff Issue / Return', 'icon' => 'chalkboard-teacher'],
];

if (!function_exists('labVerifyCsrf')) {
    function labVerifyCsrf(): bool
    {
        $token = (string) ($_POST['csrf_token'] ?? '');
        return hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token);
    }
}

if (!function_exists('labFlashRedirect')) {
    function labFlashRedirect(string $url, string $msg, bool $ok): void
    {
        $_SESSION['message'] = $msg;
        $_SESSION['message_type'] = $ok ? 'success' : 'danger';
        if ($ok) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        header('Location: ' . $url);
        exit();
    }
}
