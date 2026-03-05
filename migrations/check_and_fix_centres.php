<?php
/**
 * Check and Fix Training Centres
 * This script checks the centres table and activates NIELIT BHUBANESWAR if it exists
 */

require_once __DIR__ . '/../config/database.php';

echo "<h2>Training Centres Status Check</h2>";
echo "<hr>";

// Check all centres
echo "<h3>All Centres in Database:</h3>";
$sql = "SELECT id, name, code, city, state, is_active FROM centres ORDER BY name ASC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Code</th><th>City</th><th>State</th><th>Status</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        $status = $row['is_active'] == 1 ? '<span style="color: green;">Active</span>' : '<span style="color: red;">Inactive</span>';
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['code']) . "</td>";
        echo "<td>" . htmlspecialchars($row['city']) . "</td>";
        echo "<td>" . htmlspecialchars($row['state']) . "</td>";
        echo "<td>" . $status . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p style='color: red;'>No centres found in database!</p>";
}

echo "<hr>";

// Check for NIELIT BHUBANESWAR specifically
echo "<h3>Checking for NIELIT BHUBANESWAR:</h3>";
$sql_check = "SELECT id, name, is_active FROM centres WHERE name LIKE '%NIELIT%BHUBANESWAR%' OR name LIKE '%NIELIT%Bhubaneswar%'";
$result_check = $conn->query($sql_check);

if ($result_check && $result_check->num_rows > 0) {
    $centre = $result_check->fetch_assoc();
    echo "<p>Found: <strong>" . htmlspecialchars($centre['name']) . "</strong> (ID: " . $centre['id'] . ")</p>";
    
    if ($centre['is_active'] == 0) {
        echo "<p style='color: orange;'>Status: <strong>INACTIVE</strong></p>";
        echo "<p>Activating centre...</p>";
        
        $sql_activate = "UPDATE centres SET is_active = 1 WHERE id = ?";
        $stmt = $conn->prepare($sql_activate);
        $stmt->bind_param("i", $centre['id']);
        
        if ($stmt->execute()) {
            echo "<p style='color: green;'><strong>✓ Centre activated successfully!</strong></p>";
        } else {
            echo "<p style='color: red;'>✗ Failed to activate centre: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: green;'>Status: <strong>ACTIVE</strong> ✓</p>";
        echo "<p>No action needed.</p>";
    }
} else {
    echo "<p style='color: orange;'>NIELIT BHUBANESWAR centre not found in database.</p>";
    echo "<p>Creating centre...</p>";
    
    $sql_insert = "INSERT INTO centres (name, code, address, city, state, pincode, phone, email, is_active) 
                   VALUES ('NIELIT BHUBANESWAR', 'BBSR', 'OCAC Tower, Acharya Vihar', 'Bhubaneswar', 'Odisha', '751013', '', '', 1)";
    
    if ($conn->query($sql_insert)) {
        echo "<p style='color: green;'><strong>✓ Centre created successfully!</strong></p>";
        $new_id = $conn->insert_id;
        echo "<p>New Centre ID: " . $new_id . "</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to create centre: " . $conn->error . "</p>";
    }
}

echo "<hr>";

// Check courses using this centre
echo "<h3>Courses Using NIELIT BHUBANESWAR Centre:</h3>";
$sql_courses = "SELECT c.id, c.course_name, c.category, cen.name as centre_name, cen.is_active as centre_active
                FROM courses c
                LEFT JOIN centres cen ON c.centre_id = cen.id
                WHERE cen.name LIKE '%NIELIT%BHUBANESWAR%' OR cen.name LIKE '%NIELIT%Bhubaneswar%'
                ORDER BY c.category, c.course_name";
$result_courses = $conn->query($sql_courses);

if ($result_courses && $result_courses->num_rows > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr><th>Course ID</th><th>Course Name</th><th>Category</th><th>Centre</th><th>Centre Status</th></tr>";
    
    while ($row = $result_courses->fetch_assoc()) {
        $status = $row['centre_active'] == 1 ? '<span style="color: green;">Active</span>' : '<span style="color: red;">Inactive</span>';
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['course_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['category']) . "</td>";
        echo "<td>" . htmlspecialchars($row['centre_name']) . "</td>";
        echo "<td>" . $status . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>No courses found using NIELIT BHUBANESWAR centre.</p>";
}

echo "<hr>";
echo "<h3>Summary:</h3>";
echo "<p><a href='../admin/manage_centres.php'>Go to Manage Centres</a></p>";
echo "<p><a href='../public/courses.php'>Go to Courses Page</a></p>";
echo "<p><strong>Note:</strong> After running this script, refresh the courses page to see the changes.</p>";

$conn->close();
?>
