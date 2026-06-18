<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/multi_course_helper.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login_new.php');
    exit();
}

function normalizeDigits(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

$aadhar = normalizeDigits(trim($_GET['aadhar'] ?? $_POST['aadhar'] ?? ''));
$mobile = normalizeDigits(trim($_GET['mobile'] ?? $_POST['mobile'] ?? ''));
$email = strtolower(trim($_GET['email'] ?? $_POST['email'] ?? ''));
$studentId = trim($_GET['student_id'] ?? $_POST['student_id'] ?? '');
$name = trim($_GET['name'] ?? $_POST['name'] ?? '');

$searched = ($aadhar !== '' || $mobile !== '' || $email !== '' || $studentId !== '' || $name !== '');
$studentRows = [];
$accountRows = [];
$enrollmentRows = [];
$exists = false;
$summary = 'No search performed yet.';

if ($searched) {
    $studentConds = [];
    $studentParams = [];
    $studentTypes = '';

    if ($aadhar !== '') {
        $studentConds[] = "REPLACE(REPLACE(REPLACE(aadhar,' ',''),'-',''),'.','') = ?";
        $studentParams[] = $aadhar;
        $studentTypes .= 's';
    }
    if ($mobile !== '') {
        $studentConds[] = "REPLACE(REPLACE(mobile,' ',''),'-','') = ?";
        $studentParams[] = $mobile;
        $studentTypes .= 's';
    }
    if ($email !== '') {
        $studentConds[] = 'LOWER(TRIM(email)) = ?';
        $studentParams[] = $email;
        $studentTypes .= 's';
    }
    if ($studentId !== '') {
        $studentConds[] = 'student_id = ?';
        $studentParams[] = $studentId;
        $studentTypes .= 's';
    }
    if ($name !== '') {
        $studentConds[] = 'name LIKE ?';
        $studentParams[] = '%' . $name . '%';
        $studentTypes .= 's';
    }

    if (!empty($studentConds)) {
        $sql = 'SELECT s.id, s.student_id, s.name, s.aadhar, s.mobile, s.email, s.dob, s.course_id,
                       c.course_name, s.scheme_id, s.batch_id, s.status, s.registration_date
                FROM students s
                LEFT JOIN courses c ON c.id = s.course_id
                WHERE ' . implode(' OR ', $studentConds) . '
                ORDER BY s.registration_date DESC, s.id DESC
                LIMIT 50';
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($studentTypes, ...$studentParams);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $studentRows[] = $row;
            }
            $stmt->close();
        }
    }

    $hasAccounts = $conn->query("SHOW TABLES LIKE 'student_accounts'");
    if ($hasAccounts && $hasAccounts->num_rows > 0) {
        $accountConds = [];
        $accountParams = [];
        $accountTypes = '';

        if ($aadhar !== '') {
            $accountConds[] = "REPLACE(REPLACE(REPLACE(aadhar,' ',''),'-',''),'.','') = ?";
            $accountParams[] = $aadhar;
            $accountTypes .= 's';
        }
        if ($mobile !== '') {
            $accountConds[] = "REPLACE(REPLACE(mobile,' ',''),'-','') = ?";
            $accountParams[] = $mobile;
            $accountTypes .= 's';
        }
        if ($email !== '') {
            $accountConds[] = 'LOWER(TRIM(email)) = ?';
            $accountParams[] = $email;
            $accountTypes .= 's';
        }
        if ($studentId !== '') {
            $accountConds[] = 'student_id = ?';
            $accountParams[] = $studentId;
            $accountTypes .= 's';
        }
        if ($name !== '') {
            $accountConds[] = 'name LIKE ?';
            $accountParams[] = '%' . $name . '%';
            $accountTypes .= 's';
        }

        if (!empty($accountConds)) {
            $sql = 'SELECT id, student_id, name, aadhar, mobile, email, status, created_at
                    FROM student_accounts
                    WHERE ' . implode(' OR ', $accountConds) . '
                    ORDER BY created_at DESC, id DESC
                    LIMIT 20';
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($accountTypes, ...$accountParams);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $accountRows[] = $row;
                }
                $stmt->close();
            }
        }
    }

    $hasEnrollments = $conn->query("SHOW TABLES LIKE 'student_enrollments'");
    if ($hasEnrollments && $hasEnrollments->num_rows > 0 && ($email !== '' || $aadhar !== '' || $studentId !== '')) {
        $enrConds = [];
        $enrParams = [];
        $enrTypes = '';

        if ($aadhar !== '') {
            $enrConds[] = "REPLACE(REPLACE(REPLACE(sa.aadhar,' ',''),'-',''),'.','') = ?";
            $enrParams[] = $aadhar;
            $enrTypes .= 's';
        }
        if ($email !== '') {
            $enrConds[] = 'LOWER(TRIM(sa.email)) = ?';
            $enrParams[] = $email;
            $enrTypes .= 's';
        }
        if ($studentId !== '') {
            $enrConds[] = 'sa.student_id = ?';
            $enrParams[] = $studentId;
            $enrTypes .= 's';
        }

        if (!empty($enrConds)) {
            $sql = 'SELECT se.id, se.course_id, c.course_name, se.scheme_id, se.status, se.registered_at,
                           sa.student_id, sa.name, sa.aadhar, sa.email
                    FROM student_enrollments se
                    INNER JOIN student_accounts sa ON sa.id = se.account_id
                    LEFT JOIN courses c ON c.id = se.course_id
                    WHERE ' . implode(' OR ', $enrConds) . '
                    ORDER BY se.registered_at DESC, se.id DESC
                    LIMIT 50';
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($enrTypes, ...$enrParams);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $enrollmentRows[] = $row;
                }
                $stmt->close();
            }
        }
    }

    $exists = !empty($studentRows) || !empty($accountRows) || !empty($enrollmentRows);
    if ($exists) {
        $summary = 'Student record FOUND in the database.';
    } else {
        $summary = 'No matching student found. They are NOT registered yet.';
    }
}

