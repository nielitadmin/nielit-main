<?php
/**
 * System Enhancement Module - Data Migration Script
 * NIELIT Bhubaneswar Student Management System
 * 
 * This script populates initial data for:
 * - Training centres (NIELIT Bhubaneswar, NIELIT Balasore)
 * - Default centre assignment for existing courses
 * - Default theme from existing CSS values
 * 
 * This migration is idempotent - it can be run multiple times safely
 */

// Include database configuration
require_once __DIR__ . '/../config/database.php';

// Color codes for CLI output
define('COLOR_GREEN', "\033[32m");
define('COLOR_RED', "\033[31m");
define('COLOR_YELLOW', "\033[33m");
define('COLOR_BLUE', "\033[34m");
define('COLOR_RESET', "\033[0m");

/**
 * Print colored message
 */
function printMessage($message, $color = COLOR_RESET) {
    if (php_sapi_name() === 'cli') {
        echo $color . $message . COLOR_RESET . "\n";
    } else {
        echo "<p>" . htmlspecialchars($message) . "</p>";
    }
}

/**
 * Check if table exists
 */
function tableExists($conn, $tableName) {
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result && $result->num_rows > 0;
}

/**
 * Populate centres table with default training centres
 */
function populateCentres($conn) {
    printMessage("\n📍 Populating centres table...", COLOR_BLUE);
    
    // Check if centres table exists
    if (!tableExists($conn, 'centres')) {
        printMessage("✗ Centres table does not exist. Run install_system_enhancement.php first.", COLOR_RED);
        return false;
    }
    
    // Check if centres already exist
    $result = $conn->query("SELECT COUNT(*) as count FROM centres");
    if ($result) {
        $row = $result->fetch_assoc();
        if ($row['count'] > 0) {
            printMessage("✓ Centres already exist (" . $row['count'] . " centres found), skipping...", COLOR_YELLOW);
            return true;
        }
    }
    
    // Insert default centres
    $centres = [
        [
            'name' => 'NIELIT Bhubaneswar',
            'code' => 'BBSR',
            'address' => 'OCAC Tower, Acharya Vihar',
            'city' => 'Bhubaneswar',
            'state' => 'Odisha',
            'pincode' => '751013',
            'phone' => '0674-2960354',
            'email' => 'dir-bbsr@nielit.gov.in'
        ],
        [
            'name' => 'NIELIT Balasore Extension',
            'code' => 'BALA',
            'address' => 'Balasore Extension Centre',
            'city' => 'Balasore',
            'state' => 'Odisha',
            'pincode' => '',
            'phone' => '',
            'email' => ''
        ]
    ];
    
    $stmt = $conn->prepare("INSERT INTO centres (name, code, address, city, state, pincode, phone, email, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
    
    $inserted = 0;
    foreach ($centres as $centre) {
        $stmt->bind_param(
            "ssssssss",
            $centre['name'],
            $centre['code'],
            $centre['address'],
            $centre['city'],
            $centre['state'],
            $centre['pincode'],
            $centre['phone'],
            $centre['email']
        );
        
        if ($stmt->execute()) {
            printMessage("  ✓ Inserted: " . $centre['name'], COLOR_GREEN);
            $inserted++;
        } else {
            printMessage("  ✗ Failed to insert: " . $centre['name'] . " - " . $stmt->error, COLOR_RED);
        }
    }
    
    $stmt->close();
    
    if ($inserted > 0) {
        printMessage("✓ Successfully inserted $inserted centres", COLOR_GREEN);
        return true;
    } else {
        printMessage("✗ No centres were inserted", COLOR_RED);
        return false;
    }
}

/**
 * Update existing courses with default centre_id
 */
function updateCoursesWithDefaultCentre($conn) {
    printMessage("\n📚 Updating existing courses with default centre...", COLOR_BLUE);
    
    // Check if courses table exists
    if (!tableExists($conn, 'courses')) {
        printMessage("✓ Courses table does not exist, skipping...", COLOR_YELLOW);
        return true;
    }
    
    // Get the default centre (NIELIT Bhubaneswar)
    $result = $conn->query("SELECT id FROM centres WHERE code = 'BBSR' LIMIT 1");
    if (!$result || $result->num_rows === 0) {
        printMessage("✗ Default centre (BBSR) not found. Run populateCentres first.", COLOR_RED);
        return false;
    }
    
    $defaultCentre = $result->fetch_assoc();
    $defaultCentreId = $defaultCentre['id'];
    
    // Check how many courses need updating
    $result = $conn->query("SELECT COUNT(*) as count FROM courses WHERE centre_id IS NULL");
    if ($result) {
        $row = $result->fetch_assoc();
        $coursesToUpdate = $row['count'];
        
        if ($coursesToUpdate === 0) {
            printMessage("✓ All courses already have centre assignments, skipping...", COLOR_YELLOW);
            return true;
        }
        
        printMessage("  Found $coursesToUpdate courses without centre assignment", COLOR_BLUE);
    }
    
    // Update courses with NULL centre_id to default centre
    $sql = "UPDATE courses SET centre_id = ? WHERE centre_id IS NULL";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $defaultCentreId);
    
    if ($stmt->execute()) {
        $updated = $stmt->affected_rows;
        printMessage("✓ Updated $updated courses with default centre (NIELIT Bhubaneswar)", COLOR_GREEN);
        $stmt->close();
        return true;
    } else {
        printMessage("✗ Failed to update courses: " . $stmt->error, COLOR_RED);
        $stmt->close();
        return false;
    }
}

