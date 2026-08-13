<?php
/**
 * Shared lab issue/return register.
 * Expects $labModule and $labBorrowerType = 'student'|'staff'
 */
if (!isset($labBorrowerType) || !in_array($labBorrowerType, ['student', 'staff'], true)) {
    $labBorrowerType = 'student';
}

require_once __DIR__ . '/lab_bootstrap.php';

$isStudentReg = ($labBorrowerType === 'student');
$pageFile = $isStudentReg ? $lab['student'] : $lab['staff'];
$pageTitle = $isStudentReg ? 'Student Issue / Return' : 'Staff / Faculty Issue / Return';
$noun = $lab['item_label'];

if (isset($_GET['ajax']) && $_GET['ajax'] === 'student' && $isStudentReg) {
    header('Content-Type: application/json; charset=utf-8');
    $by = (string) ($_GET['by'] ?? 'student_id');
    $q = trim((string) ($_GET['q'] ?? $_GET['student_id'] ?? ''));
    $matches = lookupLibraryStudents($conn, $q, $by);
    $out = [];
    foreach ($matches as $row) {
        $out[] = [
            'student_id' => (string) ($row['student_id'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
        ];
    }
    echo json_encode([
        'ok' => $out !== [],
        'matches' => $out,
        'name' => $out[0]['name'] ?? '',
        'student_id' => $out[0]['student_id'] ?? '',
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!labVerifyCsrf()) {
        labFlashRedirect($pageFile, 'Invalid security token. Please try again.', false);
    }
    $action = (string) ($_POST['action'] ?? '');
    $redirect = $pageFile . '?' . http_build_query(array_filter([
        'status' => $_POST['redirect_status'] ?? null,
        'q' => $_POST['redirect_q'] ?? null,
        'centre_id' => $_POST['redirect_centre'] ?? null,
    ], static function ($v) {
        return $v !== null && $v !== '' && $v !== '0';
    }));
    $redirect = rtrim($redirect, '?');

    if ($action === 'issue') {
        $payload = [
            'item_id' => (int) ($_POST['item_id'] ?? 0),
            'borrower_type' => $labBorrowerType,
            'issue_date' => $_POST['issue_date'] ?? date('Y-m-d'),
            'due_date' => $_POST['due_date'] ?? '',
            'remarks' => $_POST['remarks'] ?? '',
            'issued_by' => $adminUser,
        ];
        if ($isStudentReg) {
            $payload['student_id'] = $_POST['student_id'] ?? '';
            $payload['student_query'] = $_POST['student_query'] ?? '';
            $payload['student_lookup'] = $_POST['student_lookup'] ?? 'student_id';
        } else {
            $payload['faculty_id'] = (int) ($_POST['faculty_id'] ?? 0);
        }
        $result = issueLabItem($conn, $labModule, $payload);
        labFlashRedirect($redirect, $result['message'], $result['success']);
    }

    if ($action === 'return') {
        $result = returnLabItem($conn, $labModule, (int) ($_POST['issue_id'] ?? 0), $adminUser);
        labFlashRedirect($redirect, $result['message'], $result['success']);
    }

    if ($action === 'return_copy') {
        $result = returnLabItemCopy($conn, $labModule, (int) ($_POST['item_id'] ?? 0), $adminUser);
        labFlashRedirect($redirect, $result['message'], $result['success']);
    }

    if ($action === 'remind') {
        $result = labSendIssueReminder($conn, $labModule, (int) ($_POST['issue_id'] ?? 0));
        labFlashRedirect($redirect, $result['message'], $result['success']);
    }
}

$filterStatus = strtolower(trim((string) ($_GET['status'] ?? 'issued')));
if (!in_array($filterStatus, ['issued', 'returned', 'overdue', 'all'], true)) {
    $filterStatus = 'issued';
}
$searchQ = trim((string) ($_GET['q'] ?? ''));
$filterCentre = (int) ($_GET['centre_id'] ?? 0);
$centres = labListCentres($conn, $labModule);
$issues = listLabIssues($conn, $labModule, [
    'borrower_type' => $labBorrowerType,
    'status' => $filterStatus,
    'q' => $searchQ,
    'centre_id' => $filterCentre,
]);
$availableItems = listLabItems($conn, $labModule, ['available_only' => true, 'centre_id' => $filterCentre]);
$orphanIssued = listOrphanIssuedLabItems($conn, $labModule);
$staffList = $isStudentReg ? [] : listLibraryStaff($conn);
$dueDefault = date('Y-m-d', strtotime('+' . labDefaultDueDays($labModule, $labBorrowerType) . ' days'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars($lab['short']); ?></title>
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
                <small><?php echo $isStudentReg ? ('Issue and return ' . $lab['item_plural'] . ' for students') : ('Issue and return ' . $lab['item_plural'] . ' for staff and faculty'); ?></small>
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
                    <h5 class="card-title" style="margin:0;">Issue a <?php echo htmlspecialchars($noun); ?></h5>
                </div>
                <div style="padding:1rem 1.25rem;">
                    <?php if (empty($availableItems)): ?>
                        <p class="lib-muted mb-0">
                            No available <?php echo htmlspecialchars($lab['item_plural']); ?> — they are currently issued.
                            Click <strong>Return</strong> on the Issued list below (return date is recorded as today).
                        </p>
                    <?php else: ?>
                    <form method="post" class="lib-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($labCsrf); ?>">
                        <input type="hidden" name="action" value="issue">
                        <input type="hidden" name="redirect_status" value="<?php echo htmlspecialchars($filterStatus); ?>">
                        <input type="hidden" name="redirect_q" value="<?php echo htmlspecialchars($searchQ); ?>">
                        <input type="hidden" name="redirect_centre" value="<?php echo (int) $filterCentre; ?>">
                        <div class="lib-row">
                            <div class="form-group">
                                <label><?php echo htmlspecialchars($lab['code_label']); ?> (available) <span class="text-danger">*</span></label>
                                <select class="form-control" name="item_id" required>
                                    <option value="">Select…</option>
                                    <?php foreach ($availableItems as $bk): ?>
                                        <option value="<?php echo (int) $bk['id']; ?>">
                                            <?php
                                            $opt = $bk['code'] . ' — ' . $bk['name'];
                                            if (!empty($bk['category'])) {
                                                $opt .= ' [' . $bk['category'] . ']';
                                            }
                                            $cname = trim((string) ($bk['centre_name'] ?? ''));
                                            if ($cname !== '') {
                                                $opt .= ' (' . $cname . ')';
                                            }
                                            echo htmlspecialchars($opt);
                                            ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if ($isStudentReg): ?>
                            <div class="form-group">
                                <label>Find student by</label>
                                <select class="form-control" name="student_lookup" id="lib_student_lookup">
                                    <?php foreach (libraryStudentLookupTypes() as $lookupKey => $lookupLabel): ?>
                                        <option value="<?php echo htmlspecialchars($lookupKey); ?>"><?php echo htmlspecialchars($lookupLabel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label id="lib_student_query_label">Student ID <span class="text-danger">*</span></label>
                                <input class="form-control" name="student_query" id="lib_student_query" required maxlength="50" placeholder="Enter student ID" autocomplete="off">
                                <input type="hidden" name="student_id" id="lib_student_id" value="">
                                <div class="lib-muted" id="lib_student_hint">Name will appear after lookup.</div>
                                <select class="form-control" id="lib_student_pick" style="display:none;margin-top:6px;"></select>
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
                        <button type="submit" class="btn btn-primary"><i class="fas fa-handshake"></i> Issue <?php echo htmlspecialchars($noun); ?></button>
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
                    <p class="lib-muted">These are marked Issued in stock but have no issue/return row. Click Return to mark them available.</p>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th><?php echo htmlspecialchars($lab['code_label']); ?></th>
                                    <th>Name</th>
                                    <th>Centre</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orphanIssued as $ob): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) $ob['code']); ?></td>
                                        <td><?php echo htmlspecialchars((string) $ob['name']); ?></td>
                                        <td><?php echo htmlspecialchars(libraryCentreLabel($ob)); ?></td>
                                        <td>
                                            <form method="post" style="margin:0;" onsubmit="return labConfirmReturn(event, this);">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($labCsrf); ?>">
                                                <input type="hidden" name="action" value="return_copy">
                                                <input type="hidden" name="item_id" value="<?php echo (int) $ob['id']; ?>">
                                                <input type="hidden" name="redirect_status" value="<?php echo htmlspecialchars($filterStatus); ?>">
                                                <input type="hidden" name="redirect_q" value="<?php echo htmlspecialchars($searchQ); ?>">
                                                <input type="hidden" name="redirect_centre" value="<?php echo (int) $filterCentre; ?>">
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
                               placeholder="Search <?php echo htmlspecialchars(strtolower($lab['code_label'])); ?>, name…" style="min-width:200px;">
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
                            <option value="issued" <?php echo $filterStatus === 'issued' ? 'selected' : ''; ?>>Issued</option>
                            <option value="overdue" <?php echo $filterStatus === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                            <option value="returned" <?php echo $filterStatus === 'returned' ? 'selected' : ''; ?>>Returned</option>
                            <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All</option>
                        </select>
                        <button class="btn btn-sm btn-secondary" type="submit">Filter</button>
                    </form>
                </div>
                <p class="lib-muted" style="padding:0 1.25rem;margin:0.75rem 0 0;">
                    Keep the filter on <strong>Issued</strong> to see items still out. Click <strong>Return</strong> for an early return — the return date is today.
                </p>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th><?php echo htmlspecialchars($lab['code_label']); ?></th>
                                <th>Name</th>
                                <th>Centre</th>
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
                                <tr><td colspan="9" class="text-center text-muted" style="padding:2rem;">No records in this view.</td></tr>
                            <?php else: ?>
                                <?php foreach ($issues as $row): ?>
                                    <?php
                                    $overdue = libraryIssueIsOverdue($row);
                                    $st = $overdue ? 'overdue' : (string) $row['status'];
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) $row['code']); ?></td>
                                        <td><?php echo htmlspecialchars((string) $row['name']); ?></td>
                                        <td><?php echo htmlspecialchars(libraryCentreLabel($row)); ?></td>
                                        <td><?php echo htmlspecialchars(libraryBorrowerLabel($row)); ?></td>
                                        <td><?php echo !empty($row['issue_date']) ? date('d M Y', strtotime($row['issue_date'])) : '—'; ?></td>
                                        <td><?php echo !empty($row['due_date']) ? date('d M Y', strtotime($row['due_date'])) : '—'; ?></td>
                                        <td><?php echo !empty($row['return_date']) ? date('d M Y', strtotime($row['return_date'])) : '—'; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo labStatusBadgeClass($st); ?>">
                                                <?php echo $overdue ? 'Overdue' : htmlspecialchars(ucfirst((string) $row['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (($row['status'] ?? '') === 'issued'): ?>
                                                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                                <form method="post" style="margin:0;" onsubmit="return labConfirmReturn(event, this);">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($labCsrf); ?>">
                                                    <input type="hidden" name="action" value="return">
                                                    <input type="hidden" name="issue_id" value="<?php echo (int) $row['id']; ?>">
                                                    <input type="hidden" name="redirect_status" value="<?php echo htmlspecialchars($filterStatus); ?>">
                                                    <input type="hidden" name="redirect_q" value="<?php echo htmlspecialchars($searchQ); ?>">
                                                    <input type="hidden" name="redirect_centre" value="<?php echo (int) $filterCentre; ?>">
                                                    <button type="submit" class="btn btn-sm btn-success">Return</button>
                                                </form>
                                                <form method="post" style="margin:0;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($labCsrf); ?>">
                                                    <input type="hidden" name="action" value="remind">
                                                    <input type="hidden" name="issue_id" value="<?php echo (int) $row['id']; ?>">
                                                    <input type="hidden" name="redirect_status" value="<?php echo htmlspecialchars($filterStatus); ?>">
                                                    <input type="hidden" name="redirect_q" value="<?php echo htmlspecialchars($searchQ); ?>">
                                                    <input type="hidden" name="redirect_centre" value="<?php echo (int) $filterCentre; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Email reminder</button>
                                                </form>
                                                </div>
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
    var days = <?php echo (int) labDefaultDueDays($labModule, $labBorrowerType); ?>;
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
    var lookup = document.getElementById('lib_student_lookup');
    var input = document.getElementById('lib_student_query');
    var hidden = document.getElementById('lib_student_id');
    var hint = document.getElementById('lib_student_hint');
    var pick = document.getElementById('lib_student_pick');
    var label = document.getElementById('lib_student_query_label');
    if (!lookup || !input || !hidden || !hint || !pick) return;
    var timer = null;
    var page = <?php echo json_encode($pageFile); ?>;
    var meta = {
        student_id: { label: 'Student ID', placeholder: 'Enter student ID', maxlength: 50, min: 2, inputmode: 'text' },
        mobile: { label: 'Mobile number', placeholder: '10-digit mobile number', maxlength: 15, min: 10, inputmode: 'numeric' },
        aadhar: { label: 'Aadhaar number', placeholder: '12-digit Aadhaar number', maxlength: 14, min: 12, inputmode: 'numeric' }
    };
    function lookupStudent() {
        var by = lookup.value || 'student_id';
        var q = input.value.trim();
        var cfg = meta[by] || meta.student_id;
        hidden.value = '';
        pick.style.display = 'none';
        pick.innerHTML = '';
        if (q.length < cfg.min) {
            hint.textContent = 'Name will appear after lookup.';
            return;
        }
        fetch(page + '?ajax=student&by=' + encodeURIComponent(by) + '&q=' + encodeURIComponent(q))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var matches = (data && data.matches) ? data.matches : [];
                if (matches.length === 1) {
                    hidden.value = matches[0].student_id || '';
                    hint.textContent = 'Student: ' + matches[0].name + ' (' + matches[0].student_id + ')';
                    return;
                }
                if (matches.length > 1) {
                    hint.textContent = matches.length + ' students match. Select the correct one.';
                    pick.innerHTML = '<option value="">Select student…</option>';
                    matches.forEach(function (m) {
                        var opt = document.createElement('option');
                        opt.value = m.student_id;
                        opt.textContent = (m.name || 'Student') + ' — ' + m.student_id;
                        pick.appendChild(opt);
                    });
                    pick.style.display = '';
                    return;
                }
                hint.textContent = (cfg.label || 'Student') + ' not found.';
            })
            .catch(function () { hint.textContent = 'Could not look up student.'; });
    }
    lookup.addEventListener('change', function () {
        var cfg = meta[lookup.value] || meta.student_id;
        if (label) label.innerHTML = cfg.label + ' <span class="text-danger">*</span>';
        input.placeholder = cfg.placeholder;
        input.maxLength = cfg.maxlength;
        input.setAttribute('inputmode', cfg.inputmode);
        hidden.value = '';
        pick.style.display = 'none';
        pick.innerHTML = '';
        hint.textContent = 'Name will appear after lookup.';
        lookupStudent();
    });
    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(lookupStudent, 350);
    });
    pick.addEventListener('change', function () {
        hidden.value = pick.value || '';
        if (pick.value) {
            var opt = pick.options[pick.selectedIndex];
            hint.textContent = 'Student: ' + (opt ? opt.textContent : pick.value);
        }
    });
})();
<?php endif; ?>
</script>
</body>
</html>
