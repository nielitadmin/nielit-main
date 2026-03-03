<?php
/**
 * Debug Script for Edit Batch Issues
 * Run this on the server to diagnose problems
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../../config/config.php';

echo "<h2>Edit Batch Diagnostic Tool</h2>";
echo "<hr>";

// 1. Check if batch_functions.php exists and is readable
echo "<h3>1. Checking batch_functions.php</h3>";
$functions_file = __DIR__ . '/../includes/batch_functions.php';
if (file_exists($functions_file)) {
    echo "✅ File exists: $functions_file<br>";
    if (is_readable($functions_file)) {
        echo "✅ File is readable<br>";
        require_once $functions_file;
        if (function_exists('updateBatch')) {
            echo "✅ updateBatch() function is loaded<br>";
        } else {
            echo "❌ updateBatch() function NOT found<br>";
        }
    } else {
        echo "❌ File is NOT readable<br>";
    }
} else {
    echo "❌ File does NOT exist: $functions_file<br>";
}

// 2. Check database connection
echo "<h3>2. Checking Database Connection</h3>";
if (isset($conn) && $conn->ping()) {
    echo "✅ Database connection is active<br>";
    echo "Database: " . $conn->get_server_info() . "<br>";
} else {
    echo "❌ Database connection FAILED<br>";
    die();
}

// 3. Check batches table structure
echo "<h3>3. Checking Batches Table Structure</h3>";
$result = $conn->query("DESCRIBE batches");
if ($result) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table><br>";
    
    // Check for required columns
    $required_columns = [
        'id', 'batch_name', 'start_date', 'end_date', 'training_fees', 
        'seats_total', 'batch_coordinator', 'status', 'scheme_id',
        'admission_order_ref', 'admission_order_date', 'examination_month',
        'class_time', 'copy_to_list', 'location'
    ];
    
    echo "<h4>Required Columns Check:</h4>";
    foreach ($required_columns as $col) {
        if (in_array($col, $columns)) {
            echo "✅ $col<br>";
        } else {
            echo "❌ <strong>MISSING: $col</strong><br>";
        }
    }
} else {
    echo "❌ Could not describe batches table: " . $conn->error . "<br>";
}

// 4. Test a sample batch query
echo "<h3>4. Testing Sample Batch Query</h3>";
$test_sql = "SELECT * FROM batches LIMIT 1";
$test_result = $conn->query($test_sql);
if ($test_result) {
    if ($test_result->num_rows > 0) {
        echo "✅ Successfully queried batches table<br>";
        $sample = $test_result->fetch_assoc();
        echo "Sample batch ID: " . $sample['id'] . "<br>";
        echo "Sample batch name: " . $sample['batch_name'] . "<br>";
    } else {
        echo "⚠️ Batches table is empty<br>";
    }
} else {
    echo "❌ Query failed: " . $conn->error . "<br>";
}

// 5. Test updateBatch function if it exists
if (function_exists('updateBatch')) {
    echo "<h3>5. Testing updateBatch Function</h3>";
    echo "Function exists and can be called<br>";
    echo "Function signature: updateBatch(\$batch_id, \$data, \$conn)<br>";
}

// 6. Check PHP version and extensions
echo "<h3>6. PHP Environment</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "MySQLi Extension: " . (extension_loaded('mysqli') ? '✅ Loaded' : '❌ Not loaded') . "<br>";

// 7. Check file permissions
echo "<h3>7. File Permissions</h3>";
$edit_batch_file = __DIR__ . '/edit_batch.php';
if (file_exists($edit_batch_file)) {
    echo "edit_batch.php: " . substr(sprintf('%o', fileperms($edit_batch_file)), -4) . "<br>";
} else {
    echo "❌ edit_batch.php not found<br>";
}

echo "<hr>";
echo "<p><strong>Diagnostic Complete!</strong></p>";
echo "<p>If you see any ❌ marks above, those are the issues that need to be fixed.</p>";

$conn->close();
?>
