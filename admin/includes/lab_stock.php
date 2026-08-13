<?php
/**
 * Lab stock register. Expects $labModule.
 */
require_once __DIR__ . '/lab_bootstrap.php';

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editItem = $editId > 0 ? getLabItem($conn, $labModule, $editId) : null;
$hasParts = !empty($lab['has_parts']);
$stockPage = $lab['stock'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!labVerifyCsrf()) {
        labFlashRedirect($stockPage, 'Invalid security token. Please try again.', false);
    }
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $result = saveLabItem($conn, $labModule, [
            'code' => $_POST['code'] ?? '',
            'name' => $_POST['name'] ?? '',
            'category' => $_POST['category'] ?? '',
            'make_name' => $_POST['make_name'] ?? '',
            'model_name' => $_POST['model_name'] ?? '',
            'serial_no' => $_POST['serial_no'] ?? '',
            'lab_name' => $_POST['lab_name'] ?? '',
            'location_note' => $_POST['location_note'] ?? '',
            'specs' => $_POST['specs'] ?? '',
            'purchase_date' => $_POST['purchase_date'] ?? '',
            'price' => $_POST['price'] ?? '',
            'remarks' => $_POST['remarks'] ?? '',
            'status' => $_POST['status'] ?? 'available',
            'centre_id' => (int) ($_POST['centre_id'] ?? 0),
            'created_by' => $adminUser,
            'add_default_parts' => !empty($_POST['add_default_parts']),
        ], $id > 0 ? $id : null);
        $go = $result['success'] && !empty($result['id']) && $hasParts
            ? ($stockPage . '?edit=' . (int) $result['id'])
            : ($result['success'] ? $stockPage : ($stockPage . ($id > 0 ? ('?edit=' . $id) : '')));
        labFlashRedirect($go, $result['message'], $result['success']);
    }
    if ($action === 'return_copy') {
        $result = returnLabItemCopy($conn, $labModule, (int) ($_POST['item_id'] ?? 0), $adminUser);
        labFlashRedirect($stockPage, $result['message'], $result['success']);
    }
    if ($hasParts && $action === 'save_part') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $partId = (int) ($_POST['part_id'] ?? 0);
        $result = saveLabPart($conn, $labModule, [
            'item_id' => $itemId,
            'part_type' => $_POST['part_type'] ?? '',
            'brand' => $_POST['brand'] ?? '',
            'serial_no' => $_POST['serial_no'] ?? '',
            'remarks' => $_POST['remarks'] ?? '',
            'status' => $_POST['status'] ?? 'working',
            'created_by' => $adminUser,
        ], $partId > 0 ? $partId : null);
        labFlashRedirect($stockPage . '?edit=' . $itemId, $result['message'], $result['success']);
    }
    if ($hasParts && $action === 'delete_part') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $result = deleteLabPart($conn, $labModule, (int) ($_POST['part_id'] ?? 0));
        labFlashRedirect($stockPage . '?edit=' . $itemId, $result['message'], $result['success']);
    }
}

