<?php
// Quick script to check and fix centres table
require_once __DIR__ . '/../config/config.php';

echo "<h2>Checking Training Centres</h2>";

// Check if centres table exists
$table_check = $conn->query("SHOW TABLES LIKE 'centres'");
if ($table_check->num_rows == 0) {
    echo "<p style='color: red;'>Centres table does not exist. Creating it...</p>";
    
    $create_table = "CREATE TABLE `centres` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `code` varchar(50) NOT NULL,
        `address` text,
        `phone` varchar(20),
        `email` varchar(100),
        `is_active` tinyint(1) DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `code` (`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($create_table)) {
        echo "<p style='color: green;'>Centres table created successfully!</p>";
    } else {
        echo "<p style='color: red;'>Error creating centres table: " . $conn->error . "</p>";
    }
}

// Check active centres count
$centres_query = "SELECT COUNT(*) as count FROM centres WHERE is_active = 1";
$result = $conn->query($centres_query);
$count = $result ? $result->fetch_assoc()['count'] : 0;

echo "<p><strong>Active centres found:</strong> $count</p>";

if ($count == 0) {
    echo "<p style='color: orange;'>No active centres found. Adding default centres...</p>";
    
    $default_centres = [
        ['NIELIT BHUBANESWAR', 'NIELIT-BBR', 'Bhubaneswar, Odisha'],
        ['NIELIT KOLKATA', 'NIELIT-KOL', 'Kolkata, West Bengal'],
        ['NIELIT DELHI', 'NIELIT-DEL', 'New Delhi'],
        ['NIELIT MUMBAI', 'NIELIT-MUM', 'Mumbai, Maharashtra']
    ];
    
    $insert_stmt = $conn->prepare("INSERT INTO centres (name, code, address, is_active) VALUES (?, ?, ?, 1)");
    
    foreach ($default_centres as $centre) {
        $insert_stmt->bind_param("sss", $centre[0], $centre[1], $centre[2]);
        if ($insert_stmt->execute()) {
            echo "<p style='color: green;'>✓ Added: {$centre[0]} ({$centre[1]})</p>";
        } else {
            echo "<p style='color: red;'>✗ Failed to add: {$centre[0]} - " . $conn->error . "</p>";
        }
    }
} else {
    echo "<p style='color: green;'>Centres are available:</p>";
    $centres_list = $conn->query("SELECT name, code FROM centres WHERE is_active = 1 ORDER BY name");
    while ($centre = $centres_list->fetch_assoc()) {
        echo "<p>• {$centre['name']} ({$centre['code']})</p>";
    }
}

echo "<hr>";
echo "<p><a href='dashboard.php'>← Back to Dashboard</a></p>";
?>