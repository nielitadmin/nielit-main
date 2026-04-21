<?php
/**
 * Get Test Student Data for API Testing
 * This will show you real student credentials to use in Postman
 */

session_start();
require_once __DIR__ . '/../config/config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    echo "ERROR: Please login as admin first at: admin/login.php";
    exit;
}

echo "=== NIELIT API TEST DATA ===\n\n";

try {
    // Get approved students with their credentials
    $stmt = $conn->prepare("
        SELECT student_id, name, email, password, course_id, training_center, status 
        FROM students 
        WHERE status = 'approved' 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "APPROVED STUDENTS FOR API TESTING:\n";
        echo "=====================================\n\n";
        
        $count = 1;
        while ($row = $result->fetch_assoc()) {
            echo "STUDENT #$count:\n";
            echo "  Student ID: " . $row['student_id'] . "\n";
            echo "  Name: " . $row['name'] . "\n";
            echo "  Email: " . $row['email'] . "\n";
            echo "  Password: " . $row['password'] . "\n";
            echo "  Course: " . $row['course_id'] . "\n";
            echo "  Training Center: " . $row['training_center'] . "\n";
            echo "  Status: " . $row['status'] . "\n";
            echo "  ---\n\n";
            $count++;
        }
        
        echo "=== HOW TO USE IN POSTMAN ===\n\n";
        echo "1. Update your Postman environment variables:\n";
        echo "   - test_student_id: Use any Student ID from above\n";
        echo "   - test_email: Use corresponding Email\n";
        echo "   - test_password: Use corresponding Password\n\n";
        
        echo "2. Test Authentication:\n";
        echo "   POST /api/v1/auth.php\n";
        echo "   Body: {\"username\": \"STUDENT_ID\", \"password\": \"PASSWORD\"}\n\n";
        
        echo "3. Test with Email:\n";
        echo "   POST /api/v1/auth.php\n";
        echo "   Body: {\"username\": \"EMAIL\", \"password\": \"PASSWORD\"}\n\n";
        
    } else {
        echo "NO APPROVED STUDENTS FOUND!\n\n";
        echo "SOLUTION: You need to approve some students first.\n\n";
        
        // Check if there are any students at all
        $check_stmt = $conn->prepare("SELECT COUNT(*) as total FROM students");
        $check_stmt->execute();
        $check_result = $check_stmt->get_result()->fetch_assoc();
        
        if ($check_result['total'] > 0) {
            echo "You have " . $check_result['total'] . " students in the database.\n";
            echo "Go to admin panel and approve some students first.\n\n";
            
            // Show pending students
            $pending_stmt = $conn->prepare("
                SELECT student_id, name, email, status 
                FROM students 
                WHERE status != 'approved' 
                LIMIT 3
            ");
            $pending_stmt->execute();
            $pending_result = $pending_stmt->get_result();
            
            if ($pending_result->num_rows > 0) {
                echo "STUDENTS WAITING FOR APPROVAL:\n";
                echo "==============================\n";
                while ($pending = $pending_result->fetch_assoc()) {
                    echo "- " . $pending['name'] . " (" . $pending['student_id'] . ") - Status: " . $pending['status'] . "\n";
                }
                echo "\nApprove these students in the admin panel first.\n";
            }
        } else {
            echo "No students found in database.\n";
            echo "Register some students first through the registration system.\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== NEXT STEPS ===\n";
echo "1. Copy student credentials from above\n";
echo "2. Update Postman environment variables\n";
echo "3. Test API authentication\n";
echo "4. Use working credentials in your mock test app\n\n";
?>