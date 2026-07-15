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
require_once __DIR__ . '/../includes/training_partner_admissions_helper.php';

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

$trainingPartnerEntries = tp_admissions_list($conn, $selectedYear, true);
$tpCategoryQuarterSummary = tp_admissions_get_category_quarter_summary($conn, $selectedYear);

$tpCategoryAdmissionTargets = report_monitor_get_category_targets(
    $conn,
    $selectedYear,
    report_monitor_tp_target_centre_id()
);
$tpCategoryQuarterTargetSummary = report_monitor_apply_category_targets(
    $tpCategoryQuarterSummary,
    $tpCategoryAdmissionTargets
);
$tpCategoryQuarterSummary = $tpCategoryQuarterTargetSummary['rows'];
$tpCategoryQuarterGrandTotal = $tpCategoryQuarterTargetSummary['grand_total'];
$tpCategoryQuarterGrandTarget = $tpCategoryQuarterTargetSummary['grand_target'];
$tpCategoryQuarterGrandAchievement = $tpCategoryQuarterTargetSummary['grand_achievement_pct'];
$tpCategoryQuarterGrand = [
    'Q1' => array_sum(array_column($tpCategoryQuarterSummary, 'Q1')),
    'Q2' => array_sum(array_column($tpCategoryQuarterSummary, 'Q2')),
    'Q3' => array_sum(array_column($tpCategoryQuarterSummary, 'Q3')),
    'Q4' => array_sum(array_column($tpCategoryQuarterSummary, 'Q4')),
    'total' => $tpCategoryQuarterGrandTotal,
];

$tpSocialCategoryQuarterSummary = tp_admissions_get_social_category_quarter_summary($conn, $selectedYear);
$tpSocialCategoryAdmissionTargets = report_monitor_get_social_category_targets(
    $conn,
    $selectedYear,
    report_monitor_tp_social_target_centre_id()
);
$tpSocialCategoryQuarterTargetSummary = report_monitor_apply_category_targets(
    $tpSocialCategoryQuarterSummary,
    $tpSocialCategoryAdmissionTargets
);
$tpSocialCategoryQuarterSummary = $tpSocialCategoryQuarterTargetSummary['rows'];
$tpSocialCategoryQuarterGrandTotal = $tpSocialCategoryQuarterTargetSummary['grand_total'];
$tpSocialCategoryQuarterGrandTarget = $tpSocialCategoryQuarterTargetSummary['grand_target'];
$tpSocialCategoryQuarterGrandAchievement = $tpSocialCategoryQuarterTargetSummary['grand_achievement_pct'];
$tpSocialCategoryQuarterGrand = [
    'Q1' => array_sum(array_column($tpSocialCategoryQuarterSummary, 'Q1')),
    'Q2' => array_sum(array_column($tpSocialCategoryQuarterSummary, 'Q2')),
    'Q3' => array_sum(array_column($tpSocialCategoryQuarterSummary, 'Q3')),
    'Q4' => array_sum(array_column($tpSocialCategoryQuarterSummary, 'Q4')),
    'total' => $tpSocialCategoryQuarterGrandTotal,
];

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

