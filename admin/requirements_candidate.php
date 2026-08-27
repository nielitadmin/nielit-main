<?php
/**
 * Candidate Requirements — full dossier for one student.
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/admin_assets.php';
require_once __DIR__ . '/../includes/multi_course_helper.php';
require_once __DIR__ . '/../includes/student_form_helpers.php';
require_once __DIR__ . '/../includes/biometric_attendance_helper.php';
require_once __DIR__ . '/../includes/attendance_in_out_helper.php';
require_once __DIR__ . '/../includes/mantra_mfs100_helper.php';
require_once __DIR__ . '/../includes/requirements_helper.php';

$placementHelper = __DIR__ . '/../batch_module/includes/batch_placement_helper.php';
if (is_file($placementHelper)) {
    require_once $placementHelper;
}

requirementsRequireAccess($conn);

$studentId = trim((string) ($_GET['id'] ?? ''));
if ($studentId === '') {
    header('Location: ' . app_url('admin/requirements'));
    exit();
}
if (!requirementsCanViewStudent($conn, $studentId)) {
    $_SESSION['message'] = 'Candidate not found, or you do not have access to this record.';
    $_SESSION['message_type'] = 'danger';
    header('Location: ' . app_url('admin/requirements'));
    exit();
}

$data = requirementsLoadCandidate($conn, $studentId);
if (!$data) {
    $_SESSION['message'] = 'Candidate not found.';
    $_SESSION['message_type'] = 'danger';
    header('Location: ' . app_url('admin/requirements'));
    exit();
}

$student = $data['student'];
$docs = $data['documents'];
$att = $data['attendance'];
$fp = $data['fingerprint'];
$active_theme = loadActiveTheme($conn);
$page_title = 'Candidate — ' . (string) ($student['name'] ?? $studentId);

$role = (string) ($_SESSION['admin_role'] ?? '');
$canEdit = !in_array($role, ['front_office_desk', 'report_viewer', 'placement_coordinator'], true);

function reqField(array $row, string $key, string $fallback = '—'): string
{
    return htmlspecialchars(requirementsDisplay($row[$key] ?? '', $fallback));
}

function reqDate($value, string $withTime = ''): string
{
    return htmlspecialchars(requirementsFormatDate($value, $withTime !== '' ? $withTime : 'd M Y'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - NIELIT Bhubaneswar</title>
    <?php adminEmitHeadAssets($active_theme); ?>
    <style>
        .req-hero { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.25rem 1.4rem; }
        .req-photo { width:96px; height:96px; border-radius:12px; object-fit:cover; background:#e2e8f0; }
        .req-photo-ph { width:96px; height:96px; border-radius:12px; background:#e2e8f0; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:2rem; }
        .req-ring { width:88px; height:88px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; color:#0f172a; background:
            conic-gradient(#16a34a calc(var(--p) * 1%), #e2e8f0 0); }
        .req-ring span { background:#fff; width:68px; height:68px; border-radius:50%; display:flex; align-items:center; justify-content:center; }
        .req-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:1.15rem 1.25rem; height:100%; }
        .req-card h5 { font-size:1rem; margin-bottom:1rem; color:#0f172a; }
        .req-dl { display:grid; grid-template-columns:140px 1fr; gap:6px 12px; margin:0; }
        .req-dl dt { color:#64748b; font-weight:500; font-size:0.85rem; }
        .req-dl dd { margin:0; font-size:0.9rem; color:#0f172a; word-break:break-word; }
        .req-doc { border:1px solid #e2e8f0; border-radius:12px; padding:0.9rem; height:100%; }
        .req-doc.ok { border-color:#bbf7d0; background:#f0fdf4; }
        .req-doc.miss { border-color:#fecaca; background:#fef2f2; }
        .req-doc img { max-width:100%; max-height:120px; object-fit:contain; border-radius:8px; }
        .req-pill { display:inline-block; padding:2px 8px; border-radius:999px; font-size:0.75rem; font-weight:600; }
        .req-pill.ok { background:#dcfce7; color:#166534; }
        .req-pill.miss { background:#fee2e2; color:#991b1b; }
        @media (max-width: 576px) { .req-dl { grid-template-columns:1fr; } }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>
<div class="admin-content">
    <div class="container-fluid py-4">
        <div class="mb-3">
            <a href="<?php echo htmlspecialchars(app_url('admin/requirements')); ?>" class="text-decoration-none">&larr; Back to Requirements</a>
        </div>

        <div class="req-hero mb-4">
            <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between">
                <div class="d-flex gap-3 align-items-center">
                    <?php if ($data['photo_url'] !== ''): ?>
                        <img class="req-photo" src="<?php echo htmlspecialchars($data['photo_url']); ?>" alt="">
                    <?php else: ?>
                        <div class="req-photo-ph"><i class="fas fa-user"></i></div>
                    <?php endif; ?>
                    <div>
                        <h2 class="mb-1"><?php echo htmlspecialchars((string) ($student['name'] ?? '')); ?></h2>
                        <div class="text-muted"><?php echo htmlspecialchars($studentId); ?></div>
                        <div class="mt-1">
                            <span class="badge text-bg-<?php echo htmlspecialchars(requirementsStatusBadgeClass((string) ($student['status'] ?? ''))); ?>">
                                <?php echo htmlspecialchars(ucfirst((string) ($student['status'] ?? ''))); ?>
                            </span>
                            <?php if (!empty($student['course_name'])): ?>
                                <span class="badge text-bg-light text-dark"><?php echo htmlspecialchars((string) $student['course_name']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($data['is_dge'])): ?>
                                <span class="badge text-bg-info">DGE</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="text-center">
                        <div class="req-ring" style="--p: <?php echo (int) $docs['percent']; ?>">
                            <span><?php echo (int) $docs['percent']; ?>%</span>
                        </div>
                        <div class="small text-muted mt-1">Required documents</div>
                    </div>
                    <div class="d-flex flex-column gap-2">
                        <?php if ($canEdit): ?>
                            <a class="btn btn-sm btn-warning" href="<?php echo htmlspecialchars(app_url('admin/edit_student') . '?id=' . rawurlencode($studentId)); ?>">
                                <i class="fas fa-edit"></i> Edit student
                            </a>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars(app_url('admin/view_student_documents') . '?id=' . rawurlencode($studentId)); ?>">
                            <i class="fas fa-folder-open"></i> Documents
                        </a>
                        <a class="btn btn-sm btn-outline-success" target="_blank" href="<?php echo htmlspecialchars(app_url('admin/download_student_form') . '?record_id=' . (int) ($student['id'] ?? 0)); ?>">
                            <i class="fas fa-download"></i> Admission form
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-lg-6">
                <div class="req-card">
                    <h5><i class="fas fa-user"></i> Personal details</h5>
                    <dl class="req-dl">
                        <dt>Full name</dt><dd><?php echo reqField($student, 'name'); ?></dd>
                        <dt>Father's name</dt><dd><?php echo reqField($student, 'father_name'); ?></dd>
                        <dt>Mother's name</dt><dd><?php echo reqField($student, 'mother_name'); ?></dd>
                        <dt>Date of birth</dt><dd><?php echo reqDate($student['dob'] ?? ''); ?></dd>
                        <dt>Age</dt><dd><?php echo reqField($student, 'age'); ?></dd>
                        <dt>Gender</dt><dd><?php echo reqField($student, 'gender'); ?></dd>
                        <dt>Aadhaar</dt><dd><?php echo reqField($student, 'aadhar'); ?></dd>
                        <dt>APAAR ID</dt><dd><?php echo reqField($student, 'apaar_id'); ?></dd>
                        <dt>Category</dt><dd><?php echo reqField($student, 'category'); ?></dd>
                        <dt>Religion</dt><dd><?php echo reqField($student, 'religion'); ?></dd>
                        <dt>Marital status</dt><dd><?php echo reqField($student, 'marital_status'); ?></dd>
                        <dt>Nationality</dt><dd><?php echo reqField($student, 'nationality'); ?></dd>
                        <dt>PwD</dt><dd><?php echo reqField($student, 'pwd_status', 'No'); ?></dd>
                        <dt>Distinguishing marks</dt><dd><?php echo reqField($student, 'distinguishing_marks'); ?></dd>
                    </dl>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="req-card">
                    <h5><i class="fas fa-address-book"></i> Contact</h5>
                    <dl class="req-dl">
                        <dt>Mobile</dt><dd><?php echo reqField($student, 'mobile'); ?></dd>
                        <dt>Email</dt><dd><?php echo reqField($student, 'email'); ?></dd>
                        <dt>Address</dt><dd><?php echo reqField($student, 'address'); ?></dd>
                        <dt>City</dt><dd><?php echo reqField($student, 'city'); ?></dd>
                        <dt>State</dt><dd><?php echo reqField($student, 'state'); ?></dd>
                        <dt>Pincode</dt><dd><?php echo reqField($student, 'pincode'); ?></dd>
                        <dt>College</dt><dd><?php echo reqField($student, 'college_name'); ?></dd>
                        <dt>Training centre</dt><dd><?php echo reqField($student, 'training_center'); ?></dd>
                        <dt>Registered</dt><dd><?php echo reqDate($student['registration_date'] ?? ($student['created_at'] ?? ''), 'd M Y, h:i A'); ?></dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="req-card mb-3">
            <h5><i class="fas fa-graduation-cap"></i> Courses, batches and schemes</h5>
            <?php if (empty($data['enrollments'])): ?>
                <p class="text-muted mb-0">No course enrolments found.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Course</th><th>Scheme</th><th>Batch</th><th>Status</th><th>Registered</th></tr></thead>
                        <tbody>
                        <?php foreach ($data['enrollments'] as $en): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(trim((string) (($en['course_code'] ?? '') !== '' ? ($en['course_code'] . ' — ' . $en['course_name']) : ($en['course_name'] ?? '')))); ?></td>
                                <td><?php echo htmlspecialchars(requirementsDisplay($en['scheme_name'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars(requirementsDisplay($en['batch_name'] ?? '')); ?></td>
                                <td><span class="badge text-bg-<?php echo htmlspecialchars(requirementsStatusBadgeClass((string) ($en['status'] ?? ''))); ?>"><?php echo htmlspecialchars(ucfirst((string) ($en['status'] ?? ''))); ?></span></td>
                                <td><?php echo reqDate($en['registration_date'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="req-card mb-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h5 class="mb-0"><i class="fas fa-clipboard-check"></i> Document requirements</h5>
                <span class="text-muted small">
                    Required <?php echo (int) $docs['required_ok']; ?>/<?php echo (int) $docs['required_total']; ?>
                    · Optional uploaded <?php echo (int) $docs['optional_ok']; ?>/<?php echo (int) $docs['optional_total']; ?>
                </span>
            </div>
            <div class="row g-3">
                <?php foreach ($docs['items'] as $item): ?>
                    <div class="col-md-4 col-lg-3">
                        <div class="req-doc <?php echo !empty($item['present']) ? 'ok' : 'miss'; ?>">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <strong><i class="fas <?php echo htmlspecialchars($item['icon']); ?>"></i> <?php echo htmlspecialchars($item['label']); ?></strong>
                                <?php if (!empty($item['required'])): ?><small class="text-danger">Required</small><?php endif; ?>
                            </div>
                            <?php if (!empty($item['present'])): ?>
                                <span class="req-pill ok">Submitted</span>
                                <?php if (!empty($item['url'])): ?>
                                    <?php
                                    $ext = strtolower(pathinfo((string) $item['path'], PATHINFO_EXTENSION));
                                    ?>
                                    <div class="mt-2">
                                        <?php if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)): ?>
                                            <img src="<?php echo htmlspecialchars($item['url']); ?>" alt="">
                                        <?php endif; ?>
                                        <a class="btn btn-sm btn-outline-primary mt-2" target="_blank" href="<?php echo htmlspecialchars($item['url']); ?>">View</a>
                                    </div>
                                <?php elseif (!empty($item['virtual'])): ?>
                                    <div class="small text-muted mt-2">Fingerprint is enrolled for attendance.</div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="req-pill miss">Not submitted</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-lg-6">
                <div class="req-card">
                    <h5><i class="fas fa-fingerprint"></i> Fingerprint</h5>
                    <?php if ($fp): ?>
                        <dl class="req-dl">
                            <dt>Status</dt><dd><span class="req-pill ok">Enrolled</span></dd>
                            <dt>Quality</dt><dd><?php echo (int) ($fp['quality'] ?? 0); ?></dd>
                            <dt>Enrolled by</dt>
                            <dd><?php echo htmlspecialchars(function_exists('fingerprintEnrolmentSourceLabel') ? fingerprintEnrolmentSourceLabel((string) ($fp['enrolled_by'] ?? '')) : (string) ($fp['enrolled_by'] ?? '—')); ?></dd>
                            <dt>Enrolled at</dt><dd><?php echo reqDate($fp['created_at'] ?? '', 'd M Y, h:i A'); ?></dd>
                        </dl>
                    <?php else: ?>
                        <p class="text-muted mb-2">This candidate has not enrolled a fingerprint yet.</p>
                        <?php if ($canEdit): ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars(app_url('admin/attendance_fingerprint_enroll') . '?q=' . rawurlencode($studentId)); ?>">Enrol fingerprint</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="req-card">
                    <h5><i class="fas fa-calendar-check"></i> Attendance</h5>
                    <dl class="req-dl">
                        <dt>Classes held</dt><dd><?php echo (int) ($att['classes_held'] ?? $att['total_classes'] ?? 0); ?></dd>
                        <dt>Present</dt><dd><?php echo (int) ($att['present_count'] ?? 0); ?></dd>
                        <dt>Partial</dt><dd><?php echo (int) ($att['partial_count'] ?? 0); ?></dd>
                        <dt>Absent</dt><dd><?php echo (int) ($att['absent_count'] ?? 0); ?></dd>
                        <dt>Percentage</dt><dd><?php echo htmlspecialchars(number_format((float) ($att['attendance_percentage'] ?? 0), 1)); ?>%</dd>
                    </dl>
                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars(app_url('admin/attendance_biometric_report')); ?>">Open fingerprint report</a>
                </div>
            </div>
        </div>

        <div class="req-card mb-3">
            <h5><i class="fas fa-university"></i> Education</h5>
            <?php if (empty($data['education'])): ?>
                <p class="text-muted mb-0">No education records on file.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Exam / qualification</th>
                                <th>Institute</th>
                                <th>Stream</th>
                                <th>Year</th>
                                <th>%</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($data['education'] as $ed): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(requirementsDisplay($ed['exam_passed'] ?? ($ed['exam_name'] ?? ''))); ?></td>
                                <td><?php echo htmlspecialchars(requirementsDisplay($ed['institute_name'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars(requirementsDisplay($ed['stream'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars(requirementsDisplay($ed['year_of_passing'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars(requirementsDisplay($ed['percentage'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-lg-6">
                <div class="req-card">
                    <h5><i class="fas fa-rupee-sign"></i> Payment</h5>
                    <dl class="req-dl">
                        <dt>UTR / transaction</dt><dd><?php echo reqField($student, 'utr_number'); ?></dd>
                        <dt>Payment date</dt><dd><?php echo reqDate($student['payment_date'] ?? ''); ?></dd>
                    </dl>
                    <?php if (!empty($student['payment_receipt']) && requirementsDocExists((string) $student['payment_receipt'])): ?>
                        <a class="btn btn-sm btn-outline-primary" target="_blank" href="<?php echo htmlspecialchars(requirementsDocUrl((string) $student['payment_receipt'])); ?>">View receipt</a>
                    <?php endif; ?>
                    <?php if (!empty($data['payments'])): ?>
                        <div class="table-responsive mt-3">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Date</th><th>Amount</th><th>Mode</th><th>Status</th></tr></thead>
                                <tbody>
                                <?php foreach ($data['payments'] as $pay): ?>
                                    <tr>
                                        <td><?php echo reqDate($pay['payment_date'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars(requirementsDisplay($pay['amount'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars(requirementsDisplay($pay['payment_mode'] ?? ($pay['mode'] ?? ''))); ?></td>
                                        <td><?php echo htmlspecialchars(requirementsDisplay($pay['status'] ?? '')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="req-card">
                    <h5><i class="fas fa-briefcase"></i> Placement</h5>
                    <?php if (empty($data['placements'])): ?>
                        <p class="text-muted mb-0">No placement record yet.</p>
                    <?php else: ?>
                        <?php foreach ($data['placements'] as $pl): ?>
                            <dl class="req-dl mb-3">
                                <dt>Status</dt><dd><?php echo htmlspecialchars(requirementsDisplay($pl['placement_status'] ?? '')); ?></dd>
                                <dt>Company</dt><dd><?php echo htmlspecialchars(requirementsDisplay($pl['placement_company'] ?? '')); ?></dd>
                                <dt>Role</dt><dd><?php echo htmlspecialchars(requirementsDisplay($pl['placement_role'] ?? '')); ?></dd>
                                <dt>Package</dt><dd><?php echo htmlspecialchars(requirementsDisplay($pl['placement_package_amount'] ?? '')); ?></dd>
                                <dt>Location</dt><dd><?php echo htmlspecialchars(requirementsDisplay($pl['placement_location'] ?? '')); ?></dd>
                                <dt>Date</dt><dd><?php echo reqDate($pl['placement_date'] ?? ''); ?></dd>
                            </dl>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
<?php $conn->close(); ?>
