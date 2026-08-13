<?php
/**
 * Lab module home — stats, current issues, Master Admin grants.
 * Expects $labModule.
 */
require_once __DIR__ . '/lab_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!labVerifyCsrf()) {
        labFlashRedirect($lab['home'], 'Invalid security token. Please try again.', false);
    }
    $action = (string) ($_POST['action'] ?? '');
    if ($isMasterAdmin) {
        $targetId = (int) ($_POST['admin_id'] ?? 0);
        if ($action === 'grant') {
            $result = labGrantAccess($conn, $labModule, $targetId, $adminUser, (int) ($_POST['centre_id'] ?? 0));
            labFlashRedirect($lab['home'], $result['message'], $result['success']);
        }
        if ($action === 'revoke') {
            $result = labRevokeAccess($conn, $labModule, $targetId, (int) ($_POST['centre_id'] ?? 0));
            labFlashRedirect($lab['home'], $result['message'], $result['success']);
        }
    }
    if ($action === 'return') {
        $result = returnLabItem($conn, $labModule, (int) ($_POST['issue_id'] ?? 0), $adminUser);
        labFlashRedirect($lab['home'], $result['message'], $result['success']);
    }
    if ($action === 'return_copy') {
        $result = returnLabItemCopy($conn, $labModule, (int) ($_POST['item_id'] ?? 0), $adminUser);
        labFlashRedirect($lab['home'], $result['message'], $result['success']);
    }
    if ($action === 'send_reminders') {
        $result = sendLabDueReminders($conn, $labModule);
        if ((int) $result['sent'] === 0 && (int) $result['failed'] === 0) {
            $msg = 'No due or overdue reminders to send right now.';
        } else {
            $msg = 'Reminders sent: ' . (int) $result['sent'] . '. Skipped (no email / already sent today): ' . (int) $result['skipped'] . '. Failed: ' . (int) $result['failed'] . '.';
        }
        labFlashRedirect($lab['home'], $msg, $result['failed'] === 0);
    }
    if ($action === 'remind') {
        $result = labSendIssueReminder($conn, $labModule, (int) ($_POST['issue_id'] ?? 0));
        labFlashRedirect($lab['home'], $result['message'], $result['success']);
    }
}