<?php if (!empty($targetsFlashMessage)): ?>
<div class="alert alert-<?php echo htmlspecialchars($targetsFlashType); ?> alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($targetsFlashMessage); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="card table-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <strong>Category Quarterly Admissions Summary</strong>
            <span class="badge bg-primary ms-2">FY <?php echo htmlspecialchars($selectedYear); ?></span>
            <?php if ($centreId > 0): ?>
                <span class="badge bg-secondary ms-1"><?php echo htmlspecialchars($selectedCentreName); ?> targets</span>
            <?php else: ?>
                <span class="badge bg-secondary ms-1">All-centre targets</span>
            <?php endif; ?>
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
                <strong>No annual targets set for FY <?php echo htmlspecialchars($selectedYear); ?>.</strong>
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
                    <th class="text-end">Q1</th>
                    <th class="text-end">Q2</th>
                    <th class="text-end">Q3</th>
                    <th class="text-end">Q4</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Target</th>
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
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($categoryRow['label']); ?></td>
                        <td class="text-end"><?php echo number_format($categoryRow['Q1']); ?></td>
                        <td class="text-end"><?php echo number_format($categoryRow['Q2']); ?></td>
                        <td class="text-end"><?php echo number_format($categoryRow['Q3']); ?></td>
                        <td class="text-end"><?php echo number_format($categoryRow['Q4']); ?></td>
                        <td class="text-end fw-bold"><?php echo number_format($categoryRow['total']); ?></td>
                        <td class="text-end">
                            <?php if (($categoryRow['target'] ?? 0) > 0): ?>
                                <?php echo number_format($categoryRow['target']); ?>
                            <?php else: ?>
                                <button type="button" class="btn btn-link btn-sm p-0 text-muted" data-bs-toggle="modal" data-bs-target="#categoryTargetsModal" title="Set target for this category">Not set</button>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-semibold <?php echo $achievementClass; ?>">
                            <?php echo $achievement !== null ? number_format($achievement, 1) . '%' : '—'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                <tr>
                    <th>Grand Total</th>
                    <th class="text-end"><?php echo number_format(array_sum(array_column($categoryQuarterSummary, 'Q1'))); ?></th>
                    <th class="text-end"><?php echo number_format(array_sum(array_column($categoryQuarterSummary, 'Q2'))); ?></th>
                    <th class="text-end"><?php echo number_format(array_sum(array_column($categoryQuarterSummary, 'Q3'))); ?></th>
                    <th class="text-end"><?php echo number_format(array_sum(array_column($categoryQuarterSummary, 'Q4'))); ?></th>
                    <th class="text-end fw-bold"><?php echo number_format($categoryQuarterGrandTotal); ?></th>
                    <th class="text-end fw-bold">
                        <?php if ($categoryQuarterGrandTarget > 0): ?>
                            <?php echo number_format($categoryQuarterGrandTarget); ?>
                        <?php else: ?>
                            <span class="text-muted fw-normal">Not set</span>
                        <?php endif; ?>
                    </th>
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
            <strong>Social Category Quarterly Admissions Summary</strong>
            <span class="badge bg-primary ms-2">FY <?php echo htmlspecialchars($selectedYear); ?></span>
            <span class="badge bg-secondary ms-1">General / OBC / SC / ST / EWS / PWD</span>
            <?php if ($centreId > 0): ?>
                <span class="badge bg-secondary ms-1"><?php echo htmlspecialchars($selectedCentreName); ?> targets</span>
            <?php else: ?>
                <span class="badge bg-secondary ms-1">All-centre targets</span>
            <?php endif; ?>
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
                <strong>No social category targets set for FY <?php echo htmlspecialchars($selectedYear); ?>.</strong>
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
                    <th class="text-end">Q1</th>
                    <th class="text-end">Q2</th>
                    <th class="text-end">Q3</th>
                    <th class="text-end">Q4</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Target</th>
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
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($socialRow['label']); ?></td>
                        <td class="text-end"><?php echo number_format($socialRow['Q1']); ?></td>
                        <td class="text-end"><?php echo number_format($socialRow['Q2']); ?></td>
                        <td class="text-end"><?php echo number_format($socialRow['Q3']); ?></td>
                        <td class="text-end"><?php echo number_format($socialRow['Q4']); ?></td>
                        <td class="text-end fw-bold"><?php echo number_format($socialRow['total']); ?></td>
                        <td class="text-end">
                            <?php if (($socialRow['target'] ?? 0) > 0): ?>
                                <?php echo number_format($socialRow['target']); ?>
                            <?php else: ?>
                                <button type="button" class="btn btn-link btn-sm p-0 text-muted" data-bs-toggle="modal" data-bs-target="#socialCategoryTargetsModal" title="Set target">Not set</button>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-semibold <?php echo $socialAchievementClass; ?>">
                            <?php echo $socialAchievement !== null ? number_format($socialAchievement, 1) . '%' : '—'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                <tr>
                    <th>Grand Total</th>
                    <th class="text-end"><?php echo number_format(array_sum(array_column($socialCategoryQuarterSummary, 'Q1'))); ?></th>
                    <th class="text-end"><?php echo number_format(array_sum(array_column($socialCategoryQuarterSummary, 'Q2'))); ?></th>
                    <th class="text-end"><?php echo number_format(array_sum(array_column($socialCategoryQuarterSummary, 'Q3'))); ?></th>
                    <th class="text-end"><?php echo number_format(array_sum(array_column($socialCategoryQuarterSummary, 'Q4'))); ?></th>
                    <th class="text-end fw-bold"><?php echo number_format($socialCategoryQuarterGrandTotal); ?></th>
                    <th class="text-end fw-bold">
                        <?php if ($socialCategoryQuarterGrandTarget > 0): ?>
                            <?php echo number_format($socialCategoryQuarterGrandTarget); ?>
                        <?php else: ?>
                            <span class="text-muted fw-normal">Not set</span>
                        <?php endif; ?>
                    </th>
                    <th class="text-end fw-bold">
                        <?php echo $socialCategoryQuarterGrandAchievement !== null ? number_format($socialCategoryQuarterGrandAchievement, 1) . '%' : '—'; ?>
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
            <strong>Category Quarterly Admissions Summary — Training Partners</strong>
            <span class="badge bg-primary ms-2">FY <?php echo htmlspecialchars($selectedYear); ?></span>
            <span class="badge bg-secondary ms-1">Manual TP entries only</span>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?php echo app_url('admin/training_partner_admissions'); ?>?year=<?php echo (int) $selectedYear; ?>" class="btn btn-outline-primary">
                <i class="fas fa-handshake me-1"></i> Manage TP Entries
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tpCategoryTargetsModal">
                <i class="fas fa-bullseye me-1"></i> Set Targets
            </button>
        </div>
    </div>
    <?php if ($tpCategoryQuarterGrandTarget <= 0): ?>
    <div class="card-body border-bottom py-3">
        <div class="alert alert-warning mb-0 d-flex align-items-start gap-2">
            <i class="fas fa-info-circle mt-1"></i>
            <div>
                <strong>No TP targets set for FY <?php echo htmlspecialchars($selectedYear); ?>.</strong>
                Click <button type="button" class="btn btn-link btn-sm p-0 align-baseline fw-semibold" data-bs-toggle="modal" data-bs-target="#tpCategoryTargetsModal">Set Targets</button>
                to enter annual goals for training partner categories.
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($tpCategoryQuarterGrand['total'] <= 0 && $tpCategoryQuarterGrandTarget > 0): ?>
    <div class="card-body border-bottom py-3">
        <div class="alert alert-info mb-0">
            <i class="fas fa-info-circle me-1"></i>
            No training partner admissions recorded for FY <?php echo htmlspecialchars($selectedYear); ?>.
            <a href="<?php echo app_url('admin/training_partner_admissions'); ?>?year=<?php echo (int) $selectedYear; ?>">Add TP entries</a> to populate this summary.
        </div>
    </div>
    <?php endif; ?>
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
                    <th class="text-end">Target</th>
                    <th class="text-end">Achievement</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($tpCategoryQuarterSummary as $tpCategoryRow): ?>
                    <?php
                    $tpAchievement = $tpCategoryRow['achievement_pct'] ?? null;
                    $tpAchievementClass = '';
                    if ($tpAchievement !== null) {
                        if ($tpAchievement >= 100) {
                            $tpAchievementClass = 'text-success';
                        } elseif ($tpAchievement >= 75) {
                            $tpAchievementClass = 'text-warning';
                        } else {
                            $tpAchievementClass = 'text-danger';
                        }
                    }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($tpCategoryRow['label']); ?></td>
                        <td class="text-end"><?php echo number_format($tpCategoryRow['Q1']); ?></td>
                        <td class="text-end"><?php echo number_format($tpCategoryRow['Q2']); ?></td>
                        <td class="text-end"><?php echo number_format($tpCategoryRow['Q3']); ?></td>
                        <td class="text-end"><?php echo number_format($tpCategoryRow['Q4']); ?></td>
                        <td class="text-end fw-bold"><?php echo number_format($tpCategoryRow['total']); ?></td>
                        <td class="text-end">
                            <?php if (($tpCategoryRow['target'] ?? 0) > 0): ?>
                                <?php echo number_format($tpCategoryRow['target']); ?>
                            <?php else: ?>
                                <button type="button" class="btn btn-link btn-sm p-0 text-muted" data-bs-toggle="modal" data-bs-target="#tpCategoryTargetsModal" title="Set TP target">Not set</button>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-semibold <?php echo $tpAchievementClass; ?>">
                            <?php echo $tpAchievement !== null ? number_format($tpAchievement, 1) . '%' : '—'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                <tr>
                    <th>Grand Total</th>
                    <th class="text-end"><?php echo number_format($tpCategoryQuarterGrand['Q1']); ?></th>
                    <th class="text-end"><?php echo number_format($tpCategoryQuarterGrand['Q2']); ?></th>
                    <th class="text-end"><?php echo number_format($tpCategoryQuarterGrand['Q3']); ?></th>
                    <th class="text-end"><?php echo number_format($tpCategoryQuarterGrand['Q4']); ?></th>
                    <th class="text-end fw-bold"><?php echo number_format($tpCategoryQuarterGrand['total']); ?></th>
                    <th class="text-end fw-bold">
                        <?php if ($tpCategoryQuarterGrandTarget > 0): ?>
                            <?php echo number_format($tpCategoryQuarterGrandTarget); ?>
                        <?php else: ?>
                            <span class="text-muted fw-normal">Not set</span>
                        <?php endif; ?>
                    </th>
                    <th class="text-end fw-bold">
                        <?php echo $tpCategoryQuarterGrandAchievement !== null ? number_format($tpCategoryQuarterGrandAchievement, 1) . '%' : '—'; ?>
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
            <strong>Social Category Quarterly Admissions Summary — Training Partners</strong>
            <span class="badge bg-primary ms-2">FY <?php echo htmlspecialchars($selectedYear); ?></span>
            <span class="badge bg-secondary ms-1">Manual TP entries only</span>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tpSocialCategoryTargetsModal">
                <i class="fas fa-bullseye me-1"></i> Set Targets
            </button>
        </div>
    </div>
    <?php if ($tpSocialCategoryQuarterGrandTarget <= 0): ?>
    <div class="card-body border-bottom py-3">
        <div class="alert alert-warning mb-0 d-flex align-items-start gap-2">
            <i class="fas fa-info-circle mt-1"></i>
            <div>
                <strong>No TP social category targets set for FY <?php echo htmlspecialchars($selectedYear); ?>.</strong>
                Click <button type="button" class="btn btn-link btn-sm p-0 align-baseline fw-semibold" data-bs-toggle="modal" data-bs-target="#tpSocialCategoryTargetsModal">Set Targets</button>
                for General, OBC, SC, ST, EWS, and PWD.
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
                    <th class="text-end">Q1</th>
                    <th class="text-end">Q2</th>
                    <th class="text-end">Q3</th>
                    <th class="text-end">Q4</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Target</th>
                    <th class="text-end">Achievement</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($tpSocialCategoryQuarterSummary as $tpSocialRow): ?>
                    <?php
                    $tpSocialAchievement = $tpSocialRow['achievement_pct'] ?? null;
                    $tpSocialAchievementClass = '';
                    if ($tpSocialAchievement !== null) {
                        if ($tpSocialAchievement >= 100) {
                            $tpSocialAchievementClass = 'text-success';
                        } elseif ($tpSocialAchievement >= 75) {
                            $tpSocialAchievementClass = 'text-warning';
                        } else {
                            $tpSocialAchievementClass = 'text-danger';
                        }
                    }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($tpSocialRow['label']); ?></td>
                        <td class="text-end"><?php echo number_format($tpSocialRow['Q1']); ?></td>
                        <td class="text-end"><?php echo number_format($tpSocialRow['Q2']); ?></td>
                        <td class="text-end"><?php echo number_format($tpSocialRow['Q3']); ?></td>
                        <td class="text-end"><?php echo number_format($tpSocialRow['Q4']); ?></td>
                        <td class="text-end fw-bold"><?php echo number_format($tpSocialRow['total']); ?></td>
                        <td class="text-end">
                            <?php if (($tpSocialRow['target'] ?? 0) > 0): ?>
                                <?php echo number_format($tpSocialRow['target']); ?>
                            <?php else: ?>
                                <button type="button" class="btn btn-link btn-sm p-0 text-muted" data-bs-toggle="modal" data-bs-target="#tpSocialCategoryTargetsModal" title="Set TP social target">Not set</button>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-semibold <?php echo $tpSocialAchievementClass; ?>">
                            <?php echo $tpSocialAchievement !== null ? number_format($tpSocialAchievement, 1) . '%' : '—'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                <tr>
                    <th>Grand Total</th>
                    <th class="text-end"><?php echo number_format($tpSocialCategoryQuarterGrand['Q1']); ?></th>
                    <th class="text-end"><?php echo number_format($tpSocialCategoryQuarterGrand['Q2']); ?></th>
                    <th class="text-end"><?php echo number_format($tpSocialCategoryQuarterGrand['Q3']); ?></th>
                    <th class="text-end"><?php echo number_format($tpSocialCategoryQuarterGrand['Q4']); ?></th>
                    <th class="text-end fw-bold"><?php echo number_format($tpSocialCategoryQuarterGrand['total']); ?></th>
                    <th class="text-end fw-bold">
                        <?php if ($tpSocialCategoryQuarterGrandTarget > 0): ?>
                            <?php echo number_format($tpSocialCategoryQuarterGrandTarget); ?>
                        <?php else: ?>
                            <span class="text-muted fw-normal">Not set</span>
                        <?php endif; ?>
                    </th>
                    <th class="text-end fw-bold">
                        <?php echo $tpSocialCategoryQuarterGrandAchievement !== null ? number_format($tpSocialCategoryQuarterGrandAchievement, 1) . '%' : '—'; ?>
                    </th>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php if (!empty($trainingPartnerEntries)): ?>
