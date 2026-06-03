<?php
$conn = new mysqli('localhost', 'root', '', 'nielit_bhubaneswar');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>All Categories in Database</h2>";
$sql_categories = "SELECT DISTINCT category FROM courses ORDER BY category";
$result_categories = $conn->query($sql_categories);
echo "<ul>";
if ($result_categories && $result_categories->num_rows > 0) {
    while ($row = $result_categories->fetch_assoc()) {
        echo "<li>" . htmlspecialchars($row['category']) . "</li>";
    }
}
echo "</ul>";

echo "<h2>Internship Programs Enrollment Status</h2>";

// First, count how many internship courses exist
$count_sql = "SELECT COUNT(*) as count FROM courses WHERE category = 'Internship Program'";
$count_result = $conn->query($count_sql);
$count_row = $count_result->fetch_assoc();
echo "<p><strong>Total Internship Program Courses:</strong> " . $count_row['count'] . "</p>";

$sql = "SELECT id, course_name, category, enrollment_status, enrollment_closing_date 
        FROM courses 
        WHERE category = 'Internship Program'
        LIMIT 20";

$result = $conn->query($sql);

if (!$result) {
    echo "<p style='color:red'><strong>Query Error:</strong> " . $conn->error . "</p>";
    exit;
}

echo "<p><strong>Query Result Rows:</strong> " . $result->num_rows . "</p>";

echo "<p><strong>Today's Date:</strong> " . date('Y-m-d') . "</p>";
echo "<table border='1' cellpadding='10' cellspacing='0'>";
echo "<tr>
        <th>Course Name</th>
        <th>Category</th>
        <th>Enrollment Status</th>
        <th>Closing Date</th>
        <th>Display as</th>
      </tr>";

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $today = date('Y-m-d');
        $enrollment_status = $row['enrollment_status'] ?? 'ongoing';
        $enrollment_closing_date = $row['enrollment_closing_date'] ?? null;
        $is_closed = false;
        
        if ($enrollment_status == 'closed') {
            $is_closed = true;
            $display = "CLOSED";
        } elseif (!empty($enrollment_closing_date) && $today > $enrollment_closing_date) {
            $is_closed = true;
            $display = "CLOSED";
        } else {
            $display = "OPEN";
        }
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['course_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['category']) . "</td>";
        echo "<td>" . ($enrollment_status ?? 'NULL') . "</td>";
        echo "<td>" . ($enrollment_closing_date ?? 'NULL') . "</td>";
        echo "<td><strong style='color: " . ($display == 'CLOSED' ? 'red' : 'green') . "'>" . $display . "</strong></td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='5'>No Internship Program courses found.</td></tr>";
}

echo "</table>";

$conn->close();
?>
