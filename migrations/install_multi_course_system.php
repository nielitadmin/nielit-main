<?php
/**
 * Install Multi-Course Student System
 * Run: php migrations/install_multi_course_system.php
 * Browser: /migrations/install_multi_course_system.php
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== NIELIT Multi-Course System Installation ===\n\n";

$steps = [];

// student_accounts
$sql1 = "CREATE TABLE IF NOT EXISTS student_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    aadhar VARCHAR(12) NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    mobile VARCHAR(20) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    dob DATE DEFAULT NULL,
    gender ENUM('Male','Female','Other') DEFAULT 'Male',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_student_id (student_id),
    UNIQUE KEY uk_aadhar (aadhar),
    KEY idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='One account per person - global Student ID'";

if ($conn->query($sql1)) {
    $steps[] = 'OK: student_accounts table';
} else {
    $steps[] = 'FAIL student_accounts: ' . $conn->error;
}

// student_enrollments
$sql2 = "CREATE TABLE IF NOT EXISTS student_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    course_id INT NOT NULL,
    student_record_id INT NOT NULL COMMENT 'FK to students.id for this course registration',
    batch_id INT DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL DEFAULT NULL,
    approved_by VARCHAR(100) DEFAULT NULL,
    UNIQUE KEY uk_account_course (account_id, course_id),
    KEY idx_course (course_id),
    KEY idx_student_record (student_record_id),
    KEY idx_status (status),
    CONSTRAINT fk_enrollment_account FOREIGN KEY (account_id) REFERENCES student_accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_enrollment_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='One enrollment per course per account'";

if ($conn->query($sql2)) {
    $steps[] = 'OK: student_enrollments table';
} else {
    $steps[] = 'FAIL student_enrollments: ' . $conn->error;
}

// account_id on students
$col = $conn->query("SHOW COLUMNS FROM students LIKE 'account_id'");
if (!$col || $col->num_rows === 0) {
    if ($conn->query("ALTER TABLE students ADD COLUMN account_id INT NULL DEFAULT NULL AFTER student_id, ADD KEY idx_account_id (account_id)")) {
        $steps[] = 'OK: students.account_id column added';
    } else {
        $steps[] = 'FAIL students.account_id: ' . $conn->error;
    }
} else {
    $steps[] = 'SKIP: students.account_id already exists';
}

// student_record_id on batch_students
$bsCol = $conn->query("SHOW COLUMNS FROM batch_students LIKE 'student_record_id'");
if ($bsCol && $bsCol->num_rows === 0) {
    $tableExists = $conn->query("SHOW TABLES LIKE 'batch_students'");
    if ($tableExists && $tableExists->num_rows > 0) {
        if ($conn->query("ALTER TABLE batch_students ADD COLUMN student_record_id INT NULL DEFAULT NULL AFTER student_id, ADD KEY idx_student_record_id (student_record_id)")) {
            $steps[] = 'OK: batch_students.student_record_id added';
        } else {
            $steps[] = 'WARN batch_students.student_record_id: ' . $conn->error;
        }
    }
} else {
    $steps[] = 'SKIP: batch_students.student_record_id exists or batch_students missing';
}

// Centre code constant note
$steps[] = 'INFO: Global ID format NIELIT/YYYY/BBSR/####';

foreach ($steps as $s) {
    echo $s . "\n";
}

echo "\n=== Installation complete ===\n";
echo "Next: Run migrations/migrate_multi_course_data.php to link existing students.\n";

$conn->close();
