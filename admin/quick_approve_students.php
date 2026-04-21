<?php
/**
 * Quick Student Approval Tool
 * Approve students quickly for API testing
 */

session_start();
require_once __DIR__ . '/../config/config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    echo "ERROR: Please login as admin first at: admin/login.php";
    exit;
}

echo "=== QUICK STUDENT APPROVAL TOOL ===\n\n";

// Auto-approve students if requested
if (isset($_GET['approve_all'])) {
    try {
        $stmt = $conn->prepare("UPDATE students SET status = 'approved' WHERE status != 'approved'");
        if ($stmt->execute()) {
            $affected = $conn->affected_rows;
            echo "SUCCESS: Approved $affected students!\n\n";
        } else {
            echo "ERROR: Failed to approve students: " . $conn->error . "\n\n";
        }
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n\n";
    }
}

try {
    // Show current student status
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM students
    ");
    
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    
    echo "STUDENT STATUS SUMMARY:\n";
    echo "======================\n";
    echo "Total Students: " . $stats['total'] . "\n";
    echo "Approved: " . $stats['approved'] . "\n";
    echo "Pending: " . $stats['pending'] . "\n";
    echo "Rejected: " . $stats['rejected'] . "\n\n";
    
    if ($stats['approved'] > 0) {
        echo "✅ You have approved students! You can test the API now.\n";
        echo "Run: admin/get_test_student_data.php to get their credentials.\n\n";
    } else {
        echo "❌ No approved students found.\n\n";
        
        if ($stats['pending'] > 0) {
            echo "PENDING STUDENTS (Need Approval):\n";
            echo "=================================\n";
            
            $pending_stmt = $conn->prepare("
                SELECT student_id, name, email, course_id, created_at 
                FROM students 
                WHERE status = 'pending' 
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            $pending_stmt->execute();
            $pending_result = $pending_stmt->get_result();
            
            while ($student = $pending_result->fetch_assoc()) {
                echo "- " . $student['name'] . " (" . $student['student_id'] . ")\n";
                echo "  Email: " . $student['email'] . "\n";
                echo "  Course: " . $student['course_id'] . "\n";
                echo "  Registered: " . $student['created_at'] . "\n\n";
            }
            
            echo "QUICK ACTIONS:\n";
            echo "=============\n";
            echo "1. Approve all pending students:\n";
            echo "   http://localhost/public_html/admin/quick_approve_students.php?approve_all=1\n\n";
            echo "2. Or approve individually in admin panel:\n";
            echo "   http://localhost/public_html/admin/students.php\n\n";
        } else {
            echo "No students found in database.\n";
            echo "Register some students first:\n";
            echo "http://localhost/public_html/student/register.php\n\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "=== TESTING WORKFLOW ===\n";
echo "1. Ensure you have approved students (use approve_all link above if needed)\n";
echo "2. Get student credentials: admin/get_test_student_data.php\n";
echo "3. Update Postman environment with real credentials\n";
echo "4. Test API endpoints\n";
echo "5. Use working API in your mock test application\n\n";
?>