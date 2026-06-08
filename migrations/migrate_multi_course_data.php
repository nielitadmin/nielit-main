<?php
/**
 * Migrate existing students into student_accounts + student_enrollments
 * Run after install_multi_course_system.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/multi_course_helper.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Multi-Course Data Migration ===\n\n";

if (!isMultiCourseSystemInstalled($conn)) {
    echo "ERROR: Run migrations/install_multi_course_system.php first.\n";
    exit(1);
}

$sql = "SELECT * FROM students WHERE aadhar IS NOT NULL AND aadhar != '' ORDER BY id ASC";
$result = $conn->query($sql);

if (!$result) {
    echo "ERROR: " . $conn->error . "\n";
    exit(1);
}

$accounts_created = 0;
$enrollments_created = 0;
$skipped = 0;
$errors = 0;

while ($row = $result->fetch_assoc()) {
    $aadhar = normalizeAadhar($row['aadhar']);
    if ($aadhar === '') {
        $skipped++;
        continue;
    }

    $existing = findAccountByAadhar($conn, $aadhar);
    $accountId = null;

    if ($existing && !empty($existing['id'])) {
        $accountId = (int)$existing['id'];
    } else {
        $accountId = createStudentAccount($conn, [
            'student_id' => $row['student_id'],
            'aadhar' => $aadhar,
            'name' => $row['name'],
            'email' => $row['email'],
            'mobile' => $row['mobile'],
            'password' => $row['password'],
            'dob' => $row['dob'] ?? null,
            'gender' => $row['gender'] ?? 'Male',
        ]);

        if ($accountId) {
            $accounts_created++;
        } else {
            $errors++;
            echo "WARN: Could not create account for students.id={$row['id']}\n";
            continue;
        }
    }

    linkStudentRecordToAccount($conn, (int)$row['id'], $accountId);

    $courseId = (int)($row['course_id'] ?? 0);
    if ($courseId > 0) {
        $chk = $conn->prepare('SELECT id FROM student_enrollments WHERE account_id = ? AND course_id = ? LIMIT 1');
        if ($chk) {
            $chk->bind_param('ii', $accountId, $courseId);
            $chk->execute();
            if ($chk->get_result()->num_rows === 0) {
                if (createStudentEnrollment($conn, $accountId, $courseId, (int)$row['id'], $row['status'] ?? 'pending')) {
                    $enrollments_created++;
                }
            }
            $chk->close();
        }
    }
}

echo "Accounts created: $accounts_created\n";
echo "Enrollments created: $enrollments_created\n";
echo "Skipped (no aadhar): $skipped\n";
echo "Errors: $errors\n";
echo "\n=== Migration complete ===\n";

$conn->close();
