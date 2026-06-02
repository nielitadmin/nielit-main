<?php
require_once __DIR__ . '/../config/config.php';

$course_ids = [32,33,34,35,36,39,40,41,42,43,45,46,47,54,55,56];
$sql = "UPDATE courses SET course_description = CONCAT(COALESCE(course_description, ''), ' [INTERNSHIP_PROGRAM]') WHERE id IN (" . implode(',', $course_ids) . ")";

if ($conn->query($sql)) {
    echo 'Added internship markers to ' . $conn->affected_rows . ' courses';
} else {
    echo 'Error: ' . $conn->error;
}
$conn->close();
?>