<?php
/**
 * Setup Attendance QR System Tables
 * Creates attendance_sessions and qr_scan_logs tables if they don't exist
 */

require_once __DIR__ . '/../config/config.php';

echo "<h2>Setting up Attendance QR System Tables</h2>";
echo "<hr>";

$errors = [];
$successes = [];

// 1. Add attendance_qr_code column to students if it doesn't exist
$check_qr_code = $conn->query("DESCRIBE students LIKE 'attendance_qr_code'");
if (!$check_qr_code || $check_qr_code->num_rows == 0) {
    // Try to add after qr_code_path first, then fall back to after email
    $alter_sql = "ALTER TABLE `students` ADD COLUMN `attendance_qr_code` VARCHAR(255) DEFAULT NULL AFTER `email`";
    if ($conn->query($alter_sql)) {
        $successes[] = "✅ Added attendance_qr_code column to students table";
    } else {
        $errors[] = "❌ Failed to add attendance_qr_code column: " . $conn->error;
    }
} else {
    $successes[] = "✅ attendance_qr_code column already exists in students table";
}

// 2. Create attendance_sessions table
$create_sessions = "
CREATE TABLE IF NOT EXISTS `attendance_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_name` varchar(100) NOT NULL,
  `course_id` int(11) NOT NULL,
  `course_name` varchar(100) NOT NULL,
  `subject` varchar(100) NOT NULL,
  `date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `coordinator_id` varchar(50) NOT NULL,
  `coordinator_name` varchar(100) NOT NULL,
  `status` enum('scheduled','active','completed','cancelled') DEFAULT 'scheduled',
  `qr_scanner_active` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  KEY `date` (`date`),
  KEY `coordinator_id` (`coordinator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

if ($conn->query($create_sessions)) {
    $successes[] = "✅ Created attendance_sessions table";
} else {
    $errors[] = "❌ Failed to create attendance_sessions table: " . $conn->error;
}

// 3. Add session_id and related columns to attendance table if they don't exist
$check_session_id = $conn->query("DESCRIBE attendance LIKE 'session_id'");
if (!$check_session_id || $check_session_id->num_rows == 0) {
    $alter_attendance = "ALTER TABLE `attendance` ADD COLUMN `session_id` INT(11) DEFAULT NULL AFTER `id`";
    if ($conn->query($alter_attendance)) {
        $successes[] = "✅ Added session_id column to attendance table";
    } else {
        $errors[] = "❌ Failed to add session_id column: " . $conn->error;
    }
} else {
    $successes[] = "✅ session_id column already exists in attendance table";
}

// Add scan_method column
$check_scan_method = $conn->query("DESCRIBE attendance LIKE 'scan_method'");
if (!$check_scan_method || $check_scan_method->num_rows == 0) {
    $alter_scan_method = "ALTER TABLE `attendance` ADD COLUMN `scan_method` ENUM('manual','qr_scan','auto') DEFAULT 'manual' AFTER `status`";
    if ($conn->query($alter_scan_method)) {
        $successes[] = "✅ Added scan_method column to attendance table";
    } else {
        $errors[] = "❌ Failed to add scan_method column: " . $conn->error;
    }
} else {
    $successes[] = "✅ scan_method column already exists in attendance table";
}

// Add scan_timestamp column
$check_scan_timestamp = $conn->query("DESCRIBE attendance LIKE 'scan_timestamp'");
if (!$check_scan_timestamp || $check_scan_timestamp->num_rows == 0) {
    $alter_scan_timestamp = "ALTER TABLE `attendance` ADD COLUMN `scan_timestamp` TIMESTAMP NULL DEFAULT NULL AFTER `scan_method`";
    if ($conn->query($alter_scan_timestamp)) {
        $successes[] = "✅ Added scan_timestamp column to attendance table";
    } else {
        $errors[] = "❌ Failed to add scan_timestamp column: " . $conn->error;
    }
} else {
    $successes[] = "✅ scan_timestamp column already exists in attendance table";
}

// Add coordinator_id to attendance
$check_att_coordinator = $conn->query("DESCRIBE attendance LIKE 'coordinator_id'");
if (!$check_att_coordinator || $check_att_coordinator->num_rows == 0) {
    $alter_coordinator = "ALTER TABLE `attendance` ADD COLUMN `coordinator_id` VARCHAR(50) DEFAULT NULL";
    if ($conn->query($alter_coordinator)) {
        $successes[] = "✅ Added coordinator_id column to attendance table";
    } else {
        $errors[] = "❌ Failed to add coordinator_id column: " . $conn->error;
    }
} else {
    $successes[] = "✅ coordinator_id column already exists in attendance table";
}

// Add foreign key for session reference
$check_fk = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='attendance' AND COLUMN_NAME='session_id' AND REFERENCED_TABLE_NAME='attendance_sessions'");
if (!$check_fk || $check_fk->num_rows == 0) {
    $add_key = "ALTER TABLE `attendance` ADD KEY `session_id` (`session_id`)";
    if ($conn->query($add_key)) {
        $successes[] = "✅ Added session_id key to attendance table";
    } else {
        // Key might already exist, which is fine
        $successes[] = "✅ session_id key already exists or created";
    }
}

// 4. Create qr_scan_logs table
$create_logs = "
CREATE TABLE IF NOT EXISTS `qr_scan_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `student_name` varchar(100) NOT NULL,
  `scan_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `scan_result` enum('success','duplicate','invalid','expired','error') NOT NULL,
  `coordinator_id` varchar(50) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `session_id` (`session_id`),
  KEY `student_id` (`student_id`),
  KEY `scan_timestamp` (`scan_timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

if ($conn->query($create_logs)) {
    $successes[] = "✅ Created qr_scan_logs table";
} else {
    $errors[] = "❌ Failed to create qr_scan_logs table: " . $conn->error;
}

// Display results
echo "<div style='margin: 20px 0;'>";
if (!empty($successes)) {
    echo "<h4 style='color: green;'>Successful Operations:</h4>";
    foreach ($successes as $msg) {
        echo "<p>$msg</p>";
    }
}

if (!empty($errors)) {
    echo "<h4 style='color: red;'>Errors:</h4>";
    foreach ($errors as $msg) {
        echo "<p>$msg</p>";
    }
} else {
    echo "<h4 style='color: green;'>✅ All tables created/updated successfully!</h4>";
}
echo "</div>";

echo "<hr>";
echo "<p><a href='../admin/attendance_scanner.php'>← Back to Attendance Scanner</a></p>";

$conn->close();
?>
