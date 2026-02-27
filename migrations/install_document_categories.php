<?php
/**
 * Document Categories Migration Installer
 * 
 * This script installs the document category columns for the document upload enhancement feature.
 * It provides automated installation, verification, and rollback capabilities.
 * 
 * Usage:
 *   php install_document_categories.php install   - Install the migration
 *   php install_document_categories.php verify    - Verify the installation
 *   php install_document_categories.php rollback  - Rollback the migration
 * 
 * Feature: document-upload-enhancement
 * Requirements: 3.1, 3.2, 3.3, 3.4, 3.5
 */

// Include database configuration
require_once __DIR__ . '/../config/database.php';

// ANSI color codes for CLI output
define('COLOR_GREEN', "\033[0;32m");
define('COLOR_RED', "\033[0;31m");
define('COLOR_YELLOW', "\033[1;33m");
define('COLOR_BLUE', "\033[0;34m");
define('COLOR_RESET', "\033[0m");

// Document category columns configuration
$documentColumns = [
    'aadhar_card_doc' => [
        'type' => 'VARCHAR(255)',
        'nullable' => true,
        'default' => 'NULL',
        'comment' => 'Path to Aadhar card document',
        'after' => 'documents'
    ],
    'caste_certificate_doc' => [
        'type' => 'VARCHAR(255)',
        'nullable' => true,
        'default' => 'NULL',
        'comment' => 'Path to caste certificate document',
        'after' => 'aadhar_card_doc'
    ],
    'tenth_marksheet_doc' => [
        'type' => 'VARCHAR(255)',
        'nullable' => true,
        'default' => 'NULL',
        'comment' => 'Path to 10th marksheet/certificate',
        'after' => 'caste_certificate_doc'
    ],
    'twelfth_marksheet_doc' => [
        'type' => 'VARCHAR(255)',
        'nullable' => true,
        'default' => 'NULL',
        'comment' => 'Path to 12th marksheet/diploma certificate',
        'after' => 'tenth_marksheet_doc'
    ],
    'graduation_certificate_doc' => [
        'type' => 'VARCHAR(255)',
        'nullable' => true,
        'default' => 'NULL',
        'comment' => 'Path to graduation certificate',
        'after' => 'twelfth_marksheet_doc'
    ],
    'other_documents_doc' => [
        'type' => 'VARCHAR(255)',
        'nullable' => true,
        'default' => 'NULL',
        'comment' => 'Path to other supporting documents',
        'after' => 'graduation_certificate_doc'
    ]
];

// Indexes to create (for frequently queried mandatory documents)
$indexes = [
    'idx_aadhar_doc' => 'aadhar_card_doc',
    'idx_tenth_doc' => 'tenth_marksheet_doc',
    'idx_twelfth_doc' => 'twelfth_marksheet_doc'
];

/**
 * Print colored message to console
 */
function printMessage($message, $color = COLOR_RESET) {
    if (php_sapi_name() === 'cli') {
        echo $color . $message . COLOR_RESET . "\n";
    } else {
        echo $message . "<br>";
    }
}

/**
 * Check if a column exists in a table
 */
