<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/institute_branding.php';
require_once __DIR__ . '/../includes/multi_course_helper.php';
require_once __DIR__ . '/../includes/student_document_upload_helper.php';
require_once __DIR__ . '/../includes/portal_assistant_tools.php';
require_once __DIR__ . '/../includes/automation_webhook_helper.php';

$assistantSettings = $conn instanceof mysqli ? getPortalAutomationSettings($conn) : [];
$assistantName = trim((string) ($assistantSettings['assistant_name'] ?? 'Gyansetu')) ?: 'Gyansetu';

$token = trim((string) ($_REQUEST['token'] ?? ''));
$recordId = (int) ($_REQUEST['record_id'] ?? ($_SESSION['active_record_id'] ?? 0));
$isLoggedStudent = !empty($_SESSION['student_id']);

$studentRow = studentDocumentAuthorizeAccess($conn, $recordId, $token);
if (!$studentRow) {
    if (!$isLoggedStudent) {
        header('Location: login.php');
        exit;
    }
    $_SESSION['error'] = 'Student record not found.';
    header('Location: dashboard.php');
    exit;
}

$recordId = (int) ($studentRow['id'] ?? 0);
$studentId = (string) ($studentRow['student_id'] ?? '');
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = studentDocumentSaveUploads($conn, $studentRow, $_FILES, $_POST);
    if (!empty($res['student']) && is_array($res['student'])) {
        $studentRow = $res['student'];
    }
    if (!empty($res['ok'])) {
        $successMessage = (string) ($res['message'] ?? 'Documents uploaded successfully.');
    } elseif (!empty($res['partial'])) {
        $successMessage = (string) ($res['message'] ?? 'Some documents were saved.');
        if (!empty($res['errors'])) {
            $errorMessage = implode(' ', (array) $res['errors']);
        }
    } else {
        $errorMessage = (string) ($res['message'] ?? 'Could not upload documents.');
    }
}

$checklist = studentDocumentChecklist($conn, $studentRow);
$requiredTotal = 0;
$requiredDone = 0;
foreach ($checklist as $item) {
    if (!empty($item['required'])) {
        $requiredTotal++;
        if (!empty($item['uploaded'])) {
            $requiredDone++;
        }
    }
}
$progressPct = $requiredTotal > 0 ? (int) round(($requiredDone / $requiredTotal) * 100) : 100;

$pdfDownloadUrl = $token !== ''
    ? saarthi_issue_pdf_token($recordId, $studentId)
    : ('download_form.php?record_id=' . $recordId);

$enrollments = ($isLoggedStudent && $studentId !== '') ? getEnrollmentsForStudentId($conn, $studentId) : [];

