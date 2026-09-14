<?php
/**
 * Requirements module removed — redirect old bookmarks.
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/url_helper.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit();
}

$_SESSION['message'] = 'The Requirements page has been removed. Use Students to manage candidate records.';
$_SESSION['message_type'] = 'info';
header('Location: ' . app_url('admin/students'));
exit();
