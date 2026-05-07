<?php
/**
 * Enhanced Scheme Fields Migration
 * Adds additional fields to schemes table for comprehensive project management
 */

require_once __DIR__ . '/../config/database.php';

echo "Starting Enhanced Scheme Fields Migration...\n";

try {
    // Add new columns to schemes table
    $alterQuery = "ALTER TABLE `schemes` 
        ADD COLUMN IF NOT EXISTS `sponsor_agency` varchar(255) DEFAULT NULL AFTER `description`,
        ADD COLUMN IF NOT EXISTS `start_date` date DEFAULT NULL AFTER `sponsor_agency`,
        ADD COLUMN IF NOT EXISTS `end_date` date DEFAULT NULL AFTER `start_date`,
        ADD COLUMN IF NOT EXISTS `physical_target` int(11) DEFAULT NULL AFTER `end_date`,
        ADD COLUMN IF NOT EXISTS `project_incharge_name` varchar(255) DEFAULT NULL AFTER `physical_target`,
        ADD COLUMN IF NOT EXISTS `target_beneficiary` varchar(255) DEFAULT NULL AFTER `project_incharge_name`";
    
    if ($conn->query($alterQuery)) {
        echo "✅ Successfully added new columns to schemes table\n";
    } else {
        echo "⚠️ Note: " . $conn->error . "\n";
    }
    
    // Update existing records with default values
    $updateQuery = "UPDATE `schemes` SET 
        `sponsor_agency` = 'Ministry of Electronics and Information Technology',
        `target_beneficiary` = 'General,SC,ST,OBC,EWS'
        WHERE `sponsor_agency` IS NULL OR `sponsor_agency` = ''";
    
    if ($conn->query($updateQuery)) {
        echo "✅ Updated existing records with default values\n";
    }
    
    echo "✅ Enhanced Scheme Fields Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
}

$conn->close();
?>