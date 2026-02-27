<?php
// Database configuration
$host = "localhost";     // Database host (usually localhost)
$username = "u664913565_nielitbbsr";      // Database username (default is 'root' for localhost)
$password = "Nielitbbsr@2025";          // Database password (default is empty for localhost)
$dbname = "u664913565_nielitbbsr";  // The name of the database

// Create a database connection
$conn = new mysqli($dbHost, $dbUsername, $dbPassword, $dbName);

// Check the connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
} else {
    echo "Database connection successful!";
}

// Close the connection
$conn->close();
?>
