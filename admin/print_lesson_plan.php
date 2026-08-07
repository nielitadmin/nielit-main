<?php
/**
 * Print Detailed Lesson Plan — header + bordered Week / Class Day / Topics table.
 */
require_once __DIR__ . '/../includes/url_helper.php';
session_start();

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../config/config.php';
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

$backUrl = 'edit_lesson_plan.php?id=' . $planId;
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
            color: #000;
            font-family: "Times New Roman", Times, Georgia, serif;
            font-size: 12pt;
            line-height: 1.35;
        }
        .toolbar {
            max-width: 900px;
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
            font-family: system-ui, sans-serif;
        }
        .btn-print { background: #0f172a; color: #fff; }
        .btn-back { background: #64748b; color: #fff; }
        .sheet {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            padding: 28px 32px 36px;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.12);
        }
        .lp-title {
            color: #1d4ed8;
            font-size: 15pt;
            font-weight: 700;
            text-decoration: underline;
            margin: 0 0 14px;
            line-height: 1.3;
        }
        .lp-meta {
            margin: 0 0 18px;
            font-size: 12pt;
            color: #000;
        }
        .lp-meta p {
            margin: 0 0 4px;
        }
        .lp-meta strong {
            font-weight: 700;
        }
        .lp-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 11pt;
        }
        .lp-table th,
        .lp-table td {
            border: 1.5px solid #000;
            vertical-align: top;
            padding: 6px 8px;
            text-align: left;
        }
        .lp-table thead th {
            background: #fff;
            font-weight: 700;
            text-align: center;
            vertical-align: middle;
        }
        .col-week { width: 70px; text-align: center !important; vertical-align: middle !important; font-weight: 700; }
        .col-day { width: 90px; text-align: center !important; vertical-align: middle !important; }
        .col-topic { width: auto; }
        .topic-cell {
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .topic-cell .unit-line {
            font-weight: 700;
        }
        .empty-topic { color: #64748b; font-style: italic; }
        .footer-note {
            margin-top: 14px;
            font-size: 9pt;
            color: #475569;
            font-family: system-ui, sans-serif;
        }
        @media print {
            html, body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .toolbar { display: none !important; }
            .sheet {
                max-width: none;
                margin: 0;
                padding: 12mm 14mm;
                box-shadow: none;
            }
            .lp-table th,
            .lp-table td {
                border: 1.5px solid #000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .col-week, .col-day {
                page-break-inside: avoid;
            }
            tr { page-break-inside: avoid; }
        }
        @page {
            size: A4 portrait;
            margin: 12mm;
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
        <h1 class="lp-title"><?php echo htmlspecialchars($heading); ?></h1>

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
                    <th class="col-topic">Topics to be Covered (Theory)</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($w = 1; $w <= $totalWeeks; $w++): ?>
                    <?php for ($d = 1; $d <= $daysPerWeek; $d++): ?>
                        <?php
                        $topic = trim((string) ($rows[$w][$d]['topic'] ?? ''));
                        $topicHtml = $topic !== ''
                            ? nl2br(htmlspecialchars($topic))
                            : '<span class="empty-topic">—</span>';
                        // Bold lines that look like Unit headings
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
                        }
                        ?>
                        <tr>
                            <?php if ($d === 1): ?>
                                <td class="col-week" rowspan="<?php echo (int) $daysPerWeek; ?>">
                                    <?php echo htmlspecialchars(lessonPlanOrdinal($w)); ?>
                                </td>
                            <?php endif; ?>
                            <td class="col-day"><?php echo htmlspecialchars(lessonPlanOrdinal($d)); ?></td>
                            <td class="col-topic topic-cell"><?php echo $topicHtml; ?></td>
                        </tr>
                    <?php endfor; ?>
                <?php endfor; ?>
            </tbody>
        </table>

        <?php if (!empty($plan['batch_name'])): ?>
            <p class="footer-note">
                Batch: <?php echo htmlspecialchars(($plan['batch_name'] ?? '') . ' (' . ($plan['batch_code'] ?? '') . ')'); ?>
                <?php if (!empty($plan['batch_start_date'])): ?>
                    · Start: <?php echo htmlspecialchars(date('d M Y', strtotime($plan['batch_start_date']))); ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>
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
