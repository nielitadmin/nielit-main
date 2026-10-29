<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/admin_assets.php';
require_once __DIR__ . '/../includes/training_partner_helper.php';
require_once __DIR__ . '/../includes/institute_branding.php';

if (!isset($_SESSION['admin']) || ($_SESSION['admin_role'] ?? '') !== 'master_admin') {
    header('Location: ' . APP_URL . '/admin/dashboard.php');
    exit();
}

training_partner_ensure_schema($conn);
if (($_GET['download'] ?? '') === 'student_template') {
    training_partner_student_template();
    exit;
}
$success = '';
$error = '';
$showStudents = (($_GET['students'] ?? '') === '1');
$filterScheme = 0;
$filterPartner = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && (int) $_SERVER['CONTENT_LENGTH'] > 0) {
        $uploadedMb = round(((int) $_SERVER['CONTENT_LENGTH']) / (1024 * 1024), 1);
        $maxPost = ini_get('post_max_size') ?: '64M';
        $error = "The uploaded file is too large (" . $uploadedMb . " MB). The server maximum allowed upload size is " . $maxPost . ". A 2,000-student spreadsheet with text data only is usually under 1 MB. Please save your file as CSV or clean XLSX without photos/images and try again.";
        $action = '';
    } else {
        $action = (string) ($_POST['action'] ?? '');
    }
    if ($action === 'save_partner') {
        $result = training_partner_save(
            $conn,
            (int) ($_POST['partner_id'] ?? 0),
            (string) ($_POST['partner_name'] ?? ''),
            (string) ($_POST['partner_code'] ?? ''),
            (int) ($_POST['centre_id'] ?? 0)
        );
        $success = $result['ok'] ? 'Training partner saved.' : '';
        $error = $result['ok'] ? '' : (string) ($result['error'] ?? 'Could not save the training partner.');
    } elseif ($action === 'delete_partner') {
        $success = training_partner_deactivate($conn, (int) ($_POST['partner_id'] ?? 0))
            ? 'Training partner removed.'
            : '';
        if ($success === '') {
            $error = 'Could not remove the training partner.';
        }
    } elseif ($action === 'save_student') {
        $result = training_partner_save_student(
            $conn,
            (int) ($_POST['partner_id'] ?? 0),
            (int) ($_POST['scheme_id'] ?? 0),
            (string) ($_POST['student_name'] ?? ''),
            (string) ($_POST['mobile'] ?? ''),
            isset($_POST['is_certified']) ? 1 : 0,
            isset($_POST['is_placed']) ? 1 : 0,
            (string) ($_POST['course_name'] ?? ''),
            training_partner_parse_date($_POST['course_start_date'] ?? ''),
            training_partner_parse_date($_POST['course_end_date'] ?? ''),
            training_partner_details_from_request($_POST),
            training_partner_parse_date($_POST['exam_date'] ?? ''),
            true,
            (string) ($_POST['course_category'] ?? ''),
            'manual',
            'Manual Entry'
        );
        $success = $result['ok'] ? 'Student record saved under that scheme and training partner.' : '';
        $error = $result['ok'] ? '' : (string) ($result['error'] ?? 'Could not save the student record.');
    } elseif ($action === 'upload_students') {
        $file = $_FILES['student_sheet'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($errCode === UPLOAD_ERR_INI_SIZE || $errCode === UPLOAD_ERR_FORM_SIZE) {
                $maxUpload = ini_get('upload_max_filesize') ?: '128M';
                $error = "The uploaded file exceeds the server limit of " . $maxUpload . ". A sheet with 2,000 candidates should be under 1 MB. If it has photos or scanned IDs, please export as CSV or clean XLSX without images and upload.";
            } else {
                $error = 'Choose an Excel file to upload.';
            }
        } else {
            $overrideDates = !empty($_POST['override_sheet_dates']);
            $result = training_partner_import_students(
                $conn,
                (string) $file['tmp_name'],
                (string) ($file['name'] ?? ''),
                (int) ($_POST['partner_id'] ?? 0),
                (int) ($_POST['scheme_id'] ?? 0),
                training_partner_parse_date($_POST['course_start_date'] ?? ''),
                training_partner_parse_date($_POST['course_end_date'] ?? ''),
                training_partner_parse_date($_POST['exam_date'] ?? ''),
                (string) ($_POST['course_category'] ?? ''),
                $overrideDates
            );
            if (!empty($result['ok'])) {
                $showStudents = true;
                $filterScheme = (int) ($_POST['scheme_id'] ?? 0);
                $filterPartner = (int) ($_POST['partner_id'] ?? 0);
                $uniqueCount = (int) ($result['saved'] ?? 0);
                $totalRows = (int) ($result['total_rows'] ?? $uniqueCount);
                $dupInSheet = (int) ($result['duplicates_in_sheet'] ?? 0);
                if ($dupInSheet > 0) {
                    $success = number_format($uniqueCount) . ' unique students saved (' . number_format($totalRows) . ' rows in sheet; ' . number_format($dupInSheet) . ' duplicate candidate rows merged)';
                } else {
                    $success = number_format($uniqueCount) . ' students uploaded';
                }
                if ((int) ($result['sheets'] ?? 0) > 1) {
                    $success .= ' from ' . (int) $result['sheets'] . ' sheets';
                }
                if ((int) ($result['partners_created'] ?? 0) > 0) {
                    $success .= '. ' . (int) $result['partners_created'] . ' training partners were added from the sheet';
                }
                $success .= '.';

                $dupList = is_array($result['duplicate_list'] ?? null) ? $result['duplicate_list'] : [];
                $batchId = (string) ($result['batch_id'] ?? '');
                $fileName = (string) ($result['file_name'] ?? ($file['name'] ?? 'Excel sheet'));
                $_SESSION['last_tp_upload'] = [
                    'time' => time(),
                    'batch_id' => $batchId,
                    'count' => $uniqueCount,
                    'total_rows' => $totalRows,
                    'duplicates_count' => $dupInSheet,
                    'duplicate_list' => $dupList,
                    'scheme_id' => $filterScheme,
                    'file_name' => $fileName,
                    'saved_ids' => $result['saved_ids'] ?? []
                ];
                $uploadDuplicates = $dupList;
                $uploadTotalRows = $totalRows;
                $uploadUniqueCount = $uniqueCount;
                $uploadDupCount = $dupInSheet;
            } else {
                $success = '';
            }
            $error = !empty($result['ok']) ? '' : (string) ($result['error'] ?? 'Could not read the Excel file.');
        }
    } elseif ($action === 'update_student_status') {
        $showStudents = true;
        $filterScheme = (int) ($_POST['filter_scheme_id'] ?? 0);
        $filterPartner = (int) ($_POST['filter_partner_id'] ?? 0);
        $updated = training_partner_update_student_status(
            $conn,
            (int) ($_POST['student_record_id'] ?? 0),
            isset($_POST['is_certified']) ? 1 : 0,
            isset($_POST['is_placed']) ? 1 : 0
        );
        $success = $updated ? 'Certified and placement updated for that candidate.' : '';
        if ($success === '') {
            $error = 'Could not update certified and placement for that candidate.';
        }
    } elseif ($action === 'bulk_update_scheme_dates') {
        $showStudents = true;
        $bulkSchemeId = (int) ($_POST['bulk_scheme_id'] ?? 0);
        $sStart = training_partner_parse_date($_POST['bulk_start_date'] ?? '');
        $sEnd = training_partner_parse_date($_POST['bulk_end_date'] ?? '');
        $sExam = training_partner_parse_date($_POST['bulk_exam_date'] ?? '');
        $result = training_partner_bulk_update_scheme_dates($conn, $bulkSchemeId, $sStart, $sEnd, $sExam);
        if (!empty($result['ok'])) {
            $filterScheme = $bulkSchemeId;
            $success = "Successfully updated dates for " . (int) $result['updated'] . " students under that scheme.";
        } else {
            $error = (string) ($result['error'] ?? 'Could not update scheme dates.');
        }
    } elseif ($action === 'delete_student') {
        $showStudents = true;
        $filterScheme = (int) ($_POST['filter_scheme_id'] ?? 0);
        $filterPartner = (int) ($_POST['filter_partner_id'] ?? 0);
        $success = training_partner_delete_student($conn, (int) ($_POST['student_record_id'] ?? 0))
            ? 'Student record removed.'
            : '';
        if ($success === '') {
            $error = 'Could not remove that student record.';
        }
    } elseif ($action === 'undo_last_upload') {
        $lastUpload = $_SESSION['last_tp_upload'] ?? null;
        if (is_array($lastUpload)) {
            $ids = $lastUpload['saved_ids'] ?? [];
            if (!empty($ids)) {
                $res = training_partner_delete_students_by_ids($conn, $ids);
                $delCount = (int) ($res['deleted'] ?? 0);
            } elseif (!empty($lastUpload['scheme_id'])) {
                $res = training_partner_delete_students_by_scheme($conn, (int) $lastUpload['scheme_id']);
                $delCount = (int) ($res['deleted'] ?? 0);
            } else {
                $delCount = 0;
            }
            unset($_SESSION['last_tp_upload']);
            $success = "Upload undone: successfully removed {$delCount} uploaded students. You can now re-upload your Excel sheet.";
            $showStudents = false;
        } else {
            $error = "No recent upload session found to undo. You can use the Undo by Scheme tool below.";
        }
    } elseif ($action === 'undo_by_batch') {
        $batchId = trim((string) ($_POST['undo_batch_id'] ?? ''));
        $res = training_partner_delete_students_by_batch($conn, $batchId);
        if (!empty($res['ok'])) {
            $delCount = (int) ($res['deleted'] ?? 0);
            if (!empty($_SESSION['last_tp_upload']['batch_id']) && $_SESSION['last_tp_upload']['batch_id'] === $batchId) {
                unset($_SESSION['last_tp_upload']);
            }
            $success = "Successfully removed {$delCount} candidates from that specific upload file / batch. Other files and manual entries remain completely safe.";
            $showStudents = false;
        } else {
            $error = (string) ($res['error'] ?? 'Could not remove candidates for that upload batch.');
        }
    } elseif ($action === 'undo_by_partner') {
        $partnerId = (int) ($_POST['undo_partner_id'] ?? 0);
        $res = training_partner_delete_students_by_partner($conn, $partnerId);
        if (!empty($res['ok'])) {
            $delCount = (int) ($res['deleted'] ?? 0);
            unset($_SESSION['last_tp_upload']);
            $success = "Successfully removed {$delCount} student records under that training partner. You can now re-upload for that partner cleanly.";
            $showStudents = false;
        } else {
            $error = (string) ($res['error'] ?? 'Could not remove student records for this partner.');
        }
    } elseif ($action === 'undo_by_scheme') {
        $schemeId = (int) ($_POST['undo_scheme_id'] ?? 0);
        $res = training_partner_delete_students_by_scheme($conn, $schemeId);
        if (!empty($res['ok'])) {
            $delCount = (int) ($res['deleted'] ?? 0);
            unset($_SESSION['last_tp_upload']);
            $success = "Successfully removed {$delCount} student records under that scheme. You can now re-upload cleanly.";
            $showStudents = false;
        } else {
            $error = (string) ($res['error'] ?? 'Could not remove student records for this scheme.');
        }
    } elseif ($action === 'undo_all_students') {
        $res = training_partner_delete_all_students($conn);
        if (!empty($res['ok'])) {
            $delCount = (int) ($res['deleted'] ?? 0);
            unset($_SESSION['last_tp_upload']);
            $success = "Successfully reset all training partner students (removed {$delCount} records). You can now re-upload your Excel sheets from scratch.";
            $showStudents = false;
        } else {
            $error = (string) ($res['error'] ?? 'Could not reset student records.');
        }
    }
}

