<?php
// migrate_add_enrollment_closing_date.php
require_once __DIR__ . '/config/config.php';

$sql = "ALTER TABLE `courses` ADD COLUMN `enrollment_closing_date` DATE NULL AFTER `end_date`";
if ($conn->query($sql) === TRUE) {
    echo "Migration successful: enrollment_closing_date column added.";
} else {
    echo "Migration failed: " . $conn->error;
}
$conn->close();
?>
