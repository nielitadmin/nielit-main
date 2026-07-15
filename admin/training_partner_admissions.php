<?php
/**
 * Training Partner Quarterly Admissions — manual entry module.
 */

session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/training_partner_admissions_helper.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

if (($_SESSION['admin_role'] ?? '') !== 'master_admin') {
    $_SESSION['message'] = 'Access Denied';
    $_SESSION['message_type'] = 'danger';
    header('Location: dashboard.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$active_theme = loadActiveTheme($conn);
$selectedYear = isset($_GET['year']) ? (int) $_GET['year'] : report_monitor_get_financial_year_start();
$categoryOptions = tp_admissions_get_category_options();
$adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if ($token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $_SESSION['message'] = 'Invalid security token. Refresh the page and try again.';
        $_SESSION['message_type'] = 'danger';
        header('Location: training_partner_admissions.php?year=' . $selectedYear);
        exit;
    }

    $action = $_POST['action'] ?? '';
    $redirectYear = isset($_POST['financial_year_start']) ? (int) $_POST['financial_year_start'] : $selectedYear;

    if ($action === 'save_entry') {
        $entryId = isset($_POST['entry_id']) ? (int) $_POST['entry_id'] : 0;
        $result = tp_admissions_save($conn, $_POST, $adminId, $entryId > 0 ? $entryId : null);
        $_SESSION['message'] = $result['message'] ?? 'Entry saved.';
        $_SESSION['message_type'] = !empty($result['success']) ? 'success' : 'danger';
        header('Location: training_partner_admissions.php?year=' . $redirectYear);
        exit;
    }

    if ($action === 'delete_entry') {
        $entryId = (int) ($_POST['entry_id'] ?? 0);
        $result = tp_admissions_delete($conn, $entryId, $adminId);
        $_SESSION['message'] = $result['message'] ?? 'Entry removed.';
        $_SESSION['message_type'] = !empty($result['success']) ? 'success' : 'danger';
        header('Location: training_partner_admissions.php?year=' . $redirectYear);
        exit;
    }
}

$entries = tp_admissions_list($conn, $selectedYear, true);
$grandQ1 = array_sum(array_column($entries, 'Q1'));
$grandQ2 = array_sum(array_column($entries, 'Q2'));
$grandQ3 = array_sum(array_column($entries, 'Q3'));
$grandQ4 = array_sum(array_column($entries, 'Q4'));
$grandTotal = array_sum(array_column($entries, 'total'));

$pageTitle = 'Training Partner Admissions';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> | NIELIT Bhubaneswar</title>
    <?php injectThemeCSS($active_theme); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo APP_URL; ?>/assets/css/admin-theme.css" rel="stylesheet">
</head>
<body>
<div class="admin-wrapper">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="admin-content">
<div class="admin-main">
<div class="container-fluid py-3">

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="mb-1"><i class="fas fa-handshake me-2"></i><?php echo htmlspecialchars($pageTitle); ?></h2>
        <p class="text-muted mb-0">Manually record quarterly admissions reported by training partners (TP). Figures are merged into Report Monitor.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo app_url('admin/report_monitor'); ?>?year=<?php echo (int) $selectedYear; ?>" class="btn btn-outline-secondary">
            <i class="fas fa-chart-line me-1"></i> Report Monitor
        </a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tpEntryModal" onclick="openTpEntryModal()">
            <i class="fas fa-plus me-1"></i> Add Entry
        </button>
    </div>
</div>

<?php if (!empty($_SESSION['message'])): ?>
<div class="alert alert-<?php echo htmlspecialchars($_SESSION['message_type'] ?? 'info'); ?> alert-dismissible fade show">
    <?php echo htmlspecialchars($_SESSION['message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['message'], $_SESSION['message_type']); endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Financial Year (starts April)</label>
                <select name="year" class="form-select" onchange="this.form.submit()">
                    <?php for ($y = (int) date('Y'); $y >= 2020; $y--): ?>
                        <?php $fyEndShort = substr((string) ($y + 1), -2); ?>
                        <option value="<?php echo $y; ?>" <?php echo $selectedYear === $y ? 'selected' : ''; ?>>
                            FY <?php echo $y . '-' . $fyEndShort; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-8">
                <div class="alert alert-info mb-0 py-2">
                    <i class="fas fa-info-circle me-1"></i>
                    Add one row per TP course. Enter students trained in Q1–Q4. Data appears in <strong>Report Monitor</strong> under each selected category and in the Training Partner detail table.
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Entries for FY <?php echo (int) $selectedYear; ?></strong>
        <span class="badge bg-primary"><?php echo number_format($grandTotal); ?> students trained</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0">
                <thead class="table-light">
                <tr>
                    <th>Training Partner</th>
                    <th>Course</th>
                    <th>Category</th>
                    <th class="text-end">Q1</th>
                    <th class="text-end">Q2</th>
                    <th class="text-end">Q3</th>
                    <th class="text-end">Q4</th>
                    <th class="text-end">Total</th>
                    <th class="text-end" style="width:120px;">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($entries)): ?>
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">
                        No training partner entries for FY <?php echo (int) $selectedYear; ?>.
                        Click <strong>Add Entry</strong> to record TP admissions manually.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($entries as $entry): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($entry['partner_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($entry['course_name']); ?></td>
                    <td><small><?php echo htmlspecialchars($entry['category_label']); ?></small></td>
                    <td class="text-end"><?php echo number_format($entry['Q1']); ?></td>
                    <td class="text-end"><?php echo number_format($entry['Q2']); ?></td>
                    <td class="text-end"><?php echo number_format($entry['Q3']); ?></td>
                    <td class="text-end"><?php echo number_format($entry['Q4']); ?></td>
                    <td class="text-end fw-bold"><?php echo number_format($entry['total']); ?></td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-primary"
                                onclick='openTpEntryModal(<?php echo json_encode($entry, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="post" class="d-inline" onsubmit="return confirm('Remove this training partner entry?');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="delete_entry">
                            <input type="hidden" name="entry_id" value="<?php echo (int) $entry['id']; ?>">
                            <input type="hidden" name="financial_year_start" value="<?php echo (int) $selectedYear; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <?php if (!empty($entries)): ?>
                <tfoot class="table-light">
                <tr>
                    <th colspan="3">Grand Total</th>
                    <th class="text-end"><?php echo number_format($grandQ1); ?></th>
                    <th class="text-end"><?php echo number_format($grandQ2); ?></th>
                    <th class="text-end"><?php echo number_format($grandQ3); ?></th>
                    <th class="text-end"><?php echo number_format($grandQ4); ?></th>
                    <th class="text-end"><?php echo number_format($grandTotal); ?></th>
                    <th></th>
                </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

</div>
</div>
</main>
</div>

<div class="modal fade" id="tpEntryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="tpEntryForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="save_entry">
                <input type="hidden" name="entry_id" id="tp_entry_id" value="">
                <input type="hidden" name="financial_year_start" value="<?php echo (int) $selectedYear; ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="tpEntryModalTitle">Add Training Partner Entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Training Partner Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="partner_name" id="tp_partner_name" required maxlength="200" placeholder="e.g. ABC Skills Pvt Ltd">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Course Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="course_name" id="tp_course_name" required maxlength="255" placeholder="e.g. Web Development with PHP">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" name="category_key" id="tp_category_key" required>
                                <option value="">Select category</option>
                                <?php foreach ($categoryOptions as $key => $label): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Q1 Students</label>
                            <input type="number" class="form-control text-end" name="q1_students" id="tp_q1" min="0" step="1" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Q2 Students</label>
                            <input type="number" class="form-control text-end" name="q2_students" id="tp_q2" min="0" step="1" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Q3 Students</label>
                            <input type="number" class="form-control text-end" name="q3_students" id="tp_q3" min="0" step="1" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Q4 Students</label>
                            <input type="number" class="form-control text-end" name="q4_students" id="tp_q4" min="0" step="1" value="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Remarks (optional)</label>
                            <textarea class="form-control" name="remarks" id="tp_remarks" rows="2" maxlength="1000" placeholder="Batch code, location, or source document reference"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openTpEntryModal(entry) {
    const title = document.getElementById('tpEntryModalTitle');
    document.getElementById('tp_entry_id').value = entry && entry.id ? entry.id : '';
    document.getElementById('tp_partner_name').value = entry && entry.partner_name ? entry.partner_name : '';
    document.getElementById('tp_course_name').value = entry && entry.course_name ? entry.course_name : '';
    document.getElementById('tp_category_key').value = entry && entry.category_key ? entry.category_key : '';
    document.getElementById('tp_q1').value = entry ? entry.Q1 : 0;
    document.getElementById('tp_q2').value = entry ? entry.Q2 : 0;
    document.getElementById('tp_q3').value = entry ? entry.Q3 : 0;
    document.getElementById('tp_q4').value = entry ? entry.Q4 : 0;
    document.getElementById('tp_remarks').value = entry && entry.remarks ? entry.remarks : '';
    if (title) {
        title.textContent = entry && entry.id ? 'Edit Training Partner Entry' : 'Add Training Partner Entry';
    }
}
</script>
</body>
</html>
