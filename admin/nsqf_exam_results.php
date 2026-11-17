<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../includes/admin_assets.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_once __DIR__ . '/../includes/nsqf_exam_results_helper.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['admin_role']) || !isset($_SESSION['admin_id'])) {
    if (!init_admin_session($_SESSION['admin'])) {
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit();
    }
}
refresh_session_permissions();

$role = (string) ($_SESSION['admin_role'] ?? '');
$adminId = (int) ($_SESSION['admin_id'] ?? 0);

if (!canManageNsqfExamResults($role)) {
    http_response_code(403);
    echo 'You do not have permission to manage NSQF exam results.';
    exit();
}

ensureNsqfExamResultsSchema($conn);

$tab = strtolower(trim((string) ($_GET['tab'] ?? 'results')));
if (!in_array($tab, ['results', 'components', 'certified'], true)) {
    $tab = 'results';
}

$templateId = (int) ($_GET['template_id'] ?? 0);
$courseId = (int) ($_GET['course_id'] ?? 0);
$batchId = (int) ($_GET['batch_id'] ?? 0);
$includeAllIntakes = isset($_GET['all_intakes']) && $_GET['all_intakes'] === '1' && $role === 'master_admin';
$templates = nsqf_list_nsqf_templates_for_exam($conn);
$resultOptions = nsqfExamResultStatusOptions();
$batches = [];
$courses = [];

if ($tab === 'components') {
  if ($templateId > 0 && !nsqf_exam_user_can_access_template($conn, $role, $adminId, $templateId)) {
    $templateId = 0;
  }
  if ($templateId <= 0 && $templates !== []) {
    $templateId = (int) ($templates[0]['id'] ?? 0);
  }
  $courseId = 0;
  $batchId = 0;
  $components = $templateId > 0 ? nsqf_get_template_exam_components($conn, $templateId) : [];
} else {
  if ($templateId > 0 && !nsqf_exam_user_can_access_template($conn, $role, $adminId, $templateId)) {
    $templateId = 0;
  }
  $courses = nsqf_list_intake_courses(
    $conn,
    $role,
    $adminId,
    $templateId,
    !$includeAllIntakes
  );
  if ($courseId > 0) {
    $courseInList = false;
    foreach ($courses as $c) {
      if ((int) ($c['id'] ?? 0) === $courseId) {
        $courseInList = true;
        break;
      }
    }
    if (!$courseInList || !nsqf_exam_user_can_access_course($conn, $role, $adminId, $courseId)) {
      $courseId = 0;
      $batchId = 0;
    }
  }
  if ($courseId <= 0 && $courses !== []) {
    $courseId = (int) ($courses[0]['id'] ?? 0);
  }
  if ($templateId <= 0 && $courseId > 0) {
    $templateId = nsqf_resolve_template_id_for_course($conn, $courseId);
  }
  $components = $courseId > 0 ? nsqf_get_exam_components_for_course($conn, $courseId) : [];
  $batches = $courseId > 0 ? nsqf_list_batches_for_exam_panel($conn, $courseId, $role, $adminId) : [];
}

if ($batchId <= 0 && $batches !== []) {
    $batchId = (int) ($batches[0]['id'] ?? 0);
}

$batchValid = false;
foreach ($batches as $b) {
    if ((int) ($b['id'] ?? 0) === $batchId) {
        $batchValid = true;
        break;
    }
}
if (!$batchValid) {
    $batchId = $batches !== [] ? (int) ($batches[0]['id'] ?? 0) : 0;
}

$students = ($tab === 'results' && $batchId > 0) ? nsqf_get_batch_students_for_exam($conn, $batchId) : [];
$resultsMap = ($batchId > 0 && $components !== [])
    ? nsqf_get_batch_module_results_map($conn, $batchId, array_column($components, 'id'))
    : [];
$certified = ($tab === 'certified' && $courseId > 0)
    ? nsqf_list_certified_candidates(
        $conn,
        $courseId,
        $batchId > 0 ? $batchId : 0,
        $role,
        $adminId
    )
    : [];

