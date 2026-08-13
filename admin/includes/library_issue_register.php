<?php
/**
 * Shared issue/return register UI.
 * Expects $libraryBorrowerType = 'student'|'staff'
 */
if (!isset($libraryBorrowerType) || !in_array($libraryBorrowerType, ['student', 'staff'], true)) {
    $libraryBorrowerType = 'student';
}

require_once __DIR__ . '/library_bootstrap.php';

$isStudentReg = ($libraryBorrowerType === 'student');
$pageFile = $isStudentReg ? 'library_student_issues.php' : 'library_staff_issues.php';
$pageTitle = $isStudentReg ? 'Student Issue / Return' : 'Staff / Faculty Issue / Return';

if (isset($_GET['ajax']) && $_GET['ajax'] === 'student' && $isStudentReg) {
    header('Content-Type: application/json; charset=utf-8');
    $sid = trim((string) ($_GET['student_id'] ?? ''));
    $row = lookupLibraryStudent($conn, $sid);
    echo json_encode($row ? ['ok' => true, 'name' => $row['name'], 'email' => $row['email'] ?? ''] : ['ok' => false]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!libraryVerifyCsrf()) {
        libraryFlashRedirect($pageFile, 'Invalid security token. Please try again.', false);
    }
    $action = (string) ($_POST['action'] ?? '');
    $redirect = $pageFile . '?' . http_build_query(array_filter([
        'status' => $_POST['redirect_status'] ?? null,
        'q' => $_POST['redirect_q'] ?? null,
    ]));
    $redirect = rtrim($redirect, '?');

    if ($action === 'issue') {
        $payload = [
            'book_id' => (int) ($_POST['book_id'] ?? 0),
            'borrower_type' => $libraryBorrowerType,
            'issue_date' => $_POST['issue_date'] ?? date('Y-m-d'),
            'due_date' => $_POST['due_date'] ?? '',
            'remarks' => $_POST['remarks'] ?? '',
            'issued_by' => $adminUser,
        ];
        if ($isStudentReg) {
            $payload['student_id'] = $_POST['student_id'] ?? '';
        } else {
            $payload['faculty_id'] = (int) ($_POST['faculty_id'] ?? 0);
        }
        $result = issueLibraryBook($conn, $payload);
        libraryFlashRedirect($redirect, $result['message'], $result['success']);
    }

    if ($action === 'return') {
        $result = returnLibraryBook($conn, (int) ($_POST['issue_id'] ?? 0), $adminUser);
        libraryFlashRedirect($redirect, $result['message'], $result['success']);
    }

    if ($action === 'return_copy') {
        $result = returnLibraryCopy($conn, (int) ($_POST['book_id'] ?? 0), $adminUser);
        libraryFlashRedirect($redirect, $result['message'], $result['success']);
    }
}

