<?php
$conn = new mysqli('localhost', 'root', '', 'nielit_bhubaneswar');

echo "=== Checking for ALL Duplicate Course Codes and Student ID Codes ===\n\n";

echo "1. Duplicate COURSE CODES:\n";
echo "==========================\n";
$result = $conn->query("
    SELECT course_code, GROUP_CONCAT(id ORDER BY id) as ids, 
           GROUP_CONCAT(course_name ORDER BY id SEPARATOR ' | ') as names,
           COUNT(*) as count
    FROM courses
    WHERE course_code IS NOT NULL AND course_code != ''
    GROUP BY UPPER(course_code)
    HAVING COUNT(*) > 1
");

if ($result->num_rows === 0) {
    echo "✓ No duplicate course codes\n\n";
} else {
    while ($row = $result->fetch_assoc()) {
        echo "Course Code: '{$row['course_code']}' - used by {$row['count']} courses:\n";
        $ids = explode(',', $row['ids']);
        $names = explode(' | ', $row['names']);
        for ($i = 0; $i < count($ids); $i++) {
            echo "  ID {$ids[$i]}: {$names[$i]}\n";
        }
        echo "\n";
    }
}

echo "2. Duplicate STUDENT ID CODES (Abbreviations):\n";
echo "===============================================\n";
$result = $conn->query("
    SELECT course_abbreviation, GROUP_CONCAT(id ORDER BY id) as ids,
           GROUP_CONCAT(course_name ORDER BY id SEPARATOR ' | ') as names,
           COUNT(*) as count
    FROM courses
    WHERE course_abbreviation IS NOT NULL AND course_abbreviation != ''
    GROUP BY UPPER(course_abbreviation)
    HAVING COUNT(*) > 1
");

if ($result->num_rows === 0) {
    echo "✓ No duplicate student ID codes\n\n";
} else {
    while ($row = $result->fetch_assoc()) {
        echo "Student ID Code: '{$row['course_abbreviation']}' - used by {$row['count']} courses:\n";
        $ids = explode(',', $row['ids']);
        $names = explode(' | ', $row['names']);
        for ($i = 0; $i < count($ids); $i++) {
            echo "  ID {$ids[$i]}: {$names[$i]}\n";
        }
        echo "\n";
    }
}

$conn->close();
?>
