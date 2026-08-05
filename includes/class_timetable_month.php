<?php
/**
 * Month-wise calendar for recurring weekly class timetable.
 *
 * Expected vars:
 * - $slots
 * - $ctMonthYear, $ctMonthMonth (int)
 * - $ctMonthBaseUrl (string) page URL without query, e.g. manage_class_timetable.php
 * - $ctMonthQuery (array) extra query params to preserve (batch_id, etc.)
 * - $ctMonthEditable (bool)
 * - $ctGridFilterBatch (int) hide batch label when filtering one batch
 * - $ctGridCsrf, $ctMonthYear, $ctMonthMonth, $viewMode for delete redirects (optional)
 */
if (!isset($slots) || !is_array($slots)) {
    $slots = [];
}
$ctMonthYear = (int) ($ctMonthYear ?? date('Y'));
$ctMonthMonth = (int) ($ctMonthMonth ?? date('n'));
$ctMonthBaseUrl = (string) ($ctMonthBaseUrl ?? '');
$ctMonthQuery = is_array($ctMonthQuery ?? null) ? $ctMonthQuery : [];
$ctMonthEditable = !empty($ctMonthEditable);
$ctGridFilterBatch = (int) ($ctGridFilterBatch ?? 0);
$ctGridCsrf = (string) ($ctGridCsrf ?? '');
$viewMode = (string) ($viewMode ?? 'month');

$monthBuilt = classTimetableBuildMonth($slots, $ctMonthYear, $ctMonthMonth);
$prevTs = mktime(0, 0, 0, $ctMonthMonth - 1, 1, $ctMonthYear);
$nextTs = mktime(0, 0, 0, $ctMonthMonth + 1, 1, $ctMonthYear);

$buildMonthLink = static function (int $y, int $m) use ($ctMonthBaseUrl, $ctMonthQuery): string {
    $q = array_merge($ctMonthQuery, [
        'view' => 'month',
        'year' => $y,
        'month' => $m,
    ]);
    return $ctMonthBaseUrl . '?' . http_build_query($q);
};

$weekHeaders = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$today = date('Y-m-d');
?>
<style>
.ct-month-nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 12px;
}
.ct-month-nav h5 { margin: 0; font-weight: 700; color: #0f172a; }
.ct-month-nav .ct-month-links { display: flex; gap: 8px; flex-wrap: wrap; }
.ct-month-wrap { overflow-x: auto; }
.ct-month {
    width: 100%;
    min-width: 720px;
    border-collapse: collapse;
    table-layout: fixed;
    background: #fff;
}
.ct-month th,
.ct-month td {
    border: 1px solid #0f172a;
    vertical-align: top;
    padding: 6px;
}
.ct-month thead th {
    background: #0f172a;
    color: #fff;
    text-align: center;
    font-size: 0.85rem;
    padding: 8px 4px;
}
.ct-month td {
    height: 110px;
    font-size: 0.75rem;
}
.ct-month td.ct-out {
    background: #f8fafc;
    color: #94a3b8;
}
.ct-month td.ct-sun { background: #f1f5f9; }
.ct-month td.ct-today { box-shadow: inset 0 0 0 2px #f59e0b; }
.ct-month .ct-dom {
    font-weight: 700;
    font-size: 0.85rem;
    color: #0f172a;
    margin-bottom: 4px;
}
.ct-month .ct-item {
    display: block;
    background: #fffbeb;
    border: 1px solid #fcd34d;
    border-radius: 4px;
    padding: 2px 4px;
    margin-bottom: 3px;
    line-height: 1.25;
    color: #0f172a;
    font-weight: 600;
}
.ct-month .ct-item-meta {
    display: block;
    font-weight: 500;
    color: #64748b;
    font-size: 0.68rem;
}
.ct-month .ct-item-time {
    color: #0369a1;
    font-weight: 600;
}
</style>

<div class="ct-month-nav">
    <h5><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($monthBuilt['label']); ?></h5>
    <div class="ct-month-links">
        <a class="btn btn-sm btn-secondary" href="<?php echo htmlspecialchars($buildMonthLink((int) date('Y', $prevTs), (int) date('n', $prevTs))); ?>">
            <i class="fas fa-chevron-left"></i> Prev
        </a>
        <a class="btn btn-sm btn-secondary" href="<?php echo htmlspecialchars($buildMonthLink((int) date('Y'), (int) date('n'))); ?>">
            This month
        </a>
        <a class="btn btn-sm btn-secondary" href="<?php echo htmlspecialchars($buildMonthLink((int) date('Y', $nextTs), (int) date('n', $nextTs))); ?>">
            Next <i class="fas fa-chevron-right"></i>
        </a>
    </div>
</div>

<div class="ct-month-wrap">
    <table class="ct-month" aria-label="Monthly class timetable">
        <thead>
            <tr>
                <?php foreach ($weekHeaders as $h): ?>
                    <th><?php echo htmlspecialchars($h); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($monthBuilt['weeks'] as $week): ?>
                <tr>
                    <?php foreach ($week as $cell): ?>
                        <?php
                        $isSun = !empty($cell['dow']) && (int) $cell['dow'] === 7;
                        $isToday = !empty($cell['date']) && $cell['date'] === $today;
                        $classes = [];
                        if (empty($cell['in_month'])) {
                            $classes[] = 'ct-out';
                        }
                        if ($isSun) {
                            $classes[] = 'ct-sun';
                        }
                        if ($isToday) {
                            $classes[] = 'ct-today';
                        }
                        ?>
                        <td class="<?php echo htmlspecialchars(implode(' ', $classes)); ?>">
                            <?php if (!empty($cell['in_month'])): ?>
                                <div class="ct-dom"><?php echo (int) $cell['day']; ?></div>
                                <?php if ($isSun): ?>
                                    <span class="ct-item-meta">Holiday / Off</span>
                                <?php elseif (empty($cell['slots'])): ?>
                                    <span class="ct-item-meta">—</span>
                                <?php else: ?>
                                    <?php foreach ($cell['slots'] as $slot): ?>
                                        <div class="ct-item">
                                            <span class="ct-item-time">
                                                <?php echo htmlspecialchars(classTimetableFormatTime($slot['start_time'] ?? '')); ?>
                                            </span>
                                            <?php echo htmlspecialchars(classTimetableCellLabel($slot)); ?>
                                            <?php if ($ctGridFilterBatch <= 0 && !empty($slot['batch_name'])): ?>
                                                <span class="ct-item-meta"><?php echo htmlspecialchars($slot['batch_name']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($slot['room'])): ?>
                                                <span class="ct-item-meta"><?php echo htmlspecialchars($slot['room']); ?></span>
                                            <?php endif; ?>
                                            <?php if ($ctMonthEditable): ?>
                                                <button type="button" class="btn btn-sm btn-primary" style="margin-top:2px;padding:0 4px;font-size:0.65rem;"
                                                        onclick='openSlotModal(<?php echo json_encode($slot, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>)'>
                                                    Edit
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="text-muted small mt-2 mb-0">
    Month view shows your weekly recurring schedule on each date (Monday–Saturday). Sunday is off.
</p>
