<?php
/**
 * Student Portal — Online Classes
 * View scheduled sessions for enrolled batches; join live / watch Drive recordings.
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/online_class_helper.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

$student_id = (string) $_SESSION['student_id'];
$activeRecordId = isset($_SESSION['active_record_id']) ? (int) $_SESSION['active_record_id'] : null;

ensureOnlineClassesTable($conn);
$batchIds = getStudentOnlineClassBatchIds($conn, $student_id, $activeRecordId);
$courseIds = getStudentOnlineClassCourseIds($conn, $student_id);
$classes = listOnlineClassesForStudent($conn, $student_id, $activeRecordId);
$batchLabels = !empty($batchIds) ? getStudentOnlineClassBatchLabels($conn, $batchIds) : [];

$courseLabels = [];
if (!empty($courseIds)) {
    $inCourses = implode(',', array_map('intval', $courseIds));
    $cRes = $conn->query("SELECT course_name FROM courses WHERE id IN ($inCourses) ORDER BY course_name");
    if ($cRes) {
        while ($cr = $cRes->fetch_assoc()) {
            if (!empty($cr['course_name'])) {
                $courseLabels[] = $cr['course_name'];
            }
        }
    }
}

// Split for display
$live = [];
$upcoming = [];
$past = [];
foreach ($classes as $oc) {
    $st = $oc['display_status'] ?? 'upcoming';
    if ($st === 'live') {
        $live[] = $oc;
    } elseif ($st === 'upcoming') {
        $upcoming[] = $oc;
    } else {
        $past[] = $oc;
    }
}

$page_title = 'Online Classes';
include __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="fas fa-video"></i> Online Classes</h2>
            <p class="text-muted mb-0">Join live sessions for your courses/batches and watch recordings when available.</p>
            <?php if (!empty($courseLabels)): ?>
                <p class="text-muted small mb-0 mt-1">
                    Your course<?php echo count($courseLabels) > 1 ? 's' : ''; ?>:
                    <strong><?php echo htmlspecialchars(implode(', ', $courseLabels)); ?></strong>
                </p>
            <?php endif; ?>
            <?php if (!empty($batchLabels)): ?>
                <p class="text-muted small mb-0 mt-1">
                    Assigned batch<?php echo count($batchLabels) > 1 ? 'es' : ''; ?>:
                    <strong><?php echo htmlspecialchars(implode(', ', $batchLabels)); ?></strong>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($batchIds) && empty($courseIds)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            You have no active course enrollment yet. Online classes will appear here after enrollment.
        </div>
    <?php elseif (empty($classes)): ?>
        <div class="alert alert-secondary">
            <i class="fas fa-calendar-times"></i>
            No online classes have been scheduled for your courses yet. Check back later.
        </div>
    <?php else: ?>

        <?php if (!empty($live)): ?>
            <div class="mb-4">
                <h5 class="mb-3"><span class="badge bg-danger">Live Now</span> Join these classes</h5>
                <div class="row">
                    <?php foreach ($live as $oc): ?>
                        <?php include __DIR__ . '/includes/online_class_card.php'; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($upcoming)): ?>
            <div class="mb-4">
                <h5 class="mb-3"><i class="fas fa-clock text-primary"></i> Upcoming</h5>
                <div class="row">
                    <?php foreach ($upcoming as $oc): ?>
                        <?php include __DIR__ . '/includes/online_class_card.php'; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($past)): ?>
            <div class="mb-4">
                <h5 class="mb-3"><i class="fas fa-history text-muted"></i> Past Classes</h5>
                <div class="row">
                    <?php foreach ($past as $oc): ?>
                        <?php include __DIR__ . '/includes/online_class_card.php'; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<style>
.oc-card {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #fff;
    padding: 1.25rem;
    height: 100%;
    display: flex;
    flex-direction: column;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
}
.oc-card.is-live {
    border-color: #fca5a5;
    box-shadow: 0 6px 18px rgba(220, 38, 38, 0.12);
}
.oc-card-title { font-size: 1.05rem; font-weight: 600; color: #0f172a; margin-bottom: 0.35rem; }
.oc-card-meta { font-size: 0.85rem; color: #64748b; margin-bottom: 0.75rem; }
.oc-card-notes { font-size: 0.9rem; color: #475569; margin-bottom: 1rem; flex: 1; }
.oc-card-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: auto; }
.oc-badge-live {
    background: #dc2626;
    color: #fff;
    font-size: 0.7rem;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
