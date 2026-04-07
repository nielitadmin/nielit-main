<?php
// Quick database schema check for students table
require_once __DIR__ . '/../config/config.php';

echo "<h2>Students Table Schema Check</h2>";

// Get table structure
$result = $conn->query("DESCRIBE students");

if ($result) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    $fields = [];
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
        
        $fields[] = $row['Field'];
    }
    echo "</table>";
    
    echo "<h3>Field List (for INSERT statement):</h3>";
    echo "<pre>" . implode(", ", $fields) . "</pre>";
    
    echo "<h3>Total Fields: " . count($fields) . "</h3>";
} else {
    echo "Error: " . $conn->error;
}

$conn->close();
?>