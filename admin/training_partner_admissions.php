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

$adminRole = $_SESSION['admin_role'] ?? '';
$allowedRoles = ['master_admin', 'course_coordinator'];
if (!in_array($adminRole, $allowedRoles, true)) {
    $_SESSION['message'] = 'Access Denied';
    $_SESSION['message_type'] = 'danger';
    header('Location: dashboard.php');
    exit;
}

$isMasterAdmin = ($adminRole === 'master_admin');
$isCourseCoordinator = ($adminRole === 'course_coordinator');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$active_theme = loadActiveTheme($conn);
$selectedYear = isset($_GET['year']) ? (int) $_GET['year'] : report_monitor_get_financial_year_start();
$categoryOptions = tp_admissions_get_category_options();
$centreOptions = tp_admissions_get_centre_options($conn);
$socialCategoryOptions = tp_admissions_get_social_category_options();
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
        $result = tp_admissions_save($conn, $_POST, $adminId, $entryId > 0 ? $entryId : null, $isMasterAdmin);
        $_SESSION['message'] = $result['message'] ?? 'Entry saved.';
        $_SESSION['message_type'] = !empty($result['success']) ? 'success' : 'danger';
        header('Location: training_partner_admissions.php?year=' . $redirectYear);
        exit;
    }

    if ($action === 'delete_entry') {
        $entryId = (int) ($_POST['entry_id'] ?? 0);
        $result = tp_admissions_delete($conn, $entryId, $adminId, $isMasterAdmin);
        $_SESSION['message'] = $result['message'] ?? 'Entry removed.';
        $_SESSION['message_type'] = !empty($result['success']) ? 'success' : 'danger';
        header('Location: training_partner_admissions.php?year=' . $redirectYear);
        exit;
    }
}

$listCreatedBy = ($isCourseCoordinator && $adminId > 0) ? $adminId : null;
$entries = tp_admissions_list($conn, $selectedYear, true, $listCreatedBy);
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
    <link href="<?php echo APP_URL; ?>/assets/css/toast-notifications.css" rel="stylesheet">
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
        <p class="text-muted mb-0">Add training partner admissions: partner name, training centre, course, course category, social category, quarter, and students trained.</p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($isMasterAdmin): ?>
        <a href="<?php echo app_url('admin/report_monitor'); ?>?year=<?php echo (int) $selectedYear; ?>" class="btn btn-outline-secondary">
            <i class="fas fa-chart-line me-1"></i> Report Monitor
        </a>
        <?php endif; ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tpEntryModal" onclick="openTpEntryModal()">
            <i class="fas fa-plus me-1"></i> Add Entry
        </button>
    </div>
</div>

<?php
$flashMessage = $_SESSION['message'] ?? null;
$flashType = $_SESSION['message_type'] ?? null;
if (!empty($flashMessage)) {
    unset($_SESSION['message'], $_SESSION['message_type']);
}
?>

<?php if ($isCourseCoordinator): ?>
<div class="alert alert-secondary py-2 mb-4">
    <i class="fas fa-user-lock me-1"></i>
    You are viewing <strong>only your own</strong> training partner entries. Master Admin can see all coordinators' records on Report Monitor.