/**
 * Create default theme from existing CSS values
 */
function createDefaultTheme($conn) {
    printMessage("\n🎨 Creating default theme from existing CSS values...", COLOR_BLUE);
    
    // Check if themes table exists
    if (!tableExists($conn, 'themes')) {
        printMessage("✗ Themes table does not exist. Run install_system_enhancement.php first.", COLOR_RED);
        return false;
    }
    
    // Check if a theme already exists
    $result = $conn->query("SELECT COUNT(*) as count FROM themes");
    if ($result) {
        $row = $result->fetch_assoc();
        if ($row['count'] > 0) {
            printMessage("✓ Themes already exist (" . $row['count'] . " themes found), skipping...", COLOR_YELLOW);
            return true;
        }
    }
    
    // Default theme values from existing CSS
    // From admin-theme.css and public-theme.css
    $defaultTheme = [
        'theme_name' => 'NIELIT Default Theme',
        'primary_color' => '#0d47a1',    // Deep Blue (from CSS variables)
        'secondary_color' => '#1565c0',  // Secondary Blue
        'accent_color' => '#ffc107',     // Gold Accent
        'logo_path' => 'assets/images/bhubaneswar_logo.png',
        'favicon_path' => 'assets/images/favicon.ico'
    ];
    
    // Insert default theme
    $stmt = $conn->prepare("INSERT INTO themes (theme_name, primary_color, secondary_color, accent_color, logo_path, favicon_path, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
    $stmt->bind_param(
        "ssssss",
        $defaultTheme['theme_name'],
        $defaultTheme['primary_color'],
        $defaultTheme['secondary_color'],
        $defaultTheme['accent_color'],
        $defaultTheme['logo_path'],
        $defaultTheme['favicon_path']
    );
    
    if ($stmt->execute()) {
        printMessage("✓ Created default theme: " . $defaultTheme['theme_name'], COLOR_GREEN);
        printMessage("  Primary Color: " . $defaultTheme['primary_color'], COLOR_BLUE);
        printMessage("  Secondary Color: " . $defaultTheme['secondary_color'], COLOR_BLUE);
        printMessage("  Accent Color: " . $defaultTheme['accent_color'], COLOR_BLUE);
        printMessage("  Theme is now active", COLOR_GREEN);
        $stmt->close();
        return true;
    } else {
        printMessage("✗ Failed to create default theme: " . $stmt->error, COLOR_RED);
        $stmt->close();
        return false;
    }
}

/**
 * Verify migration
 */
function verifyMigration($conn) {
    printMessage("\n🔍 Verifying migration...", COLOR_BLUE);
    
    $errors = [];
    $warnings = [];
    
    // Check centres
    $result = $conn->query("SELECT COUNT(*) as count FROM centres");
    if ($result) {
        $row = $result->fetch_assoc();
        if ($row['count'] < 2) {
            $errors[] = "Expected at least 2 centres, found " . $row['count'];
        } else {
            printMessage("  ✓ Centres: " . $row['count'] . " centres found", COLOR_GREEN);
        }
    }
    
    // Check if NIELIT Bhubaneswar exists
    $result = $conn->query("SELECT id, name FROM centres WHERE code = 'BBSR'");
    if ($result && $result->num_rows > 0) {
        $centre = $result->fetch_assoc();
        printMessage("  ✓ Default centre exists: " . $centre['name'] . " (ID: " . $centre['id'] . ")", COLOR_GREEN);
    } else {
        $errors[] = "Default centre (BBSR) not found";
    }
    
    // Check courses with centre assignment
    if (tableExists($conn, 'courses')) {
        $result = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN centre_id IS NOT NULL THEN 1 ELSE 0 END) as assigned FROM courses");
        if ($result) {
            $row = $result->fetch_assoc();
            $total = $row['total'];
            $assigned = $row['assigned'];
            
            if ($total > 0) {
                printMessage("  ✓ Courses: $assigned out of $total courses have centre assignments", COLOR_GREEN);
                
                if ($assigned < $total) {
                    $warnings[] = ($total - $assigned) . " courses still have NULL centre_id";
                }
            } else {
                printMessage("  ℹ No courses found in database", COLOR_YELLOW);
            }
        }
    }
    
    // Check themes
    $result = $conn->query("SELECT COUNT(*) as count FROM themes");
    if ($result) {
        $row = $result->fetch_assoc();
        if ($row['count'] < 1) {
            $errors[] = "No themes found, expected at least 1 default theme";
        } else {
            printMessage("  ✓ Themes: " . $row['count'] . " theme(s) found", COLOR_GREEN);
        }
    }
    
    // Check active theme
    $result = $conn->query("SELECT id, theme_name, primary_color, secondary_color, accent_color FROM themes WHERE is_active = 1");
    if ($result && $result->num_rows > 0) {
        $theme = $result->fetch_assoc();
        printMessage("  ✓ Active theme: " . $theme['theme_name'], COLOR_GREEN);
        printMessage("    Colors: " . $theme['primary_color'] . ", " . $theme['secondary_color'] . ", " . $theme['accent_color'], COLOR_BLUE);
    } else {
        $errors[] = "No active theme found";
    }
    
    // Display results
    printMessage("", COLOR_RESET);
    
    if (!empty($warnings)) {
        printMessage("⚠️  Warnings:", COLOR_YELLOW);
        foreach ($warnings as $warning) {
            printMessage("  - " . $warning, COLOR_YELLOW);
        }
        printMessage("", COLOR_RESET);
    }
    
    if (empty($errors)) {
        printMessage("✓ All verification checks passed!", COLOR_GREEN);
        return true;
    } else {
        printMessage("✗ Verification failed:", COLOR_RED);
        foreach ($errors as $error) {
            printMessage("  - " . $error, COLOR_RED);
        }
        return false;
    }
}

