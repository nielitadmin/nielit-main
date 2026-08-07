<?php
/**
 * Month-wise timetable: one weekly grid table per week (Mon–Fri with dates).
 *
 * Expected vars:
 * - $slots
 * - $ctMonthYear, $ctMonthMonth (int)
 * - $ctMonthBaseUrl (string)
 * - $ctMonthQuery (array)
 * - $ctMonthEditable (bool)
 * - $ctGridFilterBatch (int)
 * - $ctGridCsrf
 * - $ctGridCourses (optional)
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

$built = classTimetableBuildGrid($slots);
$days = $built['days'];
$periods = $built['periods'];
$grid = $built['grid'];
$facultyDisplayMap = classTimetableFacultyDisplayMap($slots);
$legends = classTimetableBuildLegends($slots);

$prevTs = mktime(0, 0, 0, $ctMonthMonth - 1, 1, $ctMonthYear);
$nextTs = mktime(0, 0, 0, $ctMonthMonth + 1, 1, $ctMonthYear);
$monthLabel = date('F Y', mktime(0, 0, 0, $ctMonthMonth, 1, $ctMonthYear));
$daysInMonth = (int) date('t', mktime(0, 0, 0, $ctMonthMonth, 1, $ctMonthYear));
$today = date('Y-m-d');

$buildMonthLink = static function (int $y, int $m) use ($ctMonthBaseUrl, $ctMonthQuery): string {
    $q = array_merge($ctMonthQuery, [
        'view' => 'month',
        'year' => $y,
        'month' => $m,
    ]);
    return $ctMonthBaseUrl . '?' . http_build_query($q);
};

// Build weeks of this month: each week = Mon–Fri dates that fall in the month
$weeks = [];
$weekIndex = 0;
$currentWeek = [];
for ($d = 1; $d <= $daysInMonth; $d++) {
    $ts = mktime(0, 0, 0, $ctMonthMonth, $d, $ctMonthYear);
    $dow = (int) date('N', $ts); // 1=Mon … 7=Sun
    if ($dow > 5) {
        continue; // skip Sat/Sun
    }
    // New week when we hit Monday (or first weekday of month)
    if ($dow === 1 || empty($currentWeek)) {
        if (!empty($currentWeek)) {
            $weeks[] = $currentWeek;
        }
        $currentWeek = [];
        $weekIndex++;
    }
    $currentWeek[$dow] = [
        'day' => $d,
        'date' => date('Y-m-d', $ts),
        'label' => date('D', $ts) . ' ' . date('j M', $ts),
        'dow' => $dow,
    ];
}
if (!empty($currentWeek)) {
    $weeks[] = $currentWeek;
}
?>
<style>
.ct-month-nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 16px;
}
.ct-month-nav h5 { margin: 0; font-weight: 700; color: #0f172a; }
.ct-month-nav .ct-month-links { display: flex; gap: 8px; flex-wrap: wrap; }
.ct-week-block { margin-bottom: 1.75rem; }
.ct-week-title {
    font-size: 1rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 8px;
    padding: 8px 12px;
    background: #f1f5f9;
    border-left: 4px solid #f59e0b;
    border-radius: 0 6px 6px 0;
}
.ct-week-wrap { overflow-x: auto; }
.ct-week-sheet {
    width: 100%;
    min-width: 980px;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 0.78rem;
    background: #fff;
}
.ct-week-sheet th,
.ct-week-sheet td {
    border: 1px solid #0f172a;
    text-align: center;
    vertical-align: middle;
    padding: 6px 4px;
}
.ct-week-sheet thead th {
    background: #f8fafc;
    font-weight: 700;
    color: #0f172a;
    white-space: pre-line;
    line-height: 1.25;
    min-width: 72px;
}
.ct-week-sheet .ct-day-col {
    width: 110px;
    min-width: 110px;
    background: #f1f5f9;
    font-weight: 700;
    text-align: left;
    padding-left: 10px;
}
.ct-week-sheet .ct-day-col .ct-day-date {
    display: block;
    font-weight: 600;
    font-size: 0.72rem;
    color: #64748b;
}
.ct-week-sheet .ct-day-col.ct-today-row {
    box-shadow: inset 3px 0 0 #f59e0b;
}
.ct-week-sheet .ct-cell-filled { background: #fffbeb; }
.ct-week-sheet .ct-cell-empty { background: #fff; color: #94a3b8; }
.ct-week-sheet .ct-cell-block { margin-bottom: 4px; }
.ct-week-sheet .ct-cell-entry { display: block; font-weight: 700; color: #0f172a; }
.ct-week-sheet .ct-cell-batch,
.ct-week-sheet .ct-cell-room {
    display: block;
    font-size: 0.68rem;
    color: #64748b;
    font-weight: 500;
}
.ct-week-sheet .ct-cell-actions {
    display: flex;
    gap: 4px;
    justify-content: center;
    margin-top: 4px;
}
.ct-week-sheet .ct-add-cell {
    border: none;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    font-size: 0.85rem;
    padding: 2px 6px;
}
.ct-week-sheet .ct-add-more {
    color: #2563eb;
    font-size: 0.72rem;
    display: block;
    margin: 4px auto 0;
}
.ct-legend {
    margin-top: 0.5rem;
    font-size: 0.85rem;
    color: #334155;
    padding: 6px 0;
    border-top: 1px solid #e2e8f0;
}
</style>

<div class="ct-month-nav">
    <h5><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($monthLabel); ?></h5>
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

<p class="ct-muted" style="margin: 0 0 14px;">
    Each week of the month is shown as a separate timetable (Monday–Friday) with dates.
    Slots appear on days covered by the <strong>batch</strong> start–end dates (course dates used only if batch dates are blank). Saturday &amp; Sunday are holidays.
</p>

<?php foreach ($weeks as $wi => $weekDays): ?>
    <?php
    $weekNum = $wi + 1;
    $dateLabels = [];
    foreach ($weekDays as $info) {
        $dateLabels[] = (int) $info['day'];
    }
    $rangeText = '';
    if (!empty($dateLabels)) {
        $firstTs = mktime(0, 0, 0, $ctMonthMonth, min($dateLabels), $ctMonthYear);
        $lastTs = mktime(0, 0, 0, $ctMonthMonth, max($dateLabels), $ctMonthYear);
        $rangeText = date('j M', $firstTs) . ' – ' . date('j M Y', $lastTs);
    }
    ?>
    <div class="ct-week-block">
        <h6 class="ct-week-title">
            Week <?php echo (int) $weekNum; ?>
            <?php if ($rangeText !== ''): ?>
                <span style="font-weight:500;color:#64748b;"> — <?php echo htmlspecialchars($rangeText); ?></span>
            <?php endif; ?>
        </h6>
        <div class="ct-week-wrap">
            <table class="ct-week-sheet" aria-label="Week <?php echo (int) $weekNum; ?> timetable">
                <thead>
                    <tr>
                        <th class="ct-day-col">Day / Date</th>
                        <?php foreach ($periods as $period): ?>
                            <th><?php echo htmlspecialchars($period['label']); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($dow = 1; $dow <= 5; $dow++): ?>
                        <?php
                        $dayInfo = $weekDays[$dow] ?? null;
                        if ($dayInfo === null) {
                            // Day not in this month for this week (e.g. month starts mid-week)
                            continue;
                        }
                        $dayName = $days[$dow] ?? date('l', strtotime($dayInfo['date']));
                        $isToday = ($dayInfo['date'] === $today);
                        ?>
                        <tr>
                            <th class="ct-day-col <?php echo $isToday ? 'ct-today-row' : ''; ?>" scope="row">
                                <?php echo htmlspecialchars($dayName); ?>
                                <span class="ct-day-date"><?php echo htmlspecialchars(date('j M Y', strtotime($dayInfo['date']))); ?></span>
                            </th>
                            <?php foreach ($periods as $period): ?>
                                <?php
                                $cellSlots = $grid[$dow][$period['key']] ?? [];
                                if (!empty($dayInfo['date']) && function_exists('classTimetableFilterSlotsForDate')) {
                                    $cellSlots = classTimetableFilterSlotsForDate($cellSlots, (string) $dayInfo['date']);
                                }
                                $filled = !empty($cellSlots);
                                ?>
                                <td class="ct-cell <?php echo $filled ? 'ct-cell-filled' : 'ct-cell-empty'; ?>">
                                    <?php if ($filled): ?>
                                        <?php foreach ($cellSlots as $slot): ?>
                                            <div class="ct-cell-block">
                                                <?php
                                                $cellSubject = trim((string) ($slot['subject'] ?? ''));
                                                $cellFacultyCode = $facultyDisplayMap[trim((string) ($slot['faculty_name'] ?? ''))] ?? '';
                                                $cellLabel = $cellFacultyCode !== '' ? ($cellSubject . ' (' . $cellFacultyCode . ')') : $cellSubject;
                                                ?>
                                                <span class="ct-cell-entry"><?php echo htmlspecialchars($cellLabel); ?></span>
                                                <?php if ($ctGridFilterBatch <= 0 && !empty($slot['batch_name'])): ?>
                                                    <span class="ct-cell-batch"><?php echo htmlspecialchars($slot['batch_name']); ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($slot['room'])): ?>
                                                    <span class="ct-cell-room"><?php echo htmlspecialchars($slot['room']); ?></span>
                                                <?php endif; ?>
                                                <?php if ($ctMonthEditable): ?>
                                                    <div class="ct-cell-actions">
                                                        <button type="button" class="btn btn-sm btn-primary" title="Edit"
                                                                onclick='openSlotModal(<?php echo json_encode($slot, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>)'>
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        &nbsp;
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>

<?php if (!empty($facultyDisplayMap) || !empty($legends['subjects'])): ?>
    <?php if (!empty($facultyDisplayMap)): ?>
        <div class="ct-legend">
            <strong>Faculty:</strong>
            <?php
            $parts = [];
            foreach ($facultyDisplayMap as $fullName => $code) {
                $parts[] = htmlspecialchars($code) . '-' . htmlspecialchars(strtoupper($fullName));
            }
            echo implode(', ', $parts);
            ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($legends['subjects'])): ?>
        <div class="ct-legend">
            <strong>Courses:</strong>
            <?php
            $courseFullNames = [];
            $ctCourseList = isset($ctGridCourses) && is_array($ctGridCourses) ? $ctGridCourses : [];
            foreach ($legends['subjects'] as $subj => $_) {
                $matched = false;
                foreach ($ctCourseList as $crs) {
                    $shortCode = strtoupper(preg_replace('/[-_].+$/', '', $crs['course_code'] ?? ''));
                    if ($shortCode !== '' && strtoupper(trim($subj)) === $shortCode) {
                        $courseFullNames[] = htmlspecialchars($subj) . ' — ' . htmlspecialchars($crs['course_name']);
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) {
                    $courseFullNames[] = htmlspecialchars($subj);
                }
            }
            echo implode(', ', $courseFullNames);
            ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
