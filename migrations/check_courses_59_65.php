<?php
$c = new mysqli('localhost', 'root', '', 'nielit_bhubaneswar');
$r = $c->query("SELECT id, course_name, course_code, course_abbreviation FROM courses WHERE id IN (59, 65)");
while($row = $r->fetch_assoc()) {
    echo "ID: {$row['id']}, Name: {$row['course_name']}, Code: {$row['course_code']}, Abbr: {$row['course_abbreviation']}\n";
}
?>
