<?php
/**
 * Assign Unassigned Courses to Default Centre
 * This script assigns all courses without a centre to NIELIT Bhubaneswar
 */

require_once __DIR__ . '/../config/database.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>Assign Courses to Centre</title>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; }
    h1 { color: #0d47a1; }
    .success { color: green; padding: 10px; background: #e8f5e9; border-left: 4px solid green; margin: 10px 0; }
    .error { color: red; padding: 10px; background: #ffebee; border-left: 4px solid red; margin: 10px 0; }
    .info { color: #0d47a1; padding: 10px; background: #e3f2fd; border-left: 4px solid #0d47a1; margin: 10px 0; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background-color: #0d47a1; color: white; }
    tr:nth-child(even) { background-color: #f2f2f2; }
    .btn { display: inline-block; padding: 10px 20px; background: #0d47a1; color: white; text-decoration: none; border-radius: 4px; margin: 10px 5px; }
</style>";
echo "</head><body>";

echo "<h1>🔗 Assign Courses to Training Centre</h1>";
echo "<hr>";

// Step 1: Check unassigned courses
echo "<h2>Step 1: Finding Unassigned Courses</h2>";

$sql_unassigned = "SELECT id, course_name, category FROM courses WHERE centre_id IS NULL ORDER BY category, course_name";
$result_unassigned = $conn->query($sql_unassigned);

if ($result_unassigned && $result_unassigned->num_rows > 0) {
    $count = $result_unassigned->num_rows;
    echo "<div class='info'>Found <strong>$count</strong> courses without an assigned centre.</div>";
    
    echo "<table>";
    echo "<tr><th>ID</th><th>Course Name</th><th>Category</th></tr>";
    
    while ($course = $result_unassigned->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $course['id'] . "</td>";
        echo "<td>" . htmlspecialchars($course['course_name']) . "</td>";
        echo "<td>" . htmlspecialchars($course['category']) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<div class='success'>✓ All courses are already assigned to a centre!</div>";
    echo "<p><a href='../public/courses.php' class='btn'>View Courses Page</a></p>";
    echo "</body></html>";
    exit;
}

// Step 2: Get training centres
echo "<h2>Step 2: Getting Training Centres</h2>";

$sql_bbsr = "SELECT id, name FROM centres WHERE name LIKE '%NIELIT%Bhubaneswar%' OR code = 'BBSR' LIMIT 1";
$result_bbsr = $conn->query($sql_bbsr);

$sql_balasore = "SELECT id, name FROM centres WHERE name LIKE '%Balasore%' OR code = 'BALA' LIMIT 1";
$result_balasore = $conn->query($sql_balasore);

if ($result_bbsr && $result_bbsr->num_rows > 0) {
    $centre_bbsr = $result_bbsr->fetch_assoc();
    $centre_id_bbsr = $centre_bbsr['id'];
    $centre_name_bbsr = $centre_bbsr['name'];
    
    echo "<div class='success'>✓ Found NIELIT Bhubaneswar: <strong>" . htmlspecialchars($centre_name_bbsr) . "</strong> (ID: $centre_id_bbsr)</div>";
} else {
    echo "<div class='error'>✗ Could not find NIELIT Bhubaneswar centre!</div>";
    echo "<p>Please run the centres installation script first.</p>";
    echo "</body></html>";
    exit;
}

if ($result_balasore && $result_balasore->num_rows > 0) {
    $centre_balasore = $result_balasore->fetch_assoc();
    $centre_id_balasore = $centre_balasore['id'];
    $centre_name_balasore = $centre_balasore['name'];
    
    echo "<div class='success'>✓ Found NIELIT Balasore: <strong>" . htmlspecialchars($centre_name_balasore) . "</strong> (ID: $centre_id_balasore)</div>";
} else {
    echo "<div class='error'>✗ Could not find NIELIT Balasore Extension centre!</div>";
    echo "<p>Please run the centres installation script first.</p>";
    echo "</body></html>";
    exit;
}

// Step 3: Assign courses to appropriate centres
echo "<h2>Step 3: Assigning Courses to Centres</h2>";

// First, assign Balasore courses
$sql_update_balasore = "UPDATE courses SET centre_id = ? WHERE centre_id IS NULL AND (course_name LIKE '%Balasore%' OR course_name LIKE '%balasore%')";
$stmt_balasore = $conn->prepare($sql_update_balasore);
$stmt_balasore->bind_param("i", $centre_id_balasore);

if ($stmt_balasore->execute()) {
    $affected_balasore = $stmt_balasore->affected_rows;
    if ($affected_balasore > 0) {
        echo "<div class='success'>✓ Assigned <strong>$affected_balasore</strong> Balasore course(s) to " . htmlspecialchars($centre_name_balasore) . "!</div>";
    } else {
        echo "<div class='info'>ℹ️ No Balasore courses found to assign.</div>";
    }
} else {
    echo "<div class='error'>✗ Failed to assign Balasore courses: " . $conn->error . "</div>";
}

// Then, assign all remaining unassigned courses to Bhubaneswar
$sql_update_bbsr = "UPDATE courses SET centre_id = ? WHERE centre_id IS NULL";
$stmt_bbsr = $conn->prepare($sql_update_bbsr);
$stmt_bbsr->bind_param("i", $centre_id_bbsr);

if ($stmt_bbsr->execute()) {
    $affected_bbsr = $stmt_bbsr->affected_rows;
    if ($affected_bbsr > 0) {
        echo "<div class='success'>✓ Assigned <strong>$affected_bbsr</strong> course(s) to " . htmlspecialchars($centre_name_bbsr) . "!</div>";
    } else {
        echo "<div class='info'>ℹ️ No additional courses to assign to Bhubaneswar.</div>";
    }
} else {
    echo "<div class='error'>✗ Failed to assign courses to Bhubaneswar: " . $conn->error . "</div>";
}

$total_assigned = $affected_balasore + $affected_bbsr;
echo "<div class='success'><strong>Total courses assigned: $total_assigned</strong></div>";

// Step 4: Verify the assignment
echo "<h2>Step 4: Verification</h2>";

$sql_verify = "SELECT 
    c.id, 
    c.course_name, 
    c.category, 
    cen.name as centre_name,
    cen.is_active as centre_active
FROM courses c
LEFT JOIN centres cen ON c.centre_id = cen.id
ORDER BY c.category, c.course_name
LIMIT 20";

$result_verify = $conn->query($sql_verify);

if ($result_verify && $result_verify->num_rows > 0) {
    echo "<div class='info'>Showing first 20 courses with their assigned centres:</div>";
    
    echo "<table>";
    echo "<tr><th>ID</th><th>Course Name</th><th>Category</th><th>Centre</th><th>Status</th></tr>";
    
    while ($course = $result_verify->fetch_assoc()) {
        $centre_display = $course['centre_name'] ? htmlspecialchars($course['centre_name']) : '<span style="color: red;">Not Assigned</span>';
        $status = '';
        if ($course['centre_name']) {
            $status = $course['centre_active'] == 1 ? '<span style="color: green;">✓ Active</span>' : '<span style="color: red;">✗ Inactive</span>';
        }
        
        echo "<tr>";
        echo "<td>" . $course['id'] . "</td>";
        echo "<td>" . htmlspecialchars($course['course_name']) . "</td>";
        echo "<td>" . htmlspecialchars($course['category']) . "</td>";
        echo "<td>" . $centre_display . "</td>";
        echo "<td>" . $status . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

// Check if any courses are still unassigned
$sql_check = "SELECT COUNT(*) as count FROM courses WHERE centre_id IS NULL";
$result_check = $conn->query($sql_check);
$row_check = $result_check->fetch_assoc();

if ($row_check['count'] > 0) {
    echo "<div class='error'>⚠️ Warning: " . $row_check['count'] . " courses are still unassigned!</div>";
} else {
    echo "<div class='success'>✓ All courses are now assigned to a centre!</div>";
}

echo "<hr>";
echo "<h2>✅ Assignment Complete!</h2>";
echo "<p>Courses have been assigned to their respective training centres:</p>";
echo "<ul>";
echo "<li>Balasore courses → " . htmlspecialchars($centre_name_balasore) . "</li>";
echo "<li>All other courses → " . htmlspecialchars($centre_name_bbsr) . "</li>";
echo "</ul>";

echo "<h3>Next Steps:</h3>";
echo "<p><a href='../public/courses.php' class='btn'>View Courses Page</a></p>";
echo "<p><a href='../admin/manage_centres.php' class='btn'>Manage Centres</a></p>";
echo "<p><a href='../admin/manage_courses.php' class='btn'>Manage Courses</a></p>";

echo "<hr>";
echo "<p style='text-align: center; color: #666;'>NIELIT Bhubaneswar - Course Assignment Script</p>";

$conn->close();

echo "</body></html>";
?>