$schemes = training_partner_schemes($conn);
$students = training_partner_students_list($conn);
$partners = training_partner_list($conn);
$centres = training_partner_centres($conn);
$schemeStudentCounts = training_partner_student_counts_by_scheme($conn);
$partnerStudentCounts = training_partner_student_counts_by_partner($conn);
$uploadBatches = training_partner_upload_batches($conn);
$editId = (int) ($_GET['edit'] ?? 0);
$editPartner = null;
foreach ($partners as $partner) {
    if ((int) $partner['id'] === $editId) {
        $editPartner = $partner;
        break;
    }
}
$lastUpload = $_SESSION['last_tp_upload'] ?? null;
if (!isset($uploadDuplicates) && is_array($lastUpload)) {
    $uploadDuplicates = $lastUpload['duplicate_list'] ?? [];
    $uploadTotalRows = $lastUpload['total_rows'] ?? ($lastUpload['count'] ?? 0);
    $uploadUniqueCount = $lastUpload['count'] ?? 0;
    $uploadDupCount = $lastUpload['duplicates_count'] ?? count($uploadDuplicates);
}
$page_title = 'Training Partner Figures';
$active_theme = loadActiveTheme($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <?php adminEmitHeadAssets($active_theme); ?>
    <style>
        .tp-page { padding: 28px 32px 48px; }
        .tp-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; }
        .tp-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px; }
        .tp-card h2 { font-size: 1.05rem; margin: 0 0 12px; }
        .tp-card .form-label { font-weight: 600; }
        .tp-table { width: 100%; border-collapse: collapse; }
        .tp-table th, .tp-table td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; text-align: left; }
        .tp-table th { font-size: 12px; text-transform: uppercase; color: #64748b; white-space: nowrap; }
        .tp-note { color: #475569; margin: 8px 0 18px; }
        .admin-content { min-width: 0; overflow-x: hidden; }
        .tp-page, .tp-card { min-width: 0; max-width: 100%; }
        .tp-student-scroll { width: 100%; max-width: 100%; overflow-x: scroll; overflow-y: auto; max-height: 70vh; border: 1px solid #e2e8f0; border-radius: 10px; }
        .tp-student-table { width: max-content; min-width: 100%; border-collapse: separate; border-spacing: 0; }
        .tp-student-table th, .tp-student-table td { box-sizing: border-box; padding: 8px 12px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: middle; white-space: nowrap; background: #fff; font-size: 13px; }
        .tp-student-table th { position: sticky; top: 0; z-index: 2; background: #f8fafc; font-size: 11px; text-transform: uppercase; color: #64748b; }
        .tp-student-table td.tp-wrap { white-space: normal; min-width: 220px; max-width: 320px; }
        .tp-student-table .tp-freeze { position: sticky; z-index: 3; background: #fff; }
        .tp-student-table thead .tp-freeze { z-index: 5; background: #f8fafc; }
        .tp-freeze-sl { left: 0; width: 76px; min-width: 76px; max-width: 76px; }
        .tp-freeze-name { left: 76px; min-width: 180px; box-shadow: 4px 0 6px rgba(15, 23, 42, 0.08); }
        .tp-flag-form { display: flex; align-items: center; gap: 12px; margin: 0; }
        .tp-flag-form label { margin: 0; white-space: nowrap; font-weight: 600; }
    </style>
    <script>
        function tpToggleCollapseManual(targetId, btn, evt) {
            if (evt) {
                if (typeof evt.preventDefault === 'function') evt.preventDefault();
                if (typeof evt.stopPropagation === 'function') evt.stopPropagation();
            }
            var el = document.getElementById(targetId);
            if (!el) return;
            var isVisible = (el.style.display === 'block') || (el.classList.contains('show') && el.style.display !== 'none');
            if (isVisible) {
                el.style.display = 'none';
                el.classList.remove('show');
                if (btn) {
                    btn.setAttribute('aria-expanded', 'false');
                    var icon = btn.querySelector('.fa-chevron-up, .fa-chevron-down');
                    if (icon) {
                        icon.classList.remove('fa-chevron-up');
                        icon.classList.add('fa-chevron-down');
                    }
                    var txt = btn.querySelector('.tp-btn-text');
                    if (txt && txt.getAttribute('data-orig-text')) {
                        txt.textContent = txt.getAttribute('data-orig-text');
                    }
                }
            } else {
                el.style.display = 'block';
                el.classList.add('show');
                if (btn) {
                    btn.setAttribute('aria-expanded', 'true');
                    var icon = btn.querySelector('.fa-chevron-down, .fa-chevron-up');
                    if (icon) {
                        icon.classList.remove('fa-chevron-down');
                        icon.classList.add('fa-chevron-up');
                    }
                    var txt = btn.querySelector('.tp-btn-text');
                    if (txt) {
                        if (!txt.getAttribute('data-orig-text')) {
                            txt.setAttribute('data-orig-text', txt.textContent);
                        }
                        txt.textContent = 'Hide duplicate candidates';
                    }
                }
            }
        }
    </script>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<div class="admin-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="admin-content">
        <div class="tp-page">
            <h1>Training Partner Figures</h1>
            <p class="tp-note">Choose the scheme or project first, then the training partner, and add each student. Tick Certified or Placed. Report Monitor counts a student when the course start and end dates fall in the selected period.</p>
            <?php if ($success !== ''): ?>
                <div class="alert alert-success shadow-sm mb-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <div class="fw-bold mb-1">
                                <i class="fas fa-check-circle me-1 text-success"></i> <?php echo htmlspecialchars($success); ?>
                            </div>
                            <?php if (!empty($uploadTotalRows)): ?>
                                <div class="small text-secondary">
                                    <strong><?php echo number_format($uploadTotalRows); ?></strong> rows read &bull;
                                    <strong class="text-success"><?php echo number_format($uploadUniqueCount); ?> unique candidates saved</strong>
                                    <?php if (!empty($uploadDupCount)): ?>
                                        &bull; <span class="badge bg-warning text-dark"><?php echo (int) $uploadDupCount; ?> duplicate candidate rows merged</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($_SESSION['last_tp_upload'])): ?>
                            <form method="post" class="m-0" onsubmit="return confirm('Undo this upload and remove the uploaded students from the database?');">
                                <input type="hidden" name="action" value="undo_last_upload">
                                <button type="submit" class="btn btn-sm btn-outline-danger bg-white">
                                    <i class="fas fa-undo me-1"></i> Undo this upload
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($uploadDuplicates)): ?>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <span class="small fw-semibold text-secondary">
                                <i class="fas fa-copy me-1"></i> <?php echo count($uploadDuplicates); ?> Duplicate Candidate Rows in Spreadsheet (Merged):
                            </span>
                            <button class="btn btn-sm btn-link p-0 text-decoration-none fw-semibold" type="button" id="tpToggleDuplicateListBtn" aria-expanded="false" onclick="tpToggleCollapseManual('tpDuplicateListCollapse', this, event);">
                                <span class="tp-btn-text">View <?php echo count($uploadDuplicates); ?> duplicate candidates</span> <i class="fas fa-chevron-down ms-1"></i>
                            </button>
                        </div>
                        <div class="collapse mt-2" id="tpDuplicateListCollapse">
                            <div class="table-responsive bg-white rounded border">
                                <table class="table table-sm table-striped mb-0 small">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Candidate Name</th>
                                            <th>Aadhaar No.</th>
                                            <th>Mobile / WhatsApp</th>
                                            <th>Training Partner / Institute</th>
                                            <th>Course</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($uploadDuplicates as $dIdx => $dup): 
                                            $aadhaarVal = trim((string) ($dup['aadhaar'] ?? ''));
                                            if ($aadhaarVal === '' && !empty($dup['name']) && !empty($dup['mobile'])) {
                                                $q = $conn->prepare('SELECT aadhaar FROM training_partner_students WHERE student_name = ? AND mobile = ? LIMIT 1');
                                                if ($q) {
                                                    $q->bind_param('ss', $dup['name'], $dup['mobile']);
                                                    $q->execute();
                                                    $ar = $q->get_result()->fetch_assoc();
                                                    $q->close();
                                                    if ($ar && !empty($ar['aadhaar'])) {
                                                        $aadhaarVal = trim((string) $ar['aadhaar']);
                                                    }
                                                }
                                            }
                                        ?>
                                            <tr>
                                                <td><?php echo $dIdx + 1; ?></td>
                                                <td class="fw-semibold"><?php echo htmlspecialchars((string) ($dup['name'] ?? '—')); ?></td>
                                                <td>
                                                    <?php 
                                                        $dispAadhaar = training_partner_show_aadhaar($aadhaarVal, (string) ($dup['name'] ?? ''));
                                                    ?>
                                                    <?php if ($dispAadhaar !== '-'): ?>
                                                        <span class="badge bg-light text-dark border font-monospace px-2 py-1 fs-6"><?php echo htmlspecialchars($dispAadhaar); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars((string) ($dup['mobile'] ?? '—')); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($dup['partner'] ?? '—')); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($dup['course'] ?? '—')); ?></td>
                                                <td><span class="badge bg-info-subtle text-dark border">Merged into existing</span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif (!empty($_SESSION['last_tp_upload'])): ?>
                <div class="alert alert-info shadow-sm mb-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <i class="fas fa-info-circle me-1"></i>
                            Last upload: <strong><?php echo (int) $_SESSION['last_tp_upload']['count']; ?> unique candidates</strong>
                            from <em><?php echo htmlspecialchars($_SESSION['last_tp_upload']['file_name']); ?></em>
                            <?php if (!empty($_SESSION['last_tp_upload']['duplicates_count'])): ?>
                                <span class="badge bg-warning text-dark ms-1"><?php echo (int) $_SESSION['last_tp_upload']['duplicates_count']; ?> duplicates merged</span>
                            <?php endif; ?>
                        </div>
                        <form method="post" class="m-0" onsubmit="return confirm('Undo this upload and remove the uploaded students from the database?');">
                            <input type="hidden" name="action" value="undo_last_upload">
                            <button type="submit" class="btn btn-sm btn-danger">
                                <i class="fas fa-undo me-1"></i> Undo Last Upload
                            </button>
                        </form>
                    </div>
                    <?php if (!empty($uploadDuplicates)): ?>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <span class="small fw-semibold text-secondary">
                                <i class="fas fa-copy me-1"></i> <?php echo count($uploadDuplicates); ?> Duplicate Candidate Rows in Spreadsheet:
                            </span>
                            <button class="btn btn-sm btn-link p-0 text-decoration-none fw-semibold" type="button" id="tpToggleLastDuplicateListBtn" aria-expanded="false" onclick="tpToggleCollapseManual('tpLastDuplicateListCollapse', this, event);">
                                <span class="tp-btn-text">View duplicate candidates</span> <i class="fas fa-chevron-down ms-1"></i>
                            </button>
                        </div>
                        <div class="collapse mt-2" id="tpLastDuplicateListCollapse">
                            <div class="table-responsive bg-white rounded border">
                                <table class="table table-sm table-striped mb-0 small">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Candidate Name</th>
                                            <th>Aadhaar No.</th>
                                            <th>Mobile / WhatsApp</th>
                                            <th>Training Partner / Institute</th>
                                            <th>Course</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($uploadDuplicates as $dIdx => $dup): 
                                            $aadhaarVal = trim((string) ($dup['aadhaar'] ?? ''));
                                            if ($aadhaarVal === '' && !empty($dup['name']) && !empty($dup['mobile'])) {
                                                $q = $conn->prepare('SELECT aadhaar FROM training_partner_students WHERE student_name = ? AND mobile = ? LIMIT 1');
                                                if ($q) {
                                                    $q->bind_param('ss', $dup['name'], $dup['mobile']);
                                                    $q->execute();
                                                    $ar = $q->get_result()->fetch_assoc();
                                                    $q->close();
                                                    if ($ar && !empty($ar['aadhaar'])) {
                                                        $aadhaarVal = trim((string) $ar['aadhaar']);
                                                    }
                                                }
                                            }
                                        ?>
                                            <tr>
                                                <td><?php echo $dIdx + 1; ?></td>
                                                <td class="fw-semibold"><?php echo htmlspecialchars((string) ($dup['name'] ?? '—')); ?></td>
                                                <td>
                                                    <?php 
                                                        $dispAadhaar = training_partner_show_aadhaar($aadhaarVal, (string) ($dup['name'] ?? ''));
                                                    ?>
                                                    <?php if ($dispAadhaar !== '-'): ?>
                                                        <span class="badge bg-light text-dark border font-monospace px-2 py-1 fs-6"><?php echo htmlspecialchars($dispAadhaar); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars((string) ($dup['mobile'] ?? '—')); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($dup['partner'] ?? '—')); ?></td>
                                                <td><?php echo htmlspecialchars((string) ($dup['course'] ?? '—')); ?></td>
                                                <td><span class="badge bg-info-subtle text-dark border">Merged into existing</span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <div class="tp-grid">
                <div class="tp-card">
                    <h2><?php echo $editPartner ? 'Edit training partner' : 'Add training partner'; ?></h2>
                    <form method="post">
                        <input type="hidden" name="action" value="save_partner">
                        <input type="hidden" name="partner_id" value="<?php echo (int) ($editPartner['id'] ?? 0); ?>">
                        <div class="mb-3">
                            <label class="form-label">Partner name</label>
                            <input class="form-control" name="partner_name" required value="<?php echo htmlspecialchars((string) ($editPartner['partner_name'] ?? '')); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Partner code</label>
                            <input class="form-control" name="partner_code" maxlength="40" value="<?php echo htmlspecialchars((string) ($editPartner['partner_code'] ?? '')); ?>" placeholder="Optional">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Centre</label>
                            <select class="form-select" name="centre_id">
                                <option value="0">All centres report only</option>
                                <?php foreach ($centres as $centre): ?>
                                    <option value="<?php echo (int) $centre['id']; ?>" <?php echo ((int) ($editPartner['centre_id'] ?? 0) === (int) $centre['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(normalize_nielit_centre_name($centre['name'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn btn-primary" type="submit">Save partner</button>
                    </form>
                    <?php if ($partners): ?>
                        <div class="table-responsive mt-3">
                            <table class="tp-table">
                                <thead>
                                    <tr>
                                        <th>Partner</th>
                                        <th>Centre</th>
                                        <th class="text-center">Students Trained</th>
                                        <th class="text-center">Certified</th>
                                        <th class="text-center">Placed</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($partners as $partner): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars((string) $partner['partner_name']); ?></strong>
                                            <?php if ((string) $partner['partner_code'] !== ''): ?>
                                                <div class="text-muted small"><?php echo htmlspecialchars((string) $partner['partner_code']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo (int) $partner['centre_id'] > 0 ? htmlspecialchars(normalize_nielit_centre_name((string) $partner['centre_name'])) : 'All centres'; ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-primary fs-6 px-2 py-1"><?php echo number_format((int) ($partner['student_count'] ?? 0)); ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-success fs-6 px-2 py-1"><?php echo number_format((int) ($partner['certified_count'] ?? 0)); ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-info text-dark fs-6 px-2 py-1"><?php echo number_format((int) ($partner['placed_count'] ?? 0)); ?></span>
                                        </td>
                                        <td>
                                            <a href="training_partners.php?edit=<?php echo (int) $partner['id']; ?>" class="btn btn-sm btn-outline-secondary me-1">Edit</a>
                                            <form method="post" style="display:inline" onsubmit="return confirm('Remove this training partner?');">
                                                <input type="hidden" name="action" value="delete_partner">
                                                <input type="hidden" name="partner_id" value="<?php echo (int) $partner['id']; ?>">
                                                <button class="btn btn-link btn-sm text-danger" type="submit">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="tp-card mt-3">
                <h2>Add a student record</h2>
                <?php if (!$partners || !$schemes): ?>
                    <p class="text-muted mb-0"><?php echo !$schemes ? 'No active scheme or project is available.' : 'Add a training partner first.'; ?></p>
                <?php else: ?>
                    <form method="post">
                        <input type="hidden" name="action" value="save_student">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Scheme / project</label>
                                <select class="form-select" name="scheme_id" required>
                                    <option value="">Select scheme or project</option>
                                    <?php foreach ($schemes as $scheme): ?>
                                        <option value="<?php echo (int) $scheme['id']; ?>">
                                            <?php echo htmlspecialchars((string) $scheme['scheme_name']); ?>
                                            <?php if ((string) $scheme['scheme_code'] !== ''): ?>
                                                (<?php echo htmlspecialchars((string) $scheme['scheme_code']); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Training partner for this project</label>
                                <select class="form-select" name="partner_id" required>
                                    <option value="">Select training partner</option>
                                    <?php foreach ($partners as $partner): ?>
                                        <option value="<?php echo (int) $partner['id']; ?>"><?php echo htmlspecialchars((string) $partner['partner_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">WhatsApp / mobile</label>
                                <input class="form-control" name="mobile" maxlength="20" placeholder="Optional">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Name of candidate</label>
                                <input class="form-control" name="student_name" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Father's name</label>
                                <input class="form-control" name="father_name">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Batch no.</label>
                                <input class="form-control" name="batch_no">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Course name</label>
                                <input class="form-control" name="course_name" placeholder="Optional">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Course category</label>
                                <select class="form-select" name="course_category">
                                    <option value="">Select course category</option>
                                    <?php foreach (training_partner_course_categories() as $catOpt): ?>
                                        <option value="<?php echo htmlspecialchars($catOpt); ?>"><?php echo htmlspecialchars($catOpt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Exam date</label>
                                <input class="form-control" type="date" name="exam_date">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Course starting date</label>
                                <input class="form-control" type="date" name="course_start_date">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Course ending date</label>
                                <input class="form-control" type="date" name="course_end_date">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">NIELIT registration no.</label>
                                <input class="form-control" name="nielit_reg_no">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Category (SC/ST/EWS)</label>
                                <input class="form-control" name="category" placeholder="SC, ST, EWS, or other">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="gender">
                                    <option value="">Select</option>
                                    <option>Male</option>
                                    <option>Female</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Aadhaar card</label>
                                <input class="form-control" name="aadhaar" maxlength="20" inputmode="numeric">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">APAAR ID</label>
                                <input class="form-control" name="apaar_id" maxlength="50">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Religion</label>
                                <input class="form-control" name="religion">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Communication address and district</label>
                            <textarea class="form-control" name="communication_address" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">District</label>
                                <input class="form-control" name="district">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">State</label>
                                <input class="form-control" name="state">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">HQ Accr number</label>
                                <input class="form-control" name="hq_accr_number">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Training venue address</label>
                            <textarea class="form-control" name="venue_address" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-check-label me-3"><input type="checkbox" name="currently_employed" value="1"> Currently employed</label>
                            <label class="form-check-label me-3"><input type="checkbox" name="is_certified" value="1"> Certified</label>
                            <label class="form-check-label"><input type="checkbox" name="is_placed" value="1"> Placed</label>
                        </div>
                        <button class="btn btn-primary" type="submit">Save student</button>
                    </form>
                    <hr>
                    <h2>Upload students from Excel</h2>
                    <div class="alert alert-info">
                        Choose the scheme here. A workbook with several sheets is read sheet by sheet. The template includes Sl. No., Batch No, Course Name, NIELIT Registration No., Name of candidate, Father's Name, Category, Gender, Aadhaar Card, APAAR ID, Religion, WhatsApp number, communication address, currently employed, Name of Institute, training venue address, HQ Accr number, District, and State. Course start date, course end date, and exam date are read when those columns are present. The course dates decide which report period the student belongs to. Certified and Placed are read when those columns are present. If the sheet already names the institute, leave the partner below blank and those partners are created automatically. Dates entered here are used when a row has no dates of its own. Uploaded students stay in this list and are not added to All Students.
                        <a href="training_partners.php?download=student_template">Download the Excel template</a>.
                    </div>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_students">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Scheme / project</label>
                                <select class="form-select" name="scheme_id" required>
                                    <option value="">Select scheme or project</option>
                                    <?php foreach ($schemes as $scheme): ?>
                                        <option value="<?php echo (int) $scheme['id']; ?>"><?php echo htmlspecialchars((string) $scheme['scheme_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Training partner for this project</label>
                                <select class="form-select" name="partner_id">
                                    <option value="0">Use the partner named in the Excel sheet</option>
                                    <?php foreach ($partners as $partner): ?>
                                        <option value="<?php echo (int) $partner['id']; ?>"><?php echo htmlspecialchars((string) $partner['partner_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Excel file</label>
                                <input class="form-control" type="file" name="student_sheet" accept=".xlsx,.xls,.csv" required>
                                <div class="form-text">Max upload size: <?php echo htmlspecialchars((string) ini_get('upload_max_filesize')); ?>. Tip: A 2,000-row spreadsheet without photos is usually under 1 MB. Save as <strong>.csv</strong> or clean <strong>.xlsx</strong>.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Course category (Default)</label>
                                <select class="form-select" name="course_category">
                                    <option value="">Auto-detect / from Excel column</option>
                                    <?php foreach (training_partner_course_categories() as $catOpt): ?>
                                        <option value="<?php echo htmlspecialchars($catOpt); ?>"><?php echo htmlspecialchars($catOpt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Course starting date</label>
                                <input class="form-control" type="date" name="course_start_date">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Course ending date</label>
                                <input class="form-control" type="date" name="course_end_date">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Exam date</label>
                                <input class="form-control" type="date" name="exam_date">
                            </div>
                            <div class="col-12 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="override_sheet_dates" value="1" id="overrideSheetDates">
                                    <label class="form-check-label" for="overrideSheetDates">
                                        <strong>Override dates in spreadsheet with the dates above</strong> (e.g. to assign all candidates to FY 2026-2027)
                                    </label>
                                </div>
                            </div>
                        </div>
                        <button class="btn btn-primary" type="submit">Upload students</button>
                    </form>

                    <div class="card mt-4 border-0 shadow-sm bg-light">
                        <div class="card-body py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-semibold text-secondary">
                                    <i class="fas fa-calendar-alt me-1 text-primary"></i> Batch Date Tool / Assign Financial Year
                                </h6>
                                <button class="btn btn-sm btn-outline-secondary" type="button" aria-expanded="false" onclick="tpToggleCollapseManual('tpBatchDateCollapse', this, event);">
                                    Adjust Existing Scheme Dates
                                </button>
                            </div>
                            <div class="collapse mt-3" id="tpBatchDateCollapse">
                                <form method="post">
                                    <input type="hidden" name="action" value="bulk_update_scheme_dates">
                                    <p class="text-muted small mb-2">
                                        If students were uploaded with dates from another year (e.g. 2025) and you want them to count in <strong>FY 2026-2027</strong> in Report Monitor, select the scheme and set the dates below:
                                    </p>
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-4">
                                            <label class="form-label small">Scheme / project</label>
                                            <select class="form-select form-select-sm" name="bulk_scheme_id" required>
                                                <option value="">Select scheme</option>
                                                <?php foreach ($schemes as $scheme): ?>
                                                    <option value="<?php echo (int) $scheme['id']; ?>"><?php echo htmlspecialchars((string) $scheme['scheme_name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small">Course Start Date</label>
                                            <input class="form-control form-control-sm" type="date" name="bulk_start_date" id="bulkStartDate" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small">Course End Date</label>
                                            <input class="form-control form-control-sm" type="date" name="bulk_end_date" id="bulkEndDate">
                                        </div>
                                        <div class="col-md-2">
                                            <button class="btn btn-sm btn-primary w-100" type="submit">Update Dates</button>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none me-3" onclick="document.getElementById('bulkStartDate').value='2026-06-01'; document.getElementById('bulkEndDate').value='2026-11-30';">
                                            <i class="fas fa-magic me-1"></i> Quick Fill FY 2026-2027 (Jun–Nov 2026)
                                        </button>
                                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" onclick="document.getElementById('bulkStartDate').value='2025-06-01'; document.getElementById('bulkEndDate').value='2025-11-30';">
                                            <i class="fas fa-undo me-1"></i> Quick Fill FY 2025-2026
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-3 border border-danger-subtle shadow-sm bg-white">
                        <div class="card-header bg-danger-subtle d-flex justify-content-between align-items-center py-2 flex-wrap gap-2">
                            <strong class="text-danger">
                                <i class="fas fa-undo me-1"></i> Undo / Reset Uploaded Spreadsheet Candidates
                            </strong>
                            <span class="badge bg-danger"><?php echo count($students); ?> total candidates in list</span>
                        </div>
                        <div class="card-body py-3">
                            <p class="small text-muted mb-3">
                                If you uploaded multiple Excel sheets under a scheme, you can remove <strong>just that specific file</strong> without touching other spreadsheets or manual entries:
                            </p>
                            
                            <!-- Option 1: By Specific Upload File or Manual Entries -->
                            <div class="p-3 mb-3 bg-light rounded border">
                                <form method="post" onsubmit="return confirm('Are you sure you want to remove candidates from this specific upload file? Other uploaded spreadsheets in this scheme will remain safe.');">
                                    <input type="hidden" name="action" value="undo_by_batch">
                                    <label class="form-label small fw-bold text-dark mb-1">
                                        <i class="fas fa-file-excel text-success me-1"></i> Option 1: Remove by Specific Uploaded File / Batch
                                    </label>
                                    <div class="input-group">
                                        <select class="form-select form-select-sm" name="undo_batch_id" required>
                                            <option value="">Select specific uploaded file / batch to remove...</option>
                                            <?php foreach ($uploadBatches as $bRow): ?>
                                                <option value="<?php echo htmlspecialchars($bRow['upload_batch_id']); ?>">
                                                    <?php echo htmlspecialchars($bRow['file_name']); ?> &bull; <?php echo htmlspecialchars($bRow['scheme_name']); ?> (<?php echo number_format((int) $bRow['total_students']); ?> candidates)
                                                    <?php if ($bRow['upload_batch_id'] === 'manual'): ?>
                                                        [Manual Entries]
                                                    <?php else: ?>
                                                        &mdash; <?php echo date('d M Y, h:i A', strtotime($bRow['last_uploaded'])); ?>
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn-sm btn-danger" type="submit">
                                            <i class="fas fa-trash-alt me-1"></i> Remove File Candidates
                                        </button>
                                    </div>
                                    <div class="form-text mt-1 text-muted small">
                                        Safely removes only the candidates from the chosen Excel or CSV file. All other files in the scheme remain untouched.
                                    </div>
                                </form>
                            </div>

                            <div class="row g-3">
                                <div class="col-lg-7 border-lg-end">
                                    <form method="post" onsubmit="return confirm('Are you sure you want to remove all candidates under this entire scheme (including all files uploaded for it)?');">
                                        <input type="hidden" name="action" value="undo_by_scheme">
                                        <label class="form-label small fw-semibold text-secondary">
                                            <i class="fas fa-folder-minus me-1"></i> Option 3: Remove All Candidates under Scheme / Project
                                        </label>
                                        <div class="input-group">
                                            <select class="form-select form-select-sm" name="undo_scheme_id" required>
                                                <option value="">Select scheme to remove...</option>
                                                <?php foreach ($schemeStudentCounts as $scRow): ?>
                                                    <option value="<?php echo (int) $scRow['id']; ?>">
                                                        <?php echo htmlspecialchars((string) $scRow['scheme_name']); ?> (<?php echo number_format((int) $scRow['total_students']); ?> candidates)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button class="btn btn-sm btn-outline-danger" type="submit">
                                                <i class="fas fa-trash-alt me-1"></i> Remove Scheme
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                <div class="col-lg-5 d-flex flex-column justify-content-end">
                                    <form method="post" onsubmit="return confirm('DANGER: This will delete ALL <?php echo count($students); ?> uploaded training partner students across all schemes and files. Are you sure?');">
                                        <input type="hidden" name="action" value="undo_all_students">
                                        <label class="form-label small fw-semibold text-danger">
                                            <i class="fas fa-bomb me-1"></i> Option 4: Reset All Uploaded Students
                                        </label>
                                        <button class="btn btn-sm btn-outline-danger w-100" type="submit" <?php echo empty($students) ? 'disabled' : ''; ?>>
                                            <i class="fas fa-trash me-1"></i> Delete All Candidates (<?php echo count($students); ?>)
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <button class="btn btn-warning mt-3" type="button" id="tpToggleStudents" aria-expanded="<?php echo $showStudents ? 'true' : 'false'; ?>">
                    <span id="tpToggleStudentsLabel"><?php echo $showStudents ? 'Hide student list' : 'Show student list'; ?></span>
                    (<?php echo count($students); ?>)
                </button>
                <div id="tpStudentList" class="mt-3" <?php echo $showStudents ? '' : 'hidden'; ?>>
                <?php if ($students): ?>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="tpFilterScheme">Scheme / project</label>
                                <select class="form-select" id="tpFilterScheme">
                                    <option value="">All schemes / projects</option>
                                    <?php foreach ($schemes as $scheme): ?>
                                        <option value="<?php echo (int) $scheme['id']; ?>" <?php echo $filterScheme === (int) $scheme['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars((string) $scheme['scheme_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="tpFilterPartner">Institute</label>
                                <select class="form-select" id="tpFilterPartner">
                                    <option value="">All institutes</option>
                                    <?php foreach ($partners as $partner): ?>
                                        <option value="<?php echo (int) $partner['id']; ?>" <?php echo $filterPartner === (int) $partner['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars((string) $partner['partner_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <p class="text-muted mb-2" id="tpFilterHint">Select the scheme or project and the institute. Only those student records are shown.</p>
                        <p class="text-muted mb-2" id="tpFilterEmpty" hidden>No student records for that scheme and institute.</p>
                        <p class="text-muted mb-2" id="tpFilterHelp" hidden>Scroll sideways to see the full record. The student name stays on the left. Tick Certified or Placed, then click Update on that row.</p>
                        <div class="tp-student-scroll" id="tpStudentScroll" hidden>
                            <table class="tp-student-table">
                                <thead>
                                    <tr>
                                        <th class="tp-freeze tp-freeze-sl">Sl. No.</th>
                                        <th class="tp-freeze tp-freeze-name">Candidate</th>
                                        <th>Father</th>
                                        <th>WhatsApp</th>
                                        <th>Batch</th>
                                        <th>Course</th>
                                        <th>Course category</th>
                                        <th>Start</th>
                                        <th>End</th>
                                        <th>Exam date</th>
                                        <th>Institute</th>
                                        <th>Scheme / project</th>
                                        <th>NIELIT reg.</th>
                                        <th>Category</th>
                                        <th>Gender</th>
                                        <th>Aadhaar</th>
                                        <th>APAAR</th>
                                        <th>Religion</th>
                                        <th>Address</th>
                                        <th>District</th>
                                        <th>State</th>
                                        <th>Venue</th>
                                        <th>HQ Accr</th>
                                        <th>Employed</th>
                                        <th>Certified / placed</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="tpStudentBody">
                                <?php foreach ($students as $studentIndex => $student): ?>
                                    <tr data-scheme="<?php echo (int) $student['scheme_id']; ?>" data-partner="<?php echo (int) $student['partner_id']; ?>" hidden>
                                        <td class="tp-freeze tp-freeze-sl tp-sl"><?php echo (int) $studentIndex + 1; ?></td>
                                        <td class="tp-freeze tp-freeze-name"><?php echo htmlspecialchars((string) $student['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars(training_partner_show($student['father_name'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars(training_partner_show($student['mobile'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars(training_partner_show($student['batch_no'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars(training_partner_show($student['course_name'] ?? '')); ?></td>
                                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars(training_partner_show($student['course_category'] ?? '')); ?></span></td>
                                        <td><?php echo htmlspecialchars(training_partner_show_date($student['course_start_date'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars(training_partner_show_date($student['course_end_date'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars(training_partner_show_date($student['exam_date'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars((string) $student['partner_name']); ?></td>
                                        <td class="tp-wrap"><?php echo htmlspecialchars((string) $student['scheme_name']); ?></td>
                                        <td><?php echo htmlspecialchars(training_partner_show($student['nielit_reg_no'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars(training_partner_show($student['category'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars(training_partner_show($student['gender'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars(training_partner_show_aadhaar($student['aadhaar'] ?? '', $student['student_name'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars(training_partner_show($student['apaar_id'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars(training_partner_show($student['religion'] ?? '')); ?></td>
                                        <td class="tp-wrap"><?php echo htmlspecialchars(training_partner_show($student['communication_address'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars(training_partner_show($student['district'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars(training_partner_show($student['state'] ?? '')); ?></td>
                                        <td class="tp-wrap"><?php echo htmlspecialchars(training_partner_show($student['venue_address'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars(training_partner_show($student['hq_accr_number'] ?? '')); ?></td>
                                        <td><?php echo (int) ($student['currently_employed'] ?? 0) === 1 ? 'Yes' : 'No'; ?></td>
                                        <td>
                                            <form method="post" class="tp-flag-form">
                                                <input type="hidden" name="action" value="update_student_status">
                                                <input type="hidden" name="student_record_id" value="<?php echo (int) $student['id']; ?>">
                                                <input type="hidden" name="show_students" value="1">
                                                <input type="hidden" name="filter_scheme_id" class="tp-keep-scheme" value="<?php echo $filterScheme; ?>">
                                                <input type="hidden" name="filter_partner_id" class="tp-keep-partner" value="<?php echo $filterPartner; ?>">
                                                <label><input type="checkbox" name="is_certified" value="1" <?php echo (int) $student['is_certified'] === 1 ? 'checked' : ''; ?>> Certified</label>
                                                <label><input type="checkbox" name="is_placed" value="1" <?php echo (int) $student['is_placed'] === 1 ? 'checked' : ''; ?>> Placed</label>
                                                <button class="btn btn-sm btn-primary" type="submit">Update</button>
                                            </form>
                                        </td>
                                        <td>
                                            <form method="post" onsubmit="return confirm('Remove this student record?');">
                                                <input type="hidden" name="action" value="delete_student">
                                                <input type="hidden" name="student_record_id" value="<?php echo (int) $student['id']; ?>">
                                                <input type="hidden" name="show_students" value="1">
                                                <input type="hidden" name="filter_scheme_id" class="tp-keep-scheme" value="<?php echo $filterScheme; ?>">
                                                <input type="hidden" name="filter_partner_id" class="tp-keep-partner" value="<?php echo $filterPartner; ?>">
                                                <button class="btn btn-link btn-sm text-danger" type="submit">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <script>
                        (function() {
                            // 1. Student List Toggle
                            var toggleBtn = document.getElementById('tpToggleStudents');
                            var list = document.getElementById('tpStudentList');
                            if (toggleBtn && list) {
                                toggleBtn.addEventListener('click', function () {
                                    var open = list.hasAttribute('hidden');
                                    if (open) {
                                        list.removeAttribute('hidden');
                                    } else {
                                        list.setAttribute('hidden', 'hidden');
                                    }
                                    toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
                                    var lbl = document.getElementById('tpToggleStudentsLabel');
                                    if (lbl) lbl.textContent = open ? 'Hide student list' : 'Show student list';
                                });
                            }

                            // 2. Training Partner Quick Select Dropdown (Left Card)
                            var partnerSelect = document.getElementById('tpPartnerQuickSelect');
                            var clearBtn = document.getElementById('tpClearPartnerFilter');
                            var card = document.getElementById('tpSelectedPartnerCard');
                            var cardName = document.getElementById('tpCardPartnerName');
                            var cardCode = document.getElementById('tpCardPartnerCode');
                            var cardCentre = document.getElementById('tpCardPartnerCentre');
                            var cardTrained = document.getElementById('tpCardTrained');
                            var cardCert = document.getElementById('tpCardCertified');
                            var cardPlaced = document.getElementById('tpCardPlaced');
                            var cardEdit = document.getElementById('tpCardEditLink');
                            var cardViewStudents = document.getElementById('tpCardViewStudentsBtn');
                            var tableRows = document.querySelectorAll('#tpPartnersTable tbody tr');
                            var toggleTableBtn = document.getElementById('tpTogglePartnersTableBtn');
                            var tableWrap = document.getElementById('tpPartnersTableWrap');
                            var toggleTableLabel = document.getElementById('tpTogglePartnersTableLabel');

                            if (toggleTableBtn && tableWrap) {
                                toggleTableBtn.addEventListener('click', function() {
                                    var hidden = (tableWrap.style.display === 'none');
                                    tableWrap.style.display = hidden ? '' : 'none';
                                    if (toggleTableLabel) toggleTableLabel.textContent = hidden ? 'Collapse Table' : 'Expand Table';
                                });
                            }

                            function filterPartnerTable(selectedId) {
                                if (!tableRows.length) return;
                                if (!selectedId) {
                                    tableRows.forEach(function(tr) { tr.style.display = ''; });
                                    if (card) card.style.display = 'none';
                                    if (clearBtn) clearBtn.style.display = 'none';
                                    return;
                                }
                                var matched = false;
                                tableRows.forEach(function(tr) {
                                    var rId = tr.getAttribute('data-partner-row-id');
                                    if (rId === String(selectedId)) {
                                        tr.style.display = '';
                                        matched = true;
                                    } else {
                                        tr.style.display = 'none';
                                    }
                                });
                                if (partnerSelect) {
                                    var opt = partnerSelect.options[partnerSelect.selectedIndex];
                                    if (opt && opt.value) {
                                        if (cardName) cardName.textContent = opt.getAttribute('data-name') || '';
                                        if (cardCode) cardCode.textContent = opt.getAttribute('data-code') || '—';
                                        if (cardCentre) cardCentre.textContent = opt.getAttribute('data-centre') || 'All centres';
                                        if (cardTrained) cardTrained.textContent = Number(opt.getAttribute('data-students') || 0).toLocaleString();
                                        if (cardCert) cardCert.textContent = Number(opt.getAttribute('data-cert') || 0).toLocaleString();
                                        if (cardPlaced) cardPlaced.textContent = Number(opt.getAttribute('data-placed') || 0).toLocaleString();
                                        if (cardEdit) cardEdit.href = 'training_partners.php?edit=' + opt.value;
                                        if (card) card.style.display = 'block';
                                        if (clearBtn) clearBtn.style.display = '';
                                    }
                                }
                            }

                            if (partnerSelect) {
                                partnerSelect.addEventListener('change', function() {
                                    filterPartnerTable(this.value);
                                });
                            }
                            if (clearBtn) {
                                clearBtn.addEventListener('click', function() {
                                    if (partnerSelect) partnerSelect.value = '';
                                    filterPartnerTable('');
                                });
                            }

                            // View Students from Partner card
                            if (cardViewStudents) {
                                cardViewStudents.addEventListener('click', function() {
                                    var pId = partnerSelect ? partnerSelect.value : '';
                                    if (!pId) return;
                                    if (list && list.hasAttribute('hidden')) {
                                        list.removeAttribute('hidden');
                                        if (toggleBtn) {
                                            toggleBtn.setAttribute('aria-expanded', 'true');
                                            var lbl = document.getElementById('tpToggleStudentsLabel');
                                            if (lbl) lbl.textContent = 'Hide student list';
                                        }
                                    }
                                    var filterPartner = document.getElementById('tpFilterPartner');
                                    if (filterPartner) {
                                        filterPartner.value = pId;
                                        tpApplyStudentFilter();
                                    }
                                    list.scrollIntoView({ behavior: 'smooth' });
                                });
                            }

                            // 3. Student Filter & Dynamic Partner Dropdown
                            // Cache original partner options
                            var origPartnerOptions = [];
                            var studentPartnerSelect = document.getElementById('tpFilterPartner');
                            if (studentPartnerSelect) {
                                Array.from(studentPartnerSelect.options).forEach(function(o, idx) {
                                    if (idx > 0) {
                                        origPartnerOptions.push({
                                            value: o.value,
                                            name: o.getAttribute('data-name') || o.textContent.replace(/\s*\([0-9,]+\s*candidates\)/i, '').trim(),
                                            total: parseInt(o.getAttribute('data-partner-total') || '0', 10)
                                        });
                                    }
                                });
                            }

                            function updatePartnerDropdownForScheme(selectedScheme) {
                                if (!studentPartnerSelect) return;
                                var currentVal = studentPartnerSelect.value;
                                
                                // Count students per partner under selectedScheme
                                var partnerCounts = {};
                                var partnerSet = new Set();
                                var totalSchemeStudents = 0;
                                document.querySelectorAll('#tpStudentBody tr').forEach(function (row) {
                                    var rowScheme = row.getAttribute('data-scheme');
                                    var rowPartner = row.getAttribute('data-partner');
                                    if (selectedScheme === '' || rowScheme === selectedScheme) {
                                        totalSchemeStudents++;
                                        partnerCounts[rowPartner] = (partnerCounts[rowPartner] || 0) + 1;
                                        partnerSet.add(rowPartner);
                                    }
                                });

                                // Clear and rebuild options
                                studentPartnerSelect.innerHTML = '';
                                var topOpt = document.createElement('option');
                                topOpt.value = '';
                                if (selectedScheme === '') {
                                    topOpt.textContent = 'All training partners / institutes (' + totalSchemeStudents.toLocaleString() + ' candidates)';
                                } else {
                                    topOpt.textContent = 'All partners in this scheme (' + totalSchemeStudents.toLocaleString() + ' candidates)';
                                }
                                studentPartnerSelect.appendChild(topOpt);

                                var activePartnersCount = 0;
                                origPartnerOptions.forEach(function(p) {
                                    var count = partnerCounts[p.value] || 0;
                                    if (selectedScheme === '' || count > 0) {
                                        activePartnersCount++;
                                        var opt = document.createElement('option');
                                        opt.value = p.value;
                                        opt.textContent = p.name + ' (' + count.toLocaleString() + ' candidates)';
                                        if (p.value === currentVal) {
                                            opt.selected = true;
                                        }
                                        studentPartnerSelect.appendChild(opt);
                                    }
                                });

                                var badge = document.getElementById('tpPartnerCountIndicator');
                                if (badge) {
                                    badge.textContent = activePartnersCount + ' partner' + (activePartnersCount === 1 ? '' : 's');
                                }
                            }

                            window.tpApplyStudentFilter = function() {
                                var schemeElem = document.getElementById('tpFilterScheme');
                                var partnerElem = document.getElementById('tpFilterPartner');
                                if (!schemeElem || !partnerElem) return;

                                var scheme = schemeElem.value;
                                var partner = partnerElem.value;
                                var shown = 0;

                                document.querySelectorAll('#tpStudentBody tr').forEach(function (row) {
                                    var rowScheme = row.getAttribute('data-scheme');
                                    var rowPartner = row.getAttribute('data-partner');
                                    var matchScheme = (scheme === '' || rowScheme === scheme);
                                    var matchPartner = (partner === '' || rowPartner === partner);
                                    var match = matchScheme && matchPartner;
                                    row.hidden = !match;
                                    if (match) {
                                        shown += 1;
                                        var sl = row.querySelector('.tp-sl');
                                        if (sl) sl.textContent = String(shown);
                                    }
                                });

                                document.querySelectorAll('.tp-keep-scheme').forEach(function (input) { input.value = scheme; });
                                document.querySelectorAll('.tp-keep-partner').forEach(function (input) { input.value = partner; });
                                
                                var hint = document.getElementById('tpFilterHint');
                                if (hint) hint.hidden = true;
                                var empty = document.getElementById('tpFilterEmpty');
                                if (empty) empty.hidden = (shown > 0);
                                var help = document.getElementById('tpFilterHelp');
                                if (help) help.hidden = (shown === 0);
                                var scroll = document.getElementById('tpStudentScroll');
                                if (scroll) scroll.hidden = (shown === 0);
                            };

                            var schemeFilterElem = document.getElementById('tpFilterScheme');
                            if (schemeFilterElem) {
                                schemeFilterElem.addEventListener('change', function() {
                                    updatePartnerDropdownForScheme(this.value);
                                    tpApplyStudentFilter();
                                });
                            }

                            if (studentPartnerSelect) {
                                studentPartnerSelect.addEventListener('change', function() {
                                    tpApplyStudentFilter();
                                });
                            }

                            // Initialize
                            if (schemeFilterElem) {
                                updatePartnerDropdownForScheme(schemeFilterElem.value);
                            }
                            tpApplyStudentFilter();
                        })();
                    </script>
                <?php else: ?>
                    <div class="alert alert-light border text-muted my-2">
                        <i class="fas fa-info-circle me-1"></i> No student records in the list yet. Upload an Excel sheet above to view candidates.
                    </div>
                <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
