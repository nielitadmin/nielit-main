<?php
/**
 * Batch Management Functions
 * NIELIT Bhubaneswar - Modular Batch System
 */

/**
 * Generate unique batch code
 */
function generateBatchCode($course_code, $conn) {
    $year = date('y');
    $base_code = strtoupper($course_code) . $year;
    
    // Find the next available number
    $sql = "SELECT batch_code FROM batches WHERE batch_code LIKE ? ORDER BY batch_code DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $search = $base_code . '%';
    $stmt->bind_param("s", $search);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $last_code = $row['batch_code'];
        // Extract number and increment
        preg_match('/\d+$/', $last_code, $matches);
        $next_num = isset($matches[0]) ? intval($matches[0]) + 1 : 1;
    } else {
        $next_num = 1;
    }
    
    $stmt->close();
    return $base_code . '_' . str_pad($next_num, 2, '0', STR_PAD_LEFT);
}

/**
 * Create new batch
 */
function createBatch($data, $conn) {
    $sql = "INSERT INTO batches (course_id, batch_name, batch_code, start_date, end_date, 
            training_fees, seats_total, batch_coordinator, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssdiis", 
        $data['course_id'],
        $data['batch_name'],
        $data['batch_code'],
        $data['start_date'],
        $data['end_date'],
        $data['training_fees'],
        $data['seats_total'],
        $data['batch_coordinator'],
        $data['status']
    );
    
    $result = $stmt->execute();
    $batch_id = $stmt->insert_id;
    $stmt->close();
    
    return $result ? $batch_id : false;
}

/**
 * Update batch
 */
function updateBatch($batch_id, $data, $conn) {
    $sql = "UPDATE batches SET 
            batch_name = ?, 
            start_date = ?, 
            end_date = ?, 
            training_fees = ?, 
            seats_total = ?, 
            batch_coordinator = ?, 
            status = ?,
            scheme_id = ?,
            admission_order_ref = ?,
            admission_order_date = ?,
            examination_month = ?,
            class_time = ?,
            copy_to_list = ?,
            location = ?
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssdisssssssssi", 
        $data['batch_name'],
        $data['start_date'],
        $data['end_date'],
        $data['training_fees'],
        $data['seats_total'],
        $data['batch_coordinator'],
        $data['status'],
        $data['scheme_id'],
        $data['admission_order_ref'],
        $data['admission_order_date'],
        $data['examination_month'],
        $data['class_time'],
        $data['copy_to_list'],
        $data['location'],
        $batch_id
    );
    
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Delete batch
 */
function deleteBatch($batch_id, $conn) {
    // Check if batch has students
    $check_sql = "SELECT COUNT(*) as count FROM batch_students WHERE batch_id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("i", $batch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row['count'] > 0) {
        return ['success' => false, 'message' => 'Cannot delete batch with enrolled students'];
    }
    
    $sql = "DELETE FROM batches WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $batch_id);
    $result = $stmt->execute();
    $stmt->close();
    
    return ['success' => $result, 'message' => $result ? 'Batch deleted successfully' : 'Error deleting batch'];
}

/**
 * Get batch by ID
 */
function getBatchById($batch_id, $conn) {
    $sql = "SELECT b.*, c.course_name, c.course_code, s.scheme_name, s.scheme_code,
            (SELECT COUNT(*) FROM students WHERE batch_id = b.id) as seats_filled
            FROM batches b 
            LEFT JOIN courses c ON b.course_id = c.id 
            LEFT JOIN schemes s ON b.scheme_id = s.id
            WHERE b.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $batch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $batch = $result->fetch_assoc();
    $stmt->close();
    
    return $batch;
}

/**
 * Get all batches for a course
 */
function getBatchesByCourse($course_id, $conn) {
    $sql = "SELECT b.*, 
            (SELECT COUNT(*) FROM batch_students WHERE batch_id = b.id) as enrolled_count
            FROM batches b 
            WHERE b.course_id = ? 
            ORDER BY b.start_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $batches = [];
    while ($row = $result->fetch_assoc()) {
        $batches[] = $row;
    }
    $stmt->close();
    
    return $batches;
}

/**
 * Get all active batches
 */
function getActiveBatches($conn) {
    $sql = "SELECT b.*, c.course_name, c.course_code,
            (SELECT COUNT(*) FROM batch_students WHERE batch_id = b.id) as enrolled_count
            FROM batches b 
            LEFT JOIN courses c ON b.course_id = c.id 
            WHERE b.status = 'Active' 
            ORDER BY b.start_date DESC";
    $result = $conn->query($sql);
    
    $batches = [];
    while ($row = $result->fetch_assoc()) {
        $batches[] = $row;
    }
    
    return $batches;
}

/**
 * Approve student and assign to batch
 */
