<?php
/**
 * Fix Duplicate QR Codes - Clean up and optimize QR code files
 * NIELIT Bhubaneswar - QR Attendance System Maintenance
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/attendance_qr_helper.php';

echo "<h2>QR Code Cleanup and Optimization</h2>";

try {
    // Get all students with QR codes
    $query = "SELECT student_id, name, attendance_qr_code FROM students WHERE attendance_qr_code IS NOT NULL";
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Failed to fetch students: " . $conn->error);
    }
    
    $students = $result->fetch_all(MYSQLI_ASSOC);
    echo "<p>Found " . count($students) . " students with QR code records</p>";
    
    $qr_dir = __DIR__ . '/../assets/qr_codes/attendance/';
    $files_cleaned = 0;
    $files_verified = 0;
    $files_regenerated = 0;
    $database_updated = 0;
    
    echo "<h3>Processing Student QR Codes...</h3>";
    
    foreach ($students as $student) {
        $student_id = $student['student_id'];
        $student_name = $student['name'];
        $current_qr_path = $student['attendance_qr_code'];
        
        echo "<div style='margin: 10px 0; padding: 10px; background: #f8f9fa; border-radius: 5px;'>";
        echo "<strong>Student:</strong> " . htmlspecialchars($student_name) . " (" . htmlspecialchars($student_id) . ")<br>";
        
        // Generate expected filename
        $safe_student_id = preg_replace('/[^a-zA-Z0-9_-]/', '_', $student_id);
        $expected_filename = 'student_qr_' . $safe_student_id . '.png';
        $expected_path = 'assets/qr_codes/attendance/' . $expected_filename;
        $expected_full_path = $qr_dir . $expected_filename;
        
        // Check current file status
        if (!empty($current_qr_path)) {
            $current_full_path = __DIR__ . '/../' . $current_qr_path;
            
            if (file_exists($current_full_path)) {
                if ($current_qr_path === $expected_path) {
                    echo "<span style='color: green;'>✓ QR code file exists and path is correct</span><br>";
                    $files_verified++;
                } else {
                    echo "<span style='color: orange;'>⚠ QR code exists but path needs updating</span><br>";
                    echo "Current: " . htmlspecialchars($current_qr_path) . "<br>";
                    echo "Expected: " . htmlspecialchars($expected_path) . "<br>";
                    
                    // Update database with correct path
                    $stmt = $conn->prepare("UPDATE students SET attendance_qr_code = ? WHERE student_id = ?");
                    if ($stmt) {
                        $stmt->bind_param("ss", $expected_path, $student_id);
                        if ($stmt->execute()) {
                            echo "<span style='color: blue;'>✓ Database path updated</span><br>";
                            $database_updated++;
                        }
                        $stmt->close();
                    }
                }
            } else {
                echo "<span style='color: red;'>✗ QR code file missing, regenerating...</span><br>";
                
                // Regenerate QR code
                $qr_result = generateStudentAttendanceQR($student_id, $student_name, $conn);
                if ($qr_result['success']) {
                    echo "<span style='color: green;'>✓ QR code regenerated successfully</span><br>";
                    $files_regenerated++;
                } else {
                    echo "<span style='color: red;'>✗ Failed to regenerate: " . htmlspecialchars($qr_result['message']) . "</span><br>";
                }
            }
        } else {
            echo "<span style='color: orange;'>⚠ No QR code path in database, generating...</span><br>";
            
            // Generate new QR code
            $qr_result = generateStudentAttendanceQR($student_id, $student_name, $conn);
            if ($qr_result['success']) {
                echo "<span style='color: green;'>✓ QR code generated successfully</span><br>";
                $files_regenerated++;
            } else {
                echo "<span style='color: red;'>✗ Failed to generate: " . htmlspecialchars($qr_result['message']) . "</span><br>";
            }
        }
        
        echo "</div>";
    }
    
    // Clean up orphaned QR files
    echo "<h3>Cleaning Up Orphaned Files...</h3>";
    
    if (is_dir($qr_dir)) {
        $files = scandir($qr_dir);
        $valid_files = [];
        
        // Get all valid filenames from database
        $query = "SELECT attendance_qr_code FROM students WHERE attendance_qr_code IS NOT NULL";
        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $filename = basename($row['attendance_qr_code']);
                $valid_files[] = $filename;
            }
        }
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            if (strpos($file, 'student_qr_') === 0 && !in_array($file, $valid_files)) {
                $file_path = $qr_dir . $file;
                if (unlink($file_path)) {
                    echo "<p style='color: orange;'>🗑 Removed orphaned file: " . htmlspecialchars($file) . "</p>";
                    $files_cleaned++;
                }
            }
        }
    }
    
    echo "<h3>Cleanup Summary</h3>";
    echo "<div style='background: #d4edda; color: #155724; padding: 20px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>✅ QR Code Cleanup Complete!</h4>";
    echo "<ul>";
    echo "<li><strong>Files Verified:</strong> $files_verified (already correct)</li>";
    echo "<li><strong>Database Paths Updated:</strong> $database_updated</li>";
    echo "<li><strong>Files Regenerated:</strong> $files_regenerated</li>";
    echo "<li><strong>Orphaned Files Cleaned:</strong> $files_cleaned</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<h4>🎯 Optimization Benefits:</h4>";
    echo "<ul>";
    echo "<li>✅ <strong>No More Duplicate Generation:</strong> QR codes are now generated only once</li>";
    echo "<li>✅ <strong>Consistent File Paths:</strong> All database paths are standardized</li>";
    echo "<li>✅ <strong>File System Cleanup:</strong> Removed unnecessary duplicate files</li>";
    echo "<li>✅ <strong>Performance Improved:</strong> Page refreshes no longer regenerate QR codes</li>";
    echo "</ul>";
    
    echo "<div style='background: #cce5ff; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>📋 Next Steps:</h4>";
    echo "<ol>";
    echo "<li>Test student attendance page - QR codes should load instantly</li>";
    echo "<li>Verify QR scanner still works with existing codes</li>";
    echo "<li>Monitor file system - no new duplicates should be created</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>❌ Cleanup Error</h4>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

$conn->close();
?>