$courseName = '';
$templateName = '';
if ($templateId > 0) {
    $tStmt = $conn->prepare('SELECT course_name FROM nsqf_course_templates WHERE id = ? LIMIT 1');
    if ($tStmt) {
        $tStmt->bind_param('i', $templateId);
        $tStmt->execute();
        $tRow = $tStmt->get_result()->fetch_assoc();
        $tStmt->close();
        if ($tRow) {
            $templateName = trim((string) $tRow['course_name']);
        }
    }
}
if ($courseId > 0) {
    $cStmt = $conn->prepare('SELECT course_name, course_code FROM courses WHERE id = ? LIMIT 1');
    if ($cStmt) {
        $cStmt->bind_param('i', $courseId);
        $cStmt->execute();
        $cRow = $cStmt->get_result()->fetch_assoc();
        $cStmt->close();
        if ($cRow) {
            $courseName = trim((string) $cRow['course_name']);
            if (!empty($cRow['course_code'])) {
                $courseName .= ' (' . $cRow['course_code'] . ')';
            }
        }
    }
}

$active_theme = loadActiveTheme($conn);
$apiUrl = app_url('admin/ajax/nsqf_exam_results_api');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NSQF Results &amp; Certification - NIELIT Bhubaneswar</title>
    <?php adminEmitHeadAssets($active_theme, ['toast' => true]); ?>
    <style>
        .ner-toolbar { background:#fff; border-radius:12px; padding:1rem 1.25rem; margin-bottom:1rem; box-shadow:0 1px 3px rgba(0,0,0,.08); }
        .ner-table-wrap { overflow-x:auto; background:#fff; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,.08); }
        .ner-table { margin:0; font-size:.85rem; }
        .ner-table th { position:sticky; top:0; background:#f8fafc; z-index:2; white-space:nowrap; }
        .ner-table td.sticky-col, .ner-table th.sticky-col { position:sticky; left:0; background:#fff; z-index:3; min-width:180px; }
        .ner-table th.sticky-col { z-index:4; }
        .ner-cert-badge { font-size:.75rem; }
        .ner-comp-row input { max-width:120px; }
        .module-select { min-width:140px; font-size:.8rem; }
    </style>
</head>
<body class="admin-body">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="admin-content">
    <div class="container-fluid py-4">
        <h1 class="h4 mb-1"><i class="fas fa-award text-primary"></i> NSQF Module / Semester Results</h1>
        <p class="text-muted small mb-3">
            <strong>Exam structure</strong> follows the same programs as
            <a href="<?php echo htmlspecialchars(app_url('admin/manage_nsqf_templates'), ENT_QUOTES, 'UTF-8'); ?>">Manage NSQF Course</a>
            (templates). <strong>Results</strong> use coordinator <em>live intakes</em> (matching course name + at least one batch)—not every row in the general course catalog.
        </p>

        <div class="ner-toolbar">
            <form method="get" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab, ENT_QUOTES, 'UTF-8'); ?>">
                <?php if ($includeAllIntakes): ?>
                    <input type="hidden" name="all_intakes" value="1">
                <?php endif; ?>
                <?php if ($tab === 'components'): ?>
                <div class="col-md-6">
                    <label class="form-label small mb-0">NSQF program (template)</label>
                    <select name="template_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <?php if ($templates === []): ?>
                            <option value="0">No NSQF programs — add them under Manage NSQF Course</option>
                        <?php endif; ?>
                        <?php foreach ($templates as $t): ?>
                            <option value="<?php echo (int) $t['id']; ?>" <?php echo (int) $t['id'] === $templateId ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) ($t['course_name'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <div class="col-md-4">
                    <label class="form-label small mb-0">Filter by program</label>
                    <select name="template_id" class="form-select form-select-sm" onchange="this.form.querySelector('[name=course_id]').value=\'0\';this.form.submit()">
                        <option value="0" <?php echo $templateId === 0 ? 'selected' : ''; ?>>All programs</option>
                        <?php foreach ($templates as $t): ?>
                            <option value="<?php echo (int) $t['id']; ?>" <?php echo (int) $t['id'] === $templateId ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) ($t['course_name'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small mb-0">Live intake (batch course)</label>
                    <select name="course_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <?php if ($courses === []): ?>
                            <option value="0">No intakes with batches for this filter</option>
                        <?php endif; ?>
                        <?php foreach ($courses as $c): ?>
                            <?php
                            $label = trim((string) ($c['course_code'] ?? ''));
                            if ($label === '') {
                                $label = '#' . (int) ($c['id'] ?? 0);
                            }
                            $tpl = trim((string) ($c['nsqf_template_name'] ?? $c['course_name'] ?? ''));
                            if ($templateId <= 0 && $tpl !== '') {
                                $label = $tpl . ' · ' . $label;
                            }
                            $batchesN = (int) ($c['batch_count'] ?? 0);
                            if ($batchesN > 0) {
                                $label .= ' · ' . $batchesN . ' batch' . ($batchesN === 1 ? '' : 'es');
                            }
                            ?>
                            <option value="<?php echo (int) $c['id']; ?>" <?php echo (int) $c['id'] === $courseId ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($role === 'master_admin'): ?>
                        <div class="form-check form-check-inline mt-1">
                            <input class="form-check-input" type="checkbox" name="all_intakes" value="1" id="all_intakes"
                                <?php echo $includeAllIntakes ? 'checked' : ''; ?>
                                onchange="this.form.submit()">
                            <label class="form-check-label small" for="all_intakes">Include intakes without batches</label>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if ($tab !== 'components'): ?>
                <div class="col-md-4">
                    <label class="form-label small mb-0">Batch</label>
                    <select name="batch_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="0" <?php echo $batchId === 0 ? 'selected' : ''; ?>>All batches (certified view)</option>
                        <?php foreach ($batches as $b): ?>
                            <option value="<?php echo (int) $b['id']; ?>" <?php echo (int) $b['id'] === $batchId ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) ($b['batch_name'] ?? 'Batch')); ?>
                                <?php if (!empty($b['batch_code'])): ?>
                                    — <?php echo htmlspecialchars((string) $b['batch_code']); ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </form>

            <ul class="nav nav-pills mt-3 gap-1">
                <li class="nav-item">
                    <a class="nav-link <?php echo $tab === 'results' ? 'active' : ''; ?>"
                       href="?tab=results&amp;template_id=<?php echo $templateId; ?>&amp;course_id=<?php echo $courseId; ?>&amp;batch_id=<?php echo $batchId; ?>">Update results</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $tab === 'components' ? 'active' : ''; ?>"
                       href="?tab=components&amp;template_id=<?php echo $templateId; ?>">Exam structure</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $tab === 'certified' ? 'active' : ''; ?>"
                       href="?tab=certified&amp;template_id=<?php echo $templateId; ?>&amp;course_id=<?php echo $courseId; ?>&amp;batch_id=<?php echo $batchId; ?>">Certified candidates</a>
                </li>
            </ul>
        </div>

        <?php if ($tab === 'components' && $templateId <= 0): ?>
            <div class="alert alert-warning">Select or create an NSQF program under Manage NSQF Course first.</div>
        <?php elseif ($tab !== 'components' && $courseId <= 0): ?>
            <div class="alert alert-warning">No live intake with batches is available for your account. Coordinators must create batches on a course whose name matches an NSQF program.</div>
        <?php elseif ($tab === 'components'): ?>
            <div class="card ner-toolbar">
                <div class="card-body">
                    <h2 class="h6"><?php echo htmlspecialchars($templateName !== '' ? $templateName : 'Program'); ?> — modules / semesters</h2>
                    <p class="small text-muted">Applies to all live intakes with the same course name. Codes must be unique per program.</p>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btnSeedOlevel">
                            <i class="fas fa-magic"></i> Apply O-Level default (M1–M4)
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnAddRow">
                            <i class="fas fa-plus"></i> Add row
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" id="btnSaveComponents">
                            <i class="fas fa-save"></i> Save structure
                        </button>
                    </div>
                    <table class="table table-sm" id="componentsTable">
                        <thead>
                        <tr>
                            <th>Code</th>
                            <th>Label</th>
                            <th>Type</th>
                            <th>Required for cert.</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($components as $comp): ?>
                            <tr class="ner-comp-row">
                                <td><input type="text" class="form-control form-control-sm comp-code" value="<?php echo htmlspecialchars((string) $comp['component_code']); ?>"></td>
                                <td><input type="text" class="form-control form-control-sm comp-label" value="<?php echo htmlspecialchars((string) $comp['component_label']); ?>"></td>
                                <td>
                                    <select class="form-select form-select-sm comp-type">
                                        <option value="module" <?php echo ($comp['component_type'] ?? '') === 'module' ? 'selected' : ''; ?>>Module</option>
                                        <option value="semester" <?php echo ($comp['component_type'] ?? '') === 'semester' ? 'selected' : ''; ?>>Semester</option>
                                    </select>
                                </td>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input comp-required" <?php echo !empty($comp['required_for_cert']) ? 'checked' : ''; ?>>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php elseif ($tab === 'certified'): ?>
            <div class="ner-table-wrap p-3">
                <h2 class="h6 mb-3"><?php echo htmlspecialchars($courseName); ?> — certified (all required modules passed)</h2>
                <?php if ($components === []): ?>
                    <div class="alert alert-info mb-0">Define exam structure first (Exam structure tab).</div>
                <?php elseif ($certified === []): ?>
                    <div class="alert alert-secondary mb-0">No certified candidates yet for this selection.</div>
                <?php else: ?>
                    <table class="table table-hover ner-table mb-0">
                        <thead>
                        <tr>
                            <th>Name</th>
                            <th>Student ID</th>
                            <th>Mobile</th>
                            <th>Modules passed</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($certified as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) ($row['name'] ?? '')); ?></td>
                                <td><code><?php echo htmlspecialchars((string) ($row['student_id'] ?? '')); ?></code></td>
                                <td><?php echo htmlspecialchars((string) ($row['mobile'] ?? '')); ?></td>
                                <td>
                                    <span class="badge bg-success ner-cert-badge">
                                        <?php echo (int) ($row['passed_modules'] ?? 0); ?> /
                                        <?php echo (int) ($row['required_modules'] ?? 0); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="small text-muted mt-2 mb-0"><?php echo count($certified); ?> certified candidate(s)</p>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <?php if ($components === []): ?>
                <div class="alert alert-info">
                    No modules defined for this course.
                    <a href="?tab=components&amp;template_id=<?php echo $templateId; ?>">Configure exam structure</a>
                    or click Apply O-Level default there.
                </div>
            <?php elseif ($batchId <= 0): ?>
                <div class="alert alert-warning">Select a batch to update results.</div>
            <?php else: ?>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnSyncOverall">
                        <i class="fas fa-sync"></i> Sync overall Pass/Certified from modules
                    </button>
                </div>
                <div class="ner-table-wrap">
                    <table class="table ner-table table-bordered mb-0">
                        <thead>
                        <tr>
                            <th class="sticky-col">Student</th>
                            <th>Portal ID</th>
                            <?php foreach ($components as $comp): ?>
                                <th>
                                    <?php echo htmlspecialchars((string) $comp['component_code']); ?>
                                    <div class="small text-muted fw-normal"><?php echo htmlspecialchars((string) $comp['component_label']); ?></div>
                                    <select class="form-select form-select-sm mt-1 bulk-col-select" data-component-id="<?php echo (int) $comp['id']; ?>">
                                        <option value="">Set all…</option>
                                        <?php foreach ($resultOptions as $val => $label): ?>
                                            <option value="<?php echo htmlspecialchars($val); ?>"><?php echo htmlspecialchars($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </th>
                            <?php endforeach; ?>
                            <th>Certification</th>
                            <th>Overall</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($students as $stu):
                            $bsId = (int) ($stu['batch_student_id'] ?? 0);
                            $byComp = $resultsMap[$bsId] ?? [];
                            $state = nsqf_student_modules_certification_state($components, $byComp);
                            $overall = nsqf_normalize_exam_result_status($stu['overall_result_status'] ?? 'exam_not_applied');
                            ?>
                            <tr data-batch-student-id="<?php echo $bsId; ?>">
                                <td class="sticky-col fw-medium"><?php echo htmlspecialchars((string) ($stu['name'] ?? '')); ?></td>
                                <td><code class="small"><?php echo htmlspecialchars((string) ($stu['student_id'] ?? '')); ?></code></td>
                                <?php foreach ($components as $comp):
                                    $cid = (int) $comp['id'];
                                    $cur = nsqf_normalize_exam_result_status($byComp[$cid]['result_status'] ?? 'exam_not_applied');
                                    ?>
                                    <td>
                                        <select class="form-select form-select-sm module-select module-result-select"
                                                data-component-id="<?php echo $cid; ?>">
                                            <?php foreach ($resultOptions as $val => $label): ?>
                                                <option value="<?php echo htmlspecialchars($val); ?>" <?php echo $val === $cur ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                <?php endforeach; ?>
                                <td>
                                    <?php if ($state['all_required_pass']): ?>
                                        <span class="badge bg-success">Fully certified</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">
                                            <?php echo (int) $state['passed_count']; ?>/<?php echo (int) $state['required_count']; ?> modules
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $overall === 'pass' ? 'success' : ($overall === 'failed' ? 'danger' : 'secondary'); ?>">
                                        <?php echo htmlspecialchars($resultOptions[$overall] ?? $overall); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<script>
(function () {
    const apiUrl = <?php echo json_encode($apiUrl); ?>;
    const courseId = <?php echo (int) $courseId; ?>;
    const templateId = <?php echo (int) $templateId; ?>;
    const batchId = <?php echo (int) $batchId; ?>;
    const isComponentsTab = <?php echo $tab === 'components' ? 'true' : 'false'; ?>;

    function postForm(data) {
        const body = new URLSearchParams(data);
        return fetch(apiUrl, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(r => r.json());
    }

    function toastOk(msg) {
        if (typeof showToast === 'function') {
            showToast(msg, 'success');
        } else {
            alert(msg);
        }
    }
    function toastErr(msg) {
        if (typeof showToast === 'function') {
            showToast(msg, 'danger');
        } else {
            alert(msg);
        }
    }

    document.querySelectorAll('.module-result-select').forEach(sel => {
        sel.addEventListener('change', function () {
            const row = this.closest('tr');
            const bsId = row ? row.getAttribute('data-batch-student-id') : '';
            postForm({
                action: 'save_result',
                course_id: courseId,
                batch_id: batchId,
                batch_student_id: bsId,
                component_id: this.getAttribute('data-component-id'),
                result_status: this.value
            }).then(res => {
                if (res.success) {
                    toastOk(res.message || 'Saved');
                    setTimeout(() => window.location.reload(), 400);
                } else {
                    toastErr(res.message || 'Save failed');
                }
            }).catch(() => toastErr('Network error'));
        });
    });

    document.querySelectorAll('.bulk-col-select').forEach(sel => {
        sel.addEventListener('change', function () {
            const val = this.value;
            if (!val) return;
            if (!confirm('Apply this result to ALL students in this batch for this module?')) {
                this.value = '';
                return;
            }
            postForm({
                action: 'bulk_save_column',
                course_id: courseId,
                batch_id: batchId,
                component_id: this.getAttribute('data-component-id'),
                result_status: val
            }).then(res => {
                if (res.success) {
                    toastOk(res.message || 'Updated');
                    window.location.reload();
                } else {
                    toastErr(res.message || 'Failed');
                }
            });
        });
    });

    const btnSync = document.getElementById('btnSyncOverall');
    if (btnSync) {
        btnSync.addEventListener('click', () => {
            postForm({ action: 'sync_all_overall', course_id: courseId, batch_id: batchId })
                .then(res => {
                    if (res.success) {
                        toastOk(res.message);
                        window.location.reload();
                    } else {
                        toastErr(res.message);
                    }
                });
        });
    }

    const tbl = document.getElementById('componentsTable');
    const btnAdd = document.getElementById('btnAddRow');
    if (btnAdd && tbl) {
        btnAdd.addEventListener('click', () => {
            const tbody = tbl.querySelector('tbody');
            const tr = document.createElement('tr');
            tr.className = 'ner-comp-row';
            tr.innerHTML = '<td><input type="text" class="form-control form-control-sm comp-code" placeholder="M5"></td>'
                + '<td><input type="text" class="form-control form-control-sm comp-label" placeholder="Module 5"></td>'
                + '<td><select class="form-select form-select-sm comp-type"><option value="module">Module</option><option value="semester">Semester</option></select></td>'
                + '<td class="text-center"><input type="checkbox" class="form-check-input comp-required" checked></td>';
            tbody.appendChild(tr);
        });
    }

    function collectComponents() {
        const rows = [];
        document.querySelectorAll('#componentsTable .ner-comp-row').forEach(tr => {
            const code = tr.querySelector('.comp-code')?.value?.trim() || '';
            const label = tr.querySelector('.comp-label')?.value?.trim() || '';
            if (!code || !label) return;
            rows.push({
                code: code,
                label: label,
                type: tr.querySelector('.comp-type')?.value || 'module',
                required_for_cert: tr.querySelector('.comp-required')?.checked ? 1 : 0
            });
        });
        return rows;
    }

    const btnSave = document.getElementById('btnSaveComponents');
    if (btnSave) {
        btnSave.addEventListener('click', () => {
            const components = collectComponents();
            if (!components.length) {
                toastErr('Add at least one module.');
                return;
            }
            postForm({
                action: 'save_components',
                template_id: templateId,
                course_id: courseId,
                components: JSON.stringify(components)
            }).then(res => {
                if (res.success) {
                    toastOk(res.message);
                    window.location.href = '?tab=components&template_id=' + templateId;
                } else {
                    toastErr(res.message);
                }
            });
        });
    }

    const btnSeed = document.getElementById('btnSeedOlevel');
    if (btnSeed) {
        btnSeed.addEventListener('click', () => {
            postForm({ action: 'seed_olevel', template_id: templateId, course_id: courseId }).then(res => {
                if (res.success) {
                    toastOk(res.message);
                    window.location.reload();
                } else {
                    toastErr(res.message);
                }
            });
        });
    }
})();
</script>
</body>
</html>