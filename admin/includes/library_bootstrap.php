<?php
/**
 * Shared bootstrap for library admin pages.
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
require_once __DIR__ . '/../../includes/library_helper.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

ensureLibraryTables($conn);
library_require_access($conn);

$active_theme = loadActiveTheme($conn);
$adminUser = (string) ($_SESSION['admin'] ?? '');
$isMasterAdmin = (($_SESSION['admin_role'] ?? '') === 'master_admin');
$libraryCsrf = (string) $_SESSION['csrf_token'];

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? 'success';
unset($_SESSION['message'], $_SESSION['message_type']);

$libraryCurrent = basename($_SERVER['PHP_SELF']);
$libraryNav = [
    ['file' => 'library.php', 'label' => 'Library Home', 'icon' => 'book'],
    ['file' => 'library_stock.php', 'label' => 'Stock Register', 'icon' => 'boxes-stacked'],
    ['file' => 'library_student_issues.php', 'label' => 'Student Issue / Return', 'icon' => 'user-graduate'],
    ['file' => 'library_staff_issues.php', 'label' => 'Staff Issue / Return', 'icon' => 'chalkboard-teacher'],
];

if (!function_exists('libraryVerifyCsrf')) {
    function libraryVerifyCsrf(): bool
    {
        $token = (string) ($_POST['csrf_token'] ?? '');
        return hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token);
    }
}

if (!function_exists('libraryFlashRedirect')) {
    function libraryFlashRedirect(string $url, string $msg, bool $ok): void
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
