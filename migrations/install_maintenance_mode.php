<?php
/**
 * Migration: Install Maintenance Mode System
 * This script creates the maintenance_mode table
 */

require_once __DIR__ . '/../config/config.php';

echo "==============================================\n";
echo "Installing Maintenance Mode System\n";
echo "==============================================\n\n";

try {
    // Check if table already exists
    $check_table = $conn->query("SHOW TABLES LIKE 'maintenance_mode'");
    
    if ($check_table->num_rows > 0) {
        echo "✓ Table 'maintenance_mode' already exists.\n";
    } else {
        // Create maintenance_mode table
        $create_table_sql = "CREATE TABLE `maintenance_mode` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `is_enabled` tinyint(1) DEFAULT 0,
            `maintenance_title` varchar(255) DEFAULT 'Site Under Maintenance',
            `maintenance_message` text DEFAULT 'We are currently performing scheduled maintenance. We will be back soon!',
            `end_time` datetime DEFAULT NULL,
            `show_countdown` tinyint(1) DEFAULT 1,
            `show_contact` tinyint(1) DEFAULT 1,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($create_table_sql)) {
            echo "✓ Table 'maintenance_mode' created successfully.\n";
            
            // Insert default record
            $insert_sql = "INSERT INTO maintenance_mode (id, is_enabled) VALUES (1, 0)";
            if ($conn->query($insert_sql)) {
                echo "✓ Default maintenance settings inserted.\n";
            } else {
                echo "✗ Error inserting default settings: " . $conn->error . "\n";
            }
        } else {
            echo "✗ Error creating table: " . $conn->error . "\n";
        }
    }
    
    echo "\n==============================================\n";
    echo "Installation Complete!\n";
    echo "==============================================\n\n";
    echo "Next Steps:\n";
    echo "1. Go to Admin Panel → Maintenance Mode Management\n";
    echo "2. Configure your maintenance message and countdown\n";
    echo "3. Enable/Disable maintenance mode as needed\n\n";
    echo "Note: Admins can always access the site even when maintenance mode is active.\n\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

$conn->close();
?>