function approveStudent($student_id, $batch_id, $admin_name, $conn) {
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update student status
        $sql1 = "UPDATE students SET 
                status = 'Approved', 
                batch_id = ?, 
                approved_by = ?, 
                approved_at = NOW() 
                WHERE id = ?";
        $stmt1 = $conn->prepare($sql1);
        $stmt1->bind_param("isi", $batch_id, $admin_name, $student_id);
        $stmt1->execute();
        $stmt1->close();
        
        // Add to batch_students
        $sql2 = "INSERT INTO batch_students (batch_id, student_id, enrollment_date) 
                VALUES (?, ?, NOW())";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("ii", $batch_id, $student_id);
        $stmt2->execute();
        $stmt2->close();
        
        // Update batch seats_filled
        $sql3 = "UPDATE batches SET seats_filled = seats_filled + 1 WHERE id = ?";
        $stmt3 = $conn->prepare($sql3);
        $stmt3->bind_param("i", $batch_id);
        $stmt3->execute();
        $stmt3->close();
        
        // Generate student ID if not exists
        $sql4 = "SELECT student_id FROM students WHERE id = ?";
        $stmt4 = $conn->prepare($sql4);
        $stmt4->bind_param("i", $student_id);
        $stmt4->execute();
        $result = $stmt4->get_result();
        $row = $result->fetch_assoc();
        $stmt4->close();
        
        if (empty($row['student_id'])) {
            // Generate student ID
            $student_id_code = 'NIELIT' . date('Y') . str_pad($student_id, 5, '0', STR_PAD_LEFT);
            $sql5 = "UPDATE students SET student_id = ? WHERE id = ?";
            $stmt5 = $conn->prepare($sql5);
            $stmt5->bind_param("si", $student_id_code, $student_id);
            $stmt5->execute();
            $stmt5->close();
        }
        
        $conn->commit();
        return ['success' => true, 'message' => 'Student approved and assigned to batch successfully'];
        
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Reject student
 */
function rejectStudent($student_id, $admin_name, $conn) {
    $sql = "UPDATE students SET 
            status = 'Rejected', 
            approved_by = ?, 
            approved_at = NOW() 
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $admin_name, $student_id);
    $result = $stmt->execute();
    $stmt->close();
    
    return ['success' => $result, 'message' => $result ? 'Student rejected' : 'Error rejecting student'];
}

/**
 * Get pending students
 */
function getPendingStudents($conn) {
    $sql = "SELECT s.*, c.course_name 
            FROM students s 
            LEFT JOIN courses c ON s.course = c.course_name 
            WHERE s.status = 'Pending' 
            ORDER BY s.created_at DESC";
    $result = $conn->query($sql);
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    
    return $students;
}

/**
 * Get students in a batch
 */
function getBatchStudents($batch_id, $conn) {
    // First, try to get students from batch_students junction table
    $check_column = $conn->query("SHOW COLUMNS FROM batch_students LIKE 'nielit_registration_no'");
    $has_nielit_column = ($check_column && $check_column->num_rows > 0);
    
    if ($has_nielit_column) {
        // Use batch_students table with nielit_registration_no
        $sql = "SELECT s.*, bs.enrollment_date, bs.fees_status, bs.fees_paid, 
                bs.attendance_percentage, bs.nielit_registration_no
                FROM batch_students bs
                INNER JOIN students s ON bs.student_id = s.id
                WHERE bs.batch_id = ? 
                ORDER BY s.name ASC";
    } else {
        // Fallback: use batch_students table without nielit_registration_no
        $sql = "SELECT s.*, bs.enrollment_date, bs.fees_status, bs.fees_paid, 
                bs.attendance_percentage, NULL as nielit_registration_no
                FROM batch_students bs
                INNER JOIN students s ON bs.student_id = s.id
                WHERE bs.batch_id = ? 
                ORDER BY s.name ASC";
    }
    
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("i", $batch_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $students = [];
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
        $stmt->close();
        
        // If we got students from batch_students, return them
        if (!empty($students)) {
            return $students;
        }
    }
    
    // If batch_students table doesn't exist OR has no records, fall back to students table
    $sql = "SELECT s.*, s.created_at as enrollment_date, 
            'Not Paid' as fees_status, 0 as fees_paid, 0 as attendance_percentage,
            s.nielit_registration_no
            FROM students s 
            WHERE s.batch_id = ? 
            ORDER BY s.name ASC";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        // Last resort: return empty array
        return [];
    }
    
    $stmt->bind_param("i", $batch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();
    
    return $students;
}

/**
 * Remove student from batch
 */
function removeStudentFromBatch($student_id, $batch_id, $conn) {
    // Update student's batch_id to NULL using numeric ID
    $sql = "UPDATE students SET batch_id = NULL WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        return ['success' => true, 'message' => 'Student removed from batch successfully'];
    } else {
        $error = $stmt->error;
        $stmt->close();
        return ['success' => false, 'message' => 'Error: ' . $error];
    }
}

/**
 * Get batch statistics
 */
function getBatchStats($batch_id, $conn) {
    $sql = "SELECT 
            COUNT(*) as total_students,
            0 as fees_paid_count,
            0 as total_fees_collected,
            0 as avg_attendance
            FROM students 
            WHERE batch_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $batch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    $stmt->close();
    
    return $stats;
}
?>
