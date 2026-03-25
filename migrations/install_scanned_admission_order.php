<?php
/**
 * Migration: Install Scanned Admission Order Upload Feature
 * Date: 2026-03-25
 * Description: Adds database columns and creates directories for scanned admission order uploads
 */

require_once __DIR__ . '/../config/config.php';

function installScannedAdmissionOrderFeature($conn) {
    try {
        echo "Installing Scanned Admission Order Upload Feature...\n";
        
        // Check if columns already exist
        $check_sql = "SHOW COLUMNS FROM batches LIKE 'scanned_admission_order'";
        $result = $conn->query($check_sql);
        
        if ($result->num_rows > 0) {
            echo "✅ Scanned admission order columns already exist!\n";
        } else {
            echo "📝 Adding scanned admission order columns...\n";
            
            // Add columns one by one to avoid issues
            $columns = [
                "ALTER TABLE `batches` ADD COLUMN `scanned_admission_order` VARCHAR(255) NULL COMMENT 'Path to uploaded scanned admission order file' AFTER `updated_at`",
                "ALTER TABLE `batches` ADD COLUMN `scanned_order_uploaded_at` TIMESTAMP NULL COMMENT 'When the scanned order was uploaded' AFTER `scanned_admission_order`",
                "ALTER TABLE `batches` ADD COLUMN `scanned_order_uploaded_by` INT(11) NULL COMMENT 'Admin ID who uploaded the scanned order' AFTER `scanned_order_uploaded_at`",
                "ALTER TABLE `batches` ADD COLUMN `scanned_order_locked` TINYINT(1) DEFAULT 0 COMMENT 'Whether the scanned order is locked (1=locked, 0=unlocked)' AFTER `scanned_order_uploaded_by`",
                "ALTER TABLE `batches` ADD COLUMN `scanned_order_locked_at` TIMESTAMP NULL COMMENT 'When the scanned order was locked' AFTER `scanned_order_locked`",
                "ALTER TABLE `batches` ADD COLUMN `scanned_order_locked_by` INT(11) NULL COMMENT 'Admin ID who locked the scanned order' AFTER `scanned_order_locked_at`"
            ];
            
            foreach ($columns as $sql) {
                if (!$conn->query($sql)) {
                    // Check if column already exists
                    if (strpos($conn->error, 'Duplicate column name') !== false) {
                        echo "⚠️ Column already exists, skipping...\n";
                        continue;
                    }
                    throw new Exception("Error adding column: " . $conn->error . "\nSQL: $sql");
                }
            }
            
            echo "✅ Columns added successfully!\n";
        }
        
        // Add foreign key constraints (with error handling)
        echo "📝 Adding foreign key constraints...\n";
        
        $constraints = [
            "ALTER TABLE `batches` ADD CONSTRAINT `fk_scanned_order_uploaded_by` FOREIGN KEY (`scanned_order_uploaded_by`) REFERENCES `admin` (`id`) ON DELETE SET NULL",
            "ALTER TABLE `batches` ADD CONSTRAINT `fk_scanned_order_locked_by` FOREIGN KEY (`scanned_order_locked_by`) REFERENCES `admin` (`id`) ON DELETE SET NULL"
        ];
        
        foreach ($constraints as $sql) {
            if (!$conn->query($sql)) {
                // Check if constraint already exists
                if (strpos($conn->error, 'Duplicate key name') !== false || strpos($conn->error, 'already exists') !== false) {
                    echo "⚠️ Foreign key constraint already exists, skipping...\n";
                    continue;
                }
                echo "⚠️ Warning: Could not add foreign key constraint: " . $conn->error . "\n";
            }
        }
        
        // Add indexes (with error handling)
        echo "📝 Adding indexes...\n";
        
        $indexes = [
            "CREATE INDEX `idx_scanned_order_locked` ON `batches` (`scanned_order_locked`)",
            "CREATE INDEX `idx_scanned_order_uploaded_at` ON `batches` (`scanned_order_uploaded_at`)"
        ];
        
        foreach ($indexes as $sql) {
            if (!$conn->query($sql)) {
                // Check if index already exists
                if (strpos($conn->error, 'Duplicate key name') !== false || strpos($conn->error, 'already exists') !== false) {
                    echo "⚠️ Index already exists, skipping...\n";
                    continue;
                }
                echo "⚠️ Warning: Could not add index: " . $conn->error . "\n";
            }
        }
        
        echo "✅ Database schema updated!\n";
        
        // Create upload directory
        $upload_dir = __DIR__ . '/../uploads/scanned_admission_orders';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                throw new Exception("Failed to create upload directory: $upload_dir");
            }
            echo "✅ Created upload directory: $upload_dir\n";
        } else {
            echo "✅ Upload directory already exists: $upload_dir\n";
        }
        
        // Create .htaccess for security
        $htaccess_file = $upload_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "# Prevent direct access to uploaded files\n";
            $htaccess_content .= "Options -Indexes\n";
            $htaccess_content .= "<Files *.php>\n";
            $htaccess_content .= "    Deny from all\n";
            $htaccess_content .= "</Files>\n";
            
            if (file_put_contents($htaccess_file, $htaccess_content)) {
                echo "✅ Created .htaccess security file\n";
            }
        } else {
            echo "✅ .htaccess security file already exists\n";
        }
        
        // Create .gitkeep file
        $gitkeep_file = $upload_dir . '/.gitkeep';
        if (!file_exists($gitkeep_file)) {
            file_put_contents($gitkeep_file, '');
            echo "✅ Created .gitkeep file\n";
        } else {
            echo "✅ .gitkeep file already exists\n";
        }
        
        echo "\n🎉 Scanned Admission Order Upload Feature installed successfully!\n";
        echo "\n📋 Next steps:\n";
        echo "1. Navigate to any batch details page\n";
        echo "2. Look for the 'Scanned Admission Order' section\n";
        echo "3. Upload a PDF file of the signed admission order\n";
        echo "4. Lock the document when ready\n";
        echo "5. Download the document anytime\n";
        
        return true;
        
    } catch (Exception $e) {
        echo "❌ Error installing Scanned Admission Order Upload Feature: " . $e->getMessage() . "\n";
        return false;
    }
}

// Run migration if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $action = $argv[1] ?? 'install';
    
    if ($action === 'install') {
        $success = installScannedAdmissionOrderFeature($conn);
        exit($success ? 0 : 1);
    } else {
        echo "Usage: php install_scanned_admission_order.php install\n";
        exit(1);
    }
}
?>