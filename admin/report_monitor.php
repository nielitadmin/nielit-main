<?php
/**
 * Report Monitor — unified analytics dashboard.
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/theme_loader.php';
require_once __DIR__ . '/../includes/report_monitor_helper.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit();
}

$adminRole = $_SESSION['admin_role'] ?? '';
$allowedRoles = ['master_admin', 'course_coordinator'];
if (!in_array($adminRole, $allowedRoles, true)) {
    $_SESSION['message'] = 'Access denied. Report Monitor is available to Master Admin and Course Coordinators.';
    $_SESSION['message_type'] = 'danger';
    header('Location: dashboard.php');
    exit();
}

$active_theme = loadActiveTheme($conn);
$adminId = (int) ($_SESSION['admin_id'] ?? 0);
$scopedCourseIds = [];

if ($adminRole === 'course_coordinator') {
    $scopedCourseIds = report_monitor_get_assigned_course_ids($conn, $adminId);
    if (empty($scopedCourseIds)) {
        $scopedCourseIds = [-1];
    }
}

$centreId = max(0, (int) ($_GET['centre_id'] ?? 0));
$chartMonths = max(6, min(24, (int) ($_GET['months'] ?? 12)));

$overallStats = report_monitor_get_overall_stats($conn, $scopedCourseIds);
$centreStats = report_monitor_get_centre_stats($conn, $scopedCourseIds);
$categoryStats = report_monitor_get_category_stats($conn, $scopedCourseIds);
$batchMonthly = report_monitor_get_batch_monthly($conn, $chartMonths, $scopedCourseIds);
$batchDetails = report_monitor_get_batch_details($conn, $scopedCourseIds, $centreId);
$centresList = report_monitor_get_centres_list($conn);

$reportPayload = [
    'batchMonthly' => $batchMonthly,
    'categoryStats' => $categoryStats,
    'centreStats' => $centreStats,
];

$pageTitle = 'Report Monitor';
$isScoped = ($adminRole === 'course_coordinator');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - NIELIT Bhubaneswar</title>
    <?php injectThemeCSS($active_theme); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo APP_URL; ?>/assets/css/admin-theme.css" rel="stylesheet">
    <link rel="icon" href="<?php echo APP_URL; ?>/assets/images/favicon.ico" type="image/x-icon">
    <style>
        .report-monitor .kpi-card {
            border: 0;
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
            height: 100%;
        }
        .report-monitor .kpi-card .kpi-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #0f172a;
        }
        .report-monitor .kpi-card .kpi-label {
            color: #64748b;
            font-size: 0.9rem;
        }
        .report-monitor .chart-card,
        .report-monitor .table-card {
            border: 0;
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        }
        .report-monitor .section-title {
            font-size: 1.05rem;
            font-weight: 600;
            color: #0f172a;
        }
        .report-monitor .filter-bar {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            padding: 1rem 1.25rem;
        }
        .report-monitor .chart-wrap {
            min-height: 280px;
            position: relative;
        }
        .report-monitor .table thead th {
            background: #f8fafc;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #475569;
            white-space: nowrap;
        }
        .report-monitor .badge-fill-high { background: #dcfce7; color: #166534; }
        .report-monitor .badge-fill-mid { background: #fef9c3; color: #854d0e; }
        .report-monitor .badge-fill-low { background: #fee2e2; color: #991b1b; }
        @media (max-width: 768px) {
            .report-monitor .chart-wrap { min-height: 220px; }
        }
    </style>
</head>
<body>
<div class="admin-wrapper report-monitor">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="admin-main">
        <div class="container-fluid py-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                <div>
                    <h2 class="mb-1"><i class="fas fa-chart-line"></i> <?php echo htmlspecialchars($pageTitle); ?></h2>
                    <p class="text-muted mb-0">
                        Overall records, centre-wise breakdown, course categories, and batch module analytics in one place.
                        <?php if ($isScoped): ?>
                            <span class="badge bg-info text-dark">Your assigned courses only</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="filter-bar">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label small mb-1">Centre</label>
                            <select name="centre_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                <?php foreach ($centresList as $centre): ?>
                                    <option value="<?php echo (int) $centre['id']; ?>" <?php echo $centreId === (int) $centre['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($centre['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Monthly chart range</label>
                            <select name="months" class="form-select form-select-sm" onchange="this.form.submit()">
                                <?php foreach ([6, 12, 18, 24] as $option): ?>
                                    <option value="<?php echo $option; ?>" <?php echo $chartMonths === $option ? 'selected' : ''; ?>>
                                        Last <?php echo $option; ?> months
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <a href="report_monitor.php" class="btn btn-sm btn-outline-secondary w-100">Reset filters</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- KPI cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="card kpi-card">
                        <div class="card-body">
                            <div class="kpi-value"><?php echo number_format($overallStats['total_applications']); ?></div>
                            <div class="kpi-label">Total Applications</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card kpi-card">
                        <div class="card-body">
                            <div class="kpi-value text-success"><?php echo number_format($overallStats['batch_enrolled_students']); ?></div>
                            <div class="kpi-label">Students in Batches</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card kpi-card">
                        <div class="card-body">
                            <div class="kpi-value text-warning"><?php echo number_format($overallStats['pending_applications']); ?></div>
                            <div class="kpi-label">Pending Approval</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card kpi-card">
                        <div class="card-body">
                            <div class="kpi-value text-primary"><?php echo number_format($overallStats['total_batches']); ?></div>
                            <div class="kpi-label">Total Batches (<?php echo number_format($overallStats['active_batches']); ?> active)</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-lg-8">
                    <div class="card chart-card h-100">
                        <div class="card-header bg-white border-0 pt-3">
                            <div class="section-title"><i class="fas fa-calendar-alt"></i> Monthly Batch & Application Trend</div>
                            <small class="text-muted">New batches, batch enrollments, and student applications per month</small>
                        </div>
                        <div class="card-body chart-wrap">
                            <canvas id="batchMonthlyChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card chart-card h-100">
                        <div class="card-header bg-white border-0 pt-3">
                            <div class="section-title"><i class="fas fa-building"></i> Centre-wise Applications</div>
                            <small class="text-muted">Applied vs batch-enrolled by centre</small>
                        </div>
                        <div class="card-body chart-wrap">
                            <canvas id="centreChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-12">
                    <div class="card chart-card">
                        <div class="card-header bg-white border-0 pt-3">
                            <div class="section-title"><i class="fas fa-layer-group"></i> Course Category Breakdown</div>
                            <small class="text-muted">Applications and batch enrollments by course category group</small>
                        </div>
                        <div class="card-body chart-wrap">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Category table -->
            <div class="card table-card mb-4">
                <div class="card-header bg-white border-0 pt-3">
                    <div class="section-title"><i class="fas fa-table"></i> Category Summary</div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th class="text-end">Courses</th>
                                    <th class="text-end">Applied</th>
                                    <th class="text-end">Approved</th>
                                    <th class="text-end">Pending</th>
                                    <th class="text-end">In Batches</th>
                                    <th class="text-end">Not in Batch</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categoryStats as $row): ?>
                                    <?php if ($row['courses'] === 0 && $row['applications'] === 0) continue; ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['label']); ?></td>
                                        <td class="text-end"><?php echo number_format($row['courses']); ?></td>
                                        <td class="text-end"><?php echo number_format($row['applications']); ?></td>
                                        <td class="text-end"><?php echo number_format($row['approved']); ?></td>
                                        <td class="text-end"><?php echo number_format($row['pending']); ?></td>
                                        <td class="text-end fw-semibold text-success"><?php echo number_format($row['batch_enrolled']); ?></td>
                                        <td class="text-end text-muted"><?php echo number_format(max(0, $row['applications'] - $row['batch_enrolled'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Centre table -->
            <div class="card table-card mb-4">
                <div class="card-header bg-white border-0 pt-3">
                    <div class="section-title"><i class="fas fa-map-marker-alt"></i> Centre-wise Records</div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Centre</th>
                                    <th class="text-end">Courses</th>
                                    <th class="text-end">Batches</th>
                                    <th class="text-end">Applied</th>
                                    <th class="text-end">In Batches</th>
                                    <th class="text-end">Awaiting Batch</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($centreStats)): ?>
                                    <tr><td colspan="6" class="text-center text-muted py-4">No centre data available.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($centreStats as $row): ?>
                                        <tr>
                                            <td>
                                                <?php echo htmlspecialchars($row['centre_name']); ?>
                                                <?php if ($row['centre_code']): ?>
                                                    <small class="text-muted">(<?php echo htmlspecialchars($row['centre_code']); ?>)</small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end"><?php echo number_format($row['course_count']); ?></td>
                                            <td class="text-end"><?php echo number_format($row['batch_count']); ?></td>
                                            <td class="text-end"><?php echo number_format($row['applications']); ?></td>
                                            <td class="text-end text-success"><?php echo number_format($row['batch_enrolled']); ?></td>
                                            <td class="text-end text-muted"><?php echo number_format($row['unassigned']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Batch details table -->
            <div class="card table-card mb-4">
                <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="section-title"><i class="fas fa-users-class"></i> Batch Module Details</div>
                        <small class="text-muted">Coordinator, faculty, enrollment, and fill rate (latest 100 batches)</small>
                    </div>
                    <a href="<?php echo APP_URL; ?>/batch_module/admin/manage_batches.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-external-link-alt"></i> Manage Batches
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Batch</th>
                                    <th>Course</th>
                                    <th>Category</th>
                                    <th>Centre</th>
                                    <th>Coordinator</th>
                                    <th>Faculty</th>
                                    <th class="text-end">Enrolled</th>
                                    <th class="text-end">Seats</th>
                                    <th class="text-end">Fill %</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($batchDetails)): ?>
                                    <tr><td colspan="10" class="text-center text-muted py-4">No batches found for the selected filters.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($batchDetails as $batch): ?>
                                        <?php
                                            $fillClass = 'badge-fill-low';
                                            if ($batch['fill_rate'] >= 75) $fillClass = 'badge-fill-high';
                                            elseif ($batch['fill_rate'] >= 40) $fillClass = 'badge-fill-mid';
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($batch['batch_name']); ?></strong>
                                                <?php if ($batch['batch_code']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($batch['batch_code']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($batch['course_name']); ?></td>
                                            <td><small><?php echo htmlspecialchars($batch['course_category']); ?></small></td>
                                            <td><?php echo htmlspecialchars($batch['centre_name']); ?></td>
                                            <td><?php echo htmlspecialchars($batch['batch_coordinator']); ?></td>
                                            <td><small><?php echo htmlspecialchars($batch['faculty_names']); ?></small></td>
                                            <td class="text-end fw-semibold"><?php echo number_format($batch['enrolled']); ?></td>
                                            <td class="text-end"><?php echo number_format($batch['seats_total']); ?></td>
                                            <td class="text-end">
                                                <span class="badge <?php echo $fillClass; ?>"><?php echo $batch['fill_rate']; ?>%</span>
                                            </td>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($batch['status']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const reportPayload = <?php echo json_encode($reportPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

function initReportCharts() {
    if (typeof Chart === 'undefined') return;

    const monthlyCanvas = document.getElementById('batchMonthlyChart');
    if (monthlyCanvas && reportPayload.batchMonthly) {
        const d = reportPayload.batchMonthly;
        new Chart(monthlyCanvas, {
            type: 'line',
            data: {
                labels: d.labels,
                datasets: [
                    {
                        label: 'Applications',
                        data: d.applications,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.12)',
                        tension: 0.35,
                        fill: true,
                    },
                    {
                        label: 'Batches Created',
                        data: d.batches_created,
                        borderColor: '#7c3aed',
                        backgroundColor: 'rgba(124, 58, 237, 0.08)',
                        tension: 0.35,
                    },
                    {
                        label: 'Batch Enrollments',
                        data: d.batch_enrollments,
                        borderColor: '#059669',
                        backgroundColor: 'rgba(5, 150, 105, 0.08)',
                        tension: 0.35,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
            },
        });
    }

    const centreCanvas = document.getElementById('centreChart');
    if (centreCanvas && reportPayload.centreStats) {
        const centres = reportPayload.centreStats.slice(0, 8);
        new Chart(centreCanvas, {
            type: 'bar',
            data: {
                labels: centres.map(c => c.centre_name),
                datasets: [
                    {
                        label: 'Applied',
                        data: centres.map(c => c.applications),
                        backgroundColor: 'rgba(37, 99, 235, 0.7)',
                    },
                    {
                        label: 'In Batches',
                        data: centres.map(c => c.batch_enrolled),
                        backgroundColor: 'rgba(5, 150, 105, 0.75)',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
            },
        });
    }

    const categoryCanvas = document.getElementById('categoryChart');
    if (categoryCanvas && reportPayload.categoryStats) {
        const categories = reportPayload.categoryStats.filter(c => c.applications > 0 || c.courses > 0);
        new Chart(categoryCanvas, {
            type: 'bar',
            data: {
                labels: categories.map(c => c.label),
                datasets: [
                    {
                        label: 'Applied',
                        data: categories.map(c => c.applications),
                        backgroundColor: 'rgba(59, 130, 246, 0.75)',
                    },
                    {
                        label: 'In Batches',
                        data: categories.map(c => c.batch_enrolled),
                        backgroundColor: 'rgba(16, 185, 129, 0.8)',
                    },
                    {
                        label: 'Pending',
                        data: categories.map(c => c.pending),
                        backgroundColor: 'rgba(245, 158, 11, 0.8)',
                    },
                ],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
            },
        });
    }
}

document.addEventListener('DOMContentLoaded', initReportCharts);
</script>
</body>
</html>
<?php $conn->close(); ?>