function columnExists($conn, $table, $column) {
    $query = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
              WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = ? 
              AND COLUMN_NAME = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

/**
 * Check if an index exists on a table
 */
function indexExists($conn, $table, $indexName) {
    $query = "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS 
              WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = ? 
              AND INDEX_NAME = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $table, $indexName);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

/**
 * Install the document category columns
 */
function install($conn) {
    global $documentColumns, $indexes;
    
    printMessage("\n=== Installing Document Category Columns ===\n", COLOR_BLUE);
    
    // Check if students table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'students'");
    if ($tableCheck->num_rows === 0) {
        printMessage("ERROR: students table does not exist!", COLOR_RED);
        return false;
    }
    
    // Add document category columns
    printMessage("Adding document category columns...", COLOR_BLUE);
    $columnsAdded = 0;
    
    foreach ($documentColumns as $columnName => $config) {
        if (columnExists($conn, 'students', $columnName)) {
            printMessage("  ⚠ Column '$columnName' already exists, skipping", COLOR_YELLOW);
            continue;
        }
        
        $sql = "ALTER TABLE students ADD COLUMN $columnName {$config['type']} ";
        $sql .= $config['nullable'] ? "NULL " : "NOT NULL ";
        $sql .= "DEFAULT {$config['default']} ";
        $sql .= "COMMENT '{$config['comment']}' ";
        $sql .= "AFTER {$config['after']}";
        
        if ($conn->query($sql)) {
            printMessage("  ✓ Added column: $columnName", COLOR_GREEN);
            $columnsAdded++;
        } else {
            printMessage("  ✗ Failed to add column '$columnName': " . $conn->error, COLOR_RED);
            return false;
        }
    }
    
    if ($columnsAdded === 0) {
        printMessage("All columns already exist.", COLOR_YELLOW);
    }
    
    // Create indexes
    printMessage("\nCreating indexes for frequently queried columns...", COLOR_BLUE);
    $indexesAdded = 0;
    
    foreach ($indexes as $indexName => $columnName) {
        if (indexExists($conn, 'students', $indexName)) {
            printMessage("  ⚠ Index '$indexName' already exists, skipping", COLOR_YELLOW);
            continue;
        }
        
        $sql = "CREATE INDEX $indexName ON students($columnName)";
        
        if ($conn->query($sql)) {
            printMessage("  ✓ Created index: $indexName on $columnName", COLOR_GREEN);
            $indexesAdded++;
        } else {
            printMessage("  ✗ Failed to create index '$indexName': " . $conn->error, COLOR_RED);
            return false;
        }
    }
    
    if ($indexesAdded === 0) {
        printMessage("All indexes already exist.", COLOR_YELLOW);
    }
    
    printMessage("\n✓ Installation completed successfully!", COLOR_GREEN);
    printMessage("Run 'php install_document_categories.php verify' to verify the installation.\n", COLOR_BLUE);
    
    return true;
}

/**
 * Verify the installation
 */
function verify($conn) {
    global $documentColumns, $indexes;
    
    printMessage("\n=== Verifying Document Category Installation ===\n", COLOR_BLUE);
    
    $allValid = true;
    
    // Check columns
    printMessage("Checking document category columns...", COLOR_BLUE);
    foreach ($documentColumns as $columnName => $config) {
        if (columnExists($conn, 'students', $columnName)) {
            printMessage("  ✓ Column '$columnName' exists", COLOR_GREEN);
        } else {
            printMessage("  ✗ Column '$columnName' is missing", COLOR_RED);
            $allValid = false;
        }
    }
    
    // Check indexes
    printMessage("\nChecking indexes...", COLOR_BLUE);
    foreach ($indexes as $indexName => $columnName) {
        if (indexExists($conn, 'students', $indexName)) {
            printMessage("  ✓ Index '$indexName' exists", COLOR_GREEN);
        } else {
            printMessage("  ✗ Index '$indexName' is missing", COLOR_RED);
            $allValid = false;
        }
    }
    
    // Check data integrity
    printMessage("\nChecking data integrity...", COLOR_BLUE);
    $query = "SELECT COUNT(*) as total FROM students";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    printMessage("  ✓ Total students in database: " . $row['total'], COLOR_GREEN);
    
    if ($allValid) {
        printMessage("\n✓ All checks passed! Installation is valid.", COLOR_GREEN);
    } else {
        printMessage("\n✗ Some checks failed. Please run 'php install_document_categories.php install'", COLOR_RED);
    }
    
    return $allValid;
}

/**
 * Rollback the installation
 */
function rollback($conn) {
    global $documentColumns, $indexes;
    
    printMessage("\n=== Rolling Back Document Category Columns ===\n", COLOR_YELLOW);
    printMessage("WARNING: This will remove all document category columns and their data!", COLOR_RED);
    printMessage("The legacy 'documents' column will be preserved.\n", COLOR_YELLOW);
    
    // Confirm rollback
    if (php_sapi_name() === 'cli') {
        echo "Type 'yes' to confirm rollback: ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        
        if (trim($line) !== 'yes') {
            printMessage("Rollback cancelled.", COLOR_YELLOW);
            return false;
        }
    }
    
    // Drop indexes first
    printMessage("Dropping indexes...", COLOR_BLUE);
    foreach ($indexes as $indexName => $columnName) {
        if (!indexExists($conn, 'students', $indexName)) {
            printMessage("  ⚠ Index '$indexName' does not exist, skipping", COLOR_YELLOW);
            continue;
        }
        
        $sql = "DROP INDEX $indexName ON students";
        if ($conn->query($sql)) {
            printMessage("  ✓ Dropped index: $indexName", COLOR_GREEN);
        } else {
            printMessage("  ✗ Failed to drop index '$indexName': " . $conn->error, COLOR_RED);
        }
    }
    
    // Drop columns (in reverse order)
    printMessage("\nDropping document category columns...", COLOR_BLUE);
    $columnsToRemove = array_reverse(array_keys($documentColumns));
    
    foreach ($columnsToRemove as $columnName) {
        if (!columnExists($conn, 'students', $columnName)) {
            printMessage("  ⚠ Column '$columnName' does not exist, skipping", COLOR_YELLOW);
            continue;
        }
        
        $sql = "ALTER TABLE students DROP COLUMN $columnName";
        if ($conn->query($sql)) {
            printMessage("  ✓ Dropped column: $columnName", COLOR_GREEN);
        } else {
            printMessage("  ✗ Failed to drop column '$columnName': " . $conn->error, COLOR_RED);
            return false;
        }
    }
    
    printMessage("\n✓ Rollback completed successfully!", COLOR_GREEN);
    return true;
}

/**
 * Display usage information
 */
function showUsage() {
    printMessage("\nDocument Categories Migration Installer", COLOR_BLUE);
    printMessage("========================================\n", COLOR_BLUE);
    printMessage("Usage:");
    printMessage("  php install_document_categories.php install   - Install the migration");
    printMessage("  php install_document_categories.php verify    - Verify the installation");
    printMessage("  php install_document_categories.php rollback  - Rollback the migration\n");
}

// Main execution
if (php_sapi_name() !== 'cli' && !isset($_GET['action'])) {
    showUsage();
    exit;
}

$action = php_sapi_name() === 'cli' ? ($argv[1] ?? '') : ($_GET['action'] ?? '');

if (empty($action)) {
    showUsage();
    exit;
}

// Connect to database
$conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    printMessage("Database connection failed: " . $conn->connect_error, COLOR_RED);
    exit(1);
}

// Execute action
switch ($action) {
    case 'install':
        $success = install($conn);
        exit($success ? 0 : 1);
        
    case 'verify':
        $success = verify($conn);
        exit($success ? 0 : 1);
        
    case 'rollback':
        $success = rollback($conn);
        exit($success ? 0 : 1);
        
    default:
        printMessage("Unknown action: $action", COLOR_RED);
        showUsage();
        exit(1);
}

$conn->close();
