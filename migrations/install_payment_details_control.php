<?php
/**
 * Migration: Add Payment Details Control to Courses
 * 
 * This migration adds a column to control whether payment details
 * are required or optional for each course during student registration.
 */

require_once __DIR__ . '/../config/config.php';

echo "=== Payment Details Control Migration ===\n";
echo "Adding payment_details_required column to courses table...\n";

try {
    // Read and execute the SQL migration
    $sql = file_get_contents(__DIR__ . '/add_payment_details_control.sql');
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement) && substr($statement, 0, 2) !== '--') {
            echo "Executing: " . substr($statement, 0, 50) . "...\n";
            
            if ($conn->query($statement)) {
                echo "✓ Success\n";
            } else {
                echo "✗ Error: " . $conn->error . "\n";
            }
        }
    }
    
    echo "\n=== Migration Complete ===\n";
    echo "Payment details control feature has been added successfully!\n";
    echo "Administrators can now control payment requirements per course.\n";
    
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

$conn->close();
?>