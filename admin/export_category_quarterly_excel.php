<?php
/**
 * Export Category Quarterly Admissions Summary to Excel (.xls)
 * NIELIT Bhubaneswar — Report Monitor
 */

session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/report_monitor_helper.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$adminRole = $_SESSION['admin_role'] ?? '';
if ($adminRole !== 'master_admin') {
    $_SESSION['message'] = 'Access Denied';
    $_SESSION['message_type'] = 'danger';
    header('Location: dashboard.php');
    exit;
}

$centreId = isset($_GET['centre_id']) ? (int) $_GET['centre_id'] : 0;
$selectedYear = isset($_GET['year'])
    ? (int) $_GET['year']
    : report_monitor_get_financial_year_start();

$scopedCourseIds = [];
$selectedCentreName = $centreId > 0
    ? report_monitor_get_centre_name($conn, $centreId)
    : 'All Centres';
$reportScopeTitleLabel = report_monitor_get_scope_title_label($centreId, $selectedCentreName);
$selectedFyFullLabel = report_monitor_format_financial_year_full_label($selectedYear);

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

$q1Sum = (int) array_sum(array_column($categoryQuarterSummary, 'Q1'));
$q2Sum = (int) array_sum(array_column($categoryQuarterSummary, 'Q2'));
$q3Sum = (int) array_sum(array_column($categoryQuarterSummary, 'Q3'));
$q4Sum = (int) array_sum(array_column($categoryQuarterSummary, 'Q4'));

$safeScope = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $reportScopeTitleLabel);
$safeFy = preg_replace('/[^0-9-]+/', '', $selectedFyFullLabel);
$filename = 'Category_Quarterly_Admissions_' . $safeScope . '_FY_' . $safeFy . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>';
echo '<table border="1" cellpadding="4" cellspacing="0">';

echo '<tr><td colspan="10" style="font-weight:bold;font-size:16px;text-align:center;">Category Quarterly Admissions Summary</td></tr>';
echo '<tr><td colspan="10" style="text-align:center;">' . htmlspecialchars($reportScopeTitleLabel) . ' FY - ' . htmlspecialchars($selectedFyFullLabel) . '</td></tr>';
echo '<tr><td colspan="10" style="text-align:center;">Centre: ' . htmlspecialchars($selectedCentreName) . ' | Generated: ' . date('d M Y, h:i A') . '</td></tr>';
echo '<tr><td colspan="10"></td></tr>';

echo '<tr style="font-weight:bold;background:#f1f5f9;">';
echo '<td>Category</td>';
echo '<td>Schemes</td>';
echo '<td>Start Date – End Date</td>';
echo '<td>Target</td>';
echo '<td>Q1</td>';
echo '<td>Q2</td>';
echo '<td>Q3</td>';
echo '<td>Q4</td>';
echo '<td>Total</td>';
echo '<td>Achievement</td>';
echo '</tr>';

foreach ($categoryQuarterSummary as $categoryRow) {
    $categoryKey = (string) ($categoryRow['key'] ?? '');
    $courseRows = $categoryCourseQuarterSummary[$categoryKey] ?? [];
    $achievement = $categoryRow['achievement_pct'] ?? null;
    $target = (int) ($categoryRow['target'] ?? 0);

    echo '<tr style="font-weight:bold;background:#e2e8f0;">';
    echo '<td>' . htmlspecialchars((string) ($categoryRow['label'] ?? '')) . '</td>';
    echo '<td>—</td>';
    echo '<td>—</td>';
    echo '<td>' . ($target > 0 ? number_format($target) : 'Not set') . '</td>';
    echo '<td>' . number_format((int) ($categoryRow['Q1'] ?? 0)) . '</td>';
    echo '<td>' . number_format((int) ($categoryRow['Q2'] ?? 0)) . '</td>';
    echo '<td>' . number_format((int) ($categoryRow['Q3'] ?? 0)) . '</td>';
    echo '<td>' . number_format((int) ($categoryRow['Q4'] ?? 0)) . '</td>';
    echo '<td>' . number_format((int) ($categoryRow['total'] ?? 0)) . '</td>';
    echo '<td>' . ($achievement !== null ? number_format((float) $achievement, 1) . '%' : '—') . '</td>';
    echo '</tr>';

    foreach ($courseRows as $courseRow) {
        $courseLabel = (string) ($courseRow['course_name'] ?? '');
        if (!empty($courseRow['course_code'])) {
            $courseLabel .= ' (' . $courseRow['course_code'] . ')';
        }
        echo '<tr>';
        echo '<td>&nbsp;&nbsp;&nbsp;↳ ' . htmlspecialchars($courseLabel) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($courseRow['scheme_names'] ?? '—')) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($courseRow['batch_period_label'] ?? '—')) . '</td>';
        echo '<td>—</td>';
        echo '<td>' . number_format((int) ($courseRow['Q1'] ?? 0)) . '</td>';
        echo '<td>' . number_format((int) ($courseRow['Q2'] ?? 0)) . '</td>';
        echo '<td>' . number_format((int) ($courseRow['Q3'] ?? 0)) . '</td>';
        echo '<td>' . number_format((int) ($courseRow['Q4'] ?? 0)) . '</td>';
        echo '<td>' . number_format((int) ($courseRow['total'] ?? 0)) . '</td>';
        echo '<td>—</td>';
        echo '</tr>';
    }
}

echo '<tr style="font-weight:bold;background:#f8fafc;">';
echo '<td>Grand Total</td>';
echo '<td></td>';
echo '<td></td>';
echo '<td>' . ($categoryQuarterGrandTarget > 0 ? number_format((int) $categoryQuarterGrandTarget) : 'Not set') . '</td>';
echo '<td>' . number_format($q1Sum) . '</td>';
echo '<td>' . number_format($q2Sum) . '</td>';
echo '<td>' . number_format($q3Sum) . '</td>';
echo '<td>' . number_format($q4Sum) . '</td>';
echo '<td>' . number_format((int) $categoryQuarterGrandTotal) . '</td>';
echo '<td>' . ($categoryQuarterGrandAchievement !== null ? number_format((float) $categoryQuarterGrandAchievement, 1) . '%' : '—') . '</td>';
echo '</tr>';

echo '</table></body></html>';
exit;
