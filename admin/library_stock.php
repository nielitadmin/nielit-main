<?php
/**
 * Library stock (accession) register.
 */
require_once __DIR__ . '/includes/library_bootstrap.php';

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editBook = $editId > 0 ? getLibraryBook($conn, $editId) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!libraryVerifyCsrf()) {
        libraryFlashRedirect('library_stock.php', 'Invalid security token. Please try again.', false);
    }
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $result = saveLibraryBook($conn, [
            'accession_no' => $_POST['accession_no'] ?? '',
            'title' => $_POST['title'] ?? '',
            'author' => $_POST['author'] ?? '',
            'publisher' => $_POST['publisher'] ?? '',
            'isbn' => $_POST['isbn'] ?? '',
            'category' => $_POST['category'] ?? '',
            'edition' => $_POST['edition'] ?? '',
            'pub_year' => $_POST['pub_year'] ?? '',
            'purchase_date' => $_POST['purchase_date'] ?? '',
            'bill_no' => $_POST['bill_no'] ?? '',
            'price' => $_POST['price'] ?? '',
            'source' => $_POST['source'] ?? 'Purchased',
            'shelf_location' => $_POST['shelf_location'] ?? '',
            'remarks' => $_POST['remarks'] ?? '',
            'status' => $_POST['status'] ?? 'available',
            'centre_id' => (int) ($_POST['centre_id'] ?? 0),
            'created_by' => $adminUser,
        ], $id > 0 ? $id : null);
        libraryFlashRedirect(
            $result['success'] ? 'library_stock.php' : ('library_stock.php' . ($id > 0 ? ('?edit=' . $id) : '')),
            $result['message'],
            $result['success']
        );
    }
    if ($action === 'return_copy') {
        $result = returnLibraryCopy($conn, (int) ($_POST['book_id'] ?? 0), $adminUser);
        libraryFlashRedirect('library_stock.php', $result['message'], $result['success']);
    }
}

