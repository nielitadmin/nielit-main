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

$placements = getStudentPortalPlacements($conn, $student_id);
$statusOptions = batch_placement_status_options();

$page_title = 'My Placement';
include 'includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="fas fa-briefcase"></i> My Placement</h2>
            <p class="text-muted mb-0">Placement details recorded by the institute for your batch enrollments.</p>
        </div>
    </div>

    <?php if (count($placements) > 0): ?>
        <div class="row">
            <?php foreach ($placements as $placement):
                $status = strtolower(trim((string) ($placement['placement_status'] ?? 'not_placed')));
                $statusLabel = $statusOptions[$status] ?? ucfirst(str_replace('_', ' ', $status));
                $package = batch_placement_format_package(
                    $placement['placement_package_amount'] ?? null,
                    $placement['placement_package_type'] ?? 'annual'
                );
            ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($placement['course_name'] ?? 'Course'); ?></h5>
                            <span class="badge bg-<?php echo batch_placement_status_badge_class($status); ?>">
                                <?php echo htmlspecialchars($statusLabel); ?>
                            </span>
                        </div>
                        <?php if (!empty($placement['batch_name'])): ?>
                            <p class="text-muted small mb-2">Batch: <?php echo htmlspecialchars($placement['batch_name']); ?></p>
                        <?php endif; ?>
                        <?php if ($status === 'placed'): ?>
                            <?php if (!empty($placement['placement_company'])): ?>
                                <p class="mb-1"><strong>Company:</strong> <?php echo htmlspecialchars($placement['placement_company']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($placement['placement_role'])): ?>
                                <p class="mb-1"><strong>Role:</strong> <?php echo htmlspecialchars($placement['placement_role']); ?></p>
                            <?php endif; ?>
                            <?php if ($package !== ''): ?>
                                <p class="mb-1"><strong>Package:</strong> <?php echo htmlspecialchars($package); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($placement['placement_location'])): ?>
                                <p class="mb-1"><strong>Location:</strong> <?php echo htmlspecialchars($placement['placement_location']); ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if (!empty($placement['placement_date'])): ?>
                            <p class="small text-muted mb-0">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('d M Y', strtotime($placement['placement_date'])); ?>
                            </p>
                        <?php endif; ?>
                        <?php if (!empty($placement['placement_remarks'])): ?>
                            <p class="small mt-2 mb-0"><?php echo nl2br(htmlspecialchars($placement['placement_remarks'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-briefcase fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">No Placement Record Yet</h4>
                <p class="text-muted mb-4">Your placement details will appear here once updated by the placement cell.</p>
                <a href="dashboard.php" class="btn btn-primary"><i class="fas fa-home"></i> Back to Dashboard</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