$filterStatus = strtolower(trim((string) ($_GET['status'] ?? 'issued')));
if (!in_array($filterStatus, ['issued', 'returned', 'overdue', 'all'], true)) {
    $filterStatus = 'issued';
}
$searchQ = trim((string) ($_GET['q'] ?? ''));
$issues = listLibraryIssues($conn, [
    'borrower_type' => $libraryBorrowerType,
    'status' => $filterStatus,
    'q' => $searchQ,
]);
$availableBooks = listLibraryBooks($conn, ['available_only' => true]);
$orphanIssued = listOrphanIssuedLibraryBooks($conn);
$staffList = $isStudentReg ? [] : listLibraryStaff($conn);
$dueDefault = date('Y-m-d', strtotime('+' . libraryDefaultDueDays($libraryBorrowerType) . ' days'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Library</title>
    <?php adminEmitHeadAssets($active_theme, ['toast' => true]); ?>
    <style>
        .lib-form .form-group { margin-bottom: 0.85rem; }
        .lib-form label { font-weight: 500; display:block; margin-bottom: 4px; color:#334155; }
        .lib-row { display:flex; gap:12px; flex-wrap:wrap; }
        .lib-row .form-group { flex:1; min-width:180px; }
        .lib-muted { color:#64748b; font-size:0.85rem; }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<div class="admin-wrapper">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="admin-content">
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-<?php echo $isStudentReg ? 'user-graduate' : 'chalkboard-teacher'; ?>"></i> <?php echo htmlspecialchars($pageTitle); ?></h4>
                <small><?php echo $isStudentReg ? 'Issue and return books for students' : 'Issue and return books for staff and faculty'; ?></small>
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
            <div style="margin-bottom:1rem;"><?php include __DIR__ . '/library_nav.php'; ?></div>

            <div class="content-card" style="margin-bottom:1.25rem;">
                <div class="card-header">
                    <h5 class="card-title" style="margin:0;">Issue a book</h5>
                </div>
                <div style="padding:1rem 1.25rem;">
                    <?php if (empty($availableBooks)): ?>
                        <p class="lib-muted mb-0">
                            No available copies — they are currently issued.
                            To return a book early, click <strong>Return</strong> on the Issued list below (return date is recorded as today).
                            <?php if (!empty($orphanIssued)): ?>
                                If the list is empty, use Return on the copies listed above the register, or on the <a href="library_stock.php">stock register</a>.
                            <?php else: ?>
                                Also check <a href="library_staff_issues.php">Staff Issue / Return</a> if it was issued to staff.
                            <?php endif; ?>
                        </p>
                    <?php else: ?>
                    <form method="post" class="lib-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($libraryCsrf); ?>">
                        <input type="hidden" name="action" value="issue">
                        <input type="hidden" name="redirect_status" value="<?php echo htmlspecialchars($filterStatus); ?>">
                        <input type="hidden" name="redirect_q" value="<?php echo htmlspecialchars($searchQ); ?>">
                        <div class="lib-row">
                            <div class="form-group">
                                <label>Book (available) <span class="text-danger">*</span></label>
                                <select class="form-control" name="book_id" required>
                                    <option value="">Select accession…</option>
                                    <?php foreach ($availableBooks as $bk): ?>
                                        <option value="<?php echo (int) $bk['id']; ?>">
                                            <?php echo htmlspecialchars($bk['accession_no'] . ' — ' . $bk['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if ($isStudentReg): ?>
                            <div class="form-group">
                                <label>Student ID <span class="text-danger">*</span></label>
                                <input class="form-control" name="student_id" id="lib_student_id" required maxlength="50" placeholder="Enter student ID">
                                <div class="lib-muted" id="lib_student_hint">Name will appear after lookup.</div>
                            </div>
                            <?php else: ?>
                            <div class="form-group">
                                <label>Staff / Faculty <span class="text-danger">*</span></label>
                                <select class="form-control" name="faculty_id" required>
                                    <option value="">Select person…</option>
                                    <?php foreach ($staffList as $st): ?>
                                        <option value="<?php echo (int) $st['id']; ?>">
                                            <?php
                                            $label = (string) ($st['name'] ?? '');
                                            if (!empty($st['designation'])) {
                                                $label .= ' — ' . $st['designation'];
                                            }
                                            if (!empty($st['staff_category'])) {
                                                $label .= ' [' . $st['staff_category'] . ']';
                                            }
                                            echo htmlspecialchars($label);
                                            ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="lib-row">
                            <div class="form-group">
                                <label>Issue date</label>
                                <input type="date" class="form-control" name="issue_date" id="lib_issue_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Due date</label>
                                <input type="date" class="form-control" name="due_date" id="lib_due_date" value="<?php echo htmlspecialchars($dueDefault); ?>" required>
                            </div>
                            <div class="form-group" style="flex:2;">
                                <label>Remarks</label>
                                <input class="form-control" name="remarks" maxlength="255">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-book-open"></i> Issue book</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($orphanIssued)): ?>
            <div class="content-card" style="margin-bottom:1.25rem;">
                <div class="card-header">
                    <h5 class="card-title" style="margin:0;">Issued in stock — no register row</h5>
                </div>
                <div style="padding:1rem 1.25rem;">
                    <p class="lib-muted">These copies are marked Issued in stock but have no issue/return row. Click Return to mark them available (return date = today).</p>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Accession</th>
                                    <th>Title</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orphanIssued as $ob): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) $ob['accession_no']); ?></td>
                                        <td><?php echo htmlspecialchars((string) $ob['title']); ?></td>
                                        <td>
                                            <form method="post" style="margin:0;" onsubmit="return confirm('Mark this copy as returned / available?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($libraryCsrf); ?>">
                                                <input type="hidden" name="action" value="return_copy">
                                                <input type="hidden" name="book_id" value="<?php echo (int) $ob['id']; ?>">
                                                <input type="hidden" name="redirect_status" value="<?php echo htmlspecialchars($filterStatus); ?>">
                                                <input type="hidden" name="redirect_q" value="<?php echo htmlspecialchars($searchQ); ?>">
                                                <button type="submit" class="btn btn-sm btn-success">Return</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="content-card">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                    <h5 class="card-title" style="margin:0;">Register <span class="lib-muted">(<?php echo count($issues); ?>)</span></h5>
                    <form method="get" style="margin:0;display:flex;gap:8px;flex-wrap:wrap;">
                        <input class="form-control form-control-sm" name="q" value="<?php echo htmlspecialchars($searchQ); ?>"
                               placeholder="Search accession, title, name…" style="min-width:200px;">
                        <select class="form-control form-control-sm" name="status" onchange="this.form.submit()">
                            <option value="issued" <?php echo $filterStatus === 'issued' ? 'selected' : ''; ?>>Issued</option>
                            <option value="overdue" <?php echo $filterStatus === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                            <option value="returned" <?php echo $filterStatus === 'returned' ? 'selected' : ''; ?>>Returned</option>
                            <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All</option>
                        </select>
                        <button class="btn btn-sm btn-secondary" type="submit">Filter</button>
                    </form>
                </div>
                <p class="lib-muted" style="padding:0 1.25rem;margin:0.75rem 0 0;">
                    Keep the filter on <strong>Issued</strong> to see books still out. Click <strong>Return</strong> for an early return — the return date is today, even if the due date is later. Use <strong>Returned</strong> or <strong>All</strong> for history.
                </p>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Accession</th>
                                <th>Title</th>
                                <th><?php echo $isStudentReg ? 'Student' : 'Staff / Faculty'; ?></th>
                                <th>Issued</th>
                                <th>Due</th>
                                <th>Returned</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($issues)): ?>
                                <tr><td colspan="8" class="text-center text-muted" style="padding:2rem;">No records in this view.</td></tr>
                            <?php else: ?>
                                <?php foreach ($issues as $row): ?>
                                    <?php
                                    $overdue = libraryIssueIsOverdue($row);
                                    $st = $overdue ? 'overdue' : (string) $row['status'];
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) $row['accession_no']); ?></td>
                                        <td><?php echo htmlspecialchars((string) $row['title']); ?></td>
                                        <td><?php echo htmlspecialchars(libraryBorrowerLabel($row)); ?></td>
                                        <td><?php echo !empty($row['issue_date']) ? date('d M Y', strtotime($row['issue_date'])) : '—'; ?></td>
                                        <td><?php echo !empty($row['due_date']) ? date('d M Y', strtotime($row['due_date'])) : '—'; ?></td>
                                        <td><?php echo !empty($row['return_date']) ? date('d M Y', strtotime($row['return_date'])) : '—'; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo libraryStatusBadgeClass($st); ?>">
                                                <?php echo $overdue ? 'Overdue' : htmlspecialchars(ucfirst((string) $row['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (($row['status'] ?? '') === 'issued'): ?>
                                                <form method="post" style="margin:0;" onsubmit="return confirm('Mark this book as returned?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($libraryCsrf); ?>">
                                                    <input type="hidden" name="action" value="return">
                                                    <input type="hidden" name="issue_id" value="<?php echo (int) $row['id']; ?>">
                                                    <input type="hidden" name="redirect_status" value="<?php echo htmlspecialchars($filterStatus); ?>">
                                                    <input type="hidden" name="redirect_q" value="<?php echo htmlspecialchars($searchQ); ?>">
                                                    <button type="submit" class="btn btn-sm btn-success">Return</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="lib-muted">—</span>
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
(function () {
    var issueEl = document.getElementById('lib_issue_date');
    var dueEl = document.getElementById('lib_due_date');
    var days = <?php echo (int) libraryDefaultDueDays($libraryBorrowerType); ?>;
    if (!issueEl || !dueEl) return;
    issueEl.addEventListener('change', function () {
        if (!issueEl.value) return;
        var d = new Date(issueEl.value + 'T00:00:00');
        if (isNaN(d.getTime())) return;
        d.setDate(d.getDate() + days);
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        dueEl.value = d.getFullYear() + '-' + m + '-' + day;
    });
})();
<?php if ($isStudentReg): ?>
(function () {
    var input = document.getElementById('lib_student_id');
    var hint = document.getElementById('lib_student_hint');
    if (!input || !hint) return;
    var timer = null;
    input.addEventListener('input', function () {
        clearTimeout(timer);
        var id = input.value.trim();
        if (id.length < 2) {
            hint.textContent = 'Name will appear after lookup.';
            return;
        }
        timer = setTimeout(function () {
            fetch('library_student_issues.php?ajax=student&student_id=' + encodeURIComponent(id))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    hint.textContent = data && data.ok ? ('Student: ' + data.name) : 'Student ID not found.';
                })
                .catch(function () { hint.textContent = 'Could not look up student.'; });
        }, 350);
    });
})();
<?php endif; ?>
</script>
</body>
</html>