/**
 * Main migration function
 */
function migrate($conn) {
    printMessage("\n🚀 Starting System Enhancement Module Data Migration...", COLOR_BLUE);
    printMessage("This migration will:", COLOR_BLUE);
    printMessage("  1. Populate centres table with NIELIT Bhubaneswar and Balasore", COLOR_BLUE);
    printMessage("  2. Update existing courses with default centre (NIELIT Bhubaneswar)", COLOR_BLUE);
    printMessage("  3. Create default theme from existing CSS values", COLOR_BLUE);
    printMessage("", COLOR_RESET);
    
    $steps = [
        'populateCentres',
        'updateCoursesWithDefaultCentre',
        'createDefaultTheme'
    ];
    
    $success = true;
    foreach ($steps as $step) {
        if (!$step($conn)) {
            printMessage("\n⚠️  Migration step failed: $step", COLOR_YELLOW);
            printMessage("Continuing with remaining steps...", COLOR_YELLOW);
            $success = false;
        }
    }
    
    if ($success) {
        printMessage("\n✓ Migration completed successfully!", COLOR_GREEN);
    } else {
        printMessage("\n⚠️  Migration completed with some warnings", COLOR_YELLOW);
    }
    
    // Verify migration
    return verifyMigration($conn);
}

/**
 * Rollback function
 */
function rollback($conn) {
    printMessage("\n⚠️  WARNING: This will remove all migrated data!", COLOR_YELLOW);
    printMessage("This includes:", COLOR_YELLOW);
    printMessage("  - All centres", COLOR_YELLOW);
    printMessage("  - All themes", COLOR_YELLOW);
    printMessage("  - centre_id assignments from courses (set to NULL)", COLOR_YELLOW);
    
    if (php_sapi_name() === 'cli') {
        echo "\nType 'yes' to continue: ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        if (trim($line) !== 'yes') {
            printMessage("Rollback cancelled", COLOR_YELLOW);
            return false;
        }
    }
    
    printMessage("\n🔄 Starting rollback...\n", COLOR_BLUE);
    
    // Reset course centre assignments
    if (tableExists($conn, 'courses')) {
        $conn->query("UPDATE courses SET centre_id = NULL");
        printMessage("✓ Reset centre_id in courses table", COLOR_GREEN);
    }
    
    // Delete all themes
    if (tableExists($conn, 'themes')) {
        $conn->query("DELETE FROM themes");
        printMessage("✓ Deleted all themes", COLOR_GREEN);
    }
    
    // Delete all centres
    if (tableExists($conn, 'centres')) {
        $conn->query("DELETE FROM centres");
        printMessage("✓ Deleted all centres", COLOR_GREEN);
    }
    
    printMessage("\n✓ Rollback completed successfully!", COLOR_GREEN);
    return true;
}

// Main execution
if (php_sapi_name() === 'cli') {
    $command = $argv[1] ?? 'migrate';
    
    switch ($command) {
        case 'migrate':
            migrate($conn);
            break;
        case 'verify':
            verifyMigration($conn);
            break;
        case 'rollback':
            rollback($conn);
            break;
        default:
            printMessage("Usage: php migrate_system_enhancement_data.php [migrate|verify|rollback]", COLOR_YELLOW);
            printMessage("", COLOR_RESET);
            printMessage("Commands:", COLOR_BLUE);
            printMessage("  migrate  - Run the data migration (default)", COLOR_RESET);
            printMessage("  verify   - Verify migration was successful", COLOR_RESET);
            printMessage("  rollback - Remove all migrated data", COLOR_RESET);
            break;
    }
} else {
    // Web interface
    echo "<!DOCTYPE html>";
    echo "<html><head><title>System Enhancement Data Migration</title>";
    echo "<style>body { font-family: Arial, sans-serif; margin: 40px; } h1 { color: #0d47a1; } p { line-height: 1.6; }</style>";
    echo "</head><body>";
    echo "<h1>System Enhancement Module - Data Migration</h1>";
    migrate($conn);
    echo "</body></html>";
}

$conn->close();
?>
