<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session_manager.php';

if (isset($_SESSION['admin']) && ($_SESSION['admin_role'] ?? '') === 'faculty') {
    header('Location: ' . rtrim((string) APP_URL, '/') . '/admin/manage_class_timetable.php');
    exit();
}

header('Location: ' . rtrim((string) APP_URL, '/') . '/admin/login');
exit();
