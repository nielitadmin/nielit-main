<?php
/**
 * ==========================================================
 * REPORT MONITOR
 * NIELIT BHUBANESWAR
 * PART 1A - 1
 * ==========================================================
 */

session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/report_monitor_helper.php';

/*------------------------------------------------------------
| Login Check
-------------------------------------------------------------*/
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

/*------------------------------------------------------------
| Master Admin Check
-------------------------------------------------------------*/
$adminRole = $_SESSION['admin_role'] ?? '';

if ($adminRole !== 'master_admin') {
    $_SESSION['message'] = "Access Denied";
    $_SESSION['message_type'] = "danger";

    header("Location: dashboard.php");
    exit;
}

/*------------------------------------------------------------
| Theme
-------------------------------------------------------------*/
$active_theme = loadActiveTheme($conn);

/*------------------------------------------------------------
| Assigned Courses
-------------------------------------------------------------*/
$scopedCourseIds = [];

if (
    isset($_SESSION['admin_id']) &&
    $adminRole != 'master_admin'
) {
    $scopedCourseIds = report_monitor_get_assigned_course_ids(
        $conn,
        (int)$_SESSION['admin_id']
    );
}

/*------------------------------------------------------------
| FILTERS
-------------------------------------------------------------*/

$centreId = isset($_GET['centre_id'])
    ? (int)$_GET['centre_id']
    : 0;

$selectedYear = isset($_GET['year'])
    ? (int)$_GET['year']
    : report_monitor_get_financial_year_start();

$selectedQuarter = isset($_GET['quarter'])
    ? $_GET['quarter']
    : report_monitor_get_current_financial_quarter();

if (!in_array($selectedQuarter, ['Q1', 'Q2', 'Q3', 'Q4', 'FY'], true)) {
    $selectedQuarter = report_monitor_get_current_financial_quarter();
}

$selectedCentreName = "";

if ($centreId > 0) {
    $selectedCentreName = report_monitor_get_centre_name(
        $conn,
        $centreId
    );
} else {
    $selectedCentreName = "All Centres";
}

$reportScopeTitleLabel = report_monitor_get_scope_title_label($centreId, $selectedCentreName);
$selectedFyFullLabel = report_monitor_format_financial_year_full_label($selectedYear);

/*------------------------------------------------------------
| Targets flash message (after AJAX save reload)
-------------------------------------------------------------*/
$targetsFlashMessage = $_SESSION['report_targets_flash'] ?? null;
$targetsFlashType = $_SESSION['report_targets_flash_type'] ?? 'success';
unset($_SESSION['report_targets_flash'], $_SESSION['report_targets_flash_type']);

/*------------------------------------------------------------
| Financial year quarter date range (Apr–Mar)
-------------------------------------------------------------*/

$quarterRange = report_monitor_get_financial_quarter_range(
    $selectedYear,
    $selectedQuarter
);

$startDate = $quarterRange['start_date'];
$endDate = $quarterRange['end_date'];
$quarterLabel = $quarterRange['quarter_label'];
$monthScopeLabel = $quarterRange['scope_label'];

$fyCalendarRange = report_monitor_get_financial_quarter_range($selectedYear, 'FY');
$fyCalendarScopeLabel = report_monitor_format_financial_year_label($selectedYear) . ' · Full Year (Apr–Mar)';

/*------------------------------------------------------------
| Month Filter
-------------------------------------------------------------*/

$monthFilter = [

    'active' => true,

    'start' =>
        $startDate . " 00:00:00",

    'end' =>
        $endDate . " 23:59:59",

    'next_start' =>
        date(
            "Y-m-d H:i:s",
            strtotime($endDate . " +1 day")
        ),

    'label' =>
        $monthScopeLabel

];

$fyMonthFilter = [

    'active' => true,

    'start' =>
        $fyCalendarRange['start_date'] . ' 00:00:00',

    'end' =>
        $fyCalendarRange['end_date'] . ' 23:59:59',

    'next_start' =>
        date(
            'Y-m-d H:i:s',
            strtotime($fyCalendarRange['end_date'] . ' +1 day')
        ),

    'label' =>
        $fyCalendarScopeLabel

];

/*------------------------------------------------------------
| Dashboard Data
-------------------------------------------------------------*/

$overallStats = report_monitor_get_overall_stats(
    $conn,
    $scopedCourseIds,
    $centreId,
    $monthFilter
);

$centreStats = report_monitor_get_centre_stats(
    $conn,
    $scopedCourseIds,
    $centreId,
    $monthFilter
);

$categoryQuarterSummary = report_monitor_get_category_quarter_summary(
    $conn,
    $scopedCourseIds,
    $centreId,
    $selectedYear
);
$categoryCourseQuarterSummary = report_monitor_get_category_course_quarter_summary(
    $conn,
    $scopedCourseIds,
    $centreId,
    $selectedYear
);

$categoryAdmissionTargets = report_monitor_get_category_targets(
    $conn,
    $selectedYear,
    $centreId
);
$categoryQuarterTargetSummary = report_monitor_apply_category_targets(
    $categoryQuarterSummary,
    $categoryAdmissionTargets
);
$categoryQuarterSummary = $categoryQuarterTargetSummary['rows'];
$categoryQuarterGrandTotal = $categoryQuarterTargetSummary['grand_total'];
$categoryQuarterGrandTarget = $categoryQuarterTargetSummary['grand_target'];
$categoryQuarterGrandAchievement = $categoryQuarterTargetSummary['grand_achievement_pct'];

$socialCategoryQuarterSummary = report_monitor_get_social_category_quarter_summary(
    $conn,
    $scopedCourseIds,
    $centreId,
    $selectedYear
);
$socialCategoryCourseQuarterSummary = report_monitor_get_social_category_course_quarter_summary(
    $conn,
    $scopedCourseIds,
    $centreId,
    $selectedYear
);
$socialCategoryAdmissionTargets = report_monitor_get_social_category_targets(
    $conn,
    $selectedYear,
    $centreId
);
$socialCategoryQuarterTargetSummary = report_monitor_apply_category_targets(
    $socialCategoryQuarterSummary,
    $socialCategoryAdmissionTargets
);
$socialCategoryQuarterSummary = $socialCategoryQuarterTargetSummary['rows'];
$socialCategoryQuarterGrandTotal = $socialCategoryQuarterTargetSummary['grand_total'];
$socialCategoryQuarterGrandTarget = $socialCategoryQuarterTargetSummary['grand_target'];
$socialCategoryQuarterGrandAchievement = $socialCategoryQuarterTargetSummary['grand_achievement_pct'];

$internshipCourseSummary = report_monitor_get_internship_course_quarter_summary(
    $conn,
    $scopedCourseIds,
    $centreId,
    $selectedYear
);

$categoryStats = report_monitor_get_category_stats(
    $conn,
    $scopedCourseIds,
    $centreId,
    $monthFilter
);

$facultyStats = report_monitor_get_faculty_stats(
    $conn,
    $scopedCourseIds,
    $centreId,
    $monthFilter
);

$facultyTrainingStats = report_monitor_get_faculty_training_summary(
    $conn,
    $scopedCourseIds,
    $centreId,
    $monthFilter
);

$batchMonthly = report_monitor_get_period_monthly(
    $conn,
    $scopedCourseIds,
    $centreId,
    $monthFilter,
    $quarterRange['graph_months']
);

$courseMonthlyProgress = report_monitor_get_course_monthly_progress(
    $conn,
    $scopedCourseIds,
    $centreId,
    $fyMonthFilter,
    $fyCalendarRange['graph_months'],
    8
);

$courseFyTimeline = report_monitor_get_course_fy_timeline(
    $conn,
    $scopedCourseIds,
    $centreId,
    $selectedYear
);

$centresList = report_monitor_get_centres_list(
    $conn
);

$batchDetails = report_monitor_get_batch_details(
    $conn,
    $scopedCourseIds,
    $centreId,
    $monthFilter
);

$courseCalendarSchedule = report_monitor_get_course_calendar_schedule(
    $conn,
    $scopedCourseIds,
    $centreId,
    $fyMonthFilter,
    $fyCalendarRange['graph_months']
);

$admissionsByBatch = report_monitor_get_admissions_by_batch(
    $conn,
    $scopedCourseIds,
    $centreId,
    $monthFilter
);

/*------------------------------------------------------------
| KPI
-------------------------------------------------------------*/

$kpiRegistered = 0;

$kpiAdmission = 0;

$kpiBatches = 0;

$kpiCompleted = 0;

foreach ($categoryStats as $row) {

    $kpiRegistered +=
        (int)$row['applications'];

    $kpiAdmission +=
        (int)$row['batch_enrolled'];

}

foreach ($centreStats as $row) {

    $kpiBatches +=
        (int)$row['batch_count'];

}

$kpiCompleted = $overallStats['completed_batches'] ?? 0;
/*------------------------------------------------------------
| Course Wise Figures
-------------------------------------------------------------*/

