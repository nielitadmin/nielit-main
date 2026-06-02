<?php
// Check courses table structure for training center columns
$conn = new mysqli('localhost', 'root', '', 'nielit_bhubaneswar');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== Checking courses table structure for training center columns ===\n\n";

$result = $conn->query("DESCRIBE courses");

echo "All columns containing 'centre' or 'training':\n";
echo "-----------------------------------------------\n";

while ($row = $result->fetch_assoc()) {
    $field_lower = strtolower($row['Field']);
    if (strpos($field_lower, 'centre') !== false || strpos($field_lower, 'training') !== false) {
        echo "Column: " . $row['Field'] . "\n";
        echo "Type: " . $row['Type'] . "\n";
        echo "Null: " . $row['Null'] . "\n";
        echo "Key: " . $row['Key'] . "\n";
        echo "Default: " . $row['Default'] . "\n";
        echo "-----------------------------------------------\n";
    }
}

// Also check if centre_id column exists
$result2 = $conn->query("SHOW COLUMNS FROM courses LIKE 'centre_id'");
if ($result2->num_rows > 0) {
    echo "\ncentre_id column EXISTS\n";
} else {
    echo "\ncentre_id column DOES NOT EXIST\n";
}

$conn->close();
?>
