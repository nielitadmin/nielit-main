<?php
/**
 * Fix Assignment Types Based on Assignment Logic
 * Date: 2026-03-10
 * Description: Updates assignment_type based on who assigned the course
 */

require_once __DIR__ . '/../config/config.php';

echo "<h2>Fixing Assignment Types</h2>";

try {
    // Check if assignment_type column exists
    $check_query = "SHOW COLUMNS FROM admin_course_assignments LIKE 'assignment_type'";
    $check_result = $conn->query($check_query);
    
    if ($check_result->num_rows == 0) {
        echo "<div style='color: red;'>❌ assignment_type column doesn't exist. Please run add_assignment_type_column.php first.</div>";
        exit();
    }
    
    echo "<div style='color: green;'>✅ assignment_type column exists</div>";
    
    // Get all assignments and fix their types
    $query = "SELECT aca.*, a.username as admin_name, ab.username as assigned_by_name
              FROM admin_course_assignments aca
              JOIN admin a ON aca.admin_id = a.id
              LEFT JOIN admin ab ON aca.assigned_by = ab.id
              ORDER BY aca.assigned_at DESC";
    
    $result = $conn->query($query);
    
    if ($result->num_rows > 0) {
        echo "<h3>Current Assignments:</h3>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>Coordinator</th><th>Course ID</th><th>Current Type</th><th>Assigned By</th><th>Should Be</th><th>Action</th></tr>";
        
        $updates = 0;
        
        while ($row = $result->fetch_assoc()) {
            $assignment_id = $row['id'];
            $admin_name = $row['admin_name'];
            $assigned_by_name = $row['assigned_by_name'];
            $current_type = $row['assignment_type'];
            
            // Determine correct assignment type
            $correct_type = 'Manual'; // Default
            
            if ($row['admin_id'] == $row['assigned_by']) {
                // Self-assigned = Auto-Assigned
                $correct_type = 'Auto-Assigned';
            } elseif ($assigned_by_name && $assigned_by_name !== $admin_name) {
                // Assigned by someone else = Manual
                $correct_type = 'Manual';
            }
            
            $needs_update = ($current_type !== $correct_type);
            
            echo "<tr>";
            echo "<td>" . $assignment_id . "</td>";
            echo "<td>" . htmlspecialchars($admin_name) . "</td>";
            echo "<td>" . $row['course_id'] . "</td>";
            echo "<td>" . htmlspecialchars($current_type) . "</td>";
            echo "<td>" . htmlspecialchars($assigned_by_name ?: 'Unknown') . "</td>";
            echo "<td style='color: " . ($needs_update ? 'red' : 'green') . ";'>" . $correct_type . "</td>";
            
            if ($needs_update) {
                // Update the assignment type
                $update_query = "UPDATE admin_course_assignments SET assignment_type = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("si", $correct_type, $assignment_id);
                
                if ($update_stmt->execute()) {
                    echo "<td style='color: green;'>✅ Updated</td>";
                    $updates++;
                } else {
                    echo "<td style='color: red;'>❌ Failed</td>";
                }
            } else {
                echo "<td style='color: green;'>✅ Correct</td>";
            }
            
            echo "</tr>";
        }
        
        echo "</table>";
        
        echo "<div style='color: green;'><strong>Summary: Updated $updates assignment(s)</strong></div>";
        
    } else {
        echo "<div style='color: orange;'>⚠️ No assignments found</div>";
    }
    
    // Show final state
    echo "<h3>Final Assignment Types:</h3>";
    $final_query = "SELECT aca.*, a.username as admin_name, ab.username as assigned_by_name, c.course_name
                    FROM admin_course_assignments aca
                    JOIN admin a ON aca.admin_id = a.id
                    LEFT JOIN admin ab ON aca.assigned_by = ab.id
                    LEFT JOIN courses c ON aca.course_id = c.id
                    WHERE aca.is_active = 1
                    ORDER BY aca.assigned_at DESC";
    
    $final_result = $conn->query($final_query);
    
    if ($final_result->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Coordinator</th><th>Course</th><th>Type</th><th>Assigned By</th><th>Date</th></tr>";
        
        while ($row = $final_result->fetch_assoc()) {
            $type_color = ($row['assignment_type'] === 'Auto-Assigned') ? 'green' : 'blue';
            
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['admin_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['course_name']) . "</td>";
            echo "<td style='color: $type_color; font-weight: bold;'>" . htmlspecialchars($row['assignment_type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['assigned_by_name'] ?: 'System') . "</td>";
            echo "<td>" . date('M d, Y', strtotime($row['assigned_at'])) . "</td>";
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
<p><a href="../tests/test_auto_course_assignment.php">← Back to Auto Assignment Test</a></p>