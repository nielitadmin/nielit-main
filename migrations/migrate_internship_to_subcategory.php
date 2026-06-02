<?php
/**
 * Migration: Move Internship Program from Category to Sub-Category
 * 
 * This script migrates existing courses that have "Internship Program" as category
 * to use it as a sub-category instead.
 */

require_once __DIR__ . '/../config/config.php';

echo "Starting Internship Program Category to Sub-Category Migration...\n";

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Find all courses with "Internship Program" category
    $check_sql = "SELECT id, course_name, category FROM courses WHERE category = 'Internship Program'";
    $result = $conn->query($check_sql);
    
    if ($result && $result->num_rows > 0) {
        echo "Found " . $result->num_rows . " courses with 'Internship Program' category.\n";
        
        $courses = [];
        while ($row = $result->fetch_assoc()) {
            $courses[] = $row;
            echo "- Course ID {$row['id']}: {$row['course_name']}\n";
        }
        
        // Update these courses to have a generic category and add internship marker
        // We'll use course_description to mark internship programs
        $update_sql = "UPDATE courses SET 
                       category = 'Skill Based (Short Term) 90-500 hrs',
                       is_nsqf = 0,
                       course_description = CONCAT(COALESCE(course_description, ''), ' [INTERNSHIP_PROGRAM]')
                       WHERE category = 'Internship Program'";
        
        if ($conn->query($update_sql)) {
            echo "Successfully updated " . $conn->affected_rows . " courses.\n";
            echo "Courses now have:\n";
            echo "- Category: 'Skill Based (Short Term) 90-500 hrs'\n";
            echo "- Sub-Category: Will be set to 'Internship Program' in the form\n";
            echo "- NSQF Status: Set to NON-NSQF (0)\n";
            echo "- Special Marker: Added [INTERNSHIP_PROGRAM] to course_description\n";
        } else {
            throw new Exception("Failed to update courses: " . $conn->error);
        }
    } else {
        echo "No courses found with 'Internship Program' category.\n";
    }
    
    // Commit transaction
    $conn->commit();
    echo "\nMigration completed successfully!\n";
    
    echo "\nNext Steps:\n";
    echo "1. Update the edit_course.php form to handle the new structure\n";
    echo "2. Update courses.php to filter by sub-category for internships\n";
    echo "3. Test the form to ensure internship programs work correctly\n";
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

$conn->close();
?>