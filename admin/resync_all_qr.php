<?php
/**
 * Admin: rebuild QR PNG files for all courses that have a registration token.
 */
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/qr_helper.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$results = [];
$summary = ['total' => 0, 'ok' => 0, 'failed' => 0];

if ($isPost) {
    $query = $conn->query(
        "SELECT id, course_name, course_code, registration_token
         FROM courses
         WHERE registration_token IS NOT NULL AND TRIM(registration_token) != ''"
    );

    if ($query) {
        while ($row = $query->fetch_assoc()) {
            $summary['total']++;
            $sync = syncCourseRegistrationLinkAndQr($conn, (int) $row['id'], true);
            $ok = !empty($sync['success']);
            if ($ok) {
                $summary['ok']++;
            } else {
                $summary['failed']++;
            }
            $results[] = [
                'id' => (int) $row['id'],
                'course_name' => $row['course_name'],
                'course_code' => $row['course_code'],
                'success' => $ok,
                'message' => $sync['message'] ?? ($ok ? 'Synced' : 'Failed'),
                'qr_target_url' => $sync['qr_target_url'] ?? '',
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resync All QR Codes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <h1 class="h3 mb-3">Resync All QR Codes</h1>
    <p class="text-muted">
        Rebuilds every course QR PNG from the current registration token and
        <code><?php echo htmlspecialchars(APP_URL); ?></code>.
        After running this, re-download QR images and reprint flyers/posters.
    </p>

    <?php if ($isPost): ?>
        <div class="alert alert-<?php echo $summary['failed'] === 0 ? 'success' : 'warning'; ?>">
            Processed <?php echo (int) $summary['total']; ?> course(s):
            <?php echo (int) $summary['ok']; ?> synced,
            <?php echo (int) $summary['failed']; ?> failed.
        </div>
        <?php if ($results): ?>
            <div class="table-responsive mb-4">
                <table class="table table-sm table-striped bg-white">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Course</th>
                            <th>Code</th>
                            <th>Status</th>
                            <th>QR URL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $item): ?>
                            <tr>
                                <td><?php echo (int) $item['id']; ?></td>
                                <td><?php echo htmlspecialchars((string) $item['course_name']); ?></td>
                                <td><?php echo htmlspecialchars((string) $item['course_code']); ?></td>
                                <td><?php echo $item['success'] ? 'OK' : 'Failed'; ?></td>
                                <td style="word-break: break-all; font-size: 12px;">
                                    <?php echo htmlspecialchars((string) $item['qr_target_url']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <form method="post" onsubmit="return confirm('Rebuild QR codes for all courses with tokens?');">
        <button type="submit" class="btn btn-primary">Resync all QR codes now</button>
        <a href="manage_courses.php" class="btn btn-outline-secondary ms-2">Back to courses</a>
    </form>
</div>
</body>
</html>
