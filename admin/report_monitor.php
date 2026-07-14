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
    $monthFilter,
    $quarterRange['graph_months'],
    8
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

    'facultyTrainingStats'=>$facultyTrainingStats

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

<option value="0">

All Centres

</option>

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
<!-- COURSE WISE TABLE -->

<div class="card table-card mb-4">

    <div class="card-header">

        <strong>

            Course Wise Figures

        </strong>

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

<div class="card table-card mb-4">
    <div class="card-header">
        <strong>Category Quarterly Admissions Summary</strong>
        <span class="badge bg-primary">FY <?php echo htmlspecialchars($selectedYear); ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0">
                <thead class="table-light">
                <tr>
                    <th>Category</th>
                    <th class="text-end">Q1</th>
                    <th class="text-end">Q2</th>
                    <th class="text-end">Q3</th>
                    <th class="text-end">Q4</th>
                    <th class="text-end">Total</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($categoryQuarterSummary as $categoryRow): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($categoryRow['label']); ?></td>
                        <td class="text-end"><?php echo number_format($categoryRow['Q1']); ?></td>
                        <td class="text-end"><?php echo number_format($categoryRow['Q2']); ?></td>
                        <td class="text-end"><?php echo number_format($categoryRow['Q3']); ?></td>
                        <td class="text-end"><?php echo number_format($categoryRow['Q4']); ?></td>
                        <td class="text-end fw-bold"><?php echo number_format($categoryRow['total']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
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

<!-- COURSE MONTHLY PROGRESS -->

<div class="row mb-4">

    <div class="col-lg-12">

        <div class="card chart-card">

            <div class="card-header">

                <strong>

                    Course-wise Monthly Progress

                </strong>

                <small class="text-muted ms-2">

                    <?php echo htmlspecialchars($monthScopeLabel); ?> · monthly registrations (top courses in chart)

                </small>

            </div>

            <div class="card-body">

                <canvas

                    id="courseMonthlyChart"

                    height="320"

                ></canvas>

            </div>

        </div>

    </div>

</div>

<div class="card table-card mb-4">

    <div class="card-header d-flex justify-content-between align-items-center">

        <strong>

            Course-wise Monthly Progress Table

        </strong>

        <span class="badge bg-primary">

            <?php echo count($courseMonthlyProgress['courses'] ?? []); ?> Courses

        </span>

    </div>

    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-bordered table-hover mb-0 monthly-progress-table">

                <thead class="table-light">

                <tr>

                    <th class="course-col">Course</th>

                    <th>Code</th>

                    <?php foreach (($courseMonthlyProgress['labels'] ?? []) as $monthLabel): ?>

                    <th class="text-end" title="Registered in <?php echo htmlspecialchars($monthLabel); ?>">

                        <?php echo htmlspecialchars($monthLabel); ?>

                    </th>

                    <?php endforeach; ?>

                    <th class="text-end">FY Total Reg.</th>

                    <th class="text-end">FY Total Adm.</th>

                    <th class="text-end">FY Batches</th>

                </tr>

                </thead>

                <tbody>

                <?php if (empty($courseMonthlyProgress['courses'])): ?>

                <tr>

                    <td colspan="<?php echo 5 + count($courseMonthlyProgress['labels'] ?? []); ?>" class="text-center">

                        No course activity for <?php echo htmlspecialchars($monthScopeLabel); ?>.

                    </td>

                </tr>

                <?php else: ?>

                <?php foreach ($courseMonthlyProgress['courses'] as $courseRow): ?>

                <tr>

                    <td class="course-col">

                        <strong><?php echo htmlspecialchars($courseRow['course_name']); ?></strong>

                    </td>

                    <td><?php echo htmlspecialchars($courseRow['course_code'] ?: '—'); ?></td>

                    <?php foreach ($courseRow['registered'] as $monthValue): ?>

                    <td class="text-end">

                        <?php echo $monthValue > 0 ? number_format($monthValue) : '—'; ?>

                    </td>

                    <?php endforeach; ?>

                    <td class="text-end fw-bold">

                        <?php echo number_format($courseRow['total_registered']); ?>

                    </td>

                    <td class="text-end text-success">

                        <?php echo number_format($courseRow['total_admissions']); ?>

                    </td>

                    <td class="text-end text-primary">

                        <?php echo number_format($courseRow['total_batches']); ?>

                    </td>

                </tr>

                <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

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

                    <td colspan="9" class="text-center">

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
COURSE MONTHLY PROGRESS GRAPH
==================================================*/

const courseMonthlyCanvas =
document.getElementById(
'courseMonthlyChart'
);

if(courseMonthlyCanvas){

const monthlyProgress =
reportPayload.courseMonthlyProgress || {};

const monthlyChartCourses =
monthlyProgress.chart_courses || [];

if(!monthlyChartCourses.length){

courseMonthlyCanvas.parentElement.innerHTML =
'<p class="text-center text-muted py-5 mb-0">No course monthly data for the selected period.</p>';

}else{

const lineColors = [
'#2563eb','#16a34a','#dc2626','#f59e0b','#7c3aed',
'#0891b2','#db2777','#65a30d','#ea580c','#4f46e5'
];

new Chart(

courseMonthlyCanvas,

{

type:'line',

data:{

labels: monthlyProgress.labels || [],

datasets: monthlyChartCourses.map(function(course, index){

const color = lineColors[index % lineColors.length];

const label = course.course_code
    ? course.course_name + ' (' + course.course_code + ')'
    : course.course_name;

return {

label: label,

data: course.registered || [],

borderColor: color,

backgroundColor: color + '22',

fill:false,

borderWidth:2,

tension:.3,

pointRadius:3

};

})

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

</script>

</body>

</html>