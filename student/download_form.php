<?php
/**
 * Student portal: Download admission form PDF per course enrollment.
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/student_form_helpers.php';
require_once __DIR__ . '/../includes/multi_course_helper.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit();
}

$sessionStudentId = $_SESSION['student_id'];

// Download one form
if (!empty($_GET['record_id'])) {
    ob_start();
    require_once __DIR__ . '/../includes/render_student_admission_form_pdf.php';

    $student = fetchStudentEnrollmentByRecordId($conn, (int)$_GET['record_id']);
    if (!$student || !enrollmentBelongsToStudentId($student, $sessionStudentId)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $_SESSION['error'] = 'Enrollment not found or access denied.';
        header('Location: download_form.php');
        exit();
    }

    $education_records = fetchEducationRecordsForStudentId($conn, $student['student_id']);
    renderStudentAdmissionFormPdf($student, $education_records);
}

$enrollments = fetchStudentEnrollmentsByStudentId($conn, $sessionStudentId);
if (empty($enrollments)) {
    $_SESSION['error'] = 'No enrollment records found.';
    header('Location: dashboard.php');
    exit();
}

if (count($enrollments) === 1) {
    header('Location: download_form.php?record_id=' . (int)$enrollments[0]['id']);
    exit();
}

$page_title = 'Download Forms';
include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="welcome-card">
                <h2><i class="fas fa-download"></i> Download Admission Forms</h2>
                <p class="text-muted mb-0">You are enrolled in <?php echo count($enrollments); ?> courses. Download a separate form for each course.</p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-file-pdf"></i> Your Course Forms</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Course</th>
                                    <th>Status</th>
                                    <th>Registered</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enrollments as $enr): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($enr['course']); ?></strong></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars(ucfirst($enr['status'] ?? 'pending')); ?></span></td>
                                    <td><?php echo !empty($enr['registration_date']) ? date('d M Y', strtotime($enr['registration_date'])) : '—'; ?></td>
                                    <td class="text-end">
                                        <a href="download_form.php?record_id=<?php echo (int)$enr['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-download"></i> Download PDF
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <a href="dashboard.php" class="btn btn-outline-secondary mt-3"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
