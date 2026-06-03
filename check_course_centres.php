<?php
require_once __DIR__ . '/config/config.php';

$result = $conn->query("SELECT c.course_name, c.centre_id, ce.name as centre_name 
FROM courses c 
LEFT JOIN centres ce ON c.centre_id = ce.id 
LIMIT 5");

echo "<pre>";
while ($row = $result->fetch_assoc()) {
    echo "Course: " . $row['course_name'] . " | Centre ID: " . ($row['centre_id'] ?? 'NULL') . " | Centre: " . ($row['centre_name'] ?? 'NULL') . "\n";
}
echo "</pre>";
?>
