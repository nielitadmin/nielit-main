<?php
/**
 * Fix Training Center Spelling in Courses Table
 * Changes "Center" to "Centre" in training_center field
 */

require_once __DIR__ . '/../config/database.php';

echo "=== Training Centre Spelling Fix for Courses Table ===\n";
echo "Checking courses table for American spelling in training_center field...\n\n";

try {
    // First, let's see what we have
    $query = "SELECT id, course_name, training_center FROM courses WHERE training_center LIKE '%Center%'";
    $result = $conn->query($query);
    
    echo "Courses with 'Center' spelling:\n";
    echo "ID | Course Name | Training Center\n";
    echo "---|-------------|----------------\n";
    
    $courses_to_fix = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo $row['id'] . " | " . $row['course_name'] . " | " . $row['training_center'] . "\n";
            
            $courses_to_fix[] = [
                'id' => $row['id'],
                'course_name' => $row['course_name'],
                'old_training_center' => $row['training_center'],
                'new_training_center' => str_replace('Center', 'Centre', $row['training_center'])
            ];
        }
    } else {
        echo "No courses found with 'Center' spelling.\n";
    }
    
    echo "\n";
    
    if (empty($courses_to_fix)) {
        echo "✅ No courses found with American spelling 'Center'. All good!\n";
    } else {
        echo "Found " . count($courses_to_fix) . " course(s) with American spelling:\n\n";
        
        foreach ($courses_to_fix as $course) {
            echo "Course ID " . $course['id'] . " (" . $course['course_name'] . "):\n";
            echo "  OLD: " . $course['old_training_center'] . "\n";
            echo "  NEW: " . $course['new_training_center'] . "\n\n";
        }
        
        echo "Updating courses to use British spelling...\n";
        
        $updated_count = 0;
        foreach ($courses_to_fix as $course) {
            $stmt = $conn->prepare("UPDATE courses SET training_center = ? WHERE id = ?");
            $stmt->bind_param("si", $course['new_training_center'], $course['id']);
            
            if ($stmt->execute()) {
                echo "✅ Updated Course ID " . $course['id'] . ": " . $course['old_training_center'] . " → " . $course['new_training_center'] . "\n";
                $updated_count++;
            } else {
                echo "❌ Failed to update Course ID " . $course['id'] . ": " . $conn->error . "\n";
            }
            $stmt->close();
        }
        
        echo "\n=== Summary ===\n";
        echo "Updated $updated_count out of " . count($courses_to_fix) . " courses.\n";
        
        // Verify the changes
        echo "\nVerifying changes...\n";
        $verify_query = "SELECT id, course_name, training_center FROM courses WHERE training_center LIKE '%Centre%' OR training_center LIKE '%Center%' ORDER BY id";
        $verify_result = $conn->query($verify_query);
        
        echo "\nAll courses with training centre data:\n";
        echo "ID | Course Name | Training Centre\n";
        echo "---|-------------|----------------\n";
        
        if ($verify_result && $verify_result->num_rows > 0) {
            while ($row = $verify_result->fetch_assoc()) {
                $status = (strpos($row['training_center'], 'Center') !== false) ? "❌ STILL HAS 'CENTER'" : "✅";
                echo $row['id'] . " | " . $row['course_name'] . " | " . $row['training_center'] . " " . $status . "\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Fix Complete ===\n";
echo "Now the dropdown should show 'Training Centre' instead of 'Training Center'.\n";
?>