<div class="card table-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <strong>Training Partner Entry Details</strong>
            <div class="text-muted small">Manual entries from training partners for FY <?php echo htmlspecialchars($selectedYear); ?></div>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-primary"><?php echo number_format(array_sum(array_column($trainingPartnerEntries, 'total'))); ?> Students</span>
            <a href="<?php echo app_url('admin/training_partner_admissions'); ?>?year=<?php echo (int) $selectedYear; ?>" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-edit me-1"></i> Manage Entries
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0">
                <thead class="table-light">
                <tr>
                    <th>Training Partner</th>
                    <th>Training Centre</th>
                    <th>Course</th>
                    <th>Course Category</th>
                    <th>Social Category</th>
                    <th>Quarter</th>
                    <th class="text-end">Students</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($trainingPartnerEntries as $tpRow): ?>
                <tr>
                    <td><?php echo htmlspecialchars($tpRow['partner_name']); ?></td>
                    <td><?php echo !empty($tpRow['centre_name']) ? htmlspecialchars($tpRow['centre_name']) : '—'; ?></td>
                    <td><?php echo htmlspecialchars($tpRow['course_name']); ?></td>
                    <td><small><?php echo htmlspecialchars($tpRow['category_label']); ?></small></td>
                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($tpRow['social_category_label'] ?? 'General'); ?></span></td>
                    <td><span class="badge bg-primary"><?php echo htmlspecialchars($tpRow['quarter'] ?: '—'); ?></span></td>
                    <td class="text-end fw-bold"><?php echo number_format($tpRow['students_trained']); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

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

