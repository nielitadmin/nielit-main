<?php
/**
 * Fix Course Status Values
 * Date: 2026-03-10
 * Description: Ensures courses have proper status values for assignment system
 */

require_once __DIR__ . '/../config/config.php';

echo "<h2>Course Status Fix Tool</h2>";

try {
    // Check if status column exists
    $check_query = "SHOW COLUMNS FROM courses LIKE 'status'";
    $check_result = $conn->query($check_query);
    
    if ($check_result->num_rows == 0) {
        echo "<div style='color: orange;'>⚠️ Status column doesn't exist. Adding it...</div>";
        
        // Add status column
        $add_column = "ALTER TABLE courses ADD COLUMN status VARCHAR(20) DEFAULT 'active'";
        if ($conn->query($add_column)) {
            echo "<div style='color: green;'>✅ Added status column with default 'active'</div>";
        } else {
            echo "<div style='color: red;'>❌ Failed to add status column: " . $conn->error . "</div>";
            exit();
        }
    } else {
        echo "<div style='color: green;'>✅ Status column exists</div>";
    }
    
    // Check current course statuses
    echo "<h3>Current Course Status Distribution:</h3>";
    $status_query = "SELECT status, COUNT(*) as count FROM courses GROUP BY status";
    $status_result = $conn->query($status_query);
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Status</th><th>Count</th></tr>";
    
    $total_courses = 0;
    $active_courses = 0;
    
    while ($row = $status_result->fetch_assoc()) {
        $status = $row['status'] ?? 'NULL';
        $count = $row['count'];
        $total_courses += $count;
        
        if (strtolower($status) === 'active') {
            $active_courses += $count;
        }
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($status) . "</td>";
        echo "<td>" . $count . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p><strong>Summary:</strong> $active_courses active courses out of $total_courses total</p>";
    
    // If no active courses, offer to fix them
    if ($active_courses === 0 && $total_courses > 0) {
        echo "<div style='color: red;'>❌ No courses have 'active' status!</div>";
        
        if (isset($_POST['fix_status'])) {
            $update_query = "UPDATE courses SET status = 'active' WHERE status IS NULL OR status = '' OR status != 'active'";
            if ($conn->query($update_query)) {
                $affected = $conn->affected_rows;
                echo "<div style='color: green;'>✅ Updated $affected course(s) to 'active' status</div>";
                
                // Show updated distribution
                echo "<h3>Updated Status Distribution:</h3>";
                $new_status_result = $conn->query($status_query);
                echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
                echo "<tr><th>Status</th><th>Count</th></tr>";
                
                while ($row = $new_status_result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['status'] ?? 'NULL') . "</td>";
                    echo "<td>" . $row['count'] . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
                
            } else {
                echo "<div style='color: red;'>❌ Failed to update course statuses: " . $conn->error . "</div>";
            }
        } else {
            echo "<form method='POST'>";
            echo "<button type='submit' name='fix_status' style='background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; margin: 10px 0;'>";
            echo "Fix Course Statuses (Set All to 'active')";
            echo "</button>";
            echo "</form>";
        }
    }
    
    // Show sample courses
    echo "<h3>Sample Courses:</h3>";
    $sample_query = "SELECT id, course_name, course_code, status FROM courses ORDER BY id LIMIT 10";
    $sample_result = $conn->query($sample_query);
    
    if ($sample_result->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>Course Name</th><th>Course Code</th><th>Status</th></tr>";
        
        while ($course = $sample_result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $course['id'] . "</td>";
            echo "<td>" . htmlspecialchars($course['course_name']) . "</td>";
            echo "<td>" . htmlspecialchars($course['course_code']) . "</td>";
            echo "<td>" . htmlspecialchars($course['status'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Error: " . $e->getMessage() . "</div>";
}

$conn->close();
?>

<p><a href="../admin/manage_course_assignments.php">← Back to Course Assignments</a></p>
<p><a href="../admin/debug_course_assignments.php">← Back to Debug Tool</a></p>