$filterStatus = strtolower(trim((string) ($_GET['status'] ?? 'all')));
$filterCat = trim((string) ($_GET['category'] ?? ''));
$searchQ = trim((string) ($_GET['q'] ?? ''));
$filterCentre = (int) ($_GET['centre_id'] ?? 0);
$centres = labListCentres($conn, $labModule);
$items = listLabItems($conn, $labModule, [
    'status' => $filterStatus,
    'q' => $searchQ,
    'centre_id' => $filterCentre,
    'category' => $filterCat,
]);
$partMap = [];
if ($hasParts && $items !== []) {
    $ids = array_map(static function ($r) {
        return (int) $r['id'];
    }, $items);
    $partMap = labPartsSummary($conn, $ids);
}
$statuses = labItemStatuses();
$categories = $lab['categories'] ?? [];
$partTypes = labPartTypes();
$partStatuses = labPartStatuses();
$parts = ($hasParts && $editItem) ? listLabParts($conn, (int) $editItem['id']) : [];
$editPartId = isset($_GET['part']) ? (int) $_GET['part'] : 0;
$editPart = null;
if ($editPartId > 0) {
    foreach ($parts as $p) {
        if ((int) $p['id'] === $editPartId) {
            $editPart = $p;
            break;
        }
    }
}
$v = $editItem ?: [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($hasParts ? 'Systems & parts' : 'Stock Register'); ?> - <?php echo htmlspecialchars($lab['short']); ?></title>
    <?php adminEmitHeadAssets($active_theme, ['toast' => true]); ?>
    <style>
        .lib-form .form-group { margin-bottom: 0.85rem; }
        .lib-form label { font-weight: 500; display:block; margin-bottom: 4px; color:#334155; }
        .lib-row { display:flex; gap:12px; flex-wrap:wrap; }
        .lib-row .form-group { flex:1; min-width:160px; }
        .lib-muted { color:#64748b; font-size:0.85rem; }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<div class="admin-wrapper">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="admin-content">
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-<?php echo htmlspecialchars($lab['stock_icon']); ?>"></i> <?php echo $hasParts ? 'Systems & parts' : 'Stock Register'; ?></h4>
                <small><?php echo $hasParts ? 'System number is the key. Add keyboard, mouse, monitor and other parts under each PC.' : 'One row per instrument (asset number). Categories include Drone, IoT, Robotics.'; ?></small>
            </div>
            <div class="topbar-right">
                <div class="user-info">
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars($adminUser); ?></span>
                        <span class="user-role"><?php echo htmlspecialchars(function_exists('get_role_display_name') ? get_role_display_name() : 'Administrator'); ?></span>
                    </div>
                    <div class="user-avatar"><?php echo strtoupper(substr($adminUser, 0, 1)); ?></div>
                </div>
            </div>
        </div>
        <div class="admin-main">
            <div style="margin-bottom:1rem;"><?php include __DIR__ . '/lab_nav.php'; ?></div>

            <div class="content-card" style="margin-bottom:1.25rem;">
                <div class="card-header">
                    <h5 class="card-title" style="margin:0;">
                        <?php
                        echo $editItem
                            ? ('Edit ' . htmlspecialchars($lab['code_label']) . ' ' . htmlspecialchars((string) $editItem['code']))
                            : ('Add ' . htmlspecialchars($lab['item_label']));
                        ?>
                    </h5>
                    <?php if ($editItem): ?>
                        <a href="<?php echo htmlspecialchars($stockPage); ?>" class="btn btn-sm btn-secondary">Cancel edit</a>
                    <?php endif; ?>
                </div>
                <div style="padding:1rem 1.25rem;">
                    <form method="post" class="lib-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($labCsrf); ?>">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" value="<?php echo (int) ($v['id'] ?? 0); ?>">
                        <div class="lib-row">
                            <div class="form-group">
                                <label><?php echo htmlspecialchars($lab['code_label']); ?> <span class="text-danger">*</span></label>
                                <input class="form-control" name="code" required maxlength="50"
                                       value="<?php echo htmlspecialchars((string) ($v['code'] ?? '')); ?>"
                                       placeholder="<?php echo $hasParts ? 'e.g. SYS-01' : 'e.g. DRN-001'; ?>">
                            </div>
                            <div class="form-group">
                                <label>Centre <span class="text-danger">*</span></label>
                                <select class="form-control" name="centre_id" required>
                                    <option value="">Select centre…</option>
                                    <?php foreach ($centres as $ctr): ?>
                                        <option value="<?php echo (int) $ctr['id']; ?>" <?php echo ((int) ($v['centre_id'] ?? 0) === (int) $ctr['id'] || (count($centres) === 1 && (int) ($v['centre_id'] ?? 0) === 0)) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars((string) $ctr['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="flex:2;">
                                <label><?php echo htmlspecialchars($lab['name_label']); ?> <span class="text-danger">*</span></label>
                                <input class="form-control" name="name" required maxlength="255"
                                       value="<?php echo htmlspecialchars((string) ($v['name'] ?? '')); ?>"
                                       placeholder="<?php echo $hasParts ? 'e.g. Lab PC 12' : 'e.g. DJI Mini drone'; ?>">
                            </div>
                        </div>
                        <div class="lib-row">
                            <div class="form-group">
                                <label>Category</label>
                                <select class="form-control" name="category">
                                    <option value="">Select…</option>
                                    <?php foreach ($categories as $ck => $cl): ?>
                                        <option value="<?php echo htmlspecialchars($ck); ?>" <?php echo (($v['category'] ?? '') === $ck) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cl); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if ($hasParts): ?>
                            <div class="form-group">
                                <label>Lab name</label>
                                <input class="form-control" name="lab_name" maxlength="120"
                                       value="<?php echo htmlspecialchars((string) ($v['lab_name'] ?? '')); ?>"
                                       placeholder="e.g. Computer Lab 1">
                            </div>
                            <div class="form-group">
                                <label>Location / seat</label>
                                <input class="form-control" name="location_note" maxlength="120"
                                       value="<?php echo htmlspecialchars((string) ($v['location_note'] ?? '')); ?>"
                                       placeholder="e.g. Row A Seat 4">
                            </div>
                            <?php else: ?>
                            <div class="form-group">
                                <label>Shelf / location</label>
                                <input class="form-control" name="location_note" maxlength="120"
                                       value="<?php echo htmlspecialchars((string) ($v['location_note'] ?? '')); ?>">
                            </div>
                            <input type="hidden" name="lab_name" value="<?php echo htmlspecialchars((string) ($v['lab_name'] ?? '')); ?>">
                            <?php endif; ?>
                            <div class="form-group">
                                <label>Make</label>
                                <input class="form-control" name="make_name" maxlength="120"
                                       value="<?php echo htmlspecialchars((string) ($v['make_name'] ?? '')); ?>">
                            </div>
                            <div class="form-group">
                                <label>Model</label>
                                <input class="form-control" name="model_name" maxlength="120"
                                       value="<?php echo htmlspecialchars((string) ($v['model_name'] ?? '')); ?>">
                            </div>
                            <div class="form-group">
                                <label>Serial No</label>
                                <input class="form-control" name="serial_no" maxlength="120"
                                       value="<?php echo htmlspecialchars((string) ($v['serial_no'] ?? '')); ?>">
                            </div>
                        </div>
                        <div class="lib-row">
                            <?php if ($hasParts): ?>
                            <div class="form-group" style="flex:2;">
                                <label>Configuration (CPU, RAM, storage, OS)</label>
                                <input class="form-control" name="specs" maxlength="500"
                                       value="<?php echo htmlspecialchars((string) ($v['specs'] ?? '')); ?>"
                                       placeholder="e.g. i5 / 8GB / 512GB SSD / Windows 11">
                            </div>
                            <?php else: ?>
                            <input type="hidden" name="specs" value="<?php echo htmlspecialchars((string) ($v['specs'] ?? '')); ?>">
                            <?php endif; ?>
                            <div class="form-group">
                                <label>Purchase date</label>
                                <input type="date" class="form-control" name="purchase_date"
                                       value="<?php echo htmlspecialchars((string) ($v['purchase_date'] ?? '')); ?>">
                            </div>
                            <div class="form-group">
                                <label>Price</label>
                                <input class="form-control" name="price" inputmode="decimal"
                                       value="<?php echo htmlspecialchars((string) ($v['price'] ?? '')); ?>">
                            </div>
                            <?php if (($v['status'] ?? '') !== 'issued'): ?>
                            <div class="form-group">
                                <label>Status</label>
                                <select class="form-control" name="status">
                                    <?php foreach ($statuses as $key => $label): ?>
                                        <?php if ($key === 'issued') { continue; } ?>
                                        <option value="<?php echo htmlspecialchars($key); ?>" <?php echo (($v['status'] ?? 'available') === $key) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>Remarks</label>
                            <textarea class="form-control" name="remarks" rows="2"><?php echo htmlspecialchars((string) ($v['remarks'] ?? '')); ?></textarea>
                        </div>
                        <?php if ($hasParts && !$editItem): ?>
                        <div class="form-group">
                            <label style="font-weight:500;">
                                <input type="checkbox" name="add_default_parts" value="1" checked>
                                Also add Keyboard, Mouse and Monitor as working parts
                            </label>
                        </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $editItem ? 'Update stock' : 'Add to stock'; ?>
                        </button>
                    </form>
                </div>
            </div>

            <?php if ($hasParts && $editItem): ?>
            <div class="content-card" style="margin-bottom:1.25rem;">
                <div class="card-header">
                    <h5 class="card-title" style="margin:0;">Parts for <?php echo htmlspecialchars((string) $editItem['code']); ?></h5>
                </div>
                <div style="padding:1rem 1.25rem;">
                    <p class="lib-muted">Track keyboard, mouse, monitor and other parts against this system number.</p>
                    <form method="post" class="lib-form" style="margin-bottom:1rem;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($labCsrf); ?>">
                        <input type="hidden" name="action" value="save_part">
                        <input type="hidden" name="item_id" value="<?php echo (int) $editItem['id']; ?>">
                        <input type="hidden" name="part_id" value="<?php echo (int) ($editPart['id'] ?? 0); ?>">
                        <div class="lib-row">
                            <div class="form-group">
                                <label>Part type <span class="text-danger">*</span></label>
                                <select class="form-control" name="part_type" required>
                                    <option value="">Select…</option>
                                    <?php foreach ($partTypes as $pk => $pl): ?>
                                        <option value="<?php echo htmlspecialchars($pk); ?>" <?php echo (($editPart['part_type'] ?? '') === $pk) ? 'selected' : ''; ?>><?php echo htmlspecialchars($pl); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Brand / make</label>
                                <input class="form-control" name="brand" maxlength="120" value="<?php echo htmlspecialchars((string) ($editPart['brand'] ?? '')); ?>">
                            </div>
                            <div class="form-group">
                                <label>Serial No</label>
                                <input class="form-control" name="serial_no" maxlength="120" value="<?php echo htmlspecialchars((string) ($editPart['serial_no'] ?? '')); ?>">
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select class="form-control" name="status">
                                    <?php foreach ($partStatuses as $pk => $pl): ?>
                                        <option value="<?php echo htmlspecialchars($pk); ?>" <?php echo (($editPart['status'] ?? 'working') === $pk) ? 'selected' : ''; ?>><?php echo htmlspecialchars($pl); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="flex:2;">
                                <label>Remarks</label>
                                <input class="form-control" name="remarks" maxlength="255" value="<?php echo htmlspecialchars((string) ($editPart['remarks'] ?? '')); ?>">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary"><?php echo $editPart ? 'Update part' : 'Add part'; ?></button>
                        <?php if ($editPart): ?>
                            <a class="btn btn-sm btn-secondary" href="<?php echo htmlspecialchars($stockPage . '?edit=' . (int) $editItem['id']); ?>">Cancel</a>
                        <?php endif; ?>
                    </form>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Part</th>
                                    <th>Brand</th>
                                    <th>Serial</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($parts)): ?>
                                    <tr><td colspan="6" class="text-muted text-center" style="padding:1.25rem;">No parts added yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($parts as $p): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars((string) $p['part_type']); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($p['brand'] ?? '')); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($p['serial_no'] ?? '')); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo labStatusBadgeClass((string) $p['status']); ?>">
                                                    <?php echo htmlspecialchars($partStatuses[$p['status']] ?? ucfirst((string) $p['status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars((string) ($p['remarks'] ?? '')); ?></td>
                                            <td>
                                                <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars($stockPage . '?edit=' . (int) $editItem['id'] . '&part=' . (int) $p['id']); ?>">Edit</a>
                                                <form method="post" style="display:inline;" onsubmit="return confirm('Remove this part?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($labCsrf); ?>">
                                                    <input type="hidden" name="action" value="delete_part">
                                                    <input type="hidden" name="item_id" value="<?php echo (int) $editItem['id']; ?>">
                                                    <input type="hidden" name="part_id" value="<?php echo (int) $p['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="content-card">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                    <h5 class="card-title" style="margin:0;">Stock list <span class="lib-muted">(<?php echo count($items); ?>)</span></h5>
                    <form method="get" style="margin:0;display:flex;gap:8px;flex-wrap:wrap;">
                        <input class="form-control form-control-sm" name="q" placeholder="Search <?php echo htmlspecialchars(strtolower($lab['code_label'])); ?>, name, serial…"
                               value="<?php echo htmlspecialchars($searchQ); ?>" style="min-width:200px;">
                        <select class="form-control form-control-sm" name="centre_id" onchange="this.form.submit()">
                            <?php if (count($centres) !== 1): ?>
                                <option value="0">All centres</option>
                            <?php endif; ?>
                            <?php foreach ($centres as $ctr): ?>
                                <option value="<?php echo (int) $ctr['id']; ?>" <?php echo ($filterCentre === (int) $ctr['id'] || (count($centres) === 1 && $filterCentre === 0)) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string) $ctr['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select class="form-control form-control-sm" name="category" onchange="this.form.submit()">
                            <option value="">All categories</option>
                            <?php foreach ($categories as $ck => $cl): ?>
                                <option value="<?php echo htmlspecialchars($ck); ?>" <?php echo $filterCat === $ck ? 'selected' : ''; ?>><?php echo htmlspecialchars($cl); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select class="form-control form-control-sm" name="status" onchange="this.form.submit()">
                            <option value="all">All statuses</option>
                            <?php foreach ($statuses as $key => $label): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $filterStatus === $key ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-sm btn-secondary" type="submit">Search</button>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th><?php echo htmlspecialchars($lab['code_label']); ?></th>
                                <th>Name</th>
                                <th>Centre</th>
                                <th>Category</th>
                                <?php if ($hasParts): ?><th>Parts</th><?php else: ?><th>Location</th><?php endif; ?>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr><td colspan="7" class="text-center text-muted" style="padding:2rem;">No records in stock yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($items as $b): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) $b['code']); ?></td>
                                        <td><?php echo htmlspecialchars((string) $b['name']); ?></td>
                                        <td><?php echo htmlspecialchars(libraryCentreLabel($b)); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($b['category'] ?? '')); ?></td>
                                        <td>
                                            <?php if ($hasParts): ?>
                                                <?php
                                                $plist = $partMap[(int) $b['id']] ?? [];
                                                if ($plist === []) {
                                                    echo '<span class="lib-muted">No parts</span>';
                                                } else {
                                                    $bits = [];
                                                    foreach ($plist as $pr) {
                                                        $bits[] = (string) $pr['part_type'] . ((($pr['status'] ?? '') !== 'working') ? ' (' . $pr['status'] . ')' : '');
                                                    }
                                                    echo htmlspecialchars(implode(', ', $bits));
                                                }
                                                ?>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars((string) ($b['location_note'] ?? '')); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo labStatusBadgeClass((string) $b['status']); ?>">
                                                <?php echo htmlspecialchars($statuses[$b['status']] ?? ucfirst((string) $b['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars($stockPage); ?>?edit=<?php echo (int) $b['id']; ?>">Edit</a>
                                            <?php if (($b['status'] ?? '') === 'issued'): ?>
                                                <form method="post" style="display:inline;" onsubmit="return labConfirmReturn(event, this);">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($labCsrf); ?>">
                                                    <input type="hidden" name="action" value="return_copy">
                                                    <input type="hidden" name="item_id" value="<?php echo (int) $b['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-success">Return</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
<script>
<?php if ($message !== ''): ?>
showToast(<?php echo json_encode($message); ?>, <?php echo json_encode($message_type === 'danger' ? 'error' : $message_type); ?>);
<?php endif; ?>
</script>
</body>
</html>
