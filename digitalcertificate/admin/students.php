<?php
// admin/students.php – Manage Students (Step 2 of Certificate Issuance Flow)
session_start();

// Redirect if not logged in as admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

require_once '../config/database.php';
require_once '../config/mis_api.php';

// ── 5) Import single student from MIS (AJAX POST) ──────────────────────────
if (isset($_POST['action_type']) && $_POST['action_type'] === 'import_from_mis') {
    header('Content-Type: application/json');
    $student_id  = trim($_POST['student_id']  ?? '');
    $name        = trim($_POST['name']        ?? '');
    $email       = trim($_POST['email']       ?? '');
    $course      = trim($_POST['course']      ?? '');
    $batch       = trim($_POST['batch']       ?? '');
    $duration    = trim($_POST['duration']    ?? '');

    if (empty($student_id) || empty($name) || empty($email) || empty($course)) {
        echo json_encode(['ok' => false, 'error' => 'Missing required fields from MIS data.']);
        exit;
    }
    try {
        $check = $pdo->prepare("SELECT id FROM students WHERE student_id = :sid OR email = :email");
        $check->execute([':sid' => $student_id, ':email' => $email]);
        if ($check->rowCount() > 0) {
            echo json_encode(['ok' => false, 'error' => 'Student already exists in this portal.']);
            exit;
        }
        $stmt = $pdo->prepare("
            INSERT INTO students (student_id, name, email, course, batch, duration, created_at)
            VALUES (:sid, :name, :email, :course, :batch, :duration, NOW())
        ");
        $stmt->execute([
            ':sid'      => $student_id,
            ':name'     => $name,
            ':email'    => $email,
            ':course'   => $course,
            ':batch'    => $batch,
            ':duration' => $duration,
        ]);
        echo json_encode(['ok' => true, 'message' => 'Student imported successfully.']);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'error' => 'DB error: ' . $e->getMessage()]);
    }
    exit;
}

// ── 6) MIS Search (AJAX GET) ──────────────────────────────────────────────
if (isset($_GET['mis_search'])) {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 2) {
        echo json_encode(['ok' => false, 'error' => 'Enter at least 2 characters.']);
        exit;
    }
    $result = mis_api_get('students.php', ['action' => 'search', 'q' => $q, 'limit' => 50]);
    if (!$result['ok']) {
        echo json_encode(['ok' => false, 'error' => $result['error']]);
        exit;
    }
    $students_raw = $result['data']['students'] ?? [];
    $out = [];
    foreach ($students_raw as $s) {
        $out[] = [
            'student_id' => $s['student_id']  ?? '',
            'name'       => $s['name']         ?? '',
            'email'      => $s['email']        ?? '',
            'course'     => $s['course_name']  ?? '',
            'batch'      => $s['batch_name']   ?? '',
            'duration'   => '',
            'photo_url'  => $s['photo_url']    ?? '',
            'mobile'     => $s['mobile']       ?? '',
        ];
    }
    echo json_encode(['ok' => true, 'students' => $out]);
    exit;
}

// --- Handle POST actions: Add, Edit, Delete, Import ---
$message = '';
$messageType = '';

// Capture the hidden action type
$action_type = $_POST['action_type'] ?? '';

