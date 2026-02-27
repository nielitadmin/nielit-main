<?php
/**
 * Schemes Module Database Installation Script
 * This script creates the necessary tables for the schemes/projects module
 */

require_once __DIR__ . '/../config/config.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Install Schemes Module</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; margin: 10px 0; }
        .info { color: #004085; padding: 10px; background: #cce5ff; border: 1px solid #b8daff; border-radius: 4px; margin: 10px 0; }
        h1 { color: #333; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <h1>Schemes Module Installation</h1>";

// Read SQL file
$sql_file = __DIR__ . '/database_schemes_system.sql';
if (!file_exists($sql_file)) {
    echo "<div class='error'>Error: SQL file not found at $sql_file</div>";
    exit;
}

$sql = file_get_contents($sql_file);

// Split SQL into individual queries
$queries = array_filter(array_map('trim', explode(';', $sql)));

$success_count = 0;
$error_count = 0;
$errors = [];

foreach ($queries as $query) {
    if (empty($query)) continue;
    
    if ($conn->query($query) === TRUE) {
        $success_count++;
    } else {
        $error_count++;
        $errors[] = $conn->error;
    }
}

echo "<div class='info'>Total queries executed: " . count($queries) . "</div>";
echo "<div class='success'>Successful: $success_count</div>";

if ($error_count > 0) {
    echo "<div class='error'>Failed: $error_count</div>";
    echo "<div class='error'><strong>Errors:</strong><br>";
    foreach ($errors as $error) {
        echo "- " . htmlspecialchars($error) . "<br>";
    }
    echo "</div>";
} else {
    echo "<div class='success'><strong>✓ Installation completed successfully!</strong></div>";
    echo "<div class='info'>
        <strong>Next Steps:</strong><br>
        1. Go to Admin Dashboard<br>
        2. Navigate to 'Schemes/Projects' menu<br>
        3. Start managing schemes and linking them to courses
    </div>";
}

echo "<a href='admin/manage_schemes.php' class='btn'>Go to Schemes Management</a>";
echo "<a href='../admin/dashboard.php' class='btn' style='background: #6c757d; margin-left: 10px;'>Go to Dashboard</a>";

echo "</body></html>";

$conn->close();
?>
