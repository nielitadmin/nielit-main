<?php
/**
 * Admin — live interview desk: add candidates and call one by one.
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/admin_assets.php';
require_once __DIR__ . '/../includes/recruitment_helper.php';

recruitmentRequireAccess($conn);
ensureRecruitmentTables($conn);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['csrf_token'];
$canEdit = recruitmentCanEdit(null, $conn);
$isMasterAdmin = recruitmentIsMasterAdmin();

$id = (int) ($_GET['id'] ?? 0);
$iv = recruitmentGetInterview($conn, $id);
if (!$iv) {
    $_SESSION['message'] = 'Interview not found.';
    $_SESSION['message_type'] = 'danger';
    header('Location: ' . app_url('admin/recruitment_interviews'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string) ($_POST['csrf_token'] ?? ''))) {
        $_SESSION['message'] = 'Invalid security token.';
        $_SESSION['message_type'] = 'danger';
        header('Location: ' . app_url('admin/recruitment_interview') . '?id=' . $id);
        exit();
    }
    if (!$canEdit) {
        $_SESSION['message'] = 'You can view this desk but cannot call candidates.';
        $_SESSION['message_type'] = 'danger';
        header('Location: ' . app_url('admin/recruitment_interview') . '?id=' . $id);
        exit();
    }
    $action = (string) ($_POST['action'] ?? '');
    $rowId = (int) ($_POST['row_id'] ?? 0);
    $msg = 'Saved.';
    $ok = true;
    if ($action === 'add') {
        $result = recruitmentAddInterviewCandidate($conn, $id, (int) ($_POST['application_id'] ?? 0), true);
        $msg = $result['message'];
        $ok = $result['success'];
    } elseif ($action === 'add_all_shortlisted') {
        $eligible = recruitmentInterviewEligibleApplications($conn, (int) $iv['job_id'], $id);
        $added = 0;
        foreach ($eligible as $el) {
            if (strtolower((string) ($el['status'] ?? '')) !== 'shortlisted') {
                continue;
            }
            $r = recruitmentAddInterviewCandidate($conn, $id, (int) $el['id'], true);
            if ($r['success']) {
                $added++;
            }
        }
        $msg = $added > 0 ? ($added . ' shortlisted candidate(s) added. Waiting-room emails will go out shortly.') : 'No shortlisted candidates left to add.';
        $ok = $added > 0;
    } elseif ($action === 'remove') {
        $ok = recruitmentRemoveInterviewCandidate($conn, $rowId);
        $msg = $ok ? 'Candidate removed from this interview.' : 'Could not remove the candidate.';
    } elseif ($action === 'call') {
        $result = recruitmentCallInterviewCandidate($conn, $id, $rowId);
        $msg = $result['message'];
        $ok = $result['success'];
    } elseif ($action === 'done') {
        $ok = recruitmentSetInterviewCallStatus($conn, $id, $rowId, 'completed');
        $row = $ok ? recruitmentGetInterviewCandidateRow($conn, $rowId) : null;
        if ($ok && $row) {
            recruitmentMarkApplicationInterviewed($conn, (int) $row['application_id'], false);
            $msg = 'Candidate marked done and Interviewed. You can call the next person.';
        } else {
            $msg = 'Could not update status.';
        }
    } elseif ($action === 'skip') {
        $ok = recruitmentSetInterviewCallStatus($conn, $id, $rowId, 'skipped');
        $msg = $ok ? 'Candidate skipped.' : 'Could not skip.';
    } elseif ($action === 'wait') {
        $ok = recruitmentSetInterviewCallStatus($conn, $id, $rowId, 'waiting');
        $msg = $ok ? 'Candidate moved back to waiting.' : 'Could not update.';
    } elseif ($action === 'undo_interview') {
        if (!$isMasterAdmin) {
            $ok = false;
            $msg = 'Only Master Admin can undo an interview.';
        } else {
            $row = recruitmentGetInterviewCandidateRow($conn, $rowId);
            if (!$row) {
                $ok = false;
                $msg = 'Candidate not found on this interview.';
            } else {
                $result = recruitmentUndoApplicationInterview($conn, (int) $row['application_id']);
                $ok = $result['success'];
                $msg = $result['message'];
            }
        }
    } elseif ($action === 'complete_session') {
        $st = $conn->prepare("UPDATE recruitment_interviews SET status = 'completed' WHERE id = ?");
        if ($st) {
            $st->bind_param('i', $id);
            $ok = $st->execute();
            $st->close();
        }
        $conn->query("UPDATE recruitment_interview_candidates SET call_status = 'completed', ended_at = IFNULL(ended_at, NOW()) WHERE interview_id = " . $id . " AND call_status = 'called'");
        $msg = 'Interview session marked completed. Join links are now closed.';
    }
    $_SESSION['message'] = $msg;
    $_SESSION['message_type'] = $ok ? 'success' : 'danger';
    header('Location: ' . app_url('admin/recruitment_interview') . '?id=' . $id);
    exit();
}

$notice = (string) ($_SESSION['message'] ?? '');
$noticeType = (string) ($_SESSION['message_type'] ?? 'success');
unset($_SESSION['message'], $_SESSION['message_type']);

$candidates = recruitmentListInterviewCandidates($conn, $id);
$eligible = recruitmentInterviewEligibleApplications($conn, (int) $iv['job_id'], $id);
$roomUrl = recruitmentInterviewRoomUrl($iv);
$calledNow = null;
foreach ($candidates as $c) {
    if (($c['call_status'] ?? '') === 'called') {
        $calledNow = $c;
        break;
    }
}
$active_theme = loadActiveTheme($conn);
$page_title = 'Interview desk';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - NIELIT Bhubaneswar</title>
    <?php adminEmitHeadAssets($active_theme); ?>
    <style>
        .iv-now { background:#ecfdf3; border:1px solid #86efac; border-radius:14px; padding:1rem 1.15rem; }
        .iv-wait { color:#64748b; }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>
<div class="admin-content">
    <div class="container-fluid py-4">
        <div class="mb-3">
            <a href="<?php echo htmlspecialchars(app_url('admin/recruitment_interviews')); ?>">&larr; Interview schedule</a>
        </div>
        <?php if ($notice !== ''): ?>
            <div class="alert alert-<?php echo htmlspecialchars($noticeType); ?>"><?php echo htmlspecialchars($notice); ?></div>
        <?php endif; ?>

        <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
            <div>
                <h2 class="mb-1"><?php echo htmlspecialchars((string) $iv['title']); ?></h2>
                <div class="text-muted">
                    <?php echo htmlspecialchars((string) ($iv['job_title'] ?? '')); ?>
                    · <?php echo htmlspecialchars(recruitmentFormatDate($iv['interview_date'] ?? '')); ?>
                    <?php $tm = substr((string) ($iv['interview_time'] ?? ''), 0, 5); echo $tm && $tm !== '00:00' ? ' · ' . htmlspecialchars($tm) : ''; ?>
                    · <?php echo htmlspecialchars(ucfirst((string) $iv['mode'])); ?>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <?php if (strtolower((string) $iv['mode']) === 'online'): ?>
                    <a class="btn btn-success" target="_blank" rel="noopener" href="<?php echo htmlspecialchars($roomUrl); ?>">Open interview room</a>
                <?php endif; ?>
                <?php if ($canEdit && ($iv['status'] ?? '') !== 'completed'): ?>
                    <form method="post" class="d-inline" onsubmit="return confirm('Close this session? Candidates will no longer be able to join.');">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                        <input type="hidden" name="action" value="complete_session">
                        <button class="btn btn-outline-secondary" type="submit">End session</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($calledNow): ?>
            <div class="iv-now mb-4">
                <div class="fw-semibold text-success mb-1"><i class="fas fa-phone-volume"></i> Currently called</div>
                <div class="fs-5"><?php echo htmlspecialchars((string) $calledNow['name']); ?></div>
                <div class="small"><?php echo htmlspecialchars((string) $calledNow['application_no']); ?> · <?php echo htmlspecialchars((string) $calledNow['mobile']); ?> · <?php echo htmlspecialchars((string) $calledNow['email']); ?></div>
                <p class="mb-0 mt-2 small">Only this candidate’s personal waiting-room link can open the online room right now. When you click Done or Call on the next person, their access closes.</p>
            </div>
        <?php else: ?>
            <div class="alert alert-light border mb-4">No one is being interviewed right now. Click <strong>Call</strong> on a waiting candidate to open the link for that person only.</div>
        <?php endif; ?>

        <?php if ($canEdit): ?>
        <div class="card mb-4">
            <div class="card-header fw-semibold">Add candidates</div>
            <div class="card-body">
                <p class="text-muted small mb-3">Shortlisted candidates get a personal waiting-room email. The meeting link stays locked until you call them.</p>
                <div class="d-flex flex-wrap gap-2 align-items-end">
                    <form method="post" class="d-flex flex-wrap gap-2 align-items-end">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                        <input type="hidden" name="action" value="add">
                        <div>
                            <label class="form-label mb-1">Candidate</label>
                            <select class="form-select" name="application_id" required style="min-width:280px">
                                <option value="">Select</option>
                                <?php foreach ($eligible as $el): ?>
                                    <option value="<?php echo (int) $el['id']; ?>">
                                        <?php echo htmlspecialchars((string) $el['name'] . ' · ' . $el['application_no'] . ' (' . $el['status'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn btn-outline-primary" type="submit" <?php echo empty($eligible) ? 'disabled' : ''; ?>>Add</button>
                    </form>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                        <input type="hidden" name="action" value="add_all_shortlisted">
                        <button class="btn btn-outline-secondary" type="submit">Add all shortlisted</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Candidate</th>
                            <th>Contact</th>
                            <th>Turn</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($candidates)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Add shortlisted candidates to start calling.</td></tr>
                    <?php else: $n = 0; foreach ($candidates as $c): $n++; ?>
                        <tr class="<?php echo ($c['call_status'] ?? '') === 'called' ? 'table-success' : ''; ?>">
                            <td><?php echo $n; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars((string) $c['name']); ?></strong>
                                <div class="small text-muted"><?php echo htmlspecialchars((string) $c['application_no']); ?></div>
                            </td>
                            <td class="small"><?php echo htmlspecialchars((string) $c['mobile']); ?><br><?php echo htmlspecialchars((string) $c['email']); ?></td>
                            <td>
                                <span class="badge text-bg-<?php
                                    $st = (string) ($c['call_status'] ?? '');
                                    echo $st === 'called' ? 'success' : ($st === 'waiting' ? 'warning' : ($st === 'skipped' ? 'secondary' : 'dark'));
                                ?>"><?php echo htmlspecialchars(recruitmentInterviewCallStatuses()[$c['call_status'] ?? ''] ?? (string) $c['call_status']); ?></span>
                            </td>
                            <td class="text-end text-nowrap">
                                <?php if ($canEdit): ?>
                                    <?php if (($c['call_status'] ?? '') !== 'called'): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                            <input type="hidden" name="action" value="call">
                                            <input type="hidden" name="row_id" value="<?php echo (int) $c['id']; ?>">
                                            <button class="btn btn-sm btn-success" type="submit">Call</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                            <input type="hidden" name="action" value="done">
                                            <input type="hidden" name="row_id" value="<?php echo (int) $c['id']; ?>">
                                            <button class="btn btn-sm btn-outline-success" type="submit">Done</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (($c['call_status'] ?? '') === 'waiting'): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                            <input type="hidden" name="action" value="skip">
                                            <input type="hidden" name="row_id" value="<?php echo (int) $c['id']; ?>">
                                            <button class="btn btn-sm btn-outline-secondary" type="submit">Skip</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (in_array(($c['call_status'] ?? ''), ['completed', 'skipped'], true)): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                            <input type="hidden" name="action" value="wait">
                                            <input type="hidden" name="row_id" value="<?php echo (int) $c['id']; ?>">
                                            <button class="btn btn-sm btn-outline-secondary" type="submit">Back to waiting</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($isMasterAdmin && (
                                        in_array((string) ($c['call_status'] ?? ''), ['completed', 'skipped', 'called'], true)
                                        || (string) ($c['app_status'] ?? '') === 'interviewed'
                                    )): ?>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Undo interview for <?php echo htmlspecialchars((string) $c['name']); ?>? They will go back to Shortlisted and waiting.');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                            <input type="hidden" name="action" value="undo_interview">
                                            <input type="hidden" name="row_id" value="<?php echo (int) $c['id']; ?>">
                                            <button class="btn btn-sm btn-outline-warning" type="submit">Undo interview</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Remove this candidate from the interview list?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="row_id" value="<?php echo (int) $c['id']; ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
<?php $conn->close(); ?>