// 1) Add new student
if (isset($_POST['add_student']) || $action_type === 'add_student') {
    $student_id = trim($_POST['student_id'] ?? '');
    $name       = trim($_POST['name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $course     = trim($_POST['course'] ?? '');
    $batch      = trim($_POST['batch'] ?? '');
    $duration   = trim($_POST['duration'] ?? '');

    if (empty($student_id) || empty($name) || empty($email) || empty($course)) {
        $message = 'Please fill in all required fields (ID, Name, Email, Course).';
        $messageType = 'error';
    } else {
        try {
            // Check if student_id or email already exists
            $check = $pdo->prepare("SELECT id FROM students WHERE student_id = :sid OR email = :email");
            $check->execute([':sid' => $student_id, ':email' => $email]);
            if ($check->rowCount() > 0) {
                $message = 'A student with this ID or Email already exists.';
                $messageType = 'error';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO students (student_id, name, email, course, batch, duration, created_at)
                    VALUES (:sid, :name, :email, :course, :batch, :duration, NOW())
                ");
                $stmt->execute([
                    ':sid'      => $student_id,
                    ':name'     => $name,
                    ':email'    => $email,
                    ':course'   => $course,
                    ':batch'    => $batch,
                    ':duration' => $duration
                ]);
                $message = 'Student added successfully!';
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Database error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// 2) Edit student
if (isset($_POST['edit_student']) || $action_type === 'edit_student') {
    $id         = (int)($_POST['id'] ?? 0);
    $student_id = trim($_POST['student_id'] ?? '');
    $name       = trim($_POST['name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $course     = trim($_POST['course'] ?? '');
    $batch      = trim($_POST['batch'] ?? '');
    $duration   = trim($_POST['duration'] ?? '');

    if (empty($student_id) || empty($name) || empty($email) || empty($course)) {
        $message = 'Please fill in all required fields.';
        $messageType = 'error';
    } else {
        try {
            // Check uniqueness excluding current record
            $check = $pdo->prepare("
                SELECT id FROM students 
                WHERE (student_id = :sid OR email = :email) AND id != :id
            ");
            $check->execute([':sid' => $student_id, ':email' => $email, ':id' => $id]);
            if ($check->rowCount() > 0) {
                $message = 'Another student with this ID or Email already exists.';
                $messageType = 'error';
            } else {
                $stmt = $pdo->prepare("
                    UPDATE students 
                    SET student_id = :sid, name = :name, email = :email, 
                        course = :course, batch = :batch, duration = :duration
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':sid'      => $student_id,
                    ':name'     => $name,
                    ':email'    => $email,
                    ':course'   => $course,
                    ':batch'    => $batch,
                    ':duration' => $duration,
                    ':id'       => $id
                ]);
                $message = 'Student updated successfully!';
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Database error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// 3) Delete student
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM students WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $message = 'Student deleted successfully.';
        $messageType = 'success';
    } catch (PDOException $e) {
        $message = 'Cannot delete: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// 4) Import CSV
if (isset($_POST['import_csv']) && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
    $file = $_FILES['csv_file']['tmp_name'];
    // Validate file type
    $mime = mime_content_type($file);
    if ($mime !== 'text/csv' && $mime !== 'text/plain') {
        $message = 'Please upload a valid CSV file.';
        $messageType = 'error';
    } else {
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle); // first row as header
        // Expected header: student_id, name, email, course, batch, duration
        $expected = ['student_id', 'name', 'email', 'course', 'batch', 'duration'];
        // Normalize header to lowercase
        $header = array_map('strtolower', $header);
        if (count(array_intersect($expected, $header)) !== count($expected)) {
            $message = 'CSV header must contain: ' . implode(', ', $expected);
            $messageType = 'error';
        } else {
            $imported = 0;
            $errors = 0;
            while (($row = fgetcsv($handle)) !== false) {
                // Map row to associative array using header
                $data = array_combine($header, $row);
                if (!$data) continue;
                $student_id = trim($data['student_id'] ?? '');
                $name       = trim($data['name'] ?? '');
                $email      = trim($data['email'] ?? '');
                $course     = trim($data['course'] ?? '');
                $batch      = trim($data['batch'] ?? '');
                $duration   = trim($data['duration'] ?? '');

                // Skip if missing required
                if (empty($student_id) || empty($name) || empty($email) || empty($course)) {
                    $errors++;
                    continue;
                }

                try {
                    // Check duplicates
                    $check = $pdo->prepare("SELECT id FROM students WHERE student_id = :sid OR email = :email");
                    $check->execute([':sid' => $student_id, ':email' => $email]);
                    if ($check->rowCount() > 0) {
                        $errors++;
                        continue;
                    }
                    $stmt = $pdo->prepare("
                        INSERT INTO students (student_id, name, email, course, batch, duration, created_at)
                        VALUES (:sid, :name, :email, :course, :batch, :duration, NOW())
                    ");
                    $stmt->execute([
                        ':sid'      => $student_id,
                        ':name'     => $name,
                        ':email'    => $email,
                        ':course'   => $course,
                        ':batch'    => $batch,
                        ':duration' => $duration
                    ]);
                    $imported++;
                } catch (PDOException $e) {
                    $errors++;
                }
            }
            fclose($handle);
            $message = "CSV import completed. $imported students added, $errors skipped (duplicates or missing data).";
            $messageType = $imported > 0 ? 'success' : 'error';
        }
    }
}

// --- Fetch all students for listing ---
$students = [];
try {
    $stmt = $pdo->query("SELECT * FROM students ORDER BY created_at DESC");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If table doesn't exist, we'll show empty list and maybe hint to create
    $students = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manage Students – Admin</title>
    <link rel="icon" type="image/png" href="../assets/images/RR.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Noto+Sans+Devanagari:wght@500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        /* ── Same root variables as dashboard ── */
        :root {
            --primary: #155E75;
            --primary-light: #0284C7;
            --primary-bg: #EFF6FF;
            --candidate: #059669;
            --candidate-bg: #ECFDF5;
            --text-dark: #0F172A;
            --text-muted: #475569;
            --bg-body: #F8FAFC;
            --surface: #FFFFFF;
            --border: #E2E8F0;
            --gold: #D97706;
            --alert: #DC2626;
            --alert-bg: #FEF2F2;
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
            --sidebar-bg: #FFFFFF;
            --sidebar-hover: var(--primary-bg);
            --sidebar-active: var(--primary);
            --sidebar-text: var(--text-dark);
            --sidebar-icon: var(--text-muted);
            --card-bg: #FFFFFF;
        }

        html[data-theme="dark"] {
            --primary: #38BDF8;
            --primary-light: #7DD3FC;
            --primary-bg: #0C2536;
            --candidate: #34D399;
            --candidate-bg: #052E22;
            --text-dark: #F1F5F9;
            --text-muted: #94A3B8;
            --bg-body: #0B1220;
            --surface: #111827;
            --border: #1F2A3C;
            --gold: #FBBF24;
            --alert: #FCA5A5;
            --alert-bg: #3B1F1F;
            --sidebar-bg: #111827;
            --sidebar-hover: #1F2A3C;
            --sidebar-active: var(--primary);
            --sidebar-text: #E2E8F0;
            --sidebar-icon: #94A3B8;
            --card-bg: #111827;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { transition: background-color 0.25s ease; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text-dark);
            background-color: var(--bg-body);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Theme Toggle (reused) */
        .theme-toggle-wrap {
            position: fixed; bottom: 26px; right: 26px;
            display: flex; align-items: center; gap: 10px; z-index: 1000;
        }
        .theme-toggle-label {
            background: var(--surface); border: 1px solid var(--border);
            color: var(--text-dark); font-size: 12.5px; font-weight: 700;
            padding: 8px 14px; border-radius: 20px; box-shadow: var(--shadow-sm);
            white-space: nowrap; opacity: 0; transform: translateX(8px);
            transition: opacity 0.3s ease, transform 0.3s ease;
            pointer-events: none;
        }
        .theme-toggle-label.show { opacity: 1; transform: translateX(0); }
        .theme-toggle-btn {
            width: 56px; height: 56px; border-radius: 50%;
            background: var(--surface); border: 1px solid var(--border);
            box-shadow: var(--shadow-md); display: flex; align-items: center;
            justify-content: center; cursor: pointer; transition: 0.25s;
        }
        .theme-toggle-btn i { font-size: 20px; color: var(--primary); }
        .theme-toggle-btn .fa-sun { display: none; }
        html[data-theme="dark"] .theme-toggle-btn .fa-moon { display: none; }
        html[data-theme="dark"] .theme-toggle-btn .fa-sun { display: inline-block; }
        .theme-toggle-btn.spin i { transform: rotate(180deg); }

        /* Top Header (same as dashboard) */
        .top-header {
            background: var(--sidebar-bg); border-bottom: 1px solid var(--border);
            position: sticky; top: 0; width: 100%; z-index: 100;
        }
        .header-container {
            display: flex; justify-content: space-between; align-items: center;
            max-width: 1380px; margin: 0 auto; padding: 12px 40px;
            flex-wrap: wrap;
        }
        .header-left { display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
        .nielit-logo { height: 50px; width: auto; }
        .header-titles { display: flex; flex-direction: column; }
        .hindi-title {
            font-family: 'Noto Sans Devanagari', sans-serif;
            font-size: 15px; color: var(--primary); font-weight: 700;
        }
        .eng-title { font-size: 13px; font-weight: 600; color: var(--text-dark); }
        .ministry-text {
            display: flex; flex-direction: column; font-size: 11px;
            color: var(--text-muted); font-weight: 600;
        }
        .ministry-text strong { font-size: 12px; color: var(--text-dark); }
        .emblem { height: 50px; margin-left: 5px; }

        /* Layout: sidebar + main */
        .app-layout { display: flex; flex: 1; min-height: calc(100vh - 75px); }

        .sidebar {
            width: 260px; background: var(--sidebar-bg); border-right: 1px solid var(--border);
            padding: 24px 16px; position: sticky; top: 75px;
            height: calc(100vh - 75px); overflow-y: auto;
        }
        .sidebar-brand {
            font-size: 15px; font-weight: 800; color: var(--primary);
            padding: 0 8px 20px 8px; border-bottom: 1px solid var(--border);
            margin-bottom: 20px; display: flex; align-items: center; gap: 10px;
        }
        .sidebar-nav { list-style: none; }
        .sidebar-nav li { margin-bottom: 4px; }
        .sidebar-nav a {
            display: flex; align-items: center; gap: 14px;
            padding: 12px 16px; border-radius: 12px;
            color: var(--sidebar-text); text-decoration: none;
            font-weight: 600; font-size: 13px; transition: 0.2s;
        }
        .sidebar-nav a i { width: 20px; text-align: center; color: var(--sidebar-icon); font-size: 16px; }
        .sidebar-nav a:hover { background: var(--sidebar-hover); }
        .sidebar-nav a.active { background: var(--primary-bg); color: var(--primary); }
        .sidebar-nav a.active i { color: var(--primary); }
        .nav-section-title {
            font-size: 11px; font-weight: 800; color: var(--text-muted);
            text-transform: uppercase; letter-spacing: 0.5px;
            padding: 15px 16px 8px 16px; margin-top: 10px;
        }
        .logout-link { margin-top: 30px; border-top: 1px solid var(--border); padding-top: 20px; }
        .logout-link a { color: var(--alert); }
        .logout-link a i { color: var(--alert); }

        /* Main content */
        .main-content { flex: 1; padding: 30px 40px; overflow-y: auto; }
        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 25px; flex-wrap: wrap; gap: 15px;
        }
        .page-header h1 { font-size: 26px; font-weight: 800; color: var(--text-dark); }
        .admin-welcome {
            font-size: 13px; font-weight: 600; color: var(--text-muted);
            background: var(--card-bg); padding: 8px 20px; border-radius: 50px;
            border: 1px solid var(--border); display: flex; align-items: center; gap: 10px;
        }

        /* Messages */
        .message {
            padding: 14px 18px; border-radius: 12px; margin-bottom: 20px;
            font-weight: 600; font-size: 14px;
        }
        .message.success { background: var(--candidate-bg); color: var(--candidate); border: 1px solid var(--candidate); }
        .message.error { background: var(--alert-bg); color: var(--alert); border: 1px solid var(--alert); }

        /* Add / Import buttons */
        .action-toolbar {
            display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px;
        }
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 20px; border-radius: 12px; font-weight: 700;
            font-size: 13px; border: none; cursor: pointer;
            text-decoration: none; transition: 0.2s;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-light); }
        .btn-success { background: var(--candidate); color: white; }
        .btn-success:hover { background: #047857; }
        .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--text-dark); }
        .btn-outline:hover { background: var(--primary-bg); }
        .btn-danger { background: var(--alert); color: white; }
        .btn-danger:hover { opacity: 0.8; }
        .btn-sm { padding: 6px 14px; font-size: 12px; }

        /* Table */
        .table-wrap { overflow-x: auto; background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        table th {
            text-align: left; padding: 14px 12px; font-weight: 700;
            color: var(--text-muted); border-bottom: 2px solid var(--border);
            font-size: 11px; text-transform: uppercase;
        }
        table td { padding: 14px 12px; border-bottom: 1px solid var(--border); color: var(--text-dark); }
        table tr:last-child td { border-bottom: none; }
        .actions-cell { display: flex; gap: 6px; flex-wrap: wrap; }

        /* Modal overlay (for add/edit) */
        .modal-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5);
            z-index: 999; align-items: center; justify-content: center;
            backdrop-filter: blur(4px);
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: var(--card-bg); border-radius: 24px; padding: 30px;
            max-width: 560px; width: 90%; max-height: 90vh; overflow-y: auto;
            box-shadow: var(--shadow-md); border: 1px solid var(--border);
        }
        .modal-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px;
        }
        .modal-header h2 { font-size: 20px; font-weight: 800; color: var(--text-dark); }
        .modal-close {
            background: none; border: none; font-size: 24px; cursor: pointer;
            color: var(--text-muted);
        }
        .modal-close:hover { color: var(--text-dark); }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-weight: 700; font-size: 13px; color: var(--text-dark); margin-bottom: 4px; }
        .form-group input, .form-group select {
            width: 100%; padding: 10px 14px; border: 1px solid var(--border);
            border-radius: 10px; background: var(--bg-body); color: var(--text-dark);
            font-size: 14px; transition: 0.2s;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(21,94,117,0.1);
        }

        /* CSV import hidden file input */
        .csv-upload-wrapper {
            display: inline-block; position: relative;
        }
        .csv-upload-wrapper input[type="file"] {
            position: absolute; left: 0; top: 0; opacity: 0; width: 100%; height: 100%; cursor: pointer;
        }

        @media (max-width: 768px) {
            .app-layout { flex-direction: column; }
            .sidebar { width: 100%; position: static; height: auto; border-right: none; border-bottom: 1px solid var(--border); }
            .main-content { padding: 20px; }
            .header-container { flex-direction: column; gap: 15px; text-align: center; }
            .header-left { justify-content: center; }
            .page-header { flex-direction: column; align-items: flex-start; }
        }
        @media (max-width: 480px) {
            .theme-toggle-wrap { bottom: 18px; right: 18px; }
            .theme-toggle-btn { width: 50px; height: 50px; }
            .action-toolbar .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

    <!-- Theme Toggle -->
    <div class="theme-toggle-wrap">
        <span class="theme-toggle-label" id="themeToggleLabel">Light Mode</span>
        <button class="theme-toggle-btn" id="themeToggleBtn" type="button">
            <i class="fas fa-moon"></i>
            <i class="fas fa-sun"></i>
        </button>
    </div>

    <!-- Header -->
    <header class="top-header">
        <div class="header-container">
            <div class="header-left">
                <img src="../assets/images/image_7c2b82.png" alt="Emblem" class="emblem" />
                <div class="ministry-text">
                    <strong>इलेक्ट्रॉनिकी और सूचना प्रौद्योगिकी मंत्रालय</strong>
                    <strong>Ministry of Electronics &amp; Information Technology</strong>
                </div>
            </div>
            <div class="header-left">
                <div class="header-titles">
                    <span class="hindi-title">राष्ट्रीय इलेक्ट्रॉनिकी एवं सूचना प्रौद्योगिकी संस्थान, भुवनेश्वर</span>
                    <span class="eng-title">National Institute of Electronics &amp; Information Technology, Bhubaneswar</span>
                </div>
            </div>
        </div>
    </header>

    <!-- Layout -->
    <div class="app-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-brand"><i class="fas fa-shield-alt"></i> Issuance Portal</div>
            <ul class="sidebar-nav">
                <li><a href="dashboard.php"><i class="fas fa-chart-pie"></i> Dashboard Overview</a></li>
                <div class="nav-section-title">Data Management</div>
                <li><a href="students.php" class="active"><i class="fas fa-users"></i> Manage Students</a></li>
                <li><a href="generate.php"><i class="fas fa-file-pdf"></i> Generate PDFs &amp; QR</a></li>
                <div class="nav-section-title">e‑Hastakshar Process</div>
                <li><a href="esign_queue.php"><i class="fas fa-signature"></i> Pending eSign Batches</a></li>
                <div class="nav-section-title">Issuance</div>
                <li><a href="issued.php"><i class="fas fa-certificate"></i> Issued Certificates</a></li>
                <li class="logout-link"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Secure Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1>Manage Students</h1>
                <div class="admin-welcome">
                    <i class="fas fa-user-circle"></i>
                    <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?>
                </div>
            </div>

            <!-- Display messages -->
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Toolbar -->
            <div class="action-toolbar">
                <button class="btn btn-primary" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add Student
                </button>
                <button class="btn btn-mis" onclick="openMisModal()">
                    <i class="fas fa-cloud-download-alt"></i> Import from MIS
                </button>
                <div class="csv-upload-wrapper">
                    <button class="btn btn-success"><i class="fas fa-file-csv"></i> Import CSV</button>
                    <form action="" method="POST" enctype="multipart/form-data" id="csvImportForm">
                        <input type="file" name="csv_file" accept=".csv" onchange="document.getElementById('csvImportForm').submit();" />
                        <input type="hidden" name="import_csv" value="1" />
                    </form>
                </div>
                <a href="?export=1" class="btn btn-outline"><i class="fas fa-download"></i> Export CSV</a>
            </div>

            <!-- Student Table -->
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Course</th>
                            <th>Batch</th>
                            <th>Duration</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($students) > 0): ?>
                            <?php foreach ($students as $s): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($s['student_id']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($s['name']); ?></td>
                                    <td><?php echo htmlspecialchars($s['email']); ?></td>
                                    <td><?php echo htmlspecialchars($s['course']); ?></td>
                                    <td><?php echo htmlspecialchars($s['batch']); ?></td>
                                    <td><?php echo htmlspecialchars($s['duration']); ?></td>
                                    <td class="actions-cell">
                                        <button class="btn btn-primary btn-sm" onclick="editStudent(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars($s['student_id']); ?>', '<?php echo htmlspecialchars($s['name']); ?>', '<?php echo htmlspecialchars($s['email']); ?>', '<?php echo htmlspecialchars($s['course']); ?>', '<?php echo htmlspecialchars($s['batch']); ?>', '<?php echo htmlspecialchars($s['duration']); ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?delete=<?php echo $s['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this student and all associated data?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding:30px; color:var(--text-muted);">
                                    <i class="fas fa-info-circle"></i> No students found. Add your first student using the "Add Student" button.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p style="margin-top:12px; font-size:12px; color:var(--text-muted);">
                <i class="fas fa-info-circle"></i> Total students: <?php echo count($students); ?>
            </p>
        </main>
    </div>

    <!-- ─── ADD / EDIT MODAL ─── -->
    <div class="modal-overlay" id="studentModal">
        <div class="modal">
            <div class="modal-header">
                <h2 id="modalTitle">Add Student</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <!-- Empty action so it submits to current URL, avoiding 404s -->
            <form action="" method="POST" id="studentForm">
                <input type="hidden" name="action_type" id="actionType" value="add_student" />
                <input type="hidden" name="id" id="editId" value="" />
                
                <div class="form-group">
                    <label for="student_id">Student ID *</label>
                    <input type="text" id="student_id" name="student_id" required placeholder="e.g. NIELIT/2026/001" />
                </div>
                <div class="form-group">
                    <label for="name">Full Name *</label>
                    <input type="text" id="name" name="name" required placeholder="John Doe" />
                </div>
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required placeholder="john@example.com" />
                </div>
                <div class="form-group">
                    <label for="course">Course *</label>
                    <input type="text" id="course" name="course" required placeholder="e.g. AI/ML, Web Development" />
                </div>
                <div class="form-group">
                    <label for="batch">Batch</label>
                    <input type="text" id="batch" name="batch" placeholder="e.g. Jan-Jun 2026" />
                </div>
                <div class="form-group">
                    <label for="duration">Duration</label>
                    <input type="text" id="duration" name="duration" placeholder="e.g. 6 months" />
                </div>
                <div style="display:flex; gap:12px; justify-content:flex-end; margin-top:12px;">
                    <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="modalSubmitBtn">Add Student</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // Theme toggle (same as dashboard)
        (function() {
            const root = document.documentElement;
            const btn = document.getElementById('themeToggleBtn');
            const label = document.getElementById('themeToggleLabel');
            let hideTimer = null;

            function flashLabel() {
                const isDark = root.getAttribute('data-theme') === 'dark';
                label.textContent = isDark ? 'Dark Mode' : 'Light Mode';
                label.classList.add('show');
                clearTimeout(hideTimer);
                hideTimer = setTimeout(() => label.classList.remove('show'), 1800);
            }
            setTimeout(flashLabel, 500);

            btn.addEventListener('click', () => {
                const isDark = root.getAttribute('data-theme') === 'dark';
                root.setAttribute('data-theme', isDark ? 'light' : 'dark');
                btn.classList.add('spin');
                setTimeout(() => btn.classList.remove('spin'), 400);
                flashLabel();
            });
            btn.addEventListener('mouseenter', flashLabel);
        })();

        // Modal controls
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Student';
            document.getElementById('editId').value = '';
            document.getElementById('student_id').value = '';
            document.getElementById('name').value = '';
            document.getElementById('email').value = '';
            document.getElementById('course').value = '';
            document.getElementById('batch').value = '';
            document.getElementById('duration').value = '';
            
            // Set action type to add
            document.getElementById('actionType').value = 'add_student';
            document.getElementById('modalSubmitBtn').textContent = 'Add Student';
            
            document.getElementById('studentModal').classList.add('active');
        }

        function editStudent(id, student_id, name, email, course, batch, duration) {
            document.getElementById('modalTitle').textContent = 'Edit Student';
            document.getElementById('editId').value = id;
            document.getElementById('student_id').value = student_id;
            document.getElementById('name').value = name;
            document.getElementById('email').value = email;
            document.getElementById('course').value = course;
            document.getElementById('batch').value = batch || '';
            document.getElementById('duration').value = duration || '';
            
            // Set action type to edit
            document.getElementById('actionType').value = 'edit_student';
            document.getElementById('modalSubmitBtn').textContent = 'Update Student';
            
            document.getElementById('studentModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('studentModal').classList.remove('active');
        }

        // Close modal on overlay click
        document.getElementById('studentModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>
    <!-- ─── MIS IMPORT MODAL ─── -->
    <div class="modal-overlay" id="misModal">
        <div class="modal" style="max-width:780px;">
            <div class="modal-header">
                <h2><i class="fas fa-cloud-download-alt" style="color:var(--primary);margin-right:8px;"></i>Import Students from MIS</h2>
                <button class="modal-close" onclick="closeMisModal()">&times;</button>
            </div>

            <!-- Search bar -->
            <div style="display:flex;gap:10px;margin-bottom:16px;">
                <input type="text" id="misSearchInput" placeholder="Enter Student ID or Name (e.g. NIELIT/2025/PPI/0026)"
                    style="flex:1;padding:10px 14px;border:1px solid var(--border);border-radius:10px;background:var(--bg-body);color:var(--text-dark);font-size:14px;"
                    onkeydown="if(event.key==='Enter') doMisSearch()" />
                <button class="btn btn-primary" onclick="doMisSearch()" id="misSearchBtn">
                    <i class="fas fa-search"></i> Search
                </button>
            </div>

            <!-- Status message -->
            <div id="misStatusMsg" style="display:none;margin-bottom:12px;padding:10px 14px;border-radius:10px;font-size:13px;font-weight:600;"></div>

            <!-- Results table -->
            <div id="misResultsWrap" style="display:none;overflow-x:auto;border:1px solid var(--border);border-radius:12px;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr>
                            <th style="padding:12px;text-align:left;border-bottom:2px solid var(--border);font-size:11px;text-transform:uppercase;color:var(--text-muted);">Photo</th>
                            <th style="padding:12px;text-align:left;border-bottom:2px solid var(--border);font-size:11px;text-transform:uppercase;color:var(--text-muted);">Student ID</th>
                            <th style="padding:12px;text-align:left;border-bottom:2px solid var(--border);font-size:11px;text-transform:uppercase;color:var(--text-muted);">Name</th>
                            <th style="padding:12px;text-align:left;border-bottom:2px solid var(--border);font-size:11px;text-transform:uppercase;color:var(--text-muted);">Course</th>
                            <th style="padding:12px;text-align:left;border-bottom:2px solid var(--border);font-size:11px;text-transform:uppercase;color:var(--text-muted);">Batch</th>
                            <th style="padding:12px;text-align:left;border-bottom:2px solid var(--border);font-size:11px;text-transform:uppercase;color:var(--text-muted);">Action</th>
                        </tr>
                    </thead>
                    <tbody id="misResultsBody"></tbody>
                </table>
            </div>

            <p style="margin-top:14px;font-size:12px;color:var(--text-muted);">
                <i class="fas fa-info-circle"></i>
                Data is fetched live from the NIELIT MIS. Only <strong>active/approved</strong> students are shown.
                Students already in this portal will show an "Already Added" badge.
            </p>
        </div>
    </div>

    <style>
        .btn-mis { background: #0369a1; color: white; }
        .btn-mis:hover { background: #0284c7; }
        .badge-added { background:#d1fae5;color:#065f46;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700; }
        .badge-new   { background:#eff6ff;color:#1d4ed8;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700; }
        #misSearchInput:focus { border-color:var(--primary);outline:none;box-shadow:0 0 0 3px rgba(21,94,117,.1); }
    </style>

    <script>
    // ── MIS Modal ──────────────────────────────────────────────────────────
    function openMisModal() {
        document.getElementById('misModal').classList.add('active');
        document.getElementById('misSearchInput').focus();
    }
    function closeMisModal() {
        document.getElementById('misModal').classList.remove('active');
        document.getElementById('misResultsWrap').style.display = 'none';
        document.getElementById('misStatusMsg').style.display   = 'none';
        document.getElementById('misResultsBody').innerHTML = '';
        document.getElementById('misSearchInput').value = '';
    }

    function misStatus(msg, type) {
        var el = document.getElementById('misStatusMsg');
        el.style.display = 'block';
        el.style.background = type === 'error' ? 'var(--alert-bg)' : 'var(--candidate-bg)';
        el.style.color = type === 'error' ? 'var(--alert)' : 'var(--candidate)';
        el.style.border = '1px solid ' + (type === 'error' ? 'var(--alert)' : 'var(--candidate)');
        el.innerHTML = msg;
    }

    async function doMisSearch() {
        var q = document.getElementById('misSearchInput').value.trim();
        if (q.length < 2) { misStatus('<i class="fas fa-exclamation-circle"></i> Enter at least 2 characters.', 'error'); return; }

        var btn = document.getElementById('misSearchBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Searching…';
        document.getElementById('misResultsWrap').style.display = 'none';
        document.getElementById('misStatusMsg').style.display   = 'none';

        try {
            var res = await fetch('students.php?mis_search=1&q=' + encodeURIComponent(q));
            var json = await res.json();

            if (!json.ok) { misStatus('<i class="fas fa-times-circle"></i> ' + json.error, 'error'); return; }

            var students = json.students || [];
            if (students.length === 0) { misStatus('<i class="fas fa-search"></i> No students found for "' + q + '".', 'error'); return; }

            var tbody = document.getElementById('misResultsBody');
            tbody.innerHTML = '';
            students.forEach(function(s) {
                var photo = s.photo_url
                    ? '<img src="' + s.photo_url + '" style="width:40px;height:48px;object-fit:cover;border-radius:6px;border:1px solid var(--border);" onerror="this.style.display=\'none\'">'
                    : '<span style="display:inline-block;width:40px;height:48px;background:var(--primary-bg);border-radius:6px;text-align:center;line-height:48px;color:var(--text-muted);font-size:18px;"><i class=\'fas fa-user\'></i></span>';

                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td style="padding:10px 12px;">' + photo + '</td>' +
                    '<td style="padding:10px 12px;"><strong>' + s.student_id + '</strong></td>' +
                    '<td style="padding:10px 12px;">' + s.name + '<br><small style="color:var(--text-muted);">' + (s.mobile || '') + '</small></td>' +
                    '<td style="padding:10px 12px;">' + (s.course || '—') + '</td>' +
                    '<td style="padding:10px 12px;">' + (s.batch  || '—') + '</td>' +
                    '<td style="padding:10px 12px;" id="action_' + btoa(s.student_id).replace(/=/g,'') + '">' +
                        '<button class="btn btn-success btn-sm" onclick="importMisStudent(' + JSON.stringify(s).replace(/"/g, '&quot;') + ')">' +
                        '<i class="fas fa-plus"></i> Import</button>' +
                    '</td>';
                tbody.appendChild(tr);
            });

            document.getElementById('misResultsWrap').style.display = 'block';
            misStatus('<i class="fas fa-check-circle"></i> Found ' + students.length + ' enrollment(s).', 'success');
        } catch(e) {
            misStatus('<i class="fas fa-times-circle"></i> Network error: ' + e.message, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-search"></i> Search';
        }
    }

    async function importMisStudent(s) {
        var key = btoa(s.student_id).replace(/=/g,'');
        var cell = document.getElementById('action_' + key);
        if (cell) cell.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        var fd = new FormData();
        fd.append('action_type', 'import_from_mis');
        fd.append('student_id',  s.student_id);
        fd.append('name',        s.name);
        fd.append('email',       s.email);
        fd.append('course',      s.course);
        fd.append('batch',       s.batch);
        fd.append('duration',    s.duration || '');

        try {
            var res  = await fetch('students.php', { method: 'POST', body: fd });
            var json = await res.json();
            if (json.ok) {
                if (cell) cell.innerHTML = '<span class="badge-added"><i class="fas fa-check"></i> Imported</span>';
                misStatus('<i class="fas fa-check-circle"></i> ' + s.name + ' imported successfully!', 'success');
            } else {
                if (cell) {
                    if (json.error && json.error.includes('already exists')) {
                        cell.innerHTML = '<span class="badge-added"><i class="fas fa-check"></i> Already Added</span>';
                    } else {
                        cell.innerHTML = '<span style="color:var(--alert);font-size:12px;">' + json.error + '</span>';
                    }
                }
                misStatus('<i class="fas fa-exclamation-circle"></i> ' + json.error, 'error');
            }
        } catch(e) {
            if (cell) cell.innerHTML = '<span style="color:var(--alert);font-size:12px;">Network error</span>';
        }
    }
    </script>

</body>
</html>