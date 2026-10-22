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
$success = '';
$error = '';

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
            (string) ($_POST['figure_month'] ?? ''),
            (int) ($_POST['enrolled'] ?? 0),
            (int) ($_POST['certified'] ?? 0),
            (int) ($_POST['placed'] ?? 0)
        );
        $success = $result['ok'] ? 'Training partner figures saved. They appear on Report Monitor for that month.' : '';
        $error = $result['ok'] ? '' : (string) ($result['error'] ?? 'Could not save the figures.');
    } elseif ($action === 'delete_figure') {
        $success = training_partner_delete_figure($conn, (int) ($_POST['figure_id'] ?? 0))
            ? 'Figure row removed.'
            : '';
        if ($success === '') {
            $error = 'Could not remove that figure row.';
        }
    }
}

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
        .tp-table th { font-size: 12px; text-transform: uppercase; color: #64748b; }
        .tp-note { color: #475569; margin: 8px 0 18px; }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<div class="admin-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="admin-content">
        <div class="tp-page">
            <h1>Training Partner Figures</h1>
            <p class="tp-note">Enter whole numbers for each partner and month. Report Monitor adds the months that fall in the selected financial year or quarter. These figures stay separate from the centre student totals.</p>
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
                    <h2>Monthly figures</h2>
                    <?php if (!$partners): ?>
                        <p class="text-muted">Add a training partner first.</p>
                    <?php else: ?>
                        <form method="post">
                            <input type="hidden" name="action" value="save_figure">
                            <div class="mb-3">
                                <label class="form-label">Training partner</label>
                                <select class="form-select" name="partner_id" required>
                                    <?php foreach ($partners as $partner): ?>
                                        <option value="<?php echo (int) $partner['id']; ?>"><?php echo htmlspecialchars((string) $partner['partner_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Month</label>
                                <input class="form-control" type="month" name="figure_month" required value="<?php echo date('Y-m'); ?>">
                            </div>
                            <div class="row">
                                <div class="col-4 mb-3">
                                    <label class="form-label">Enrolled</label>
                                    <input class="form-control" type="number" min="0" step="1" name="enrolled" value="0" required>
                                </div>
                                <div class="col-4 mb-3">
                                    <label class="form-label">Certified</label>
                                    <input class="form-control" type="number" min="0" step="1" name="certified" value="0" required>
                                </div>
                                <div class="col-4 mb-3">
                                    <label class="form-label">Placed</label>
                                    <input class="form-control" type="number" min="0" step="1" name="placed" value="0" required>
                                </div>
                            </div>
                            <button class="btn btn-primary" type="submit">Save figures</button>
                        </form>
                    <?php endif; ?>
                </div>
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
                                    <th>Month</th>
                                    <th>Partner</th>
                                    <th>Enrolled</th>
                                    <th>Certified</th>
                                    <th>Placed</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($figures as $figure): ?>
                                <tr>
                                    <td><?php echo date('M Y', strtotime((string) $figure['figure_month'])); ?></td>
                                    <td><?php echo htmlspecialchars((string) $figure['partner_name']); ?></td>
                                    <td><?php echo number_format((int) $figure['enrolled']); ?></td>
                                    <td><?php echo number_format((int) $figure['certified']); ?></td>
                                    <td><?php echo number_format((int) $figure['placed']); ?></td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('Remove this month?');">
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
