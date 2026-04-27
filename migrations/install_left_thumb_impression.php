<?php
/**
 * Left Thumb Impression Migration Installer
 *
 * Usage:
 *   php install_left_thumb_impression.php install
 *   php install_left_thumb_impression.php verify
 *   php install_left_thumb_impression.php rollback
 */

require_once __DIR__ . '/../config/config.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection is not available.\n");
}

function out($message) {
    if (php_sapi_name() === 'cli') {
        echo $message . PHP_EOL;
    } else {
        echo htmlspecialchars($message) . "<br>\n";
    }
}

function columnExists(mysqli $conn, $table, $column) {
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = ($result && $result->num_rows > 0);
    $stmt->close();
    return $exists;
}

function install(mysqli $conn) {
    if (columnExists($conn, 'students', 'left_thumb_impression')) {
        out("left_thumb_impression column already exists. Nothing to do.");
        return true;
    }

    $sql = "ALTER TABLE students ADD COLUMN left_thumb_impression VARCHAR(255) NULL DEFAULT NULL COMMENT 'Path to left hand thumb impression image' AFTER signature";
    if ($conn->query($sql)) {
        out("left_thumb_impression column added successfully.");
        return true;
    }

    out("Failed to add left_thumb_impression column: " . $conn->error);
    return false;
}

function verify(mysqli $conn) {
    if (columnExists($conn, 'students', 'left_thumb_impression')) {
        out("Verification successful: left_thumb_impression column exists.");
        return true;
    }

    out("Verification failed: left_thumb_impression column is missing.");
    return false;
}

function rollback(mysqli $conn) {
    if (!columnExists($conn, 'students', 'left_thumb_impression')) {
        out("left_thumb_impression column does not exist. Nothing to rollback.");
        return true;
    }

    $sql = "ALTER TABLE students DROP COLUMN left_thumb_impression";
    if ($conn->query($sql)) {
        out("left_thumb_impression column dropped successfully.");
        return true;
    }

    out("Failed to drop left_thumb_impression column: " . $conn->error);
    return false;
}

$action = $argv[1] ?? ($_GET['action'] ?? 'verify');
$action = strtolower(trim($action));

switch ($action) {
    case 'install':
        $ok = install($conn);
        break;
    case 'verify':
        $ok = verify($conn);
        break;
    case 'rollback':
        $ok = rollback($conn);
        break;
    default:
        out("Unknown action: " . $action);
        out("Valid actions: install, verify, rollback");
        $ok = false;
        break;
}

$conn->close();
exit($ok ? 0 : 1);