$courseStats = report_monitor_get_course_stats(
    $conn,
    $scopedCourseIds,
    $centreId,
    $monthFilter
);

$courseChartStats = array_slice(
    array_values(array_filter($courseStats, static function ($row) {
        return ($row['applications'] ?? 0) > 0
            || ($row['batch_enrolled'] ?? 0) > 0
            || ($row['pending'] ?? 0) > 0
            || ($row['batch_count'] ?? 0) > 0;
    })),
    0,
    20
);

/*------------------------------------------------------------
| Report Payload
-------------------------------------------------------------*/

$reportPayload=[

    'batchMonthly'=>$batchMonthly,

    'centreStats'=>$centreStats,

    'categoryStats'=>$categoryStats,

    'courseStats'=>$courseChartStats,

    'courseMonthlyProgress'=>$courseMonthlyProgress,

    'courseFyTimeline'=>$courseFyTimeline,

    'facultyTrainingStats'=>$facultyTrainingStats,

    'calendarEvents'=>$courseCalendarSchedule['events'] ?? [],

    'calendarRange'=>[
        'start'=>$fyCalendarRange['start_date'],
        'end'=>date('Y-m-d', strtotime($fyCalendarRange['end_date'] . ' +1 day')),
        'fy_label'=>$fyCalendarScopeLabel,
    ],

    'calendarMeta'=>[
        'total_batches'=>(int) ($courseCalendarSchedule['total_batches'] ?? 0),
        'total_footfall'=>(int) ($courseCalendarSchedule['total_footfall'] ?? 0),
        'scope_label'=>$fyCalendarScopeLabel,
    ],

];

$pageTitle="Report Monitor";

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>

        <?php echo htmlspecialchars($pageTitle); ?>

        | NIELIT Bhubaneswar

    </title>

    <?php injectThemeCSS($active_theme); ?>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        rel="stylesheet"
    >

    <link
        href="<?php echo APP_URL;?>/assets/css/admin-theme.css"
        rel="stylesheet"
    >

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>

    <link
        href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css"
        rel="stylesheet"
    >

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>

    <style>

        .report-card{

            border:none;

            border-radius:14px;

            box-shadow:0 5px 15px rgba(0,0,0,.08);

        }

        .kpi-card{

            border:none;

            border-radius:14px;

            color:#fff;

        }

        .bg-register{

            background:#2563eb;

        }

        .bg-admission{

            background:#16a34a;

        }

        .bg-batch{

            background:#f59e0b;

        }

        .kpi-value{

            font-size:34px;

            font-weight:bold;

        }

        .chart-card{

            border:none;

            border-radius:15px;

            box-shadow:0 5px 15px rgba(0,0,0,.08);

        }

        .chart-card .card-body{

            min-height:340px;

        }

        .monthly-progress-table th,
        .monthly-progress-table td{

            white-space:nowrap;

            font-size:13px;

        }

        .monthly-progress-table .course-col{

            white-space:normal;

            min-width:180px;

        }

        .calendar-month-header{

            background:linear-gradient(90deg,#eff6ff,#f8fafc);

            border-left:4px solid #2563eb;

        }

        .calendar-month-header td{

            font-weight:600;

            color:#1e3a8a;

        }

        .calendar-schedule-table th,
        .calendar-schedule-table td{

            font-size:13px;

            vertical-align:middle;

        }

        #courseBatchCalendar{

            min-height:960px;

        }

        #courseBatchCalendar .fc-multimonth{

            border-radius:12px;

            overflow:hidden;

        }

        #courseBatchCalendar .fc-multimonth-month{

            border-color:#e2e8f0;

        }

        #courseBatchCalendar .fc-multimonth-title{

            font-size:14px;

            font-weight:600;

            color:#1e3a8a;

        }

        #courseBatchCalendar .fc-toolbar-title{

            font-size:1.25rem;

            font-weight:600;

        }

        #courseBatchCalendar .fc-event{

            cursor:pointer;

            font-size:12px;

            border-radius:6px;

            padding:2px 4px;

        }

        #courseBatchCalendar .fc-daygrid-day-number{

            font-weight:600;

        }

        .calendar-legend{

            display:flex;

            flex-wrap:wrap;

            gap:10px 16px;

        }

        .calendar-legend-item{

            display:inline-flex;

            align-items:center;

            gap:6px;

            font-size:12px;

            color:#475569;

        }

        .calendar-legend-dot{

            width:12px;

            height:12px;

            border-radius:999px;

            display:inline-block;

        }

        .calendar-event-detail dt{

            font-weight:600;

            color:#64748b;

        }

        .calendar-event-detail dd{

            margin-bottom:.65rem;

        }

        .course-fy-timeline-wrap{

            overflow-x:auto;

            padding-bottom:8px;

        }

        .course-fy-timeline{

            min-width:1200px;

        }

        .course-fy-timeline-summary{

            display:flex;

            flex-wrap:wrap;

            gap:10px;

            margin-bottom:16px;

        }

        .course-fy-timeline-head{

            display:grid;

            grid-template-columns:240px minmax(640px,1fr) 150px;

            gap:12px;

            align-items:end;

            margin-bottom:8px;

            position:sticky;

            top:0;

            z-index:2;

            background:#fff;

            padding-bottom:8px;

            border-bottom:2px solid #e2e8f0;

        }

        .course-fy-axis-months{

            display:flex;

            height:28px;

            border:1px solid #dbeafe;

            border-radius:8px 8px 0 0;

            overflow:hidden;

            background:#f8fafc;

        }

        .course-fy-axis-month{

            display:flex;

            align-items:center;

            justify-content:center;

            font-size:11px;

            font-weight:600;

            color:#1e3a8a;

            border-right:1px solid #dbeafe;

            white-space:nowrap;

            padding:0 4px;

        }

        .course-fy-axis-days{

            display:flex;

            height:22px;

            border:1px solid #e2e8f0;

            border-top:none;

            background:#fff;

        }

        .course-fy-axis-day{

            flex:1 0 0;

            min-width:3px;

            font-size:9px;

            color:#64748b;

            text-align:center;

            border-right:1px solid #f1f5f9;

            line-height:22px;

            overflow:hidden;

        }

        .course-fy-axis-day.is-month-start{

            background:#eff6ff;

            color:#2563eb;

            font-weight:600;

        }

        .course-fy-row{

            display:grid;

            grid-template-columns:240px minmax(640px,1fr) 150px;

            gap:12px;

            align-items:center;

            padding:10px 0;

            border-bottom:1px solid #f1f5f9;

        }

        .course-fy-row:hover{

            background:#fafbff;

        }

        .course-fy-course-name{

            font-weight:600;

            font-size:13px;

            line-height:1.3;

        }

        .course-fy-course-code{

            font-size:11px;

            color:#64748b;

        }

        .course-fy-totals{

            display:flex;

            flex-wrap:wrap;

            gap:6px;

            margin-top:6px;

        }

        .course-fy-totals .badge{

            font-size:10px;

            font-weight:500;

        }

        .course-fy-track{

            position:relative;

            height:34px;

            border:1px solid #e2e8f0;

            border-radius:8px;

            background:repeating-linear-gradient(
                90deg,
                #fff,
                #fff  calc(100% / var(--fy-days, 365) - 1px),
                #f8fafc calc(100% / var(--fy-days, 365) - 1px),
                #f8fafc calc(100% / var(--fy-days, 365))
            );

            overflow:hidden;

        }

        .course-fy-bar{

            position:absolute;

            top:5px;

            height:24px;

            border-radius:999px;

            background:linear-gradient(90deg,#2563eb,#3b82f6);

            box-shadow:0 1px 3px rgba(37,99,235,.25);

            cursor:pointer;

            min-width:4px;

        }

        .course-fy-bar:nth-child(odd){

            background:linear-gradient(90deg,#16a34a,#22c55e);

        }

        .course-fy-sparkline{

            height:44px;

            width:140px;

        }

        .course-fy-sparkline canvas{

            width:100% !important;

            height:44px !important;

        }

        #courseFyGanttWrap{

            position:relative;

            min-height:420px;

            margin-bottom:24px;

            padding:12px;

            border:1px solid #e2e8f0;

            border-radius:12px;

            background:#fafbff;

        }

        .course-fy-gantt-scroll{

            overflow-x:auto;

            overflow-y:hidden;

            padding-bottom:4px;

        }

        #courseFyGanttInner{

            min-width:100%;

        }

        #courseFyGanttWrap canvas{

            width:100% !important;

        }

        .table-card{

            border:none;

            border-radius:15px;

            box-shadow:0 5px 15px rgba(0,0,0,.08);

        }

    </style>

</head>

<body>

<div class="admin-wrapper">

<?php include __DIR__.'/includes/sidebar.php'; ?>

<main class="admin-content">

<div class="admin-main">