$page_title = 'Check Student Exists';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - NIELIT Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f1f5f9; }
        .page-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 16px rgba(15,23,42,.08); }
        .status-found { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .status-missing { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .table th { background: #0f172a; color: #fff; font-size: 0.85rem; }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="fas fa-user-check text-primary"></i> Check Student Exists</h1>
            <p class="text-muted mb-0">Search by Aadhar, mobile, email, or Student ID</p>
        </div>
        <div class="d-flex gap-2">
            <a href="students.php" class="btn btn-outline-secondary"><i class="fas fa-users"></i> Students</a>
            <a href="dashboard.php" class="btn btn-outline-primary"><i class="fas fa-home"></i> Dashboard</a>
        </div>
    </div>

    <div class="page-card p-4 mb-4">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Aadhar Number</label>
                <input type="text" name="aadhar" class="form-control" maxlength="12"
                       value="<?php echo htmlspecialchars($aadhar); ?>" placeholder="993041115828">
            </div>
            <div class="col-md-3">
                <label class="form-label">Mobile</label>
                <input type="text" name="mobile" class="form-control" maxlength="15"
                       value="<?php echo htmlspecialchars($mobile); ?>" placeholder="8926374271">
            </div>
            <div class="col-md-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control"
                       value="<?php echo htmlspecialchars($email); ?>" placeholder="name@email.com">
            </div>
            <div class="col-md-3">
                <label class="form-label">Student ID</label>
                <input type="text" name="student_id" class="form-control"
                       value="<?php echo htmlspecialchars($studentId); ?>" placeholder="NIELIT/2026/BBSR/0001">
            </div>
            <div class="col-md-6">
                <label class="form-label">Name (optional, partial match)</label>
                <input type="text" name="name" class="form-control"
                       value="<?php echo htmlspecialchars($name); ?>" placeholder="Student name">
            </div>
            <div class="col-md-6 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Check</button>
                <a href="check_student_exists.php" class="btn btn-light">Clear</a>
            </div>
        </form>
    </div>

    <?php if ($searched): ?>
    <div class="alert <?php echo $exists ? 'status-found' : 'status-missing'; ?> mb-4">
        <strong><?php echo htmlspecialchars($summary); ?></strong>
        <div class="small mt-1">
            students: <?php echo count($studentRows); ?> |
            accounts: <?php echo count($accountRows); ?> |
            enrollments: <?php echo count($enrollmentRows); ?>
        </div>
    </div>

    <?php if (!empty($studentRows)): ?>
    <div class="page-card p-0 mb-4 overflow-hidden">
        <div class="p-3 border-bottom"><h2 class="h5 mb-0"><i class="fas fa-id-card"></i> students table</h2></div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Record ID</th>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Aadhar</th>
                        <th>Mobile</th>
                        <th>Email</th>
                        <th>Course</th>
                        <th>Status</th>
                        <th>Registered</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($studentRows as $row): ?>
                    <tr>
                        <td><?php echo (int)$row['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($row['student_id']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['aadhar']); ?></td>
                        <td><?php echo htmlspecialchars($row['mobile']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo htmlspecialchars($row['course_name'] ?? ('ID ' . ($row['course_id'] ?? ''))); ?></td>
                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['status']); ?></span></td>
                        <td><?php echo !empty($row['registration_date']) ? date('d M Y', strtotime($row['registration_date'])) : '—'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($accountRows)): ?>
    <div class="page-card p-0 mb-4 overflow-hidden">
        <div class="p-3 border-bottom"><h2 class="h5 mb-0"><i class="fas fa-user-circle"></i> student_accounts table</h2></div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Account ID</th>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Aadhar</th>
                        <th>Mobile</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accountRows as $row): ?>
                    <tr>
                        <td><?php echo (int)$row['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($row['student_id']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['aadhar']); ?></td>
                        <td><?php echo htmlspecialchars($row['mobile']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo htmlspecialchars($row['status'] ?? '—'); ?></td>
                        <td><?php echo !empty($row['created_at']) ? date('d M Y', strtotime($row['created_at'])) : '—'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($enrollmentRows)): ?>
    <div class="page-card p-0 mb-4 overflow-hidden">
        <div class="p-3 border-bottom"><h2 class="h5 mb-0"><i class="fas fa-layer-group"></i> student_enrollments</h2></div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Enrollment ID</th>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Course</th>
                        <th>Scheme ID</th>
                        <th>Status</th>
                        <th>Registered</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($enrollmentRows as $row): ?>
                    <tr>
                        <td><?php echo (int)$row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['course_name'] ?? ('ID ' . ($row['course_id'] ?? ''))); ?></td>
                        <td><?php echo htmlspecialchars((string)($row['scheme_id'] ?? '—')); ?></td>
                        <td><?php echo htmlspecialchars($row['status']); ?></td>
                        <td><?php echo !empty($row['registered_at']) ? date('d M Y', strtotime($row['registered_at'])) : '—'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$exists): ?>
    <div class="page-card p-4">
        <h2 class="h5">What to do next</h2>
        <ul class="mb-0">
            <li>Ask the student to register again from the course <strong>Apply Now</strong> link (preferably on a computer).</li>
            <li>Or add them manually from <a href="students.php">Admin → Students</a>.</li>
        </ul>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