<div class="modal fade" id="tpCategoryTargetsModal" tabindex="-1" aria-labelledby="tpCategoryTargetsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <form id="tpCategoryTargetsForm" method="post" action="<?php echo htmlspecialchars(APP_URL); ?>/admin/ajax_report_category_targets.php">
                <input type="hidden" name="action" value="save_category_targets">
                <input type="hidden" name="target_scope" value="training_partner">
                <input type="hidden" name="financial_year_start" value="<?php echo (int) $selectedYear; ?>">
                <input type="hidden" name="centre_id" value="<?php echo (int) report_monitor_tp_target_centre_id(); ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="tpCategoryTargetsModalLabel">
                        <i class="fas fa-bullseye me-2 text-primary"></i>
                        Set Training Partner Category Targets
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="tpCategoryTargetsError" class="alert alert-danger d-none" role="alert"></div>
                    <div class="alert alert-info py-2 small mb-3">
                        Enter the <strong>annual admission target</strong> for each category for training partners in
                        <strong>FY <?php echo htmlspecialchars($selectedYear); ?></strong>.
                        Compared against the TP admissions total in each row.
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
                            <?php foreach ($tpCategoryQuarterSummary as $tpCategoryRow): ?>
                                <?php $tpSavedTarget = (int) ($tpCategoryAdmissionTargets[$tpCategoryRow['key']] ?? 0); ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($tpCategoryRow['label']); ?></td>
                                    <td>
                                        <input
                                            type="number"
                                            class="form-control text-end"
                                            name="targets[<?php echo htmlspecialchars($tpCategoryRow['key']); ?>]"
                                            min="0"
                                            step="1"
                                            placeholder="e.g. 500"
                                            value="<?php echo $tpSavedTarget > 0 ? $tpSavedTarget : ''; ?>"
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