<div class="container-fluid py-3">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2>

<i class="fas fa-chart-line"></i>

Report Monitor

</h2>

<p class="text-muted mb-0">

Quarterly Analytics Dashboard (Financial Year: April–March)

</p>

</div>

</div>

<!-- FILTERS -->

<div class="card report-card mb-4">

<div class="card-body">

<form method="GET">

<div class="row">

<div class="col-md-4">

<label>

Centre

</label>

<select

class="form-select"

name="centre_id"

onchange="this.form.submit()"

>

<?php foreach($centresList as $centre): ?>

<option

value="<?php echo $centre['id'];?>"

<?php echo $centreId==$centre['id']?'selected':'';?>

>

<?php echo htmlspecialchars($centre['name']);?>

</option>

<?php endforeach;?>

</select>

</div>

<div class="col-md-4">

<label>

Financial Year (starts April)

</label>

<select

name="year"

class="form-select"

onchange="this.form.submit()"

>

<?php

for(

$y = (int)date("Y");

$y >= 2020;

$y--

):

$fyEndShort = substr((string)($y + 1), -2);

?>

<option

value="<?php echo $y;?>"

<?php echo $selectedYear==$y?'selected':'';?>

>

FY <?php echo $y . '-' . $fyEndShort; ?>

</option>

<?php endfor;?>

</select>

</div>

<div class="col-md-4">

<label>

Quarter / Full Year

</label>

<select

name="quarter"

class="form-select"

onchange="this.form.submit()"

>

<option value="FY" <?php echo $selectedQuarter=="FY"?"selected":"";?>>

Full Year (Apr–Mar)

</option>

<option value="Q1" <?php echo $selectedQuarter=="Q1"?"selected":"";?>>

Q1 (Apr–Jun)

</option>

<option value="Q2" <?php echo $selectedQuarter=="Q2"?"selected":"";?>>

Q2 (Jul–Sep)

</option>

<option value="Q3" <?php echo $selectedQuarter=="Q3"?"selected":"";?>>

Q3 (Oct–Dec)

</option>

<option value="Q4" <?php echo $selectedQuarter=="Q4"?"selected":"";?>>

Q4 (Jan–Mar)

</option>

</select>

</div>

</div>

</form>

</div>

</div>
<!-- KPI CARDS -->

<div class="row mb-4">

    <div class="col-md-4 mb-3">

        <div class="card kpi-card bg-register">

            <div class="card-body">

                <small>

                    Total Students Registered

                </small>

                <div class="kpi-value">

                    <?php echo number_format($kpiRegistered);?>

                </div>

                <small>

                    <?php echo htmlspecialchars($monthScopeLabel);?>

                </small>

            </div>

        </div>

    </div>

    <div class="col-md-4 mb-3">

        <div class="card kpi-card bg-admission">

            <div class="card-body">

                <small>

                    Total Admissions Got

                </small>

                <div class="kpi-value">

                    <?php echo number_format($kpiAdmission);?>

                </div>

                <small>

                    Students in Batch

                </small>

            </div>

        </div>

    </div>

    <div class="col-md-3 mb-3">

        <div class="card kpi-card bg-batch">

            <div class="card-body">

                <small>

                    Total Active Batches

                </small>

                <div class="kpi-value">

                    <?php echo number_format($kpiBatches);?>

                </div>

                <small>

                    Running Batches

                </small>

            </div>

        </div>

    </div>

    <div class="col-md-3 mb-3">

        <div class="card kpi-card bg-secondary">

            <div class="card-body">

                <small>

                    Total Completed Batches

                </small>

                <div class="kpi-value">

                    <?php echo number_format($kpiCompleted);?>

                </div>

                <small>

                    Completed Batches

                </small>

            </div>

        </div>

    </div>

</div>

