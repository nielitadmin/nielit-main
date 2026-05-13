<?php
/**
 * Populate Sample Batch Performance Data
 * 
 * This script adds sample data to make the Batch Performance chart visible
 * by updating seats_filled and adding sample attendance data.
 */

require_once __DIR__ . '/../config/config.php';

echo "=== Populating Sample Batch Performance Data ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Step 1: Update existing batches with sample seat fill data
    echo "Step 1: Updating batch seat fill data...\n";
    
    $batches_query = "SELECT id, batch_name, seats_total FROM batches ORDER BY id";
    $batches_result = $conn->query($batches_query);
    
    if ($batches_result && $batches_result->num_rows > 0) {
        while ($batch = $batches_result->fetch_assoc()) {
            $batch_id = $batch['id'];
            $seats_total = $batch['seats_total'];
            
            // Generate random filled seats (60-95% capacity)
            $fill_percentage = rand(60, 95);
            $seats_filled = round(($fill_percentage / 100) * $seats_total);
            
            // Update batch with filled seats
            $update_batch = $conn->prepare("UPDATE batches SET seats_filled = ? WHERE id = ?");
            $update_batch->bind_param("ii", $seats_filled, $batch_id);
            $update_batch->execute();
            
            echo "✅ Updated batch '{$batch['batch_name']}': {$seats_filled}/{$seats_total} seats filled ({$fill_percentage}%)\n";
        }
    }
    
    // Step 2: Add sample students to batch_students table with attendance data
    echo "\nStep 2: Adding sample batch-student assignments with attendance...\n";
    
    // Clear existing batch_students data first
    $conn->query("DELETE FROM batch_students");
    echo "🗑️ Cleared existing batch_students data\n";
    
    // Get some students to assign to batches
    $students_query = "SELECT id FROM students ORDER BY id LIMIT 50";
    $students_result = $conn->query($students_query);
    $student_ids = [];
    
    if ($students_result) {
        while ($student = $students_result->fetch_assoc()) {
            $student_ids[] = $student['id'];
        }
    }
    
    if (empty($student_ids)) {
        echo "⚠️ No students found in database. Creating sample students...\n";
        
        // Create some sample students
        for ($i = 1; $i <= 20; $i++) {
            $name = "Sample Student " . $i;
            $email = "student{$i}@example.com";
            $phone = "98765432" . str_pad($i, 2, '0', STR_PAD_LEFT);
            $course_id = 1; // Assuming course ID 1 exists
            
            $insert_student = $conn->prepare("INSERT INTO students (name, email, phone, course_id, status) VALUES (?, ?, ?, ?, 'approved')");
            $insert_student->bind_param("sssi", $name, $email, $phone, $course_id);
            $insert_student->execute();
            $student_ids[] = $conn->insert_id;
        }
        echo "✅ Created 20 sample students\n";
    }
    
    // Assign students to batches with attendance data
    $batches_result = $conn->query("SELECT id, batch_name, seats_filled FROM batches ORDER BY id");
    $student_index = 0;
    
    if ($batches_result) {
        while ($batch = $batches_result->fetch_assoc()) {
            $batch_id = $batch['id'];
            $seats_filled = $batch['seats_filled'];
            
            // Assign students to this batch
            for ($i = 0; $i < $seats_filled && $student_index < count($student_ids); $i++) {
                $student_id = $student_ids[$student_index];
                
                // Generate random attendance percentage (70-98%)
                $attendance_percentage = rand(70, 98) + (rand(0, 99) / 100);
                
                // Generate random fees data
                $fees_paid = rand(5000, 15000);
                $fees_status = rand(1, 10) > 2 ? 'Paid' : (rand(1, 2) == 1 ? 'Partial' : 'Pending');
                
                $insert_batch_student = $conn->prepare("
                    INSERT INTO batch_students 
                    (batch_id, student_id, attendance_percentage, fees_paid, fees_status, enrollment_date) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $insert_batch_student->bind_param("iidss", $batch_id, $student_id, $attendance_percentage, $fees_paid, $fees_status);
                $insert_batch_student->execute();
                
                $student_index++;
            }
            
            echo "✅ Assigned {$seats_filled} students to batch '{$batch['batch_name']}' with attendance data\n";
        }
    }
    
    // Step 3: Verify the results
    echo "\nStep 3: Verifying batch performance data...\n";
    
    $verify_query = "
        SELECT 
            b.batch_name, 
            b.seats_total, 
            b.seats_filled,
            COALESCE(ROUND(AVG(COALESCE(bs.attendance_percentage, 0)), 1), 0) AS avg_attendance,
            COUNT(bs.student_id) as assigned_students
        FROM batches b 
        LEFT JOIN batch_students bs ON bs.batch_id = b.id 
        GROUP BY b.id 
        ORDER BY b.updated_at DESC
    ";
    
    $verify_result = $conn->query($verify_query);
    
    if ($verify_result && $verify_result->num_rows > 0) {
        echo "\n📊 Batch Performance Summary:\n";
        echo str_pad("Batch Name", 20) . str_pad("Seats", 10) . str_pad("Filled", 10) . str_pad("Students", 12) . str_pad("Avg Attendance", 15) . "\n";
        echo str_repeat("-", 67) . "\n";
        
        while ($row = $verify_result->fetch_assoc()) {
            $fill_rate = $row['seats_total'] > 0 ? round(($row['seats_filled'] / $row['seats_total']) * 100, 1) : 0;
            
            echo str_pad($row['batch_name'], 20) . 
                 str_pad($row['seats_total'], 10) . 
                 str_pad($row['seats_filled'], 10) . 
                 str_pad($row['assigned_students'], 12) . 
                 str_pad($row['avg_attendance'] . "%", 15) . "\n";
        }
    }
    
    echo "\n✅ Sample data population completed successfully!\n";
    echo "🎯 The Batch Performance chart in the dashboard should now display data.\n";
    echo "🔄 Refresh your dashboard to see the updated charts.\n\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

$conn->close();
?>