$stats = labStats($conn, $labModule);
$currentIssues = listLabIssues($conn, $labModule, ['status' => 'issued']);
$orphanIssued = listOrphanIssuedLabItems($conn, $labModule);
$candidates = $isMasterAdmin ? listLabAccessCandidates($conn, $labModule) : [];
$allCentres = $isMasterAdmin ? labListCentres($conn, $labModule, false) : [];
$roleNames = [
    'course_coordinator' => 'Course Coordinator',
    'faculty' => 'Faculty',
];
$hasParts = !empty($lab['has_parts']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($lab['title']); ?> - NIELIT</title>
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
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="admin-content">
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-<?php echo htmlspecialchars($lab['icon']); ?>"></i> <?php echo htmlspecialchars($lab['title']); ?></h4>
                <small><?php echo $hasParts ? 'Computer systems, parts (keyboard, mouse, monitor…) and issue / return' : 'Instrument stock (Drone, IoT, …) and issue / return'; ?></small>
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

            <div class="lib-stats">
                <div class="lib-stat">
                    <b><?php echo (int) $stats['total']; ?></b>
                    <span>Total <?php echo htmlspecialchars($lab['item_plural']); ?></span>
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
                <?php if ($hasParts): ?>
                <div class="lib-stat">
                    <b><?php echo (int) $stats['damaged_parts']; ?></b>
                    <span>Damaged / missing parts</span>
                </div>
                <?php else: ?>
                <div class="lib-stat">
                    <b><?php echo (int) $stats['repair']; ?></b>
                    <span>Under repair</span>
                </div>
                <?php endif; ?>
            </div>

            <div class="content-card" style="margin-bottom:1.25rem;">
                <div class="card-header">
                    <h5 class="card-title" style="margin:0;">Quick actions</h5>
                </div>
                <div style="padding:1rem 1.25rem;display:flex;gap:10px;flex-wrap:wrap;">
                    <a class="btn btn-primary" href="<?php echo htmlspecialchars($lab['stock']); ?>"><i class="fas fa-plus"></i> Add to stock</a>
                    <a class="btn btn-success" href="<?php echo htmlspecialchars($lab['stock']); ?>?export=excel"><i class="fas fa-file-excel"></i> Download all stock</a>
                    <a class="btn btn-secondary" href="<?php echo htmlspecialchars($lab['student']); ?>"><i class="fas fa-user-graduate"></i> Issue to student</a>
                    <a class="btn btn-secondary" href="<?php echo htmlspecialchars($lab['staff']); ?>"><i class="fas fa-chalkboard-teacher"></i> Issue to staff</a>
                    <?php if ($stats['overdue'] > 0): ?>
                        <a class="btn btn-danger" href="<?php echo htmlspecialchars($lab['student']); ?>?status=overdue">Student overdue</a>
                        <a class="btn btn-danger" href="<?php echo htmlspecialchars($lab['staff']); ?>?status=overdue">Staff overdue</a>
                    <?php endif; ?>
                    <form method="post" style="margin:0;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($labCsrf); ?>">
                        <input type="hidden" name="action" value="send_reminders">
                        <button type="submit" class="btn btn-secondary"><i class="fas fa-envelope"></i> Send due reminders</button>
                    </form>
                </div>
            </div>

            <div class="content-card" style="margin-bottom:1.25rem;">
                <div class="card-header">
                    <h5 class="card-title" style="margin:0;">Currently issued</h5>
                </div>
                <div style="padding:1rem 1.25rem;">
                    <p class="lib-muted">Click Return for an early return. The return date is recorded as today.</p>
                    <?php if (empty($currentIssues) && empty($orphanIssued)): ?>
                        <p class="lib-muted mb-0">Nothing is currently issued.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th><?php echo htmlspecialchars($lab['code_label']); ?></th>
                                    <th>Name</th>
                                    <th>Centre</th>
                                    <th>Borrower</th>
                                    <th>Issued</th>
                                    <th>Due</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($currentIssues as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) ($row['code'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($row['name'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars(libraryCentreLabel($row)); ?></td>
                                        <td><?php echo htmlspecialchars(libraryBorrowerLabel($row)); ?></td>
                                        <td><?php echo !empty($row['issue_date']) ? date('d M Y', strtotime($row['issue_date'])) : '—'; ?></td>
                                        <td><?php echo !empty($row['due_date']) ? date('d M Y', strtotime($row['due_date'])) : '—'; ?></td>
                                        <td>
                                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                            <form method="post" style="margin:0;" onsubmit="return labConfirmReturn(event, this);">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($labCsrf); ?>">
                                                <input type="hidden" name="action" value="return">
                                                <input type="hidden" name="issue_id" value="<?php echo (int) $row['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success">Return</button>
                                            </form>
                                            <form method="post" style="margin:0;">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($labCsrf); ?>">
                                                <input type="hidden" name="action" value="remind">
                                                <input type="hidden" name="issue_id" value="<?php echo (int) $row['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">Email reminder</button>
                                            </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php foreach ($orphanIssued as $ob): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) $ob['code']); ?></td>
                                        <td><?php echo htmlspecialchars((string) $ob['name']); ?></td>
                                        <td><?php echo htmlspecialchars(libraryCentreLabel($ob)); ?></td>
                                        <td class="lib-muted">No issue row</td>
                                        <td>—</td>
                                        <td>—</td>
                                        <td>
                                            <form method="post" style="margin:0;" onsubmit="return labConfirmReturn(event, this);">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($labCsrf); ?>">
                                                <input type="hidden" name="action" value="return_copy">
                                                <input type="hidden" name="item_id" value="<?php echo (int) $ob['id']; ?>">
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
                    <h5 class="card-title" style="margin:0;"><i class="fas fa-user-shield"></i> Grant <?php echo htmlspecialchars($lab['short']); ?> access</h5>
                </div>
                <div style="padding:1rem 1.25rem;">
                    <p class="lib-muted">Grant a Course Coordinator or Faculty for a specific centre. They will only see that centre’s stock and issue register.</p>
                    <?php if (empty($allCentres)): ?>
                        <p class="lib-muted">Add training centres first under Training Centres, then grant access per centre.</p>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Centres</th>
                                    <th>Grant centre</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($candidates)): ?>
                                    <tr><td colspan="4" class="text-muted text-center" style="padding:1.5rem;">No course coordinator or faculty accounts found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($candidates as $c): ?>
                                        <?php
                                        $grantedIds = [];
                                        foreach ($c['grants'] ?? [] as $g) {
                                            $grantedIds[(int) $g['centre_id']] = true;
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars((string) $c['username']); ?></td>
                                            <td><?php echo htmlspecialchars($roleNames[$c['role']] ?? (string) $c['role']); ?></td>
                                            <td>
                                                <?php if (empty($c['grants'])): ?>
                                                    <span class="badge bg-secondary">Not granted</span>
                                                <?php else: ?>
                                                    <?php foreach ($c['grants'] as $g): ?>
                                                        <form method="post" style="display:inline-flex;align-items:center;gap:6px;margin:0 8px 6px 0;">
                                                            <span class="badge bg-success"><?php echo htmlspecialchars((string) ($g['centre_name'] ?: ('Centre #' . (int) $g['centre_id']))); ?></span>
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($labCsrf); ?>">
                                                            <input type="hidden" name="action" value="revoke">
                                                            <input type="hidden" name="admin_id" value="<?php echo (int) $c['id']; ?>">
                                                            <input type="hidden" name="centre_id" value="<?php echo (int) $g['centre_id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">Revoke</button>
                                                        </form>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $remaining = [];
                                                foreach ($allCentres as $ctr) {
                                                    if (empty($grantedIds[(int) $ctr['id']])) {
                                                        $remaining[] = $ctr;
                                                    }
                                                }
                                                ?>
                                                <?php if (empty($remaining)): ?>
                                                    <span class="lib-muted">All centres granted</span>
                                                <?php else: ?>
                                                    <form method="post" style="margin:0;display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($labCsrf); ?>">
                                                        <input type="hidden" name="action" value="grant">
                                                        <input type="hidden" name="admin_id" value="<?php echo (int) $c['id']; ?>">
                                                        <select name="centre_id" class="form-control form-control-sm" required style="min-width:160px;">
                                                            <option value="">Select centre…</option>
                                                            <?php foreach ($remaining as $ctr): ?>
                                                                <option value="<?php echo (int) $ctr['id']; ?>"><?php echo htmlspecialchars((string) $ctr['name']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
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
