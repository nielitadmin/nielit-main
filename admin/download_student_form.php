<?php
/**
 * Admin: Download admission form PDF for a specific course enrollment.
 * ?record_id=123  — one enrollment (preferred)
 * ?id=STUDENT_ID  — picker if multiple courses, else single form
 * ?id=...&course_id=5 — specific course enrollment
 */
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/student_form_helpers.php';

if (!isset($_SESSION['admin'])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: login.php');
    exit();
}

if (empty($_GET['record_id']) && empty($_GET['id'])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: students.php');
    exit();
}

$student = null;

if (!empty($_GET['record_id'])) {
    $student = fetchStudentEnrollmentByRecordId($conn, (int)$_GET['record_id']);
} elseif (!empty($_GET['id'])) {
    $studentId = trim($_GET['id']);
    $enrollments = fetchStudentEnrollmentsByStudentId($conn, $studentId);

    if (count($enrollments) > 1 && empty($_GET['course_id'])) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $page_title = 'Download Admission Forms';
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Download Forms - NIELIT Admin</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin-theme.css">
        </head>
        <body>
        <div class="admin-wrapper">
            <?php include __DIR__ . '/includes/sidebar.php'; ?>
            <main class="admin-content">
                <div class="admin-topbar">
                    <div class="topbar-left">
                        <h4><i class="fas fa-file-pdf"></i> Download Admission Forms</h4>
                        <small>Student ID: <?php echo htmlspecialchars($studentId); ?> — select a course</small>
                    </div>
                </div>
                <div class="admin-main">
                    <div class="content-card">
                        <p class="text-muted">This student has <?php echo count($enrollments); ?> course enrollment(s). Download a separate form for each course.</p>
                        <div class="table-responsive">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Course</th>
                                        <th>Status</th>
                                        <th>Registered</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($enrollments as $enr): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($enr['course']); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst($enr['status'] ?? 'pending')); ?></td>
                                        <td><?php echo !empty($enr['registration_date']) ? date('d M Y', strtotime($enr['registration_date'])) : '—'; ?></td>
                                        <td>
                                            <a href="download_student_form.php?record_id=<?php echo (int)$enr['id']; ?>" class="btn btn-success btn-sm" target="_blank">
                                                <i class="fas fa-download"></i> Download Form
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <a href="students.php" class="btn btn-secondary mt-3"><i class="fas fa-arrow-left"></i> Back to Students</a>
                    </div>
                </div>
            </main>
        </div>
        </body>
        </html>
        <?php
        exit();
    }

    $student = resolveStudentEnrollmentForForm($conn, $_GET);
}

if (!$student) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $_SESSION['message'] = 'Student enrollment record not found.';
    $_SESSION['message_type'] = 'danger';
    header('Location: students.php');
    exit();
}

require_once __DIR__ . '/../includes/render_student_admission_form_pdf.php';
$education_records = fetchEducationRecordsForStudentId($conn, $student['student_id']);
renderStudentAdmissionFormPdf($student, $education_records);