$filterStatus = strtolower(trim((string) ($_GET['status'] ?? 'all')));
$searchQ = trim((string) ($_GET['q'] ?? ''));
$filterCentre = (int) ($_GET['centre_id'] ?? 0);
$centres = listLibraryCentres($conn);
$books = listLibraryBooks($conn, ['status' => $filterStatus, 'q' => $searchQ, 'centre_id' => $filterCentre]);
$statuses = libraryBookStatuses();
$sources = libraryBookSources();
$v = $editBook ?: [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Register - Library</title>
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
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="admin-content">
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-boxes-stacked"></i> Stock Register</h4>
                <small>One row per physical copy (accession number)</small>
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
            <div style="margin-bottom:1rem;"><?php include __DIR__ . '/includes/library_nav.php'; ?></div>

            <div class="content-card" style="margin-bottom:1.25rem;">
                <div class="card-header">
                    <h5 class="card-title" style="margin:0;">
                        <?php echo $editBook ? 'Edit accession ' . htmlspecialchars((string) $editBook['accession_no']) : 'Add book to stock'; ?>
                    </h5>
                    <?php if ($editBook): ?>
                        <a href="library_stock.php" class="btn btn-sm btn-secondary">Cancel edit</a>
                    <?php endif; ?>
                </div>
                <div style="padding:1rem 1.25rem;">
                    <form method="post" class="lib-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($libraryCsrf); ?>">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" value="<?php echo (int) ($v['id'] ?? 0); ?>">
                        <div class="lib-row">
                            <div class="form-group">
                                <label>Accession No <span class="text-danger">*</span></label>
                                <input class="form-control" name="accession_no" required maxlength="50"
                                       value="<?php echo htmlspecialchars((string) ($v['accession_no'] ?? '')); ?>">
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
                                <label>Title <span class="text-danger">*</span></label>
                                <input class="form-control" name="title" required maxlength="255"
                                       value="<?php echo htmlspecialchars((string) ($v['title'] ?? '')); ?>">
                            </div>
                        </div>
                        <div class="lib-row">
                            <div class="form-group">
                                <label>Author</label>
                                <input class="form-control" name="author" maxlength="255"
                                       value="<?php echo htmlspecialchars((string) ($v['author'] ?? '')); ?>">
                            </div>
                            <div class="form-group">
                                <label>Publisher</label>
                                <input class="form-control" name="publisher" maxlength="255"
                                       value="<?php echo htmlspecialchars((string) ($v['publisher'] ?? '')); ?>">
                            </div>
                            <div class="form-group">
                                <label>ISBN</label>
                                <input class="form-control" name="isbn" maxlength="40"
                                       value="<?php echo htmlspecialchars((string) ($v['isbn'] ?? '')); ?>">
                            </div>
                        </div>
                        <div class="lib-row">
                            <div class="form-group">
                                <label>Category</label>
                                <input class="form-control" name="category" maxlength="100" placeholder="e.g. Computer Science"
                                       value="<?php echo htmlspecialchars((string) ($v['category'] ?? '')); ?>">
                            </div>
                            <div class="form-group">
                                <label>Edition</label>
                                <input class="form-control" name="edition" maxlength="50"
                                       value="<?php echo htmlspecialchars((string) ($v['edition'] ?? '')); ?>">
                            </div>
                            <div class="form-group">
                                <label>Year</label>
                                <input class="form-control" name="pub_year" maxlength="10"
                                       value="<?php echo htmlspecialchars((string) ($v['pub_year'] ?? '')); ?>">
                            </div>
                            <div class="form-group">
                                <label>Shelf / location</label>
                                <input class="form-control" name="shelf_location" maxlength="100"
                                       value="<?php echo htmlspecialchars((string) ($v['shelf_location'] ?? '')); ?>">
                            </div>
                        </div>
                        <div class="lib-row">
                            <div class="form-group">
                                <label>Purchase date</label>
                                <input type="date" class="form-control" name="purchase_date"
                                       value="<?php echo htmlspecialchars((string) ($v['purchase_date'] ?? '')); ?>">
                            </div>
                            <div class="form-group">
                                <label>Bill / invoice no</label>
                                <input class="form-control" name="bill_no" maxlength="80"
                                       value="<?php echo htmlspecialchars((string) ($v['bill_no'] ?? '')); ?>">
                            </div>
                            <div class="form-group">
                                <label>Price (₹)</label>
                                <input type="number" step="0.01" min="0" class="form-control" name="price"
                                       value="<?php echo htmlspecialchars((string) ($v['price'] ?? '')); ?>">
                            </div>
                            <div class="form-group">
                                <label>Source</label>
                                <select class="form-control" name="source">
                                    <?php foreach ($sources as $key => $label): ?>
                                        <option value="<?php echo htmlspecialchars($key); ?>" <?php echo (($v['source'] ?? 'Purchased') === $key) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if ($editBook && ($editBook['status'] ?? '') !== 'issued'): ?>
                            <div class="form-group">
                                <label>Status</label>
                                <select class="form-control" name="status">
                                    <?php foreach ($statuses as $key => $label): ?>
                                        <?php if ($key === 'issued') { continue; } ?>
                                        <option value="<?php echo htmlspecialchars($key); ?>" <?php echo (($v['status'] ?? '') === $key) ? 'selected' : ''; ?>>
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
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $editBook ? 'Update stock' : 'Add to stock'; ?>
                        </button>
                    </form>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                    <h5 class="card-title" style="margin:0;">Stock list <span class="lib-muted">(<?php echo count($books); ?>)</span></h5>
                    <form method="get" style="margin:0;display:flex;gap:8px;flex-wrap:wrap;">
                        <input class="form-control form-control-sm" name="q" placeholder="Search accession, title, author…"
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
                                <th>Accession</th>
                                <th>Title</th>
                                <th>Centre</th>
                                <th>Author</th>
                                <th>Category</th>
                                <th>Shelf</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($books)): ?>
                                <tr><td colspan="8" class="text-center text-muted" style="padding:2rem;">No books in stock yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($books as $b): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) $b['accession_no']); ?></td>
                                        <td><?php echo htmlspecialchars((string) $b['title']); ?></td>
                                        <td><?php echo htmlspecialchars(libraryCentreLabel($b)); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($b['author'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($b['category'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($b['shelf_location'] ?? '')); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo libraryStatusBadgeClass((string) $b['status']); ?>">
                                                <?php echo htmlspecialchars($statuses[$b['status']] ?? ucfirst((string) $b['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a class="btn btn-sm btn-outline-primary" href="library_stock.php?edit=<?php echo (int) $b['id']; ?>">Edit</a>
                                            <?php if (($b['status'] ?? '') === 'issued'): ?>
                                                <form method="post" style="display:inline;margin:0;" onsubmit="return libraryConfirmReturn(event, this);">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($libraryCsrf); ?>">
                                                    <input type="hidden" name="action" value="return_copy">
                                                    <input type="hidden" name="book_id" value="<?php echo (int) $b['id']; ?>">
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
