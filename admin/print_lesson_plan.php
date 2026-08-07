<?php
/**
 * Print Detailed Lesson Plan — NIELIT header/footer, dates, bordered week table.
 */
require_once __DIR__ . '/../includes/url_helper.php';
session_start();

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/institute_branding.php';
require_once __DIR__ . '/../includes/lesson_plan_helper.php';

$role = $_SESSION['admin_role'] ?? '';
$blocked = in_array($role, ['nsqf_manager', 'front_office', 'placement_coordinator'], true);
if ($blocked) {
    $_SESSION['message'] = 'Access denied.';
    $_SESSION['message_type'] = 'danger';
    header('Location: ' . relative_url('dashboard.php'));
    exit();
}

ensureLessonPlanTables($conn);

$planId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$plan = $planId > 0 ? getLessonPlan($conn, $planId) : null;
if (!$plan) {
    $_SESSION['message'] = 'Lesson plan not found.';
    $_SESSION['message_type'] = 'danger';
    header('Location: manage_lesson_plans.php');
    exit();
}

$rows = getLessonPlanRows($conn, $planId);
$totalWeeks = max(1, (int) ($plan['total_weeks'] ?? 16));
$daysPerWeek = max(1, min(6, (int) ($plan['days_per_week'] ?? 5)));
$autoPrint = !isset($_GET['noprint']);

$title = trim((string) ($plan['plan_title'] ?? ''));
if ($title === '') {
    $title = 'Detailed Lesson Plan';
}
$module = trim((string) ($plan['module_code'] ?? ''));
$courseName = trim((string) ($plan['course_name'] ?? ''));
if ($courseName === '' && !empty($plan['linked_course_name'])) {
    $courseName = (string) $plan['linked_course_name'];
}
$semester = trim((string) ($plan['semester'] ?? ''));
$faculty = trim((string) ($plan['faculty_name'] ?? ''));
$hours = $plan['total_hours'] ?? '';
$batchStart = lessonPlanEffectiveStartDate($plan);

$heading = 'Detailed Lesson Plan';
if ($module !== '' || $title !== 'Detailed Lesson Plan') {
    $parts = [];
    if ($module !== '') {
        $parts[] = $module;
    }
    if ($title !== '' && stripos($title, 'detailed lesson plan') === false) {
        $parts[] = $title;
    } elseif ($courseName !== '') {
        $parts[] = $courseName;
    }
    if (!empty($parts)) {
        $heading = 'Detailed Lesson Plan - ' . implode(' ', $parts);
    }
}

// Map week/day → calendar date from batch start
$dateMap = [];
$calendar = lessonPlanBuildMonthCalendar($batchStart, $totalWeeks, $daysPerWeek, $rows);
foreach ($calendar as $monthBlock) {
    foreach ($monthBlock['weeks'] as $weekBlock) {
        foreach ($weekBlock['days'] as $day) {
            $dateMap[(int) $day['week']][(int) $day['dow']] = $day;
        }
    }
}

$planStartLabel = '';
$planEndLabel = '';
if (!empty($dateMap[1][1]['date'])) {
    $planStartLabel = date('d M Y', strtotime($dateMap[1][1]['date']));
}
$lastWeek = $totalWeeks;
$lastDay = $daysPerWeek;
if (!empty($dateMap[$lastWeek][$lastDay]['date'])) {
    $planEndLabel = date('d M Y', strtotime($dateMap[$lastWeek][$lastDay]['date']));
}