</div>
<?php endif; ?>

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
                    <?php if ($isMasterAdmin): ?>
                    Figures appear in the separate <strong>Category Quarterly Admissions Summary — Training Partners</strong> on Report Monitor (not mixed with NIELIT data).
                    <?php else: ?>
                    Enter training partner admissions here. Summary reports are available to Master Admin on Report Monitor.
                    <?php endif; ?>
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
                    <th>Training Centre</th>
                    <th>Course</th>
                    <th>Course Category</th>
                    <th>Social Category</th>
                    <th>Quarter</th>
                    <th class="text-end">Students</th>
                    <?php if ($isMasterAdmin): ?>
                    <th>Entered By</th>
                    <?php endif; ?>
                    <th class="text-end" style="width:120px;">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($entries)): ?>
                <tr>
                    <td colspan="<?php echo $isMasterAdmin ? 9 : 8; ?>" class="text-center text-muted py-4">
                        No training partner entries for FY <?php echo (int) $selectedYear; ?>.
                        Click <strong>Add Entry</strong> to record TP admissions manually.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($entries as $entry): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($entry['partner_name']); ?></strong></td>
                    <td>
                        <?php if (!empty($entry['centre_name'])): ?>
                            <?php echo htmlspecialchars($entry['centre_name']); ?>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($entry['course_name']); ?></td>
                    <td><small><?php echo htmlspecialchars($entry['category_label']); ?></small></td>
                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($entry['social_category_label'] ?? 'General'); ?></span></td>
                    <td><span class="badge bg-primary"><?php echo htmlspecialchars($entry['quarter'] ?: '—'); ?></span></td>
                    <td class="text-end fw-bold"><?php echo number_format($entry['students_trained']); ?></td>
                    <?php if ($isMasterAdmin): ?>
                    <td>
                        <?php if (!empty($entry['created_by_name'])): ?>
                            <?php echo htmlspecialchars($entry['created_by_name']); ?>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-primary tp-edit-entry-btn"
                                data-entry="<?php echo htmlspecialchars(json_encode($entry, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8'); ?>">
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
                    <th colspan="<?php echo $isMasterAdmin ? 7 : 6; ?>">Quarterly totals</th>
                    <th class="text-end"><?php echo number_format($grandTotal); ?></th>
                    <?php if ($isMasterAdmin): ?>
                    <th></th>
                    <?php endif; ?>
                    <th></th>
                </tr>
                <tr>
                    <td colspan="<?php echo $isMasterAdmin ? 9 : 8; ?>" class="text-muted small">Q1: <?php echo number_format($grandQ1); ?> · Q2: <?php echo number_format($grandQ2); ?> · Q3: <?php echo number_format($grandQ3); ?> · Q4: <?php echo number_format($grandQ4); ?></td>
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
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="tpEntryForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="save_entry">
                <input type="hidden" name="entry_id" id="tp_entry_id" value="">
                <input type="hidden" name="financial_year_start" value="<?php echo (int) $selectedYear; ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="tpEntryModalTitle">Add Entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Training Partner Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="partner_name" id="tp_partner_name" required maxlength="200" placeholder="e.g. ABC Skills Pvt Ltd">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Training Centre <span class="text-danger">*</span></label>
                        <select class="form-select" name="centre_id" id="tp_centre_id" required>
                            <option value="">Select training centre</option>
                            <?php foreach ($centreOptions as $centreId => $centreLabel): ?>
                            <option value="<?php echo (int) $centreId; ?>"><?php echo htmlspecialchars($centreLabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($centreOptions)): ?>
                        <div class="form-text text-danger">No active training centres found. Ask Master Admin to add centres first.</div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Course Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="course_name" id="tp_course_name" required maxlength="255" placeholder="e.g. Web Development">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Course Category <span class="text-danger">*</span></label>
                        <select class="form-select" name="category_key" id="tp_category_key" required>
                            <option value="">Select course category</option>
                            <?php foreach ($categoryOptions as $key => $label): ?>
                            <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Social Category <span class="text-danger">*</span></label>
                        <select class="form-select" name="social_category_key" id="tp_social_category_key" required>
                            <option value="">Select social category</option>
                            <?php foreach ($socialCategoryOptions as $key => $label): ?>
                            <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quarter <span class="text-danger">*</span></label>
                        <select class="form-select" name="quarter" id="tp_quarter" required>
                            <option value="">Select quarter</option>
                            <option value="Q1">Q1 (Apr–Jun)</option>
                            <option value="Q2">Q2 (Jul–Sep)</option>
                            <option value="Q3">Q3 (Oct–Dec)</option>
                            <option value="Q4">Q4 (Jan–Mar)</option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Students Trained <span class="text-danger">*</span></label>
                        <input type="number" class="form-control text-end" name="students_trained" id="tp_students_trained" min="1" step="1" required placeholder="e.g. 50">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
<?php if (!empty($flashMessage)): ?>
<?php
$toastType = ($flashType === 'danger') ? 'error' : (string) $flashType;
if (!in_array($toastType, ['success', 'error', 'warning', 'info'], true)) {
    $toastType = 'info';
}
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof toast !== 'undefined') {
        toast.<?php echo $toastType; ?>('<?php echo addslashes((string) $flashMessage); ?>', 5000);
    }
});
</script>
<?php endif; ?>
<script>
function openTpEntryModal(entry) {
    entry = entry || null;

    const title = document.getElementById('tpEntryModalTitle');
    const submitBtn = document.querySelector('#tpEntryForm button[type="submit"]');
    document.getElementById('tp_entry_id').value = entry && entry.id ? entry.id : '';
    document.getElementById('tp_partner_name').value = entry && entry.partner_name ? entry.partner_name : '';
    document.getElementById('tp_centre_id').value = entry && entry.centre_id ? String(entry.centre_id) : '';
    document.getElementById('tp_course_name').value = entry && entry.course_name ? entry.course_name : '';
    document.getElementById('tp_category_key').value = entry && entry.category_key ? entry.category_key : '';
    document.getElementById('tp_social_category_key').value = entry && entry.social_category_key ? entry.social_category_key : '';
    document.getElementById('tp_quarter').value = entry && entry.quarter ? entry.quarter : '';
    document.getElementById('tp_students_trained').value = entry && entry.students_trained ? entry.students_trained : '';
    if (title) {
        title.textContent = entry && entry.id ? 'Edit Entry' : 'Add Entry';
    }
    if (submitBtn) {
        submitBtn.innerHTML = entry && entry.id
            ? '<i class="fas fa-save me-1"></i> Update'
            : '<i class="fas fa-save me-1"></i> Add';
    }

    const modalEl = document.getElementById('tpEntryModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
}

document.querySelectorAll('.tp-edit-entry-btn').forEach(function (button) {
    button.addEventListener('click', function () {
        let entry = null;
        try {
            entry = JSON.parse(button.getAttribute('data-entry') || 'null');
        } catch (error) {
            entry = null;
        }
        openTpEntryModal(entry);
    });
});
</script>
</body>
</html>
