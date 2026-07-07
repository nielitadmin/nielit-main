<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_once __DIR__ . '/../includes/staff_profile_helper.php';
require_once __DIR__ . '/../includes/render_staff_profile_pdf.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['admin_role']) || !isset($_SESSION['admin_id'])) {
    if (!init_admin_session($_SESSION['admin'])) {
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit();
    }
}

$admin_role = $_SESSION['admin_role'] ?? '';
if (!in_array($admin_role, ['master_admin'], true)) {
    header('Location: dashboard.php');
    exit();
}

$facultyId = (int) ($_GET['id'] ?? 0);
if ($facultyId <= 0) {
    http_response_code(400);
    exit('Invalid staff ID.');
}

$staff = loadStaffProfileById($conn, $facultyId);
if (!$staff) {
    http_response_code(404);
    exit('Staff member not found.');
}

renderStaffProfilePdf($staff);