$page_title = 'Upload Documents';
if ($isLoggedStudent) {
    include __DIR__ . '/includes/header.php';
} else {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Upload Pending Documents - NIELIT Bhubaneswar</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
    <body style="background:#f4f7fb;">
        <nav class="navbar navbar-dark" style="background:linear-gradient(135deg,#0a1628 0%,#112240 100%);">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center gap-2" href="../index.php">
                    <img src="../assets/images/bhubaneswar_logo.png" alt="NIELIT" style="height:36px;">
                    <span>NIELIT Bhubaneswar — Document Upload</span>
                </a>
                <a href="login.php" class="btn btn-outline-light btn-sm"><i class="fas fa-sign-in-alt me-1"></i> Student Login</a>
            </div>
        </nav>
        <main>
    <?php
}
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h2 class="mb-1"><i class="fas fa-cloud-upload-alt text-primary"></i> Upload Photo, Signature &amp; Documents</h2>
                    <p class="text-muted mb-0">
                        Student ID: <strong><?php echo htmlspecialchars($studentId); ?></strong>
                        &middot; Course: <strong><?php echo htmlspecialchars((string) ($studentRow['course'] ?? '')); ?></strong>
                    </p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?php echo htmlspecialchars($pdfDownloadUrl); ?>" target="_blank" class="btn btn-warning fw-semibold">
                        <i class="fas fa-file-pdf me-1"></i> Download Admission PDF
                    </a>
                    <?php if ($isLoggedStudent): ?>
                        <a href="profile.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Back to Profile
                        </a>
                    <?php else: ?>
                        <a href="../index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-home me-1"></i> Back to Portal
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($isLoggedStudent && count($enrollments) > 1): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body py-2 d-flex flex-wrap align-items-center gap-2">
                        <span class="small fw-semibold text-muted me-2">Course Enrollment:</span>
                        <?php foreach ($enrollments as $enr):
                            $eRecId = (int) ($enr['student_record_id'] ?? $enr['id'] ?? 0);
                            if ($eRecId <= 0) {
                                continue;
                            }
                            $activeClass = $eRecId === $recordId ? 'btn-primary' : 'btn-outline-primary';
                        ?>
                            <a href="upload_documents.php?record_id=<?php echo $eRecId; ?>" class="btn btn-sm <?php echo $activeClass; ?>">
                                <?php echo htmlspecialchars((string) ($enr['course_name'] ?? $enr['course'] ?? ('Record #' . $eRecId))); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold">
                            Required Documents Progress: <?php echo $requiredDone; ?> / <?php echo $requiredTotal; ?> uploaded
                        </span>
                        <span class="badge <?php echo ($requiredTotal > 0 && $requiredDone >= $requiredTotal) ? 'bg-success' : 'bg-warning text-dark'; ?>">
                            <?php echo $progressPct; ?>%
                        </span>
                    </div>
                    <div class="progress" style="height:10px;">
                        <div class="progress-bar <?php echo ($requiredTotal > 0 && $requiredDone >= $requiredTotal) ? 'bg-success' : 'bg-primary'; ?>"
                             role="progressbar"
                             style="width: <?php echo $progressPct; ?>%;"
                             aria-valuenow="<?php echo $progressPct; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="small text-muted mt-2">
                        Upload any pending documents below (all at once or one by one). Your updated Passport Photo &amp; Signature will appear immediately on your Admission Form PDF.
                    </div>
                </div>
            </div>

            <?php if ($successMessage !== ''): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-1"></i> <?php echo htmlspecialchars($successMessage); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($errorMessage !== ''): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-1"></i> <?php echo htmlspecialchars($errorMessage); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="upload_documents.php" enctype="multipart/form-data">
                <input type="hidden" name="record_id" value="<?php echo $recordId; ?>">
                <?php if ($token !== ''): ?>
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <?php endif; ?>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-id-card text-primary me-1"></i> Aadhar Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="aadharInput">12-Digit Aadhar Number</label>
                                <input type="text"
                                       id="aadharInput"
                                       name="aadhar"
                                       class="form-control"
                                       maxlength="12"
                                       pattern="\d{12}"
                                       inputmode="numeric"
                                       placeholder="Enter 12-digit Aadhar number"
                                       value="<?php echo htmlspecialchars((string) ($studentRow['aadhar'] ?? '')); ?>">
                            </div>
                            <div class="col-md-6">
                                <div class="small text-muted pt-md-4">
                                    <?php if (!empty($studentRow['aadhar']) && preg_match('/^\d{12}$/', (string) $studentRow['aadhar'])): ?>
                                        <span class="badge bg-success"><i class="fas fa-check"></i> Aadhar Saved</span>
                                        Your Aadhar number is saved on this application.
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                        Enter your 12-digit Aadhar number here if you registered quickly via <?php echo htmlspecialchars($assistantName, ENT_QUOTES, 'UTF-8'); ?> chat.
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <?php foreach ($checklist as $slot):
                        $slotKey = (string) $slot['key'];
                        $isUp = !empty($slot['uploaded']);
                        $isReq = !empty($slot['required']);
                        $relPath = (string) ($slot['path'] ?? '');
                        $ext = strtolower(pathinfo($relPath, PATHINFO_EXTENSION));
                    ?>
                        <div class="col-md-6">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-header bg-white d-flex justify-content-between align-items-center gap-2">
                                    <span class="fw-semibold">
                                        <?php echo htmlspecialchars((string) $slot['label']); ?>
                                        <?php if ($isReq): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-1">Required</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary ms-1">Optional</span>
                                        <?php endif; ?>
                                    </span>
                                    <?php if ($isUp): ?>
                                        <span class="badge bg-success"><i class="fas fa-check me-1"></i>Uploaded</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <?php if ($isUp && $relPath !== ''): ?>
                                        <div class="d-flex align-items-center gap-3 mb-3 p-2 rounded bg-light">
                                            <?php if (in_array($ext, ['jpg', 'jpeg', 'png'], true)): ?>
                                                <img src="../<?php echo htmlspecialchars($relPath); ?>"
                                                     alt="<?php echo htmlspecialchars((string) $slot['label']); ?>"
                                                     style="width:56px;height:56px;object-fit:cover;border-radius:6px;border:1px solid #dee2e6;">
                                            <?php else: ?>
                                                <i class="fas fa-file-pdf text-danger fa-2x"></i>
                                            <?php endif; ?>
                                            <div class="small">
                                                <div class="fw-semibold text-success">Current file on record</div>
                                                <a href="../<?php echo htmlspecialchars($relPath); ?>" target="_blank" class="text-decoration-none">
                                                    <i class="fas fa-external-link-alt me-1"></i> View uploaded file
                                                </a>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <label class="form-label small text-muted mb-1" for="file_<?php echo htmlspecialchars($slotKey); ?>">
                                        <?php echo htmlspecialchars((string) $slot['hint']); ?>
                                    </label>
                                    <input type="file"
                                           class="form-control"
                                           id="file_<?php echo htmlspecialchars($slotKey); ?>"
                                           name="<?php echo htmlspecialchars($slotKey); ?>"
                                           accept="<?php echo htmlspecialchars((string) $slot['accept']); ?>">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-4 p-3 bg-white rounded shadow-sm">
                    <div class="small text-muted">
                        <i class="fas fa-shield-alt text-success me-1"></i>
                        Files are verified securely and attached directly to your NIELIT record (<strong><?php echo htmlspecialchars($studentId); ?></strong>).
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg px-4">
                        <i class="fas fa-upload me-2"></i> Save &amp; Upload Documents
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
if ($isLoggedStudent) {
    include __DIR__ . '/includes/footer.php';
} else {
    ?>
        </main>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}
