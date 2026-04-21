<?php
/**
 * Direct Student Credentials for API Testing
 * No session required - direct database access
 */

require_once __DIR__ . '/../config/config.php';

echo "=== NIELIT API TEST CREDENTIALS ===\n\n";

try {
    // Get approved students with their credentials
    $stmt = $conn->prepare("
        SELECT student_id, name, email, password, course_id, training_center 
        FROM students 
        WHERE status = 'approved' 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "READY-TO-USE STUDENT CREDENTIALS:\n";
        echo "=================================\n\n";
        
        $count = 1;
        while ($row = $result->fetch_assoc()) {
            echo "STUDENT #$count (Use in Postman):\n";
            echo "  Student ID: " . $row['student_id'] . "\n";
            echo "  Name: " . $row['name'] . "\n";
            echo "  Email: " . $row['email'] . "\n";
            echo "  Password: " . $row['password'] . "\n";
            echo "  Course: " . $row['course_id'] . "\n";
            echo "  Training Center: " . $row['training_center'] . "\n";
            echo "  ---\n\n";
            $count++;
        }
        
        // Get the first student for quick copy-paste
        $stmt->execute();
        $first_student = $stmt->get_result()->fetch_assoc();
        
        echo "=== QUICK COPY-PASTE FOR POSTMAN ===\n\n";
        echo "Update your Postman environment with these values:\n\n";
        echo "test_student_id: " . $first_student['student_id'] . "\n";
        echo "test_email: " . $first_student['email'] . "\n";
        echo "test_password: " . $first_student['password'] . "\n\n";
        
        echo "=== POSTMAN TEST EXAMPLES ===\n\n";
        echo "1. Authentication with Student ID:\n";
        echo "   POST /api/v1/auth.php\n";
        echo "   Body: {\"username\": \"" . $first_student['student_id'] . "\", \"password\": \"" . $first_student['password'] . "\"}\n\n";
        
        echo "2. Authentication with Email:\n";
        echo "   POST /api/v1/auth.php\n";
        echo "   Body: {\"username\": \"" . $first_student['email'] . "\", \"password\": \"" . $first_student['password'] . "\"}\n\n";
        
        echo "3. Get Student Data:\n";
        echo "   GET /api/v1/students.php?action=get&student_id=" . $first_student['student_id'] . "&api_key=YOUR_API_KEY\n\n";
        
    } else {
        echo "ERROR: No approved students found!\n";
        echo "Run the approval tool first: admin/quick_approve_students.php?approve_all=1\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "=== NEXT STEPS ===\n";
echo "1. Copy the credentials above\n";
echo "2. Update Postman environment variables\n";
echo "3. Test API authentication in Postman\n";
echo "4. All tests should now pass!\n\n";
?>