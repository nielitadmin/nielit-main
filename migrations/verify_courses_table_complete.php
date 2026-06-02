<?php
// Verify that all required columns exist in courses table
$conn = new mysqli('localhost', 'root', '', 'nielit_bhubaneswar');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== Checking courses table for ALL required columns ===\n\n";

$required_columns = [
    'course_name',
    'course_code',
    'course_abbreviation',
    'eligibility',
    'duration',
    'training_fees',
    'category',
    'start_date',
    'end_date',
    'description_url',
    'description_pdf',
    'apply_link',
    'course_coordinator',
    'training_center',
    'is_nsqf',
    'link_published',
    'course_description',
    'registration_token'
];

$result = $conn->query("DESCRIBE courses");
$existing_columns = [];

while ($row = $result->fetch_assoc()) {
    $existing_columns[] = $row['Field'];
}

echo "Required columns:\n";
echo "=================\n";
$missing = [];
foreach ($required_columns as $col) {
    $status = in_array($col, $existing_columns) ? '✓ EXISTS' : '✗ MISSING';
    echo "$col ... $status\n";
    if (!in_array($col, $existing_columns)) {
        $missing[] = $col;
    }
}

if (empty($missing)) {
    echo "\n✓ ALL REQUIRED COLUMNS EXIST!\n";
} else {
    echo "\n✗ MISSING COLUMNS:\n";
    foreach ($missing as $col) {
        echo "  - $col\n";
    }
    
    echo "\n=== SQL TO ADD MISSING COLUMNS ===\n";
    foreach ($missing as $col) {
        if ($col === 'registration_token') {
            echo "ALTER TABLE courses ADD COLUMN $col VARCHAR(255) DEFAULT NULL;\n";
        } elseif ($col === 'is_nsqf' || $col === 'link_published') {
            echo "ALTER TABLE courses ADD COLUMN $col TINYINT(1) DEFAULT 0;\n";
        } elseif ($col === 'course_description') {
            echo "ALTER TABLE courses ADD COLUMN $col TEXT DEFAULT NULL;\n";
        } else {
            echo "ALTER TABLE courses ADD COLUMN $col VARCHAR(255) DEFAULT NULL;\n";
        }
    }
}

$conn->close();
?>
