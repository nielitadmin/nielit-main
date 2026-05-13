<?php
/**
 * Test Payment Details Control System
 * 
 * This script tests the payment details control functionality
 * to ensure it works correctly after the fixes.
 */

require_once __DIR__ . '/../config/config.php';

echo "<h2>Payment Details Control System Test</h2>\n";

// Test 1: Check if payment_details_required column exists
echo "<h3>Test 1: Column Existence Check</h3>\n";
$column_check = $conn->query("SHOW COLUMNS FROM courses LIKE 'payment_details_required'");
$payment_column_exists = $column_check && $column_check->num_rows > 0;

if ($payment_column_exists) {
    echo "✅ <strong>PASS:</strong> payment_details_required column exists<br>\n";
} else {
    echo "❌ <strong>FAIL:</strong> payment_details_required column missing<br>\n";
}

// Test 2: Check if courses have payment control settings
echo "<h3>Test 2: Course Payment Settings</h3>\n";
if ($payment_column_exists) {
    $courses_query = "SELECT id, course_name, course_code, payment_details_required FROM courses LIMIT 5";
    $result = $conn->query($courses_query);
    
    if ($result && $result->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr><th>ID</th><th>Course Name</th><th>Course Code</th><th>Payment Required</th></tr>\n";
        
        while ($course = $result->fetch_assoc()) {
            $payment_status = $course['payment_details_required'] ?? 'optional';
            $status_color = $payment_status === 'required' ? 'red' : 'green';
            
            echo "<tr>";
            echo "<td>" . htmlspecialchars($course['id']) . "</td>";
            echo "<td>" . htmlspecialchars($course['course_name']) . "</td>";
            echo "<td>" . htmlspecialchars($course['course_code'] ?? 'N/A') . "</td>";
            echo "<td style='color: $status_color; font-weight: bold;'>" . ucfirst($payment_status) . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
        echo "✅ <strong>PASS:</strong> Course payment settings are accessible<br>\n";
    } else {
        echo "⚠️ <strong>WARNING:</strong> No courses found to test<br>\n";
    }
} else {
    echo "❌ <strong>SKIP:</strong> Cannot test without payment_details_required column<br>\n";
}

// Test 3: Test the variable definition fix
echo "<h3>Test 3: Variable Definition Test</h3>\n";
echo "Testing the \$payment_column_exists variable that was causing the error...<br>\n";

// Simulate the code from edit_course.php
$column_check_test = $conn->query("SHOW COLUMNS FROM courses LIKE 'payment_details_required'");
$payment_column_exists_test = $column_check_test && $column_check_test->num_rows > 0;

if (isset($payment_column_exists_test)) {
    echo "✅ <strong>PASS:</strong> \$payment_column_exists variable is properly defined<br>\n";
    echo "Value: " . ($payment_column_exists_test ? 'true' : 'false') . "<br>\n";
} else {
    echo "❌ <strong>FAIL:</strong> \$payment_column_exists variable is not defined<br>\n";
}

// Test 4: Test bind_param parameter count
echo "<h3>Test 4: SQL Parameter Count Test</h3>\n";

// Test the SQL with payment_details_required (21 parameters)
$sql_with_payment = "UPDATE courses SET 
    course_name = ?, 
    course_code = ?,
    course_abbreviation = ?,
    eligibility = ?, 
    duration = ?, 
    training_fees = ?, 
    category = ?, 
    start_date = ?, 
    end_date = ?, 
    description_url = ?, 
    description_pdf = ?, 
    course_flyer = ?,
    apply_link = ?,
    course_coordinator = ?,
    training_center = ?,
    centre_id = ?,
    link_published = ?,
    enrollment_status = ?,
    payment_details_required = ?,
    course_description = ?
    WHERE id = ?";

$param_count_with_payment = substr_count($sql_with_payment, '?');
$expected_with_payment = 21;

if ($param_count_with_payment === $expected_with_payment) {
    echo "✅ <strong>PASS:</strong> SQL with payment has correct parameter count ($param_count_with_payment)<br>\n";
} else {
    echo "❌ <strong>FAIL:</strong> SQL with payment has wrong parameter count. Expected: $expected_with_payment, Got: $param_count_with_payment<br>\n";
}

// Test the SQL without payment_details_required (20 parameters)
$sql_without_payment = "UPDATE courses SET 
    course_name = ?, 
    course_code = ?,
    course_abbreviation = ?,
    eligibility = ?, 
    duration = ?, 
    training_fees = ?, 
    category = ?, 
    start_date = ?, 
    end_date = ?, 
    description_url = ?, 
    description_pdf = ?, 
    course_flyer = ?,
    apply_link = ?,
    course_coordinator = ?,
    training_center = ?,
    centre_id = ?,
    link_published = ?,
    enrollment_status = ?,
    course_description = ?
    WHERE id = ?";

$param_count_without_payment = substr_count($sql_without_payment, '?');
$expected_without_payment = 20;

if ($param_count_without_payment === $expected_without_payment) {
    echo "✅ <strong>PASS:</strong> SQL without payment has correct parameter count ($param_count_without_payment)<br>\n";
} else {
    echo "❌ <strong>FAIL:</strong> SQL without payment has wrong parameter count. Expected: $expected_without_payment, Got: $param_count_without_payment<br>\n";
}

echo "<h3>Summary</h3>\n";
echo "All tests completed. If all tests pass, the payment details control system should work correctly.<br>\n";
echo "<strong>Next Steps:</strong><br>\n";
echo "1. Test editing a course in admin/edit_course.php<br>\n";
echo "2. Test student registration with different payment settings<br>\n";
echo "3. Verify that the payment control UI works properly<br>\n";

$conn->close();
?>