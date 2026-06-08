<?php
/**
 * Scheme-based dual enrollment: same course, different projects/schemes.
 * Run: php migrations/install_scheme_enrollment.php
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: text/plain; charset=utf-8');
echo "=== Scheme-Based Enrollment Migration ===\n\n";

$steps = [];

// students.scheme_id
$col = $conn->query("SHOW COLUMNS FROM students LIKE 'scheme_id'");
if (!$col || $col->num_rows === 0) {
    if ($conn->query("ALTER TABLE students ADD COLUMN scheme_id INT NULL DEFAULT NULL AFTER course_id, ADD KEY idx_students_scheme (scheme_id)")) {
        $steps[] = 'OK: students.scheme_id added';
    } else {
        $steps[] = 'FAIL students.scheme_id: ' . $conn->error;
    }
} else {
    $steps[] = 'SKIP: students.scheme_id exists';
}

// student_enrollments.scheme_id + unique key update
$enrCol = $conn->query("SHOW COLUMNS FROM student_enrollments LIKE 'scheme_id'");
if ($enrCol && $enrCol->num_rows === 0) {
    $tableExists = $conn->query("SHOW TABLES LIKE 'student_enrollments'");
    if ($tableExists && $tableExists->num_rows > 0) {
        if ($conn->query("ALTER TABLE student_enrollments ADD COLUMN scheme_id INT NULL DEFAULT NULL AFTER course_id")) {
            $steps[] = 'OK: student_enrollments.scheme_id added';
        } else {
            $steps[] = 'FAIL student_enrollments.scheme_id: ' . $conn->error;
        }
    }
} else {
    $steps[] = 'SKIP: student_enrollments.scheme_id exists or table missing';
}

// Drop old unique (account_id, course_id) and add (account_id, course_id, scheme_id)
$idx = $conn->query("SHOW INDEX FROM student_enrollments WHERE Key_name = 'uk_account_course'");
if ($idx && $idx->num_rows > 0) {
    if ($conn->query("ALTER TABLE student_enrollments DROP INDEX uk_account_course")) {
        $steps[] = 'OK: dropped uk_account_course';
    } else {
        $steps[] = 'WARN drop uk_account_course: ' . $conn->error;
    }
}

$idx2 = $conn->query("SHOW INDEX FROM student_enrollments WHERE Key_name = 'uk_account_course_scheme'");
if (!$idx2 || $idx2->num_rows === 0) {
    $tableExists = $conn->query("SHOW TABLES LIKE 'student_enrollments'");
    if ($tableExists && $tableExists->num_rows > 0) {
        if ($conn->query("ALTER TABLE student_enrollments ADD UNIQUE KEY uk_account_course_scheme (account_id, course_id, scheme_id)")) {
            $steps[] = 'OK: uk_account_course_scheme added';
        } else {
            $steps[] = 'WARN uk_account_course_scheme: ' . $conn->error;
        }
    }
} else {
    $steps[] = 'SKIP: uk_account_course_scheme exists';
}

// Backfill student_enrollments.scheme_id from students row
$backfill = $conn->query(
    "UPDATE student_enrollments se
     INNER JOIN students s ON s.id = se.student_record_id
     SET se.scheme_id = s.scheme_id
     WHERE se.scheme_id IS NULL AND s.scheme_id IS NOT NULL"
);
if ($backfill) {
    $steps[] = 'OK: backfilled enrollment scheme_id (' . $conn->affected_rows . ' rows)';
}

foreach ($steps as $s) {
    echo $s . "\n";
}

echo "\n=== Done ===\n";
$conn->close();