<!-- ADMISSIONS BY BATCH (for selected quarter) -->
<div class="card table-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>
            Admissions by Batch (<?php echo htmlspecialchars($monthScopeLabel); ?>)
        </strong>
        <span class="badge bg-primary">
            <?php echo array_sum(array_column($admissionsByBatch, 'admissions')); ?> Admissions
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-bordered mb-0">
                <thead class="table-light">
                <tr>
                    <th>Batch</th>
                    <th>Course</th>
                    <th>Centre</th>
                    <th class="text-end">Admissions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($admissionsByBatch)): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">No admissions found for this period</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($admissionsByBatch as $row): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($row['batch_name']); ?></strong>
                                <br><small><?php echo htmlspecialchars($row['batch_code']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['centre_name']); ?></td>
                            <td class="text-end"><?php echo number_format($row['admissions']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- CHARTS -->

<div class="row">

    <div class="col-lg-8 mb-4">

        <div class="card chart-card">

            <div class="card-header">

                <strong>

                    Course / Batch Trend

                </strong>

                <small class="text-muted ms-2">

                    <?php echo htmlspecialchars($monthScopeLabel); ?>

                </small>

            </div>

            <div class="card-body">

                <canvas

                    id="batchMonthlyChart"

                    height="120"

                ></canvas>

            </div>

        </div>

    </div>

    <div class="col-lg-4 mb-4">

        <div class="card chart-card">

            <div class="card-header">

                <strong>

                    Centre Wise Report

                </strong>

            </div>

            <div class="card-body">

                <canvas

                    id="centreChart"

                    height="120"

                ></canvas>

            </div>

        </div>

    </div>

</div>

<!-- CATEGORY / COURSE GRAPH -->

<div class="row mb-4">

    <div class="col-lg-12">

        <div class="card chart-card">

            <div class="card-header">

                <strong>

                    Course Wise Figures

                </strong>

                <small class="text-muted ms-2">

                    Top 20 courses in chart; full list in table below

                </small>

            </div>

            <div class="card-body">

                <canvas

                    id="courseChart"

                    height="320"

                ></canvas>

            </div>

        </div>

    </div>

</div>
<!-- COURSE CALENDAR -->

<div class="card table-card mb-4">

    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">

        <div>
            <strong>
                <i class="fas fa-calendar-alt me-2"></i>
                Course Calendar
            </strong>
            <small class="text-muted ms-2">
                <?php echo htmlspecialchars($fyCalendarScopeLabel); ?> · 12-month view · click a course bar for details
            </small>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <span class="badge bg-primary">
                <?php echo number_format($courseCalendarSchedule['total_batches'] ?? 0); ?> Batches
            </span>
            <span class="badge bg-success">
                <?php echo number_format($courseCalendarSchedule['total_footfall'] ?? 0); ?> Footfall
            </span>
        </div>

    </div>

    <div class="card-body">

        <div class="calendar-legend mb-3">
            <span class="calendar-legend-item"><span class="calendar-legend-dot" style="background:#2563eb"></span> Long Term</span>
            <span class="calendar-legend-item"><span class="calendar-legend-dot" style="background:#16a34a"></span> Short Term</span>
            <span class="calendar-legend-item"><span class="calendar-legend-dot" style="background:#d97706"></span> Internship</span>
            <span class="calendar-legend-item"><span class="calendar-legend-dot" style="background:#7c3aed"></span> Workshop</span>
            <span class="calendar-legend-item"><span class="calendar-legend-dot" style="background:#6366f1"></span> Other</span>
        </div>

        <div id="courseBatchCalendar"></div>

        <?php if (($courseCalendarSchedule['total_batches'] ?? 0) === 0): ?>
        <p class="text-center text-muted mt-3 mb-0">
            No batches scheduled for <?php echo htmlspecialchars($fyCalendarScopeLabel); ?>.
        </p>
        <?php endif; ?>

    </div>

</div>

<!-- COURSE WISE SUMMARY TABLE -->

<div class="card table-card mb-4">

    <div class="card-header">

        <strong>

            Course Wise Summary

        </strong>

        <small class="text-muted ms-2">Registration counts for the selected period</small>

    </div>

    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-bordered table-hover mb-0">

                <thead class="table-light">

                <tr>

                    <th>Course</th>

                    <th>Code</th>

                    <th class="text-end">

                        Registered

                    </th>

                    <th class="text-end">

                        Admission

                    </th>

                    <th class="text-end">

                        Pending

                    </th>

                    <th class="text-end">

                        Batches

                    </th>

                </tr>

                </thead>

                <tbody>

                <?php if(empty($courseStats)): ?>

                <tr>

                    <td colspan="6" class="text-center">

                        No Records Found

                    </td>

                </tr>

                <?php else: ?>

                <?php foreach($courseStats as $course): ?>

                <tr>

                    <td>

                        <?php echo htmlspecialchars($course['course_name']);?>

                    </td>

                    <td>

                        <?php echo htmlspecialchars($course['course_code']);?>

                    </td>

                    <td class="text-end">

                        <?php echo number_format($course['applications']);?>

                    </td>

                    <td class="text-end text-success fw-bold">

                        <?php echo number_format($course['batch_enrolled']);?>

                    </td>

                    <td class="text-end text-danger">

                        <?php echo number_format($course['pending']);?>

                    </td>

                    <td class="text-end text-primary">

                        <?php echo number_format($course['batch_count']);?>

                    </td>

                </tr>

                <?php endforeach;?>

                <?php endif;?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<!-- CATEGORY QUARTERLY ADMISSIONS SUMMARY -->

<?php if (!empty($targetsFlashMessage)): ?>
<div class="alert alert-<?php echo htmlspecialchars($targetsFlashType); ?> alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($targetsFlashMessage); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="card table-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <strong>Category Quarterly Admissions Summary <?php echo htmlspecialchars($reportScopeTitleLabel); ?> FY - <?php echo htmlspecialchars($selectedFyFullLabel); ?></strong>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryTargetsModal">
            <i class="fas fa-bullseye me-1"></i> Set Targets
        </button>
    </div>
    <?php if ($categoryQuarterGrandTarget <= 0): ?>
    <div class="card-body border-bottom py-3">
        <div class="alert alert-warning mb-0 d-flex align-items-start gap-2">
            <i class="fas fa-info-circle mt-1"></i>
            <div>
                <strong>No annual targets set for FY - <?php echo htmlspecialchars($selectedFyFullLabel); ?>.</strong>
                Click <button type="button" class="btn btn-link btn-sm p-0 align-baseline fw-semibold" data-bs-toggle="modal" data-bs-target="#categoryTargetsModal">Set Targets</button>
                to enter admission goals for each category. Targets will appear in the <em>Target</em> and <em>Achievement</em> columns.
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0">
                <thead class="table-light">
                <tr>
                    <th>Category</th>
                    <th>Schemes</th>
                    <th>Start Date – End Date</th>
                    <th class="text-end">Target</th>
                    <th class="text-end">Q1</th>
                    <th class="text-end">Q2</th>
                    <th class="text-end">Q3</th>
                    <th class="text-end">Q4</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Achievement</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($categoryQuarterSummary as $categoryRow): ?>
                    <?php
                    $achievement = $categoryRow['achievement_pct'] ?? null;
                    $achievementClass = '';
                    if ($achievement !== null) {
                        if ($achievement >= 100) {
                            $achievementClass = 'text-success';
                        } elseif ($achievement >= 75) {
                            $achievementClass = 'text-warning';
                        } else {
                            $achievementClass = 'text-danger';
                        }
                    }
                    $categoryKey = (string) ($categoryRow['key'] ?? '');
                    $categoryCourseRows = $categoryCourseQuarterSummary[$categoryKey] ?? [];
                    ?>
                    <tr class="table-light">
                        <td class="fw-semibold"><?php echo htmlspecialchars($categoryRow['label']); ?></td>
                        <td class="text-muted">—</td>
                        <td class="text-muted">—</td>
                        <td class="text-end">
                            <?php if (($categoryRow['target'] ?? 0) > 0): ?>
                                <?php echo number_format($categoryRow['target']); ?>
                            <?php else: ?>
                                <button type="button" class="btn btn-link btn-sm p-0 text-muted" data-bs-toggle="modal" data-bs-target="#categoryTargetsModal" title="Set target for this category">Not set</button>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-semibold"><?php echo number_format($categoryRow['Q1']); ?></td>
                        <td class="text-end fw-semibold"><?php echo number_format($categoryRow['Q2']); ?></td>
                        <td class="text-end fw-semibold"><?php echo number_format($categoryRow['Q3']); ?></td>
                        <td class="text-end fw-semibold"><?php echo number_format($categoryRow['Q4']); ?></td>
                        <td class="text-end fw-bold"><?php echo number_format($categoryRow['total']); ?></td>
                        <td class="text-end fw-semibold <?php echo $achievementClass; ?>">
                            <?php echo $achievement !== null ? number_format($achievement, 1) . '%' : '—'; ?>
                        </td>
                    </tr>
                    <?php foreach ($categoryCourseRows as $courseRow): ?>
                    <tr>
                        <td class="ps-4">
                            <span class="text-muted me-1">↳</span>
                            <?php echo htmlspecialchars($courseRow['course_name']); ?>
                            <?php if (!empty($courseRow['course_code'])): ?>
                                <small class="text-muted">(<?php echo htmlspecialchars($courseRow['course_code']); ?>)</small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($courseRow['scheme_names'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($courseRow['batch_period_label'] ?? '—'); ?></td>
                        <td class="text-end text-muted">—</td>
                        <td class="text-end"><?php echo number_format($courseRow['Q1']); ?></td>
                        <td class="text-end"><?php echo number_format($courseRow['Q2']); ?></td>
                        <td class="text-end"><?php echo number_format($courseRow['Q3']); ?></td>
                        <td class="text-end"><?php echo number_format($courseRow['Q4']); ?></td>
                        <td class="text-end fw-semibold"><?php echo number_format($courseRow['total']); ?></td>
                        <td class="text-end text-muted">—</td>
                    </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                <tr>
                    <th>Grand Total</th>
                    <th></th>
                    <th></th>
                    <th class="text-end fw-bold">
                        <?php if ($categoryQuarterGrandTarget > 0): ?>
                            <?php echo number_format($categoryQuarterGrandTarget); ?>
                        <?php else: ?>
                            <span class="text-muted fw-normal">Not set</span>
                        <?php endif; ?>
                    </th>
                    <th class="text-end"><?php echo number_format(array_sum(array_column($categoryQuarterSummary, 'Q1'))); ?></th>
                    <th class="text-end"><?php echo number_format(array_sum(array_column($categoryQuarterSummary, 'Q2'))); ?></th>
                    <th class="text-end"><?php echo number_format(array_sum(array_column($categoryQuarterSummary, 'Q3'))); ?></th>
                    <th class="text-end"><?php echo number_format(array_sum(array_column($categoryQuarterSummary, 'Q4'))); ?></th>
                    <th class="text-end fw-bold"><?php echo number_format($categoryQuarterGrandTotal); ?></th>
                    <th class="text-end fw-bold">
                        <?php echo $categoryQuarterGrandAchievement !== null ? number_format($categoryQuarterGrandAchievement, 1) . '%' : '—'; ?>
                    </th>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<div class="card table-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <strong>Social Category Quarterly Admissions Summary <?php echo htmlspecialchars($reportScopeTitleLabel); ?> FY - <?php echo htmlspecialchars($selectedFyFullLabel); ?></strong>
            <span class="badge bg-secondary ms-2">General / OBC / SC / ST / EWS / PWD</span>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#socialCategoryTargetsModal">
            <i class="fas fa-bullseye me-1"></i> Set Targets
        </button>
    </div>
    <?php if ($socialCategoryQuarterGrandTarget <= 0): ?>
    <div class="card-body border-bottom py-3">
        <div class="alert alert-warning mb-0 d-flex align-items-start gap-2">
            <i class="fas fa-info-circle mt-1"></i>
            <div>
                <strong>No social category targets set for FY - <?php echo htmlspecialchars($selectedFyFullLabel); ?>.</strong>
                Click <button type="button" class="btn btn-link btn-sm p-0 align-baseline fw-semibold" data-bs-toggle="modal" data-bs-target="#socialCategoryTargetsModal">Set Targets</button>
                for General, OBC, SC, ST, EWS, and PWD admissions.
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0">
                <thead class="table-light">
                <tr>
                    <th>Social Category</th>
                    <th>Schemes</th>
                    <th>Start Date – End Date</th>
                    <th class="text-end">Target</th>
                    <th class="text-end">Q1</th>
                    <th class="text-end">Q2</th>
                    <th class="text-end">Q3</th>
                    <th class="text-end">Q4</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Achievement</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($socialCategoryQuarterSummary as $socialRow): ?>
                    <?php
                    $socialAchievement = $socialRow['achievement_pct'] ?? null;
                    $socialAchievementClass = '';
                    if ($socialAchievement !== null) {
                        if ($socialAchievement >= 100) {
                            $socialAchievementClass = 'text-success';
                        } elseif ($socialAchievement >= 75) {
                            $socialAchievementClass = 'text-warning';
                        } else {
                            $socialAchievementClass = 'text-danger';
                        }
                    }
                    $socialKey = (string) ($socialRow['key'] ?? '');
                    $socialCourseRows = $socialCategoryCourseQuarterSummary[$socialKey] ?? [];
                    ?>
                    <tr class="table-light">
                        <td class="fw-semibold"><?php echo htmlspecialchars($socialRow['label']); ?></td>
                        <td class="text-muted">—</td>
                        <td class="text-muted">—</td>
                        <td class="text-end">
                            <?php if (($socialRow['target'] ?? 0) > 0): ?>
                                <?php echo number_format($socialRow['target']); ?>
                            <?php else: ?>
                                <button type="button" class="btn btn-link btn-sm p-0 text-muted" data-bs-toggle="modal" data-bs-target="#socialCategoryTargetsModal" title="Set target">Not set</button>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-semibold"><?php echo number_format($socialRow['Q1']); ?></td>
                        <td class="text-end fw-semibold"><?php echo number_format($socialRow['Q2']); ?></td>
                        <td class="text-end fw-semibold"><?php echo number_format($socialRow['Q3']); ?></td>
                        <td class="text-end fw-semibold"><?php echo number_format($socialRow['Q4']); ?></td>
                        <td class="text-end fw-bold"><?php echo number_format($socialRow['total']); ?></td>
                        <td class="text-end fw-semibold <?php echo $socialAchievementClass; ?>">
                            <?php echo $socialAchievement !== null ? number_format($socialAchievement, 1) . '%' : '—'; ?>
                        </td>
                    </tr>
                    <?php foreach ($socialCourseRows as $courseRow): ?>
                    <tr>
                        <td class="ps-4">
                            <span class="text-muted me-1">↳</span>
                            <?php echo htmlspecialchars($courseRow['course_name']); ?>
                            <?php if (!empty($courseRow['course_code'])): ?>
                                <small class="text-muted">(<?php echo htmlspecialchars($courseRow['course_code']); ?>)</small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($courseRow['scheme_names'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($courseRow['batch_period_label'] ?? '—'); ?></td>
                        <td class="text-end text-muted">—</td>
                        <td class="text-end"><?php echo number_format($courseRow['Q1']); ?></td>
                        <td class="text-end"><?php echo number_format($courseRow['Q2']); ?></td>
                        <td class="text-end"><?php echo number_format($courseRow['Q3']); ?></td>
                        <td class="text-end"><?php echo number_format($courseRow['Q4']); ?></td>
                        <td class="text-end fw-semibold"><?php echo number_format($courseRow['total']); ?></td>
                        <td class="text-end text-muted">—</td>
                    </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                <tr>
                    <th>Grand Total</th>
                    <th></th>
                    <th></th>
                    <th class="text-end fw-bold">
                        <?php if ($socialCategoryQuarterGrandTarget > 0): ?>
                            <?php echo number_format($socialCategoryQuarterGrandTarget); ?>
                        <?php else: ?>
                            <span class="text-muted fw-normal">Not set</span>
                        <?php endif; ?>
                    </th>
                    <th class="text-end"><?php echo number_format(array_sum(array_column($socialCategoryQuarterSummary, 'Q1'))); ?></th>
                    <th class="text-end"><?php echo number_format(array_sum(array_column($socialCategoryQuarterSummary, 'Q2'))); ?></th>
                    <th class="text-end"><?php echo number_format(array_sum(array_column($socialCategoryQuarterSummary, 'Q3'))); ?></th>
                    <th class="text-end"><?php echo number_format(array_sum(array_column($socialCategoryQuarterSummary, 'Q4'))); ?></th>
                    <th class="text-end fw-bold"><?php echo number_format($socialCategoryQuarterGrandTotal); ?></th>
                    <th class="text-end fw-bold">
                        <?php echo $socialCategoryQuarterGrandAchievement !== null ? number_format($socialCategoryQuarterGrandAchievement, 1) . '%' : '—'; ?>
                    </th>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php if (!empty($internshipCourseSummary)): ?>
<div class="card table-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <strong>Internship / Bootcamp Course Admissions</strong>
            <div class="text-muted">Internship course-level admissions details for FY <?php echo htmlspecialchars($selectedYear); ?></div>
        </div>
        <span class="badge bg-primary"><?php echo number_format(array_sum(array_column($internshipCourseSummary, 'total'))); ?> Admissions</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0">
                <thead class="table-light">
                <tr>
                    <th>Course</th>
                    <th>Code</th>
                    <th>Centre</th>
                    <th class="text-end">Q1</th>
                    <th class="text-end">Q2</th>
                    <th class="text-end">Q3</th>
                    <th class="text-end">Q4</th>
                    <th class="text-end">Total</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($internshipCourseSummary as $courseRow): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($courseRow['course_name']); ?></td>
                        <td><?php echo htmlspecialchars($courseRow['course_code']); ?></td>
                        <td><?php echo htmlspecialchars($courseRow['centre_name']); ?></td>
                        <td class="text-end"><?php echo number_format($courseRow['Q1']); ?></td>
                        <td class="text-end"><?php echo number_format($courseRow['Q2']); ?></td>
                        <td class="text-end"><?php echo number_format($courseRow['Q3']); ?></td>
                        <td class="text-end"><?php echo number_format($courseRow['Q4']); ?></td>
                        <td class="text-end fw-bold"><?php echo number_format($courseRow['total']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- CENTRE WISE TABLE -->

<div class="card table-card mb-4">

    <div class="card-header">

        <strong>

            Centre Wise Report

        </strong>

    </div>

    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-bordered table-hover mb-0">

                <thead class="table-light">

                <tr>

                    <th>Centre</th>

                    <th class="text-end">

                        Courses

                    </th>

                    <th class="text-end">

                        Registered

                    </th>

                    <th class="text-end">

                        Admissions

                    </th>

                    <th class="text-end">

                        Batches

                    </th>

                </tr>

                </thead>

                <tbody>

                <?php foreach($centreStats as $centre): ?>

                <tr>

                    <td>

                        <?php echo htmlspecialchars($centre['centre_name']);?>

                    </td>

                    <td class="text-end">

                        <?php echo number_format($centre['course_count']);?>

                    </td>

                    <td class="text-end">

                        <?php echo number_format($centre['applications']);?>

                    </td>

                    <td class="text-end text-success">

                        <?php echo number_format($centre['batch_enrolled']);?>

                    </td>

                    <td class="text-end text-warning">

                        <?php echo number_format($centre['batch_count']);?>

                    </td>

                </tr>

                <?php endforeach;?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<!-- COURSE FY TIMELINE PROGRESS -->

<div class="card table-card mb-4">

    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">

        <div>
            <strong>
                <i class="fas fa-chart-line me-2"></i>
                Course-wise FY Timeline Progress
            </strong>
            <small class="text-muted ms-2">
                <?php echo htmlspecialchars($fyCalendarScopeLabel); ?> · graph + daily timeline · hover for figures
            </small>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <span class="badge bg-dark"><?php echo number_format($courseFyTimeline['grand_totals']['courses'] ?? 0); ?> Courses</span>
            <span class="badge bg-primary"><?php echo number_format($courseFyTimeline['grand_totals']['registered'] ?? 0); ?> Total Reg.</span>
            <span class="badge bg-success"><?php echo number_format($courseFyTimeline['grand_totals']['footfall'] ?? 0); ?> Total Footfall</span>
            <span class="badge bg-warning text-dark"><?php echo number_format($courseFyTimeline['grand_totals']['batches'] ?? 0); ?> Batches</span>
        </div>

    </div>

    <div class="card-body">

        <div id="courseFyGanttWrap">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <strong class="text-primary"><i class="fas fa-chart-bar me-1"></i> Course Duration Graph</strong>
                <span class="text-muted small">Y-axis: course · X-axis: all <?php echo (int) ($courseFyTimeline['day_count'] ?? 365); ?> days (d.m.yy) · scroll horizontally · hover for figures</span>
            </div>
            <div class="course-fy-gantt-scroll">
                <div id="courseFyGanttInner">
                    <canvas id="courseFyGanttChart"></canvas>
                </div>
            </div>
        </div>

        <details class="mb-0">
            <summary class="fw-semibold text-muted mb-3" style="cursor:pointer;">Detailed daily timeline table (click to expand)</summary>
            <div id="courseFyTimeline" class="course-fy-timeline-wrap">
                <p class="text-center text-muted py-4 mb-0">Loading timeline…</p>
            </div>
        </details>

    </div>

</div>

<!-- BATCH DETAILS -->

<div class="card table-card mb-4">

    <div class="card-header d-flex justify-content-between align-items-center">

        <strong>

            Active Batch Details

        </strong>

        <span class="badge bg-primary">

            <?php echo count($batchDetails); ?> Batches

        </span>

    </div>

    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-hover table-bordered mb-0">

                <thead class="table-light">

                <tr>

                    <th>Batch</th>

                    <th>Course</th>

                    <th>Centre</th>

                    <th>Start Date</th>

                    <th>End Date</th>

                    <th>Faculty</th>

                    <th class="text-end">

                        Students

                    </th>

                    <th class="text-end">

                        Seats

                    </th>

                    <th class="text-end">

                        Fill %

                    </th>

                    <th>Status</th>

                    <th>Scanned Order</th>

                </tr>

                </thead>

                <tbody>

                <?php if(empty($batchDetails)): ?>

                <tr>

                    <td colspan="11" class="text-center">

                        No Batch Found

                    </td>

                </tr>

                <?php else: ?>

                <?php foreach($batchDetails as $batch): ?>

                <tr>

                    <td>

                        <strong>

                            <?php echo htmlspecialchars($batch['batch_name']);?>

                        </strong>

                        <br>

                        <small>

                            <?php echo htmlspecialchars($batch['batch_code']);?>

                        </small>

                    </td>

                    <td>

                        <?php echo htmlspecialchars($batch['course_name']);?>

                    </td>

                    <td>

                        <?php echo htmlspecialchars($batch['centre_name']);?>

                    </td>

                    <td>

                        <?php echo htmlspecialchars(report_monitor_format_display_date($batch['start_date'] ?? null)); ?>

                    </td>

                    <td>

                        <?php echo htmlspecialchars(report_monitor_format_display_date($batch['end_date'] ?? null)); ?>

                    </td>

                    <td>

                        <?php echo htmlspecialchars($batch['faculty_names']);?>

                    </td>

                    <td class="text-end">

                        <?php echo number_format($batch['enrolled']);?>

                    </td>

                    <td class="text-end">

                        <?php echo number_format($batch['seats_total']);?>

                    </td>

                    <td class="text-end">

                        <span class="badge bg-success">

                            <?php echo $batch['fill_rate'];?>%

                        </span>

                    </td>

                    <td>

                        <span class="badge bg-primary">

                            <?php echo htmlspecialchars($batch['status']);?>

                        </span>

                    </td>

                    <td>

                        <?php if (!empty($batch['scanned_admission_order'])): ?>

                            <form method="post" action="<?php echo APP_URL; ?>/batch_module/admin/upload_scanned_admission_order.php" style="display:inline;">

                                <input type="hidden" name="action" value="download">

                                <input type="hidden" name="batch_id" value="<?php echo (int) $batch['id']; ?>">

                                <button type="submit" class="btn btn-sm btn-outline-primary">

                                    <i class="fas fa-download"></i> Download

                                </button>

                            </form>

                        <?php else: ?>

                            <span class="text-muted">—</span>

                        <?php endif; ?>

                    </td>

                </tr>

                <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<!-- FACULTY TRAINING SUMMARY -->

<div class="card table-card mb-4">

    <div class="card-header d-flex justify-content-between align-items-center">

        <strong>

            <i class="fas fa-chalkboard-teacher me-2"></i>
            Faculty Training Summary

        </strong>

        <span class="badge bg-primary">

            <?php echo count($facultyTrainingStats); ?> Faculty

        </span>

    </div>

    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-hover table-bordered mb-0">

                <thead class="table-light">

                <tr>

                    <th>#</th>

                    <th>Faculty Name</th>

                    <th>Designation</th>

                    <th>Department</th>

                    <th class="text-end">Batches</th>

                    <th class="text-end">Courses</th>

                    <th class="text-end">Students Trained</th>

                </tr>

                </thead>

                <tbody>

                <?php if (empty($facultyTrainingStats)): ?>

                <tr>

                    <td colspan="7" class="text-center text-muted py-4">

                        No faculty training data for <?php echo htmlspecialchars($monthScopeLabel); ?>.

                    </td>

                </tr>

                <?php else: ?>

                <?php foreach ($facultyTrainingStats as $index => $faculty): ?>

                <tr>

                    <td><?php echo $index + 1; ?></td>

                    <td><strong><?php echo htmlspecialchars($faculty['name']); ?></strong></td>

                    <td><?php echo htmlspecialchars($faculty['designation'] ?: '—'); ?></td>

                    <td><?php echo htmlspecialchars($faculty['department'] ?: '—'); ?></td>

                    <td class="text-end"><?php echo number_format($faculty['batch_count']); ?></td>

                    <td class="text-end"><?php echo number_format($faculty['course_count']); ?></td>

                    <td class="text-end text-success fw-bold"><?php echo number_format($faculty['students_trained']); ?></td>

                </tr>

                <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</div>

</div>

</main>

</div>

<div class="modal fade" id="categoryTargetsModal" tabindex="-1" aria-labelledby="categoryTargetsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <form id="categoryTargetsForm" method="post" action="<?php echo htmlspecialchars(APP_URL); ?>/admin/ajax_report_category_targets.php">
                <input type="hidden" name="action" value="save_category_targets">
                <input type="hidden" name="financial_year_start" value="<?php echo (int) $selectedYear; ?>">
                <input type="hidden" name="centre_id" value="<?php echo (int) $centreId; ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="categoryTargetsModalLabel">
                        <i class="fas fa-bullseye me-2 text-primary"></i>
                        Set Category Admission Targets
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="categoryTargetsError" class="alert alert-danger d-none" role="alert"></div>
                    <div class="alert alert-info py-2 small mb-3">
                        Enter the <strong>annual admission goal</strong> for each category for
                        <strong>FY <?php echo htmlspecialchars($selectedYear); ?></strong>
                        <?php if ($centreId > 0): ?>
                            at <strong><?php echo htmlspecialchars($selectedCentreName); ?></strong>.
                        <?php else: ?>
                            across <strong>all centres</strong>.
                        <?php endif; ?>
                        Leave blank or 0 for categories with no target.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead class="table-light">
                            <tr>
                                <th>Category</th>
                                <th class="text-end" style="width: 200px;">Annual Target</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($categoryQuarterSummary as $categoryRow): ?>
                                <?php $savedTarget = (int) ($categoryAdmissionTargets[$categoryRow['key']] ?? 0); ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($categoryRow['label']); ?></td>
                                    <td>
                                        <input
                                            type="number"
                                            class="form-control text-end"
                                            name="targets[<?php echo htmlspecialchars($categoryRow['key']); ?>]"
                                            min="0"
                                            step="1"
                                            placeholder="e.g. 500"
                                            value="<?php echo $savedTarget > 0 ? $savedTarget : ''; ?>"
                                        >
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Save Targets
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="socialCategoryTargetsModal" tabindex="-1" aria-labelledby="socialCategoryTargetsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <form id="socialCategoryTargetsForm" method="post" action="<?php echo htmlspecialchars(APP_URL); ?>/admin/ajax_report_category_targets.php">
                <input type="hidden" name="action" value="save_category_targets">
                <input type="hidden" name="target_scope" value="nielit_social">
                <input type="hidden" name="financial_year_start" value="<?php echo (int) $selectedYear; ?>">
                <input type="hidden" name="centre_id" value="<?php echo (int) $centreId; ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="socialCategoryTargetsModalLabel">
                        <i class="fas fa-bullseye me-2 text-primary"></i>
                        Set Social Category Admission Targets
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="socialCategoryTargetsError" class="alert alert-danger d-none" role="alert"></div>
                    <div class="alert alert-info py-2 small mb-3">
                        Enter annual targets for <strong>General, OBC, SC, ST, EWS, and PWD</strong> admissions in
                        <strong>FY <?php echo htmlspecialchars($selectedYear); ?></strong>.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead class="table-light">
                            <tr>
                                <th>Social Category</th>
                                <th class="text-end" style="width: 200px;">Annual Target</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($socialCategoryQuarterSummary as $socialRow): ?>
                                <?php $socialSavedTarget = (int) ($socialCategoryAdmissionTargets[$socialRow['key']] ?? 0); ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($socialRow['label']); ?></td>
                                    <td>
                                        <input type="number" class="form-control text-end" name="targets[<?php echo htmlspecialchars($socialRow['key']); ?>]" min="0" step="1" placeholder="e.g. 100" value="<?php echo $socialSavedTarget > 0 ? $socialSavedTarget : ''; ?>">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Targets</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="courseCalendarEventModal" tabindex="-1" aria-labelledby="courseCalendarEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="courseCalendarEventModalLabel">Course Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="row calendar-event-detail mb-0" id="courseCalendarEventBody"></dl>
            </div>
        </div>
    </div>
</div>

<!-- PART 2 COMPLETED -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- ===========================
PART 3 - 1
Chart.js Initialization
=========================== -->

<script>

const reportPayload = <?php echo json_encode(
    $reportPayload,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES
); ?>;

document.addEventListener(

'DOMContentLoaded',

function(){

/*==================================================
MONTH WISE GRAPH
==================================================*/

const batchCanvas =
document.getElementById(
'batchMonthlyChart'
);

if(batchCanvas){

new Chart(batchCanvas,{

type:'line',

data:{

labels:
reportPayload.batchMonthly.labels,

datasets:[

{

label:'Registered',

data:
reportPayload.batchMonthly.applications,

borderColor:'#2563eb',

backgroundColor:'rgba(37,99,235,.12)',

fill:true,

borderWidth:3,

tension:.35

},

{

label:'Admissions',

data:
reportPayload.batchMonthly.batch_enrollments,

borderColor:'#16a34a',

backgroundColor:'rgba(22,163,74,.12)',

fill:true,

borderWidth:3,

tension:.35

},

{

label:'Batches',

data:
reportPayload.batchMonthly.batches_created,

borderColor:'#f59e0b',

backgroundColor:'rgba(245,158,11,.12)',

fill:true,

borderWidth:3,

tension:.35

}

]

},

options:{

responsive:true,

maintainAspectRatio:false,

plugins:{

legend:{
position:'bottom'
}

},

scales:{

y:{
beginAtZero:true
}

}

}

});

}

/*==================================================
CENTRE GRAPH
==================================================*/

const centreCanvas =
document.getElementById(
'centreChart'
);

if(centreCanvas){

new Chart(

centreCanvas,

{

type:'bar',

data:{

labels:
reportPayload.centreStats.map(
x=>x.centre_name
),

datasets:[

{

label:'Registered',

data:
reportPayload.centreStats.map(
x=>x.applications
),

backgroundColor:'#2563eb'

},

{

label:'Admissions',

data:
reportPayload.centreStats.map(
x=>x.batch_enrolled
),

backgroundColor:'#16a34a'

}

]

},

options:{

responsive:true,

plugins:{

legend:{

position:'bottom'

}

},

scales:{

y:{

beginAtZero:true

}

}

}

}

);

}
/*==================================================
COURSE WISE GRAPH
==================================================*/

const courseCanvas =
document.getElementById(
'courseChart'
);

if(courseCanvas){

if(!reportPayload.courseStats.length){

courseCanvas.parentElement.innerHTML =
'<p class="text-center text-muted py-5 mb-0">No course activity for the selected quarter.</p>';

}else{

new Chart(

courseCanvas,

{

type:'bar',

data:{

labels:
reportPayload.courseStats.map(
x=>x.course_code
    ? x.course_name + ' (' + x.course_code + ')'
    : x.course_name
),

datasets:[

{

label:'Registered',

data:
reportPayload.courseStats.map(
x=>x.applications
),

backgroundColor:'#2563eb'

},

{

label:'Admissions',

data:
reportPayload.courseStats.map(
x=>x.batch_enrolled
),

backgroundColor:'#16a34a'

},

{

label:'Pending',

data:
reportPayload.courseStats.map(
x=>x.pending
),

backgroundColor:'#dc2626'

},

{

label:'Batches',

data:
reportPayload.courseStats.map(
x=>x.batch_count
),

backgroundColor:'#f59e0b'

}

]

},

options:{

responsive:true,

maintainAspectRatio:false,

plugins:{

legend:{

position:'bottom'

},

tooltip:{

mode:'index',

intersect:false

}

},

interaction:{

mode:'index',

intersect:false

},

scales:{

x:{

ticks:{

autoSkip:false,

maxRotation:45,

minRotation:20

}

},

y:{

beginAtZero:true,

ticks:{

precision:0

}

}

}

}

}

);

}

}

/*==================================================
COURSE FY TIMELINE
==================================================*/

const courseFyTimelineData = reportPayload.courseFyTimeline || {};
renderCourseFyGanttChart(courseFyTimelineData);
renderCourseFyTimeline(courseFyTimelineData);

function formatDayTick(value) {
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) {
        return '';
    }
    return d.getDate() + '.' + (d.getMonth() + 1) + '.' + String(d.getFullYear()).slice(-2);
}

function renderCourseFyGanttChart(timeline) {
    const wrap = document.getElementById('courseFyGanttWrap');
    const inner = document.getElementById('courseFyGanttInner');
    const canvas = document.getElementById('courseFyGanttChart');
    if (!wrap || !canvas || typeof Chart === 'undefined') {
        return;
    }

    const courses = timeline.courses || [];
    const dayCount = timeline.day_count || 0;
    const batchPoints = [];
    const barColors = [
        'rgba(37,99,235,0.85)', 'rgba(22,163,74,0.85)', 'rgba(217,119,6,0.85)',
        'rgba(124,58,237,0.85)', 'rgba(8,145,178,0.85)', 'rgba(219,39,119,0.85)',
        'rgba(101,163,13,0.85)', 'rgba(234,88,12,0.85)', 'rgba(79,70,229,0.85)'
    ];

    courses.forEach(function (course) {
        const batches = course.batches || [];
        if (!batches.length) {
            return;
        }
        batches.forEach(function (batch) {
            const yLabel = batches.length > 1 && batch.batch_code
                ? (course.course_name + ' · ' + batch.batch_code)
                : course.course_name;
            batchPoints.push({
                x: [batch.start_date, batch.end_date + 'T23:59:59'],
                y: yLabel,
                batch_name: batch.batch_name,
                batch_code: batch.batch_code,
                start_label: batch.start_label,
                end_label: batch.end_label,
                footfall: batch.footfall,
                total_registered: course.total_registered,
                total_admissions: course.total_admissions,
                total_footfall: course.total_footfall
            });
        });
    });

    if (!batchPoints.length) {
        wrap.innerHTML = '<p class="text-center text-muted py-5 mb-0">No batch periods to plot on the graph for this financial year.</p>';
        return;
    }

    const pixelsPerDay = 5;
    const chartWidth = Math.max(wrap.clientWidth - 24, (dayCount || 365) * pixelsPerDay);
    if (inner) {
        inner.style.width = chartWidth + 'px';
    }

    const chartHeight = Math.max(420, Math.min(900, batchPoints.length * 32 + 120));
    wrap.style.height = chartHeight + 'px';

    const fyEndMax = timeline.fy_end ? (timeline.fy_end + 'T23:59:59') : undefined;

    new Chart(canvas, {
        type: 'bar',
        data: {
            datasets: [{
                label: 'Batch period',
                data: batchPoints.map(function (point) {
                    return { x: point.x, y: point.y };
                }),
                backgroundColor: batchPoints.map(function (_, index) {
                    return barColors[index % barColors.length];
                }),
                borderColor: batchPoints.map(function (_, index) {
                    return barColors[index % barColors.length].replace('0.85', '1');
                }),
                borderWidth: 1,
                borderRadius: 6,
                barThickness: 16,
                batchMeta: batchPoints
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: { bottom: 8, right: 8 }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: function (items) {
                            if (!items.length) {
                                return '';
                            }
                            const meta = items[0].dataset.batchMeta[items[0].dataIndex];
                            return meta ? meta.y : '';
                        },
                        label: function (ctx) {
                            const meta = ctx.dataset.batchMeta[ctx.dataIndex];
                            if (!meta) {
                                return '';
                            }
                            return [
                                'Batch: ' + meta.batch_name,
                                'Period: ' + meta.start_label + ' → ' + meta.end_label,
                                'Footfall: ' + Number(meta.footfall || 0).toLocaleString(),
                                'Course Reg: ' + Number(meta.total_registered || 0).toLocaleString(),
                                'Course Adm: ' + Number(meta.total_admissions || 0).toLocaleString()
                            ];
                        }
                    }
                }
            },
            scales: {
                x: {
                    type: 'time',
                    min: timeline.fy_start,
                    max: fyEndMax,
                    time: {
                        unit: 'day',
                        round: 'day',
                        displayFormats: {
                            day: 'd.M.yy'
                        },
                        tooltipFormat: 'd.M.yy'
                    },
                    title: {
                        display: true,
                        text: 'Date — all ' + (dayCount || 365) + ' days (d.m.yy) · scroll →'
                    },
                    grid: {
                        color: function (context) {
                            if (!context.tick || !context.tick.value) {
                                return 'rgba(148,163,184,0.15)';
                            }
                            const d = new Date(context.tick.value);
                            return d.getDate() === 1
                                ? 'rgba(37,99,235,0.22)'
                                : 'rgba(148,163,184,0.12)';
                        },
                        lineWidth: function (context) {
                            if (!context.tick || !context.tick.value) {
                                return 1;
                            }
                            const d = new Date(context.tick.value);
                            return d.getDate() === 1 ? 1.5 : 1;
                        }
                    },
                    ticks: {
                        source: 'auto',
                        autoSkip: false,
                        maxTicksLimit: (dayCount || 366) + 5,
                        maxRotation: 90,
                        minRotation: 90,
                        font: { size: 8 },
                        padding: 2,
                        callback: function (value) {
                            return formatDayTick(value);
                        }
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Course'
                    },
                    grid: {
                        display: false
                    },
                    ticks: {
                        autoSkip: false,
                        font: { size: 11 }
                    }
                }
            }
        }
    });
}

function renderCourseFyTimeline(timeline) {
    const container = document.getElementById('courseFyTimeline');
    if (!container) {
        return;
    }

    const courses = timeline.courses || [];
    const dayCount = timeline.day_count || 0;
    const dayLabels = timeline.day_labels || [];
    const monthMarkers = timeline.month_markers || [];

    if (!courses.length || !dayCount) {
        container.innerHTML = '<p class="text-center text-muted py-5 mb-0">No course timeline data for ' +
            (timeline.fy_label ? timeline.fy_label : 'the selected financial year') + '.</p>';
        return;
    }

    const dayWidth = Math.max(3, Math.min(6, Math.floor(1100 / dayCount)));
    const trackWidth = dayCount * dayWidth;

    let monthsHtml = '';
    monthMarkers.forEach(function (marker) {
        const width = marker.day_count * dayWidth;
        monthsHtml += '<div class="course-fy-axis-month" style="width:' + width + 'px">' +
            marker.label + '</div>';
    });

    let daysHtml = '';
    for (let i = 0; i < dayCount; i++) {
        const label = dayLabels[i] || '';
        const isMonthStart = /^1\./.test(label);
        daysHtml += '<div class="course-fy-axis-day' + (isMonthStart ? ' is-month-start' : '') + '" style="width:' + dayWidth + 'px" title="' + label + '">' +
            (isMonthStart ? label.split('.')[0] : '') + '</div>';
    }

    let rowsHtml = '';
    courses.forEach(function (course, courseIndex) {
        let barsHtml = '';
        (course.batches || []).forEach(function (batch) {
            const left = (batch.start_index / dayCount) * 100;
            const span = (batch.end_index - batch.start_index + 1);
            const width = (span / dayCount) * 100;
            const tip = (batch.batch_name || 'Batch') +
                ' | ' + batch.start_label + ' to ' + batch.end_label +
                ' | Footfall: ' + Number(batch.footfall || 0).toLocaleString();
            barsHtml += '<div class="course-fy-bar" style="left:' + left + '%;width:' + width + '%" title="' + tip + '"></div>';
        });

        rowsHtml += '<div class="course-fy-row">' +
            '<div>' +
                '<div class="course-fy-course-name">' + escapeHtml(course.course_name || '') + '</div>' +
                (course.course_code ? '<div class="course-fy-course-code">' + escapeHtml(course.course_code) + '</div>' : '') +
                '<div class="course-fy-totals">' +
                    '<span class="badge bg-primary">Reg: ' + Number(course.total_registered || 0).toLocaleString() + '</span>' +
                    '<span class="badge bg-success">Adm: ' + Number(course.total_admissions || 0).toLocaleString() + '</span>' +
                    '<span class="badge bg-info text-dark">Footfall: ' + Number(course.total_footfall || 0).toLocaleString() + '</span>' +
                    '<span class="badge bg-secondary">Batches: ' + Number(course.total_batches || 0).toLocaleString() + '</span>' +
                '</div>' +
            '</div>' +
            '<div class="course-fy-track" style="width:' + trackWidth + 'px;--fy-days:' + dayCount + '">' + barsHtml + '</div>' +
            '<div class="course-fy-sparkline"><canvas id="courseFySpark' + courseIndex + '"></canvas></div>' +
        '</div>';
    });

    container.innerHTML =
        '<div class="course-fy-timeline" style="width:' + (240 + 12 + trackWidth + 12 + 150) + 'px">' +
            '<div class="course-fy-timeline-head">' +
                '<div><strong>Course</strong><div class="text-muted small">Totals per course</div></div>' +
                '<div style="width:' + trackWidth + 'px">' +
                    '<div class="course-fy-axis-months" style="width:' + trackWidth + 'px">' + monthsHtml + '</div>' +
                    '<div class="course-fy-axis-days" style="width:' + trackWidth + 'px">' + daysHtml + '</div>' +
                '</div>' +
                '<div class="text-muted small text-center">Daily trend<br><span class="badge bg-light text-dark border">hover</span></div>' +
            '</div>' +
            rowsHtml +
        '</div>';

    courses.forEach(function (course, courseIndex) {
        const canvas = document.getElementById('courseFySpark' + courseIndex);
        if (!canvas || typeof Chart === 'undefined') {
            return;
        }

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: timeline.day_labels || [],
                datasets: [
                    {
                        label: 'Registered',
                        data: course.daily_registered || [],
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37,99,235,0.12)',
                        borderWidth: 1.5,
                        pointRadius: 0,
                        tension: 0.25,
                        fill: true
                    },
                    {
                        label: 'Admissions',
                        data: course.daily_admissions || [],
                        borderColor: '#16a34a',
                        backgroundColor: 'rgba(22,163,74,0.08)',
                        borderWidth: 1.5,
                        pointRadius: 0,
                        tension: 0.25,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            title: function (items) {
                                return items.length ? ('Date: ' + items[0].label) : '';
                            }
                        }
                    }
                },
                scales: {
                    x: { display: false },
                    y: { display: false, beginAtZero: true }
                }
            }
        });
    });
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/*==================================================
COURSE BATCH CALENDAR
==================================================*/

const calendarEl = document.getElementById('courseBatchCalendar');
const calendarEvents = reportPayload.calendarEvents || [];
const calendarRange = reportPayload.calendarRange || {};

if (calendarEl && typeof FullCalendar !== 'undefined') {
    const calendarModalEl = document.getElementById('courseCalendarEventModal');
    const calendarModalBody = document.getElementById('courseCalendarEventBody');
    const calendarModalTitle = document.getElementById('courseCalendarEventModalLabel');

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'multiMonthYear',
        height: 'auto',
        firstDay: 1,
        multiMonthMaxColumns: 3,
        multiMonthMinWidth: 240,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'multiMonthYear,dayGridMonth,listMonth'
        },
        views: {
            multiMonthYear: {
                type: 'multiMonthYear',
                duration: { months: 12 }
            }
        },
        validRange: calendarRange.start && calendarRange.end ? {
            start: calendarRange.start,
            end: calendarRange.end
        } : undefined,
        initialDate: calendarRange.start || undefined,
        events: calendarEvents,
        displayEventTime: false,
        eventDisplay: 'block',
        dayMaxEvents: 3,
        moreLinkClick: 'popover',
        eventClick: function(info) {
            const props = info.event.extendedProps || {};
            if (calendarModalTitle) {
                calendarModalTitle.textContent = props.course_name || info.event.title;
            }
            if (calendarModalBody) {
                calendarModalBody.innerHTML = [
                    detailRow('Course', props.course_name, props.course_code),
                    detailRow('Batch', props.batch_name, props.batch_code),
                    detailRow('Category', props.course_category),
                    detailRow('Scheme', props.scheme_name),
                    detailRow('Centre', props.centre_name),
                    detailRow('Start Date', props.start_label),
                    detailRow('End Date', props.end_label),
                    detailRow('Footfall', props.footfall != null ? Number(props.footfall).toLocaleString() : '—'),
                    detailRow('Seats', props.seats_total != null ? Number(props.seats_total).toLocaleString() : '—'),
                    detailRow('Fill %', props.fill_rate != null ? props.fill_rate + '%' : '—'),
                    detailRow('Status', props.status)
                ].join('');
            }
            if (calendarModalEl && typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getOrCreateInstance(calendarModalEl).show();
            }
        }
    });

    calendar.render();
}

