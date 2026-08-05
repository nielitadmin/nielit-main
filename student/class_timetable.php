<?php
/**
 * Student Portal — Class Timetable (spreadsheet-style weekly grid)
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/class_timetable_helper.php';
require_once __DIR__ . '/../includes/online_class_helper.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

$student_id = (string) $_SESSION['student_id'];
$activeRecordId = isset($_SESSION['active_record_id']) ? (int) $_SESSION['active_record_id'] : null;

ensureClassTimetableTable($conn);
$batchIds = getStudentOnlineClassBatchIds($conn, $student_id, $activeRecordId);
$batchLabelsMap = [];
if (!empty($batchIds)) {
    $inList = implode(',', array_map('intval', $batchIds));
    $bres = $conn->query("SELECT id, batch_name, batch_code FROM batches WHERE id IN ($inList) ORDER BY batch_name");
    if ($bres) {
        while ($br = $bres->fetch_assoc()) {
            $label = trim((string) ($br['batch_name'] ?? ''));
            if (!empty($br['batch_code'])) {
                $label .= ' (' . $br['batch_code'] . ')';
            }
            $batchLabelsMap[(int) $br['id']] = $label !== '' ? $label : ('Batch #' . $br['id']);
        }
    }
}

$selectedBatch = isset($_GET['batch_id']) ? (int) $_GET['batch_id'] : 0;
if ($selectedBatch > 0 && !in_array($selectedBatch, $batchIds, true)) {
    $selectedBatch = 0;
}
if ($selectedBatch <= 0 && count($batchIds) === 1) {
    $selectedBatch = (int) $batchIds[0];
}

$viewBatchIds = $selectedBatch > 0 ? [$selectedBatch] : $batchIds;
$slots = listClassTimetableForBatches($conn, $viewBatchIds);

$byBatch = [];
foreach ($slots as $slot) {
    $bid = (int) ($slot['batch_id'] ?? 0);
    if (!isset($byBatch[$bid])) {
        $byBatch[$bid] = [];
    }
    $byBatch[$bid][] = $slot;
}

$page_title = 'Class Timetable';
include __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="fas fa-calendar-alt"></i> Class Timetable</h2>
            <p class="text-muted mb-0">Weekly schedule grid for your assigned batch<?php echo count($batchIds) > 1 ? 'es' : ''; ?>.</p>
        </div>
    </div>

    <?php if (empty($batchIds)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            You are not assigned to a batch yet. The timetable will appear here after batch assignment.
        </div>
    <?php else: ?>

        <?php if (count($batchIds) > 1): ?>
            <ul class="nav nav-pills mb-4 flex-wrap gap-2">
                <li class="nav-item">
                    <a class="nav-link <?php echo $selectedBatch === 0 ? 'active' : ''; ?>" href="class_timetable.php">All batches</a>
                </li>
                <?php foreach ($batchIds as $bid): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $selectedBatch === (int) $bid ? 'active' : ''; ?>"
                           href="class_timetable.php?batch_id=<?php echo (int) $bid; ?>">
                            <?php echo htmlspecialchars($batchLabelsMap[(int) $bid] ?? ('Batch #' . $bid)); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (empty($slots)): ?>
            <div class="alert alert-secondary">
                <i class="fas fa-calendar-times"></i>
                No timetable has been published for your batch yet. Check back later.
            </div>
        <?php elseif ($selectedBatch === 0 && count($byBatch) > 1): ?>
            <?php foreach ($byBatch as $bid => $batchSlots): ?>
                <h4 class="mb-3 mt-2">
                    <i class="fas fa-layer-group"></i>
                    <?php echo htmlspecialchars($batchLabelsMap[$bid] ?? ('Batch #' . $bid)); ?>
                </h4>
                <?php
                $slots = $batchSlots;
                $ctGridEditable = false;
                $ctGridShowLegends = true;
                $ctGridFilterBatch = (int) $bid;
                include __DIR__ . '/../includes/class_timetable_grid.php';
                ?>
            <?php endforeach; ?>
        <?php else: ?>
            <?php
            $ctGridEditable = false;
            $ctGridShowLegends = true;
            $ctGridFilterBatch = $selectedBatch > 0 ? $selectedBatch : 0;
            include __DIR__ . '/../includes/class_timetable_grid.php';
            ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
