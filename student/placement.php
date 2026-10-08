<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

$student_id = $_SESSION['student_id'];
require_once __DIR__ . '/includes/load_enrollments.php';
require_once __DIR__ . '/../batch_module/includes/batch_placement_helper.php';

$student = $student_profile;
if (!$student) {
    header('Location: login.php');
    exit;
}

$placement = getStudentUnifiedPlacement($conn, $student_id);
$statusOptions = batch_placement_status_options();
$packageTypes = batch_placement_package_type_options();

$page_title = 'My Placement';
include 'includes/header.php';
?>

<style>
.placement-card { border: 1px solid #e2e8f0; border-radius: 12px; }
.placement-fields { display: none; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e2e8f0; }
.placement-fields.is-open { display: block; }
.placement-view p { margin-bottom: 0.35rem; }
</style>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="fas fa-briefcase"></i> My Placement</h2>
            <p class="text-muted mb-0">You have one placement record. After you submit, a placement officer must verify it before the official status is updated.</p>
        </div>
    </div>

    <div id="placementAlert" class="alert d-none" role="alert"></div>

    <?php if ($placement):
                $verification = strtolower(trim((string) ($placement['placement_verification_status'] ?? 'none')));
                $officialStatus = strtolower(trim((string) ($placement['official_placement_status'] ?? 'not_placed')));
                $status = $verification === 'pending'
                    ? strtolower(trim((string) ($placement['placement_status'] ?? 'not_placed')))
                    : $officialStatus;
                $statusLabel = $statusOptions[$status] ?? ucfirst(str_replace('_', ' ', $status));
                if ($verification === 'pending') {
                    $statusLabel = 'Awaiting verification';
                } elseif ($verification === 'rejected') {
                    $statusLabel = 'Rejected — resubmit';
                }
                $package = batch_placement_format_package(
                    $placement['placement_package_amount'] ?? null,
                    $placement['placement_package_type'] ?? 'annual'
                );
                $formId = 'placement-form-1';
                $batchId = (int) ($placement['batch_id'] ?? 0);
                $recordId = (int) ($placement['student_record_id'] ?? 0);
                $placementDate = !empty($placement['placement_date']) ? date('Y-m-d', strtotime($placement['placement_date'])) : '';
                $courseNames = $placement['course_names'] ?? array_filter([(string) ($placement['course_name'] ?? '')]);
                $batchNames = $placement['batch_names'] ?? [];
            ?>
        <div class="row">
            <div class="col-lg-8 mb-4">
                <div class="card placement-card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title mb-0">My Placement</h5>
                            <span class="badge bg-<?php echo $verification === 'pending' ? 'warning' : ($verification === 'rejected' ? 'danger' : batch_placement_status_badge_class($officialStatus)); ?>">
                                <?php echo htmlspecialchars($statusLabel); ?>
                            </span>
                        </div>
                        <?php if (!empty($courseNames)): ?>
                            <p class="text-muted small mb-1">Courses: <?php echo htmlspecialchars(implode(', ', $courseNames)); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($batchNames)): ?>
                            <p class="text-muted small mb-2">Batch: <?php echo htmlspecialchars(implode(', ', $batchNames)); ?></p>
                        <?php else: ?>
                            <p class="text-muted small mb-2">Batch: Not assigned</p>
                        <?php endif; ?>

                        <div class="placement-view" id="view-1">
                            <?php if ($status === 'placed'): ?>
                                <?php if (!empty($placement['placement_company'])): ?>
                                    <p><strong>Company:</strong> <?php echo htmlspecialchars($placement['placement_company']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($placement['placement_role'])): ?>
                                    <p><strong>Role:</strong> <?php echo htmlspecialchars($placement['placement_role']); ?></p>
                                <?php endif; ?>
                                <?php if ($package !== ''): ?>
                                    <p><strong>Package:</strong> <?php echo htmlspecialchars($package); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($placement['placement_location'])): ?>
                                    <p><strong>Location:</strong> <?php echo htmlspecialchars($placement['placement_location']); ?></p>
                                <?php endif; ?>
                            <?php elseif ($status === 'in_process'): ?>
                                <p class="text-muted mb-0">Your placement is marked as in process.</p>
                            <?php elseif ($status === 'higher_studies'): ?>
                                <p class="text-muted mb-0">Marked as pursuing higher studies.</p>
                            <?php else: ?>
                                <p class="text-muted mb-0">Not placed yet. Submit details when you have placement news. A placement officer will verify before it is updated.</p>
                            <?php endif; ?>
                            <?php if ($verification === 'pending'): ?>
                                <p class="small text-warning mt-2 mb-0"><i class="fas fa-clock"></i> Submitted — waiting for placement officer verification.</p>
                            <?php elseif ($verification === 'rejected'): ?>
                                <p class="small text-danger mt-2 mb-0">
                                    <i class="fas fa-times-circle"></i> Rejected.
                                    <?php if (!empty($placement['placement_rejection_note'])): ?>
                                        <?php echo htmlspecialchars((string) $placement['placement_rejection_note']); ?>
                                    <?php endif; ?>
                                    Please update and submit again.
                                </p>
                            <?php elseif ($verification === 'approved'): ?>
                                <p class="small text-success mt-2 mb-0"><i class="fas fa-check-circle"></i> Verified by placement officer.</p>
                            <?php endif; ?>
                            <?php if (!empty($placement['placement_date'])): ?>
                                <p class="small text-muted mt-2 mb-0">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('d M Y', strtotime($placement['placement_date'])); ?>
                                </p>
                            <?php endif; ?>
                            <?php if (!empty($placement['placement_remarks'])): ?>
                                <p class="small mt-2 mb-0"><?php echo nl2br(htmlspecialchars($placement['placement_remarks'])); ?></p>
                            <?php endif; ?>
                        </div>

                        <button type="button" class="btn btn-outline-primary btn-sm mt-3 toggle-placement-form" data-target="<?php echo htmlspecialchars($formId); ?>">
                            <i class="fas fa-edit"></i> Update Placement
                        </button>

                        <div class="placement-fields" id="<?php echo htmlspecialchars($formId); ?>">
                            <form class="student-placement-form">
                                <input type="hidden" name="batch_id" value="<?php echo $batchId; ?>">
                                <input type="hidden" name="student_record_id" value="<?php echo $recordId; ?>">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="placement_status" required>
                                        <?php foreach ($statusOptions as $value => $label): ?>
                                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $status === $value ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Company / Organization</label>
                                    <input type="text" class="form-control" name="placement_company" maxlength="255"
                                           value="<?php echo htmlspecialchars((string) ($placement['placement_company'] ?? '')); ?>"
                                           placeholder="Required when status is Placed">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Job Role / Designation</label>
                                    <input type="text" class="form-control" name="placement_role" maxlength="255"
                                           value="<?php echo htmlspecialchars((string) ($placement['placement_role'] ?? '')); ?>">
                                </div>
                                <div class="row">
                                    <div class="col-md-7 mb-3">
                                        <label class="form-label">Package Amount</label>
                                        <input type="number" step="0.01" min="0" class="form-control" name="placement_package_amount"
                                               value="<?php echo htmlspecialchars((string) ($placement['placement_package_amount'] ?? '')); ?>">
                                    </div>
                                    <div class="col-md-5 mb-3">
                                        <label class="form-label">Package Type</label>
                                        <select class="form-select" name="placement_package_type">
                                            <?php foreach ($packageTypes as $value => $label): ?>
                                                <option value="<?php echo htmlspecialchars($value); ?>" <?php echo (($placement['placement_package_type'] ?? 'annual') === $value) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Location</label>
                                    <input type="text" class="form-control" name="placement_location" maxlength="255"
                                           value="<?php echo htmlspecialchars((string) ($placement['placement_location'] ?? '')); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Placement Date</label>
                                    <input type="date" class="form-control" name="placement_date" value="<?php echo htmlspecialchars($placementDate); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Remarks</label>
                                    <textarea class="form-control" name="placement_remarks" rows="2" maxlength="2000"><?php echo htmlspecialchars((string) ($placement['placement_remarks'] ?? '')); ?></textarea>
                                </div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i> Submit for verification
                                    </button>
                                    <button type="button" class="btn btn-secondary cancel-placement-form" data-target="<?php echo htmlspecialchars($formId); ?>">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-briefcase fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">No Enrollment Found</h4>
                <p class="text-muted mb-4">Placement can be updated after you are registered.</p>
                <a href="dashboard.php" class="btn btn-primary"><i class="fas fa-home"></i> Back to Dashboard</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.querySelectorAll('.toggle-placement-form').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var panel = document.getElementById(btn.getAttribute('data-target'));
        if (panel) {
            panel.classList.add('is-open');
        }
    });
});

document.querySelectorAll('.cancel-placement-form').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var panel = document.getElementById(btn.getAttribute('data-target'));
        if (panel) {
            panel.classList.remove('is-open');
        }
    });
});

function showPlacementAlert(message, type) {
    var alert = document.getElementById('placementAlert');
    if (!alert) return;
    alert.className = 'alert alert-' + type;
    alert.textContent = message;
    alert.classList.remove('d-none');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

document.querySelectorAll('.student-placement-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var submitBtn = form.querySelector('button[type="submit"]');
        var originalHtml = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        fetch('save_placement.php', {
            method: 'POST',
            body: new FormData(form)
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showPlacementAlert(data.message || 'Placement details saved.', 'success');
                setTimeout(function() { window.location.reload(); }, 900);
            } else {
                showPlacementAlert(data.message || 'Could not save placement details.', 'danger');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHtml;
            }
        })
        .catch(function() {
            showPlacementAlert('Could not save placement details. Please try again.', 'danger');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHtml;
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