function detailRow(label, value, secondary) {
    const main = value ? String(value) : '—';
    const extra = secondary ? ' <small class="text-muted">(' + String(secondary) + ')</small>' : '';
    return '<dt class="col-sm-4">' + label + '</dt><dd class="col-sm-8">' + main + extra + '</dd>';
}

/*==================================================
AUTO RESIZE
==================================================*/

window.addEventListener(

'resize',

function(){

Chart.helpers.each(

Chart.instances,

function(instance){

instance.resize();

}

);

});

});

/*==================================================
CATEGORY TARGETS SAVE (AJAX)
==================================================*/

const categoryTargetsSaveUrl = <?php echo json_encode(APP_URL . '/admin/ajax_report_category_targets.php', JSON_UNESCAPED_SLASHES); ?>;

function bindCategoryTargetsForm(formId, errorBoxId, modalId) {
    const form = document.getElementById(formId);
    if (!form) {
        return;
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const submitBtn = form.querySelector('button[type="submit"]');
        const errorBox = document.getElementById(errorBoxId);
        const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';

        if (errorBox) {
            errorBox.classList.add('d-none');
            errorBox.textContent = '';
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';
        }

        try {
            const formData = new FormData(form);
            const response = await fetch(categoryTargetsSaveUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const rawText = await response.text();
            let data;
            try {
                data = JSON.parse(rawText);
            } catch (parseError) {
                throw new Error('Server returned an invalid response. Please refresh the page and try again.');
            }

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Could not save targets.');
            }

            const modalEl = document.getElementById(modalId);
            if (modalEl && typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getInstance(modalEl)?.hide();
            }

            window.location.reload();
        } catch (err) {
            const message = err && err.message ? err.message : 'Could not save targets.';
            if (errorBox) {
                errorBox.textContent = message;
                errorBox.classList.remove('d-none');
            } else {
                alert('Request failed: ' + message);
            }
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;
            }
        }
    });
}

bindCategoryTargetsForm('categoryTargetsForm', 'categoryTargetsError', 'categoryTargetsModal');
bindCategoryTargetsForm('socialCategoryTargetsForm', 'socialCategoryTargetsError', 'socialCategoryTargetsModal');

</script>

</body>

</html>