<div class="modal fade" id="tpSocialCategoryTargetsModal" tabindex="-1" aria-labelledby="tpSocialCategoryTargetsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <form id="tpSocialCategoryTargetsForm" method="post" action="<?php echo htmlspecialchars(APP_URL); ?>/admin/ajax_report_category_targets.php">
                <input type="hidden" name="action" value="save_category_targets">
                <input type="hidden" name="target_scope" value="training_partner_social">
                <input type="hidden" name="financial_year_start" value="<?php echo (int) $selectedYear; ?>">
                <input type="hidden" name="centre_id" value="<?php echo (int) report_monitor_tp_social_target_centre_id(); ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="tpSocialCategoryTargetsModalLabel">
                        <i class="fas fa-bullseye me-2 text-primary"></i>
                        Set TP Social Category Targets
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="tpSocialCategoryTargetsError" class="alert alert-danger d-none" role="alert"></div>
                    <div class="alert alert-info py-2 small mb-3">
                        Enter annual social category targets for training partner admissions in
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
                            <?php foreach ($tpSocialCategoryQuarterSummary as $tpSocialRow): ?>
                                <?php $tpSocialSavedTarget = (int) ($tpSocialCategoryAdmissionTargets[$tpSocialRow['key']] ?? 0); ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($tpSocialRow['label']); ?></td>
                                    <td>
                                        <input type="number" class="form-control text-end" name="targets[<?php echo htmlspecialchars($tpSocialRow['key']); ?>]" min="0" step="1" placeholder="e.g. 100" value="<?php echo $tpSocialSavedTarget > 0 ? $tpSocialSavedTarget : ''; ?>">
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
bindCategoryTargetsForm('tpCategoryTargetsForm', 'tpCategoryTargetsError', 'tpCategoryTargetsModal');
bindCategoryTargetsForm('socialCategoryTargetsForm', 'socialCategoryTargetsError', 'socialCategoryTargetsModal');
bindCategoryTargetsForm('tpSocialCategoryTargetsForm', 'tpSocialCategoryTargetsError', 'tpSocialCategoryTargetsModal');

</script>

</body>

</html>