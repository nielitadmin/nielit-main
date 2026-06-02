<?php
/**
 * Migration: Add registration_token column to courses table
 * Date: June 2, 2026
 * Description: Adds registration_token column for token-based registration links
 */

require_once __DIR__ . '/../config/config.php';

echo "=== Add registration_token Column Migration ===\n\n";

// Check if column already exists
$check_sql = "SHOW COLUMNS FROM courses LIKE 'registration_token'";
$result = $conn->query($check_sql);

if ($result && $result->num_rows > 0) {
    echo "✓ Column 'registration_token' already exists in courses table.\n";
} else {
    echo "Adding 'registration_token' column to courses table...\n";
    
    $alter_sql = "ALTER TABLE courses 
                  ADD COLUMN registration_token VARCHAR(255) DEFAULT NULL 
                  AFTER apply_link";
    
    if ($conn->query($alter_sql)) {
        echo "✓ Successfully added 'registration_token' column.\n";
        
        // Add index for faster lookups
        echo "Adding index on 'registration_token' column...\n";
        $index_sql = "CREATE INDEX idx_registration_token ON courses(registration_token)";
        
        if ($conn->query($index_sql)) {
            echo "✓ Successfully added index on 'registration_token' column.\n";
        } else {
            echo "⚠ Warning: Could not add index: " . $conn->error . "\n";
            echo "  (This is not critical - you can add it manually later)\n";
        }
    } else {
        echo "✗ Error adding column: " . $conn->error . "\n";
        exit(1);
    }
}

// Generate tokens for existing courses that have apply_link but no token
echo "\nChecking for courses that need tokens...\n";
$check_courses = "SELECT id, course_code, apply_link FROM courses 
                  WHERE apply_link IS NOT NULL 
                  AND apply_link != '' 
                  AND (registration_token IS NULL OR registration_token = '')";

$courses_result = $conn->query($check_courses);

if ($courses_result && $courses_result->num_rows > 0) {
    echo "Found " . $courses_result->num_rows . " course(s) that need tokens.\n";
    
    // Function to generate unique token
    function generateShortToken($length = 8) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $token = '';
        for ($i = 0; $i < $length; $i++) {
            $token .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $token;
    }
    
    $updated_count = 0;
    while ($course = $courses_result->fetch_assoc()) {
        // Generate unique token
        do {
            $token = generateShortToken(8);
            $check = $conn->prepare("SELECT id FROM courses WHERE registration_token = ?");
            $check->bind_param("s", $token);
            $check->execute();
            $check->store_result();
        } while ($check->num_rows > 0);
        
        // Update course with token
        $update = $conn->prepare("UPDATE courses SET registration_token = ? WHERE id = ?");
        $update->bind_param("si", $token, $course['id']);
        
        if ($update->execute()) {
            echo "  ✓ Generated token for course ID {$course['id']} ({$course['course_code']}): {$token}\n";
            $updated_count++;
        } else {
            echo "  ✗ Failed to update course ID {$course['id']}: " . $conn->error . "\n";
        }
    }
    
    echo "\n✓ Updated {$updated_count} course(s) with registration tokens.\n";
} else {
    echo "No courses need tokens (all existing courses either have tokens or no apply_link).\n";
}

echo "\n=== Migration Complete ===\n";
echo "\nNext steps:\n";
echo "1. Test adding a new course with token generation\n";
echo "2. Verify existing courses can still be edited\n";
echo "3. Check that registration links work with tokens\n";

$conn->close();
?>
