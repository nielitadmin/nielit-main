<?php
/**
 * Student Portal — Class Timetable
 * Weekly schedule for enrolled / assigned batches.
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

// If multiple batches selected (all), group by batch then day; if one, group by day only
$byBatch = [];
foreach ($slots as $slot) {
    $bid = (int) ($slot['batch_id'] ?? 0);
    if (!isset($byBatch[$bid])) {
        $byBatch[$bid] = [];
    }
    $byBatch[$bid][] = $slot;
}

$dayLabels = classTimetableDayLabels();

$page_title = 'Class Timetable';
include __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="fas fa-calendar-alt"></i> Class Timetable</h2>
            <p class="text-muted mb-0">Weekly class schedule for your assigned batch<?php echo count($batchIds) > 1 ? 'es' : ''; ?>.</p>
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
        <?php else: ?>
            <?php foreach ($byBatch as $bid => $batchSlots): ?>
                <?php
                $grouped = groupClassTimetableByDay($batchSlots);
                $batchTitle = $batchLabelsMap[$bid] ?? ($batchSlots[0]['batch_name'] ?? ('Batch #' . $bid));
                ?>
                <?php if (count($byBatch) > 1): ?>
                    <h4 class="mb-3 mt-2"><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($batchTitle); ?></h4>
                <?php endif; ?>

                <div class="tt-week mb-5">
                    <?php foreach ($dayLabels as $dayNum => $dayName): ?>
                        <div class="tt-day">
                            <div class="tt-day-head"><?php echo htmlspecialchars($dayName); ?></div>
                            <div class="tt-day-body">
                                <?php if (empty($grouped[$dayNum])): ?>
                                    <p class="tt-empty mb-0">No classes</p>
                                <?php else: ?>
                                    <?php foreach ($grouped[$dayNum] as $slot): ?>
                                        <div class="tt-slot">
                                            <div class="tt-time">
                                                <?php echo htmlspecialchars(classTimetableFormatTime($slot['start_time'] ?? '')); ?>
                                                –
                                                <?php echo htmlspecialchars(classTimetableFormatTime($slot['end_time'] ?? '')); ?>
                                            </div>
                                            <div class="tt-subject"><?php echo htmlspecialchars($slot['subject'] ?? ''); ?></div>
                                            <?php if (!empty($slot['faculty_name']) || !empty($slot['room'])): ?>
                                                <div class="tt-meta">
                                                    <?php if (!empty($slot['faculty_name'])): ?>
                                                        <span><i class="fas fa-chalkboard-teacher"></i> <?php echo htmlspecialchars($slot['faculty_name']); ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($slot['room'])): ?>
                                                        <span><i class="fas fa-door-open"></i> <?php echo htmlspecialchars($slot['room']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($slot['notes'])): ?>
                                                <div class="tt-notes"><?php echo htmlspecialchars($slot['notes']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.tt-week {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
}
.tt-day {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #fff;
    overflow: hidden;
    min-height: 120px;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
}
.tt-day-head {
    background: #0f172a;
    color: #fff;
    font-weight: 600;
    font-size: 0.9rem;
    padding: 0.65rem 0.85rem;
}
.tt-day-body { padding: 0.75rem; }
.tt-empty { color: #94a3b8; font-size: 0.85rem; }
.tt-slot {
    padding: 0.65rem 0;
    border-bottom: 1px solid #f1f5f9;
}
.tt-slot:last-child { border-bottom: 0; padding-bottom: 0; }
.tt-slot:first-child { padding-top: 0; }
.tt-time { font-size: 0.78rem; color: #0369a1; font-weight: 600; margin-bottom: 0.2rem; }
.tt-subject { font-size: 0.95rem; font-weight: 600; color: #0f172a; }
.tt-meta {
    display: flex;
    flex-direction: column;
    gap: 2px;
    margin-top: 0.35rem;
    font-size: 0.78rem;
    color: #64748b;
}
.tt-notes { font-size: 0.78rem; color: #475569; margin-top: 0.35rem; }
@media (max-width: 576px) {
    .tt-week { grid-template-columns: 1fr; }
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