$logoUrl = APP_URL . '/assets/images/bhubaneswar_logo.png';
$backUrl = 'edit_lesson_plan.php?id=' . $planId;
$printedAt = date('d M Y, h:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($heading); ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 16px;
            background: #e2e8f0;
            color: #0f172a;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            line-height: 1.35;
        }
        .toolbar {
            max-width: 980px;
            margin: 0 auto 12px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .toolbar .btn {
            border: 0;
            border-radius: 6px;
            padding: 8px 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .btn-print { background: #0f172a; color: #fff; }
        .btn-back { background: #64748b; color: #fff; }
        .sheet {
            max-width: 980px;
            margin: 0 auto;
            background: #fff;
            padding: 16px 18px 20px;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.12);
        }
        .lh-header { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; }
        .lh-header img { height: 48px; width: auto; display: block; }
        .lh-text { flex: 1; text-align: center; }
        .lh-text .hi { font-size: 13px; font-weight: 700; margin: 0; line-height: 1.25; }
        .lh-text .en { font-size: 12px; font-weight: 700; margin: 0; line-height: 1.25; }
        .lh-text .centre { font-size: 11px; font-weight: 600; margin: 0; line-height: 1.25; }
        .lh-text .tag { font-size: 9px; color: #334155; margin: 0; line-height: 1.25; }
        .lh-rule { border: 0; border-top: 1.5px solid #0f172a; margin: 6px 0 10px; }
        .lp-title {
            color: #1d4ed8;
            font-size: 14px;
            font-weight: 800;
            text-decoration: underline;
            margin: 0 0 8px;
            text-align: center;
            line-height: 1.3;
        }
        .doc-meta {
            text-align: center;
            font-size: 10px;
            color: #334155;
            margin: 0 0 10px;
        }
        .lp-meta {
            margin: 0 0 12px;
            font-size: 11px;
            color: #000;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2px 16px;
        }
        .lp-meta p { margin: 0; }
        .lp-meta strong { font-weight: 700; }
        .lp-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 10px;
        }
        .lp-table th,
        .lp-table td {
            border: 1.5px solid #0f172a;
            vertical-align: top;
            padding: 5px 6px;
            text-align: left;
        }
        .lp-table thead th {
            background: #f1f5f9;
            font-weight: 700;
            text-align: center;
            vertical-align: middle;
        }
        .col-week { width: 58px; text-align: center !important; vertical-align: middle !important; font-weight: 700; }
        .col-day { width: 72px; text-align: center !important; vertical-align: middle !important; }
        .col-date { width: 88px; text-align: center !important; vertical-align: middle !important; font-size: 9px; }
        .col-topic { width: auto; }
        .day-name { display: block; font-weight: 700; }
        .day-ord { display: block; font-size: 8.5px; color: #64748b; font-weight: 500; }
        .topic-cell { word-wrap: break-word; }
        .topic-cell .unit-line { font-weight: 700; }
        .empty-topic { color: #94a3b8; font-style: italic; }
        .lp-footer {
            margin-top: 16px;
            border-top: 1px solid #cbd5e1;
            padding-top: 10px;
        }
        .lp-footer-meta {
            font-size: 9px;
            color: #475569;
            margin: 0 0 14px;
            text-align: center;
        }
        .lp-signs {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
            margin-top: 28px;
            text-align: center;
            font-size: 10px;
        }
        .lp-signs .line {
            border-top: 1px solid #0f172a;
            margin: 0 auto 4px;
            width: 70%;
            padding-top: 4px;
        }
        .lp-signs strong { display: block; }
        .lp-signs span { color: #64748b; font-size: 8.5px; }
        @media print {
            html, body { background: #fff !important; padding: 0 !important; margin: 0 !important; }
            .toolbar { display: none !important; }
            .sheet {
                max-width: none;
                margin: 0;
                padding: 8mm 10mm;
                box-shadow: none;
            }
            .lp-table th, .lp-table td {
                border: 1.5px solid #0f172a !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .lp-table thead th { background: #f1f5f9 !important; }
            tr { page-break-inside: avoid; }
            .lh-header { page-break-after: avoid; }
            .lp-footer { page-break-inside: avoid; }
        }
        @page {
            size: A4 portrait;
            margin: 10mm;
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="btn btn-print" onclick="window.print()">Print</button>
        <a class="btn btn-back" href="<?php echo htmlspecialchars($backUrl); ?>">Back to Edit</a>
        <a class="btn btn-back" href="manage_lesson_plans.php">All Plans</a>
    </div>

    <div class="sheet">
        <div class="lh-header">
            <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="NIELIT Logo">
            <div class="lh-text">
                <p class="hi"><?php echo htmlspecialchars(INSTITUTE_NAME_HI_FORMAL, ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="en">National Institute of Electronics and Information Technology (NIELIT)</p>
                <p class="centre">NIELIT Bhubaneswar | Raipur | Baleshwar</p>
                <p class="tag">(An Autonomous Scientific Society of Ministry of Electronics and Information Technology (MeitY), Govt. of India)</p>
            </div>
        </div>
        <hr class="lh-rule">

        <h1 class="lp-title"><?php echo htmlspecialchars($heading); ?></h1>
        <p class="doc-meta">
            <?php if (!empty($plan['centre_name'])): ?>
                <strong>Centre:</strong> <?php echo htmlspecialchars($plan['centre_name']); ?>
                &nbsp;|&nbsp;
            <?php endif; ?>
            <?php if (!empty($plan['batch_name'])): ?>
                <strong>Batch:</strong>
                <?php echo htmlspecialchars(($plan['batch_name'] ?? '') . ' (' . ($plan['batch_code'] ?? '') . ')'); ?>
                &nbsp;|&nbsp;
            <?php endif; ?>
            <?php if ($planStartLabel !== '' && $planEndLabel !== ''): ?>
                <strong>Period:</strong> <?php echo htmlspecialchars($planStartLabel . ' – ' . $planEndLabel); ?>
                &nbsp;|&nbsp;
            <?php elseif (!empty($batchStart)): ?>
                <strong>Batch start:</strong> <?php echo htmlspecialchars(date('d M Y', strtotime($batchStart))); ?>
                &nbsp;|&nbsp;
            <?php endif; ?>
            <strong>Printed:</strong> <?php echo htmlspecialchars($printedAt); ?>
        </p>

        <div class="lp-meta">
            <p><strong>Course Name:</strong> <?php echo htmlspecialchars($courseName !== '' ? $courseName : '—'); ?></p>
            <p><strong>Semester:</strong> <?php echo htmlspecialchars($semester !== '' ? $semester : '—'); ?></p>
            <p><strong>Name of Faculty:</strong> <?php echo htmlspecialchars($faculty !== '' ? $faculty : '—'); ?></p>
            <p><strong>No. of Days/Week Class Allotted:</strong> <?php echo (int) $daysPerWeek; ?></p>
            <p><strong>No. of Weeks:</strong> <?php echo (int) $totalWeeks; ?></p>
            <p><strong>No. of Hours:</strong> <?php echo htmlspecialchars($hours !== '' && $hours !== null ? (string) $hours : '—'); ?></p>
        </div>

        <table class="lp-table">
            <thead>
                <tr>
                    <th class="col-week">Week</th>
                    <th class="col-day">Class Day</th>
                    <th class="col-date">Date</th>
                    <th class="col-topic">Topics to be Covered (Theory)</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($w = 1; $w <= $totalWeeks; $w++): ?>
                    <?php for ($d = 1; $d <= $daysPerWeek; $d++): ?>
                        <?php
                        $topic = trim((string) ($rows[$w][$d]['topic'] ?? ''));
                        $dayInfo = $dateMap[$w][$d] ?? null;
                        $dateLabel = '—';
                        $dayName = '';
                        if ($dayInfo && !empty($dayInfo['date'])) {
                            $dateLabel = date('d M Y', strtotime($dayInfo['date']));
                            $dayName = (string) ($dayInfo['day_name'] ?? date('l', strtotime($dayInfo['date'])));
                        }
                        if ($topic !== '') {
                            $lines = preg_split("/\r\n|\n|\r/", $topic) ?: [];
                            $partsHtml = [];
                            foreach ($lines as $line) {
                                $lineEsc = htmlspecialchars($line);
                                if (preg_match('/^\s*Unit[\s\-–—]*\d+/i', $line)) {
                                    $partsHtml[] = '<span class="unit-line">' . $lineEsc . '</span>';
                                } else {
                                    $partsHtml[] = $lineEsc;
                                }
                            }
                            $topicHtml = implode('<br>', $partsHtml);
                        } else {
                            $topicHtml = '<span class="empty-topic">—</span>';
                        }
                        ?>
                        <tr>
                            <?php if ($d === 1): ?>
                                <td class="col-week" rowspan="<?php echo (int) $daysPerWeek; ?>">
                                    <?php echo htmlspecialchars(lessonPlanOrdinal($w)); ?>
                                </td>
                            <?php endif; ?>
                            <td class="col-day">
                                <?php if ($dayName !== ''): ?>
                                    <span class="day-name"><?php echo htmlspecialchars($dayName); ?></span>
                                    <span class="day-ord"><?php echo htmlspecialchars(lessonPlanOrdinal($d)); ?> day</span>
                                <?php else: ?>
                                    <?php echo htmlspecialchars(lessonPlanOrdinal($d)); ?>
                                <?php endif; ?>
                            </td>
                            <td class="col-date"><?php echo htmlspecialchars($dateLabel); ?></td>
                            <td class="col-topic topic-cell"><?php echo $topicHtml; ?></td>
                        </tr>
                    <?php endfor; ?>
                <?php endfor; ?>
            </tbody>
        </table>

        <div class="lp-footer">
            <p class="lp-footer-meta">
                NIELIT Bhubaneswar | Raipur | Baleshwar
                &nbsp;·&nbsp; Detailed Lesson Plan
                <?php if (!empty($plan['notes'])): ?>
                    &nbsp;·&nbsp; <?php echo htmlspecialchars((string) $plan['notes']); ?>
                <?php endif; ?>
            </p>
            <div class="lp-signs">
                <div>
                    <div class="line"></div>
                    <strong>Faculty</strong>
                    <span><?php echo htmlspecialchars($faculty !== '' ? $faculty : 'Signature &amp; Date'); ?></span>
                </div>
                <div>
                    <div class="line"></div>
                    <strong>Course Coordinator</strong>
                    <span>Signature &amp; Date</span>
                </div>
                <div>
                    <div class="line"></div>
                    <strong>Centre In-Charge</strong>
                    <span>Signature &amp; Date</span>
                </div>
            </div>
        </div>
    </div>

    <?php if ($autoPrint): ?>
    <script>
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 250);
        });
    </script>
    <?php endif; ?>
</body>
</html>
