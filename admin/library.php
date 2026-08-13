<?php
/**
 * Library home — counts and Master Admin grant access.
 */
require_once __DIR__ . '/includes/library_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!libraryVerifyCsrf()) {
        libraryFlashRedirect('library.php', 'Invalid security token. Please try again.', false);
    }
    $action = (string) ($_POST['action'] ?? '');
    if ($isMasterAdmin) {
        $targetId = (int) ($_POST['admin_id'] ?? 0);
        if ($action === 'grant') {
            $result = libraryGrantAccess($conn, $targetId, $adminUser);
            libraryFlashRedirect('library.php', $result['message'], $result['success']);
        }
        if ($action === 'revoke') {
            $result = libraryRevokeAccess($conn, $targetId);
            libraryFlashRedirect('library.php', $result['message'], $result['success']);
        }
    }
    if ($action === 'return') {
        $result = returnLibraryBook($conn, (int) ($_POST['issue_id'] ?? 0), $adminUser);
        libraryFlashRedirect('library.php', $result['message'], $result['success']);
    }
    if ($action === 'return_copy') {
        $result = returnLibraryCopy($conn, (int) ($_POST['book_id'] ?? 0), $adminUser);
        libraryFlashRedirect('library.php', $result['message'], $result['success']);
    }
}

$stats = libraryStats($conn);
$currentIssues = listLibraryIssues($conn, ['status' => 'issued']);
$orphanIssued = listOrphanIssuedLibraryBooks($conn);
$candidates = $isMasterAdmin ? listLibraryAccessCandidates($conn) : [];
$roleNames = [
    'course_coordinator' => 'Course Coordinator',
    'faculty' => 'Faculty',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Management - NIELIT Bhubaneswar</title>
    <?php adminEmitHeadAssets($active_theme, ['toast' => true]); ?>
    <style>
        .lib-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; margin-bottom:1.25rem; }
        .lib-stat { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1rem 1.1rem; }
        .lib-stat b { display:block; font-size:1.6rem; color:#0f172a; }
        .lib-stat span { color:#64748b; font-size:0.85rem; }
        .lib-muted { color:#64748b; font-size:0.875rem; }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<div class="admin-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="admin-content">
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-book"></i> Library Management</h4>
                <small>Stock register and issue / return for students and staff</small>
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

            <div class="lib-stats">
                <div class="lib-stat">
                    <b><?php echo (int) $stats['total']; ?></b>
                    <span>Total copies</span>
                </div>
                <div class="lib-stat">
                    <b><?php echo (int) $stats['available']; ?></b>
                    <span>Available</span>
                </div>
                <div class="lib-stat">
                    <b><?php echo (int) $stats['issued']; ?></b>
                    <span>Currently issued</span>
                </div>
                <div class="lib-stat">
                    <b><?php echo (int) $stats['overdue']; ?></b>
                    <span>Overdue</span>
                </div>
            </div>

            <div class="content-card" style="margin-bottom:1.25rem;">
                <div class="card-header">
                    <h5 class="card-title" style="margin:0;">Quick actions</h5>
                </div>
                <div style="padding:1rem 1.25rem;display:flex;gap:10px;flex-wrap:wrap;">
                    <a class="btn btn-primary" href="library_stock.php"><i class="fas fa-plus"></i> Add to stock</a>
                    <a class="btn btn-secondary" href="library_student_issues.php"><i class="fas fa-user-graduate"></i> Issue to student</a>
                    <a class="btn btn-secondary" href="library_staff_issues.php"><i class="fas fa-chalkboard-teacher"></i> Issue to staff</a>
                    <?php if ($stats['overdue'] > 0): ?>
                        <a class="btn btn-danger" href="library_student_issues.php?status=overdue">Student overdue</a>
                        <a class="btn btn-danger" href="library_staff_issues.php?status=overdue">Staff overdue</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="content-card" style="margin-bottom:1.25rem;">
                <div class="card-header">
                    <h5 class="card-title" style="margin:0;">Currently issued</h5>
                </div>
                <div style="padding:1rem 1.25rem;">
                    <p class="lib-muted">Click Return for an early return. The return date is recorded as today.</p>
                    <?php if (empty($currentIssues) && empty($orphanIssued)): ?>
                        <p class="lib-muted mb-0">No books are currently issued.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Accession</th>
                                    <th>Title</th>
                                    <th>Borrower</th>
                                    <th>Issued</th>
                                    <th>Due</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($currentIssues as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) ($row['accession_no'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($row['title'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars(libraryBorrowerLabel($row)); ?></td>
                                        <td><?php echo !empty($row['issue_date']) ? date('d M Y', strtotime($row['issue_date'])) : '—'; ?></td>
                                        <td><?php echo !empty($row['due_date']) ? date('d M Y', strtotime($row['due_date'])) : '—'; ?></td>
                                        <td>
                                            <form method="post" style="margin:0;" onsubmit="return confirm('Mark this book as returned?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($libraryCsrf); ?>">
                                                <input type="hidden" name="action" value="return">
                                                <input type="hidden" name="issue_id" value="<?php echo (int) $row['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success">Return</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php foreach ($orphanIssued as $ob): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) $ob['accession_no']); ?></td>
                                        <td><?php echo htmlspecialchars((string) $ob['title']); ?></td>
                                        <td class="lib-muted">No issue row</td>
                                        <td>—</td>
                                        <td>—</td>
                                        <td>
                                            <form method="post" style="margin:0;" onsubmit="return confirm('Mark this copy as returned / available?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($libraryCsrf); ?>">
                                                <input type="hidden" name="action" value="return_copy">
                                                <input type="hidden" name="book_id" value="<?php echo (int) $ob['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success">Return</button>
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

            <?php if ($isMasterAdmin): ?>
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title" style="margin:0;"><i class="fas fa-user-shield"></i> Grant library access</h5>
                </div>
                <div style="padding:1rem 1.25rem;">
                    <p class="lib-muted">Course Coordinators and Faculty with access will see Library in their sidebar.</p>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Access</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($candidates)): ?>
                                    <tr><td colspan="4" class="text-muted text-center" style="padding:1.5rem;">No course coordinator or faculty accounts found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($candidates as $c): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars((string) $c['username']); ?></td>
                                            <td><?php echo htmlspecialchars($roleNames[$c['role']] ?? (string) $c['role']); ?></td>
                                            <td>
                                                <?php if (!empty($c['grant_id'])): ?>
                                                    <span class="badge bg-success">Granted</span>
                                                    <?php if (!empty($c['granted_by'])): ?>
                                                        <span class="lib-muted">by <?php echo htmlspecialchars((string) $c['granted_by']); ?></span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Not granted</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($c['grant_id'])): ?>
                                                    <form method="post" style="margin:0;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($libraryCsrf); ?>">
                                                        <input type="hidden" name="action" value="revoke">
                                                        <input type="hidden" name="admin_id" value="<?php echo (int) $c['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Revoke</button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="post" style="margin:0;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($libraryCsrf); ?>">
                                                        <input type="hidden" name="action" value="grant">
                                                        <input type="hidden" name="admin_id" value="<?php echo (int) $c['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-primary">Grant</button>
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
            <?php endif; ?>
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
