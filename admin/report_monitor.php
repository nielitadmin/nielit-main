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

        #courseFyGanttWrap{

            position:relative;

            margin-bottom:8px;

            padding:12px;

            border:1px solid #e2e8f0;

            border-radius:12px;

            background:#fafbff;

            max-width:100%;

            overflow:visible;

        }

        .course-fy-admission-chart{

            display:grid;

            grid-template-columns:88px minmax(0,1fr);

            gap:8px;

            align-items:stretch;

            max-width:100%;

        }

        .course-fy-y-rail{

            display:flex;

            flex-direction:column;

        }

        .course-fy-y-title{

            writing-mode:vertical-rl;

            transform:rotate(180deg);

            font-size:12px;

            font-weight:700;

            color:#1e3a8a;

            text-align:center;

            margin:0 auto 8px;

            letter-spacing:.02em;

        }

        .course-fy-y-ticks{

            flex:1;

            display:flex;

            flex-direction:column-reverse;

            justify-content:space-between;

            font-size:11px;

            font-weight:700;

            color:#334155;

            text-align:right;

            padding:8px 8px 48px 0;

            border-right:2px solid #94a3b8;

        }

        .course-fy-y-tick{

            display:flex;

            align-items:center;

            justify-content:flex-end;

            box-sizing:border-box;

            padding-right:4px;

            color:#1e3a8a;

            line-height:1;

            flex:0 0 auto;

        }

        .course-fy-gantt-scroll{

            display:block;

            width:100%;

            max-width:100%;

            overflow-x:scroll;

            overflow-y:auto;

            max-height:760px;

            padding-bottom:8px;

            -webkit-overflow-scrolling:touch;

            border:1px solid #cbd5e1;

            border-radius:10px;

            background:#fff;

        }

        .course-fy-gantt-scroll::-webkit-scrollbar{

            height:12px;

            width:10px;

        }

        .course-fy-gantt-scroll::-webkit-scrollbar-track{

            background:#e2e8f0;

            border-radius:999px;

        }

        .course-fy-gantt-scroll::-webkit-scrollbar-thumb{

            background:#64748b;

            border-radius:999px;

        }

        .course-fy-time-scroll{

            display:block;

            width:100%;

            max-width:100%;

            overflow-x:scroll;

            overflow-y:hidden;

            padding-bottom:8px;

            -webkit-overflow-scrolling:touch;

            border:1px solid #cbd5e1;

            border-radius:10px;

            background:#fff;

        }

        #courseFyGanttInner{

            position:relative;

            display:block;

            width:max-content;

            min-width:100%;

        }

        .course-fy-chart-box{

            position:relative;

            height:420px;

            min-height:420px;

            width:100%;

            background:
                linear-gradient(#f1f5f9 1px, transparent 1px) 0 0 / 100% 40px,
                linear-gradient(90deg, #f1f5f9 1px, transparent 1px) 0 0 / 24px 100%,
                #fff;

        }

        #courseFyTimeChart{

            display:block;

            width:100% !important;

            height:100% !important;

        }

        .course-fy-plot{

            position:relative;

            border-bottom:2px solid #94a3b8;

            background:#fff;

        }

        .course-fy-lane{

            position:relative;

            box-sizing:border-box;

            border-bottom:1px solid #f1f5f9;

            background:repeating-linear-gradient(
                90deg,
                #fff,
                #fff 27px,
                #f8fafc 27px,
                #f8fafc 28px
            );

        }

        .course-fy-lane-bar{

            position:absolute;

            top:10px;

            height:36px;

            display:flex;

            align-items:center;

            gap:8px;

            padding:0 10px;

            border-radius:999px;

            border:1px solid #3b82f6;

            background:linear-gradient(90deg,#93c5fd,#60a5fa);

            box-shadow:0 1px 4px rgba(37,99,235,.16);

            color:#0f172a;

            font-size:12px;

            white-space:nowrap;

            overflow:hidden;

            cursor:pointer;

            box-sizing:border-box;

            z-index:2;

        }

        .course-fy-lane-bar:hover{

            filter:brightness(1.04);

            box-shadow:0 3px 10px rgba(15,23,42,.18);

        }

        .course-fy-lane-bar .bar-date{

            flex:0 0 auto;

            font-size:10px;

            font-weight:800;

            background:rgba(255,255,255,.92);

            border:1px solid rgba(15,23,42,.12);

            border-radius:6px;

            padding:2px 6px;

            color:#0f172a;

        }

        .course-fy-lane-bar .bar-name{

            flex:1 1 auto;

            min-width:0;

            font-weight:700;

            text-align:center;

            overflow:hidden;

            text-overflow:ellipsis;

        }

        .course-fy-hover-panel{

            position:fixed;

            left:0;

            top:0;

            width:300px;

            max-width:calc(100vw - 24px);

            background:#fff;

            border:1px solid #94a3b8;

            border-radius:12px;

            box-shadow:0 12px 32px rgba(15,23,42,.22);

            padding:12px;

            z-index:10050;

            pointer-events:auto;

            opacity:0;

            visibility:hidden;

            transform:translateY(4px);

            transition:opacity .12s ease, transform .12s ease, visibility .12s ease;

        }

        .course-fy-hover-panel.is-visible{

            opacity:1;

            visibility:visible;

            transform:translateY(0);

        }

        .course-fy-hover-panel h6{

            margin:0 0 4px;

            font-size:13px;

            font-weight:700;

            color:#0f172a;

        }

        .course-fy-hover-panel .hover-meta{

            font-size:11px;

            color:#64748b;

            margin-bottom:8px;

        }

        .course-fy-hover-panel .hover-chart-box{

            width:100%;

            height:150px;

            position:relative;

        }

        .course-fy-hover-panel canvas{

            width:100% !important;

            height:150px !important;

        }

        .course-fy-month-axis{

            display:flex;

            flex-wrap:nowrap;

            border-top:1px solid #93c5fd;

            background:#eff6ff;

            position:sticky;

            bottom:36px;

            z-index:6;

        }

        .course-fy-month-tick{

            flex:0 0 auto;

            box-sizing:border-box;

            height:28px;

            border-right:2px solid #2563eb;

            font-size:11px;

            font-weight:800;

            color:#1e3a8a;

            text-align:center;

            line-height:28px;

            white-space:nowrap;

            overflow:hidden;

            text-overflow:ellipsis;

            padding:0 4px;

        }

        .course-fy-day-axis{

            display:flex;

            flex-wrap:nowrap;

            border-top:1px solid #e2e8f0;

            background:#f8fafc;

            position:sticky;

            bottom:0;

            z-index:5;

        }

        .course-fy-day-tick{

            flex:0 0 auto;

            box-sizing:border-box;

            height:36px;

            border-right:1px solid #e2e8f0;

            font-size:10px;

            font-weight:600;

            color:#334155;

            text-align:center;

            line-height:36px;

        }

        .course-fy-day-tick.is-month-start{

            background:#dbeafe;

            color:#1e3a8a;

            font-weight:800;

            border-right:2px solid #2563eb;

        }

        .course-fy-x-caption{

            display:flex;

            justify-content:space-between;

            align-items:center;

            padding:8px 4px 0;

            font-size:12px;

            font-weight:600;

            color:#1e3a8a;

        }

        .course-fy-gantt-hint{

            display:flex;

            align-items:center;

            justify-content:space-between;

            gap:8px;

            margin-top:8px;

            font-size:12px;

            color:#475569;

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
                <?php echo htmlspecialchars($fyCalendarScopeLabel); ?> · duration graph · hover for figures
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
                <strong class="text-primary"><i class="fas fa-chart-area me-1"></i> Course Duration Graph</strong>
                <span class="text-muted small">Time graph · one coloured area per course · hover a line for that course’s details</span>
            </div>
            <div class="course-fy-time-scroll" id="courseFyGanttScroll">
                <div id="courseFyGanttInner">
                    <div class="course-fy-chart-box" id="courseFyChartBox">
                        <canvas id="courseFyTimeChart"></canvas>
                    </div>
                    <div class="course-fy-month-axis" id="courseFyMonthAxis"></div>
                    <div class="course-fy-day-axis" id="courseFyGanttDayAxis"></div>
                </div>
            </div>
            <div class="course-fy-x-caption">
                <span>Financial year wise (1 Apr → 31 Mar)</span>
                <span>Day wise →</span>
            </div>
            <div class="course-fy-hover-panel" id="courseFyHoverPanel">
                <h6 id="courseFyHoverTitle">Course Detail</h6>
                <div class="hover-meta" id="courseFyHoverMeta">Hover a course area/line to see that course’s data.</div>
                <div class="hover-chart-box">
                    <canvas id="courseFyHoverChart"></canvas>
                </div>
            </div>
            <div class="course-fy-gantt-hint">
                <span><i class="fas fa-mouse-pointer me-1"></i> Each colour is a course. Hover the graph to open that course’s seats, centre, and dates.</span>
                <span class="badge bg-light text-dark border">Time graph</span>
            </div>
        </div>

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

let courseFyHoverChartInstance = null;
let courseFyTimeChartInstance = null;

const courseFyTimelineData = reportPayload.courseFyTimeline || {};
renderCourseFyGanttChart(courseFyTimelineData);

function escapeHtmlAttr(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function renderCourseFyGanttChart(timeline) {
    const wrap = document.getElementById('courseFyGanttWrap');
    const inner = document.getElementById('courseFyGanttInner');
    const canvas = document.getElementById('courseFyTimeChart');
    const dayAxis = document.getElementById('courseFyGanttDayAxis');
    const monthAxis = document.getElementById('courseFyMonthAxis');
    const hoverPanel = document.getElementById('courseFyHoverPanel');
    const hoverTitle = document.getElementById('courseFyHoverTitle');
    const hoverMeta = document.getElementById('courseFyHoverMeta');
    const hoverCanvas = document.getElementById('courseFyHoverChart');
    if (!wrap || !inner || !canvas || typeof Chart === 'undefined') {
        return;
    }

    const courses = timeline.courses || [];
    const dayCount = timeline.day_count || 0;
    const dayLabels = timeline.day_labels || [];
    const dayIsoList = timeline.day_iso || [];

    if (!courses.length || !dayCount) {
        wrap.innerHTML = '<p class="text-center text-muted py-5 mb-0">No course timeline data for this financial year.</p>';
        return;
    }

    const palette = [
        { border: '#2563eb', fill: 'rgba(37,99,235,0.22)', point: '#1d4ed8' },
        { border: '#16a34a', fill: 'rgba(22,163,74,0.20)', point: '#15803d' },
        { border: '#db2777', fill: 'rgba(219,39,119,0.18)', point: '#be185d' },
        { border: '#ca8a04', fill: 'rgba(202,138,4,0.20)', point: '#a16207' },
        { border: '#7c3aed', fill: 'rgba(124,58,237,0.18)', point: '#6d28d9' },
        { border: '#0891b2', fill: 'rgba(8,145,178,0.20)', point: '#0e7490' },
        { border: '#ea580c', fill: 'rgba(234,88,12,0.18)', point: '#c2410c' },
        { border: '#4f46e5', fill: 'rgba(79,70,229,0.18)', point: '#4338ca' },
        { border: '#059669', fill: 'rgba(5,150,105,0.18)', point: '#047857' },
        { border: '#e11d48', fill: 'rgba(225,29,72,0.18)', point: '#be123c' }
    ];

    function buildCourseSeries(course) {
        const series = new Array(dayCount).fill(0);
        const daily = course.daily_admissions || [];
        let run = 0;
        let hasDaily = false;
        for (let i = 0; i < dayCount; i++) {
            const v = Number(daily[i] || 0);
            if (v) {
                hasDaily = true;
            }
            run += v;
            series[i] = run;
        }
        if (hasDaily && run > 0) {
            return series;
        }
        // Fallback: batch-module enrolled progress over time (step when batch starts).
        for (let i = 0; i < dayCount; i++) {
            let sum = 0;
            (course.batches || []).forEach(function (batch) {
                const startIdx = Number.isFinite(batch.start_index) ? batch.start_index : 0;
                if (i >= startIdx) {
                    sum += Number(batch.footfall) || 0;
                }
            });
            series[i] = sum;
        }
        return series;
    }

    const courseMeta = [];
    const datasets = [];
    let yPeak = 10;

    courses.forEach(function (course, index) {
        const batches = course.batches || [];
        if (!batches.length && !(course.total_footfall || course.total_admissions || course.total_registered)) {
            return;
        }
        const color = palette[index % palette.length];
        const data = buildCourseSeries(course);
        const peak = data.length ? Math.max.apply(null, data) : 0;
        if (peak > yPeak) {
            yPeak = peak;
        }
        let seatsTotal = 0;
        let footfall = 0;
        let startLabel = '';
        let endLabel = '';
        let centreName = '';
        let startIdx = Infinity;
        let endIdx = -1;
        batches.forEach(function (batch) {
            footfall += Number(batch.footfall) || 0;
            seatsTotal += Number(batch.seats_total) || 0;
            const s = Number.isFinite(batch.start_index) ? batch.start_index : 0;
            const e = Number.isFinite(batch.end_index) ? batch.end_index : s;
            if (s < startIdx) {
                startIdx = s;
                startLabel = batch.start_label || '';
                centreName = batch.centre_name || centreName;
            }
            if (e > endIdx) {
                endIdx = e;
                endLabel = batch.end_label || '';
            }
            if (!centreName && batch.centre_name) {
                centreName = batch.centre_name;
            }
        });
        if (!Number.isFinite(startIdx)) {
            startIdx = 0;
        }
        if (endIdx < 0) {
            endIdx = dayCount - 1;
        }

        const label = course.course_name || 'Course';
        courseMeta.push({
            course_name: label,
            course_code: course.course_code || '',
            centre_name: centreName || '',
            footfall: footfall || Number(course.total_footfall) || 0,
            seats_total: seatsTotal,
            registered: Number(course.total_registered) || 0,
            admissions: Number(course.total_admissions) || footfall || 0,
            start_label: startLabel,
            end_label: endLabel,
            daily_admissions: course.daily_admissions || [],
            batches: batches
        });

        const pointRadius = data.map(function (v, i) {
            const prev = i > 0 ? Number(data[i - 1] || 0) : 0;
            return Number(v) > 0 && Number(v) !== prev ? 3 : 0;
        });

        datasets.push({
            label: label + (course.course_code ? ' (' + course.course_code + ')' : ''),
            data: data,
            borderColor: color.border,
            backgroundColor: color.fill,
            pointBackgroundColor: color.point,
            pointBorderColor: '#fff',
            pointRadius: pointRadius,
            pointHoverRadius: 6,
            borderWidth: 2.5,
            fill: true,
            tension: 0.35,
            spanGaps: true,
            metaIndex: courseMeta.length - 1
        });
    });

    if (!datasets.length) {
        wrap.innerHTML = '<p class="text-center text-muted py-5 mb-0">No course progress to plot for this financial year.</p>';
        return;
    }

    // Keep canvas under browser size limits (high-DPI × huge width was blanking the chart).
    const chartBox = document.getElementById('courseFyChartBox');
    const chartHeight = 420;
    const maxCssWidth = 7200;
    let pixelsPerDay = Math.max(3, Math.min(10, Math.floor(maxCssWidth / Math.max(dayCount, 1))));
    let chartWidth = Math.max(dayCount * pixelsPerDay, 960);
    if (chartWidth > maxCssWidth) {
        pixelsPerDay = Math.max(2, Math.floor(maxCssWidth / Math.max(dayCount, 1)));
        chartWidth = Math.max(dayCount * pixelsPerDay, 960);
    }
    inner.style.width = chartWidth + 'px';
    inner.style.minWidth = chartWidth + 'px';
    if (chartBox) {
        chartBox.style.width = chartWidth + 'px';
        chartBox.style.height = chartHeight + 'px';
    }
    canvas.style.width = chartWidth + 'px';
    canvas.style.height = chartHeight + 'px';
    canvas.removeAttribute('width');
    canvas.removeAttribute('height');

    if (dayAxis) {
        let daysHtml = '';
        for (let i = 0; i < dayCount; i++) {
            const label = dayLabels[i] || '';
            const iso = dayIsoList[i] || '';
            let dayOfMonth = i + 1;
            const labelMatch = String(label).match(/^(\d+)\./);
            if (labelMatch) {
                dayOfMonth = Number(labelMatch[1]);
            } else if (iso.length >= 10) {
                dayOfMonth = Number(iso.slice(8, 10));
            }
            const isMonthStart = dayOfMonth === 1 || i === 0;
            const tip = label || iso || String(dayOfMonth);
            daysHtml += '<div class="course-fy-day-tick' + (isMonthStart ? ' is-month-start' : '') +
                '" style="width:' + pixelsPerDay + 'px" title="' + escapeHtmlAttr(tip) + '">' + dayOfMonth + '</div>';
        }
        dayAxis.innerHTML = daysHtml;
        dayAxis.style.width = chartWidth + 'px';
    }

    if (monthAxis) {
        const markers = timeline.month_markers || [];
        let monthsHtml = '';
        if (markers.length) {
            markers.forEach(function (marker) {
                const width = Math.max(1, Number(marker.day_count) || 0) * pixelsPerDay;
                monthsHtml += '<div class="course-fy-month-tick" style="width:' + width + 'px" title="' +
                    escapeHtmlAttr(marker.label || '') + '">' + escapeHtmlAttr(marker.label || '') + '</div>';
            });
        } else {
            monthsHtml = '<div class="course-fy-month-tick" style="width:' + chartWidth + 'px">Financial Year</div>';
        }
        monthAxis.innerHTML = monthsHtml;
        monthAxis.style.width = chartWidth + 'px';
    }

    if (hoverPanel && hoverPanel.parentElement !== document.body) {
        document.body.appendChild(hoverPanel);
    }

    function hideCourseDetail() {
        if (hoverPanel) {
            hoverPanel.classList.remove('is-visible');
        }
    }

    function placeHoverAtEvent(evt) {
        if (!hoverPanel || !evt) {
            return;
        }
        const panelWidth = Math.min(320, window.innerWidth - 24);
        const panelHeight = hoverPanel.offsetHeight || 240;
        let left = (evt.clientX || 0) + 16;
        let top = (evt.clientY || 0) - 20;
        if (left + panelWidth > window.innerWidth - 12) {
            left = Math.max(12, (evt.clientX || 0) - panelWidth - 16);
        }
        if (top < 12) {
            top = 12;
        }
        if (top + panelHeight > window.innerHeight - 12) {
            top = Math.max(12, window.innerHeight - panelHeight - 12);
        }
        hoverPanel.style.width = panelWidth + 'px';
        hoverPanel.style.left = left + 'px';
        hoverPanel.style.top = top + 'px';
    }

    function showCourseDetail(meta, evt) {
        if (!hoverPanel || !meta) {
            return;
        }
        cancelHideCourseDetail();
        if (hoverTitle) {
            hoverTitle.textContent = meta.course_name + (meta.course_code ? ' (' + meta.course_code + ')' : '');
        }
        if (hoverMeta) {
            hoverMeta.innerHTML =
                (meta.centre_name ? ('Centre: <strong>' + escapeHtmlAttr(meta.centre_name) + '</strong><br>') : '') +
                'Batch seats: <strong>' + Number(meta.footfall || 0).toLocaleString() + ' / ' +
                Number(meta.seats_total || 0).toLocaleString() + '</strong>' +
                ' · Registered: <strong>' + Number(meta.registered || 0).toLocaleString() + '</strong>' +
                '<br>Period: ' + (meta.start_label || '—') + ' → ' + (meta.end_label || '—') +
                ' · Batches: <strong>' + Number((meta.batches || []).length).toLocaleString() + '</strong>';
        }

        if (courseFyHoverChartInstance) {
            courseFyHoverChartInstance.destroy();
            courseFyHoverChartInstance = null;
        }

        hoverPanel.classList.add('is-visible');
        placeHoverAtEvent(evt);

        if (!hoverCanvas) {
            return;
        }

        const monthBuckets = {};
        const monthOrder = [];
        (timeline.month_markers || []).forEach(function (marker) {
            monthBuckets[marker.label] = 0;
            monthOrder.push(marker.label);
        });
        if (!monthOrder.length) {
            for (let i = 0; i < dayCount; i++) {
                monthBuckets['D' + (i + 1)] = Number(meta.daily_admissions[i] || 0);
                monthOrder.push('D' + (i + 1));
            }
        } else {
            (timeline.month_markers || []).forEach(function (marker) {
                const start = marker.start_index || 0;
                const count = marker.day_count || 0;
                let sum = 0;
                for (let i = start; i < start + count && i < dayCount; i++) {
                    sum += Number(meta.daily_admissions[i] || 0);
                }
                monthBuckets[marker.label] = sum;
            });
        }

        try {
            courseFyHoverChartInstance = new Chart(hoverCanvas, {
                type: 'bar',
                data: {
                    labels: monthOrder,
                    datasets: [{
                        label: 'Admissions',
                        data: monthOrder.map(function (key) { return monthBuckets[key] || 0; }),
                        backgroundColor: 'rgba(37,99,235,0.75)',
                        borderRadius: 4,
                        maxBarThickness: 18
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { maxRotation: 45, font: { size: 9 }, autoSkip: true, maxTicksLimit: 6 }, grid: { display: false } },
                        y: { beginAtZero: true, ticks: { precision: 0, font: { size: 9 } } }
                    }
                }
            });
        } catch (err) {
            // keep text details even if mini chart fails
        }
        requestAnimationFrame(function () { placeHoverAtEvent(evt); });
    }

    let hoverHideTimer = null;
    function scheduleHideCourseDetail() {
        clearTimeout(hoverHideTimer);
        hoverHideTimer = setTimeout(hideCourseDetail, 350);
    }
    function cancelHideCourseDetail() {
        clearTimeout(hoverHideTimer);
    }

    if (courseFyTimeChartInstance) {
        courseFyTimeChartInstance.destroy();
        courseFyTimeChartInstance = null;
    }

    const yMax = Math.max(100, Math.ceil((Math.max(yPeak, 10) * 1.25) / 10) * 10);

    try {
        courseFyTimeChartInstance = new Chart(canvas, {
            type: 'line',
            data: {
                labels: dayLabels,
                datasets: datasets
            },
            options: {
                responsive: false,
                maintainAspectRatio: false,
                devicePixelRatio: 1,
                animation: false,
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            boxWidth: 12,
                            font: { size: 11 },
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        enabled: true,
                        callbacks: {
                            title: function (items) {
                                if (!items.length) {
                                    return '';
                                }
                                return 'Date: ' + items[0].label;
                            },
                            label: function (ctx) {
                                return (ctx.dataset.label || 'Course') + ': ' + Number(ctx.parsed.y || 0).toLocaleString();
                            }
                        }
                    }
                },
                onHover: function (evt, elements) {
                    if (!elements || !elements.length) {
                        scheduleHideCourseDetail();
                        return;
                    }
                    const el = elements[0];
                    const ds = datasets[el.datasetIndex];
                    if (!ds) {
                        return;
                    }
                    const meta = courseMeta[ds.metaIndex];
                    const native = evt && (evt.native || evt);
                    showCourseDetail(meta, native);
                },
                onClick: function (evt, elements) {
                    if (!elements || !elements.length) {
                        return;
                    }
                    const el = elements[0];
                    const ds = datasets[el.datasetIndex];
                    if (!ds) {
                        return;
                    }
                    showCourseDetail(courseMeta[ds.metaIndex], evt && (evt.native || evt));
                },
                scales: {
                    x: {
                        display: false,
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        max: yMax,
                        title: {
                            display: true,
                            text: 'Student admission completed ↑',
                            font: { size: 12, weight: '700' },
                            color: '#1e3a8a'
                        },
                        ticks: {
                            stepSize: yMax > 400 ? 50 : (yMax > 100 ? 20 : 10),
                            precision: 0,
                            font: { size: 10 }
                        },
                        grid: {
                            color: 'rgba(148,163,184,0.25)'
                        }
                    }
                }
            }
        });
    } catch (err) {
        console.error('Course FY time chart failed', err);
        if (chartBox) {
            chartBox.innerHTML = '<p class="text-danger text-center py-5 mb-0">Could not draw course graph. Please refresh.</p>';
        }
        return;
    }

    try {
        courseFyTimeChartInstance.resize(chartWidth, chartHeight);
        courseFyTimeChartInstance.update('none');
    } catch (err) {
        // chart already created; resize is best-effort
    }

    if (hoverPanel) {
        hoverPanel.addEventListener('mouseenter', cancelHideCourseDetail);
        hoverPanel.addEventListener('mouseleave', scheduleHideCourseDetail);
    }
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

if (instance === courseFyTimeChartInstance) {
    return;
}

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