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
    $action = (string) ($_POST['action'] ?? '');
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
    } elseif ($action === 'save_figure') {
        $result = training_partner_save_figure(
            $conn,
            (int) ($_POST['partner_id'] ?? 0),
            (int) ($_POST['fy_year'] ?? 0),
            (string) ($_POST['quarter'] ?? ''),
            0,
            (int) ($_POST['certified'] ?? 0),
            (int) ($_POST['placed'] ?? 0),
            (int) ($_POST['figure_id'] ?? 0)
        );
        $success = $result['ok'] ? 'Quarter figures saved. Saving the same partner and quarter again updates those numbers.' : '';
        $error = $result['ok'] ? '' : (string) ($result['error'] ?? 'Could not save the figures.');
    } elseif ($action === 'save_student') {
        $result = training_partner_save_student(
            $conn,
            (int) ($_POST['partner_id'] ?? 0),
            (int) ($_POST['scheme_id'] ?? 0),
            (string) ($_POST['student_name'] ?? ''),
            (string) ($_POST['mobile'] ?? ''),
            (int) ($_POST['fy_year'] ?? 0),
            (string) ($_POST['quarter'] ?? ''),
            isset($_POST['is_certified']) ? 1 : 0,
            isset($_POST['is_placed']) ? 1 : 0,
            (string) ($_POST['course_name'] ?? ''),
            training_partner_parse_date($_POST['course_start_date'] ?? ''),
            training_partner_parse_date($_POST['course_end_date'] ?? ''),
            training_partner_details_from_request($_POST)
        );
        $success = $result['ok'] ? 'Student record saved under that scheme and training partner.' : '';
        $error = $result['ok'] ? '' : (string) ($result['error'] ?? 'Could not save the student record.');
    } elseif ($action === 'upload_students') {
        $file = $_FILES['student_sheet'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $error = 'Choose an Excel file to upload.';
        } else {
            $result = training_partner_import_students(
                $conn,
                (string) $file['tmp_name'],
                (string) ($file['name'] ?? ''),
                (int) ($_POST['partner_id'] ?? 0),
                (int) ($_POST['scheme_id'] ?? 0),
                (int) ($_POST['fy_year'] ?? 0),
                (string) ($_POST['quarter'] ?? ''),
                training_partner_parse_date($_POST['course_start_date'] ?? ''),
                training_partner_parse_date($_POST['course_end_date'] ?? '')
            );
            if (!empty($result['ok'])) {
                $showStudents = true;
                $filterScheme = (int) ($_POST['scheme_id'] ?? 0);
                $filterPartner = (int) ($_POST['partner_id'] ?? 0);
                $success = (int) $result['saved'] . ' students uploaded';
                if ((int) ($result['sheets'] ?? 0) > 1) {
                    $success .= ' from ' . (int) $result['sheets'] . ' sheets';
                }
                if ((int) ($result['partners_created'] ?? 0) > 0) {
                    $success .= '. ' . (int) $result['partners_created'] . ' training partners were added from the sheet';
                }
                $success .= '.';
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
    } elseif ($action === 'delete_figure') {
        $success = training_partner_delete_figure($conn, (int) ($_POST['figure_id'] ?? 0))
            ? 'Figure row removed.'
            : '';
        if ($success === '') {
            $error = 'Could not remove that figure row.';
        }
    }
}

$schemes = training_partner_schemes($conn);
$students = training_partner_students_list($conn);
$partners = training_partner_list($conn);
$centres = training_partner_centres($conn);
$figures = training_partner_figures_list($conn);
$editId = (int) ($_GET['edit'] ?? 0);
$editPartner = null;
foreach ($partners as $partner) {
    if ((int) $partner['id'] === $editId) {
        $editPartner = $partner;
        break;
    }
}
$editFigure = null;
$editFigureId = (int) ($_GET['figure'] ?? 0);
foreach ($figures as $figure) {
    if ((int) $figure['id'] === $editFigureId) {
        $editFigure = $figure;
        break;
    }
}
$currentQuarter = training_partner_current_quarter();
$figureQuarter = $editFigure
    ? training_partner_quarter_from_date((string) $editFigure['figure_month'])
    : $currentQuarter;
$fyChoices = [];
for ($fy = (int) date('Y') + 1; $fy >= (int) date('Y') - 6; $fy--) {
    $fyChoices[] = $fy;
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
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<div class="admin-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="admin-content">
        <div class="tp-page">
            <h1>Training Partner Figures</h1>
            <p class="tp-note">Choose the scheme or project first, then the training partner, and add each student. Tick Certified or Placed. Report Monitor counts those students on the certified and placed lines for that quarter.</p>
            <?php if ($success !== ''): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
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
                        <table class="tp-table mt-3">
                            <thead><tr><th>Partner</th><th>Centre</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($partners as $partner): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars((string) $partner['partner_name']); ?>
                                        <?php if ((string) $partner['partner_code'] !== ''): ?>
                                            <div class="text-muted small"><?php echo htmlspecialchars((string) $partner['partner_code']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo (int) $partner['centre_id'] > 0 ? htmlspecialchars(normalize_nielit_centre_name((string) $partner['centre_name'])) : 'All centres'; ?></td>
                                    <td>
                                        <a href="training_partners.php?edit=<?php echo (int) $partner['id']; ?>">Edit</a>
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
                    <?php endif; ?>
                </div>
                <div class="tp-card">
                    <h2><?php echo $editFigure ? 'Update quarter figures' : 'Quarter figures'; ?></h2>
                    <?php if (!$partners): ?>
                        <p class="text-muted">Add a training partner first.</p>
                    <?php else: ?>
                        <form method="post">
                            <input type="hidden" name="action" value="save_figure">
                            <input type="hidden" name="figure_id" value="<?php echo (int) ($editFigure['id'] ?? 0); ?>">
                            <div class="mb-3">
                                <label class="form-label">Training partner</label>
                                <select class="form-select" name="partner_id" required>
                                    <?php foreach ($partners as $partner): ?>
                                        <option value="<?php echo (int) $partner['id']; ?>" <?php echo ((int) ($editFigure['partner_id'] ?? 0) === (int) $partner['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $partner['partner_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">Financial year</label>
                                    <select class="form-select" name="fy_year" required>
                                        <?php foreach ($fyChoices as $fy): ?>
                                            <?php $endShort = substr((string) ($fy + 1), -2); ?>
                                            <option value="<?php echo $fy; ?>" <?php echo ((int) $figureQuarter['fy'] === $fy) ? 'selected' : ''; ?>>
                                                FY <?php echo $fy . '-' . $endShort; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">Quarter</label>
                                    <select class="form-select" name="quarter" required>
                                        <?php foreach (training_partner_quarter_options() as $quarterCode => $quarterLabel): ?>
                                            <option value="<?php echo htmlspecialchars($quarterCode); ?>" <?php echo (($figureQuarter['quarter'] ?? '') === $quarterCode) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($quarterLabel); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">Certified</label>
                                    <input class="form-control" type="number" min="0" step="1" name="certified" value="<?php echo (int) ($editFigure['certified'] ?? 0); ?>" required>
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">Placed</label>
                                    <input class="form-control" type="number" min="0" step="1" name="placed" value="<?php echo (int) ($editFigure['placed'] ?? 0); ?>" required>
                                </div>
                            </div>
                            <button class="btn btn-primary" type="submit"><?php echo $editFigure ? 'Update figures' : 'Save figures'; ?></button>
                        </form>
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
                                <label class="form-label">Financial year</label>
                                <select class="form-select" name="fy_year" required>
                                    <?php foreach ($fyChoices as $fy): ?>
                                        <?php $endShort = substr((string) ($fy + 1), -2); ?>
                                        <option value="<?php echo $fy; ?>" <?php echo ((int) $currentQuarter['fy'] === $fy) ? 'selected' : ''; ?>>
                                            FY <?php echo $fy . '-' . $endShort; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Quarter</label>
                                <select class="form-select" name="quarter" required>
                                    <?php foreach (training_partner_quarter_options() as $quarterCode => $quarterLabel): ?>
                                        <option value="<?php echo htmlspecialchars($quarterCode); ?>" <?php echo (($currentQuarter['quarter'] ?? '') === $quarterCode) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($quarterLabel); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
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
                                <label class="form-label">Course starting date</label>
                                <input class="form-control" type="date" name="course_start_date">
                            </div>
                            <div class="col-md-4 mb-3">
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
                        Choose the scheme, year, and quarter here. A workbook with several sheets is read sheet by sheet. The template now includes Sl. No., Batch No, Course Name, NIELIT Registration No., Name of candidate, Father's Name, Category, Gender, Aadhaar Card, APAAR ID, Religion, WhatsApp number, communication address, currently employed, Name of Institute, training venue address, HQ Accr number, District, and State. Course start date, course end date, Certified, and Placed are still read when those columns are present. If the sheet already names the institute, leave the partner below blank and those partners are created automatically. Dates entered here are used when a row has no dates of its own. Uploaded students stay in this list and are not added to All Students.
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
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Financial year</label>
                                <select class="form-select" name="fy_year" required>
                                    <?php foreach ($fyChoices as $fy): ?>
                                        <option value="<?php echo $fy; ?>" <?php echo ((int) $currentQuarter['fy'] === $fy) ? 'selected' : ''; ?>>FY <?php echo $fy . '-' . substr((string) ($fy + 1), -2); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Quarter</label>
                                <select class="form-select" name="quarter" required>
                                    <?php foreach (training_partner_quarter_options() as $quarterCode => $quarterLabel): ?>
                                        <option value="<?php echo htmlspecialchars($quarterCode); ?>" <?php echo (($currentQuarter['quarter'] ?? '') === $quarterCode) ? 'selected' : ''; ?>><?php echo htmlspecialchars($quarterLabel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Excel file</label>
                                <input class="form-control" type="file" name="student_sheet" accept=".xlsx,.xls,.csv" required>
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
                        </div>
                        <button class="btn btn-primary" type="submit">Upload students</button>
                    </form>
                <?php endif; ?>
                <?php if ($students): ?>
                    <button class="btn btn-warning mt-3" type="button" id="tpToggleStudents" aria-expanded="<?php echo $showStudents ? 'true' : 'false'; ?>">
                        <span id="tpToggleStudentsLabel"><?php echo $showStudents ? 'Hide student list' : 'Show student list'; ?></span>
                        (<?php echo count($students); ?>)
                    </button>
                    <div id="tpStudentList" class="mt-3" <?php echo $showStudents ? '' : 'hidden'; ?>>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="tpFilterScheme">Scheme / project</label>
                                <select class="form-select" id="tpFilterScheme">
                                    <option value="">Select scheme or project</option>
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
                                    <option value="">Select institute</option>
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
                                        <th>Start</th>
                                        <th>End</th>
                                        <th>Quarter</th>
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
                                        <td><?php echo htmlspecialchars(training_partner_show_date($student['course_start_date'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars(training_partner_show_date($student['course_end_date'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars(training_partner_quarter_from_date((string) $student['figure_month'])['label']); ?></td>
                                        <td><?php echo htmlspecialchars((string) $student['partner_name']); ?></td>
                                        <td class="tp-wrap"><?php echo htmlspecialchars((string) $student['scheme_name']); ?></td>
                                        <td><?php echo htmlspecialchars(training_partner_show($student['nielit_reg_no'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars(training_partner_show($student['category'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars(training_partner_show($student['gender'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars(training_partner_show($student['aadhaar'] ?? '')); ?></td>
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
                        document.getElementById('tpToggleStudents').addEventListener('click', function () {
                            var list = document.getElementById('tpStudentList');
                            var open = list.hasAttribute('hidden');
                            if (open) {
                                list.removeAttribute('hidden');
                            } else {
                                list.setAttribute('hidden', 'hidden');
                            }
                            this.setAttribute('aria-expanded', open ? 'true' : 'false');
                            document.getElementById('tpToggleStudentsLabel').textContent = open ? 'Hide student list' : 'Show student list';
                        });
                        function tpApplyStudentFilter() {
                            var scheme = document.getElementById('tpFilterScheme').value;
                            var partner = document.getElementById('tpFilterPartner').value;
                            var ready = scheme !== '' && partner !== '';
                            var shown = 0;
                            document.querySelectorAll('#tpStudentBody tr').forEach(function (row) {
                                var match = ready && row.getAttribute('data-scheme') === scheme && row.getAttribute('data-partner') === partner;
                                row.hidden = !match;
                                if (match) {
                                    shown += 1;
                                    row.querySelector('.tp-sl').textContent = String(shown);
                                }
                            });
                            document.querySelectorAll('.tp-keep-scheme').forEach(function (input) { input.value = scheme; });
                            document.querySelectorAll('.tp-keep-partner').forEach(function (input) { input.value = partner; });
                            document.getElementById('tpFilterHint').hidden = ready;
                            document.getElementById('tpFilterEmpty').hidden = !ready || shown > 0;
                            document.getElementById('tpFilterHelp').hidden = shown === 0;
                            document.getElementById('tpStudentScroll').hidden = shown === 0;
                        }
                        document.getElementById('tpFilterScheme').addEventListener('change', tpApplyStudentFilter);
                        document.getElementById('tpFilterPartner').addEventListener('change', tpApplyStudentFilter);
                        tpApplyStudentFilter();
                    </script>
                <?php else: ?>
                    <p class="text-muted mt-3 mb-0">No student records yet. Upload a sheet or add a candidate to open the student list.</p>
                <?php endif; ?>
            </div>
            <div class="tp-card mt-3">
                <h2>Saved figures</h2>
                <?php if (!$figures): ?>
                    <p class="text-muted mb-0">No figures saved yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="tp-table">
                            <thead>
                                <tr>
                                    <th>Quarter</th>
                                    <th>Partner</th>
                                    <th>Certified</th>
                                    <th>Placed</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($figures as $figure): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(training_partner_quarter_from_date((string) $figure['figure_month'])['label']); ?></td>
                                    <td><?php echo htmlspecialchars((string) $figure['partner_name']); ?></td>
                                    <td><?php echo number_format((int) $figure['certified']); ?></td>
                                    <td><?php echo number_format((int) $figure['placed']); ?></td>
                                    <td>
                                        <a href="training_partners.php?figure=<?php echo (int) $figure['id']; ?>">Update</a>
                                        <form method="post" onsubmit="return confirm('Remove this quarter?');">
                                            <input type="hidden" name="action" value="delete_figure">
                                            <input type="hidden" name="figure_id" value="<?php echo (int) $figure['id']; ?>">
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
    </main>
</div>
</body>
</html>
