<?php
/**
 * Faculty Management System Migration
 * This creates the necessary tables for managing faculty members and their batch assignments
 */

require_once __DIR__ . '/../config/database.php';

try {
    // Start transaction
    $pdo->beginTransaction();
    
    echo "Installing Faculty Management System...\n";
    
    // Create faculty table
    $sql_faculty = "
    CREATE TABLE IF NOT EXISTS faculty (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE,
        phone VARCHAR(20),
        designation VARCHAR(100),
        department VARCHAR(100),
        is_active TINYINT(1) DEFAULT 1,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_name (name),
        INDEX idx_active (is_active)
    )";
    
    $pdo->exec($sql_faculty);
    echo "✓ Faculty table created\n";
    
    // Create batch_faculty junction table
    $sql_batch_faculty = "
    CREATE TABLE IF NOT EXISTS batch_faculty (
        id INT AUTO_INCREMENT PRIMARY KEY,
        batch_id INT NOT NULL,
        faculty_id INT NOT NULL,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        assigned_by INT,
        UNIQUE KEY unique_batch_faculty (batch_id, faculty_id),
        INDEX idx_batch_id (batch_id),
        INDEX idx_faculty_id (faculty_id)
    )";
    
    $pdo->exec($sql_batch_faculty);
    echo "✓ Batch-Faculty junction table created\n";
    
    // Insert sample faculty data
    $sample_faculty = [
        ['Dr. Rajesh Kumar', 'rajesh.kumar@nielit.gov.in', '9876543210', 'Senior Faculty', 'Computer Science'],
        ['Prof. Priya Sharma', 'priya.sharma@nielit.gov.in', '9876543211', 'Associate Professor', 'Information Technology'],
        ['Mr. Amit Singh', 'amit.singh@nielit.gov.in', '9876543212', 'Assistant Professor', 'Electronics'],
        ['Dr. Sunita Patel', 'sunita.patel@nielit.gov.in', '9876543213', 'Professor', 'Data Science'],
        ['Ms. Kavita Joshi', 'kavita.joshi@nielit.gov.in', '9876543214', 'Lecturer', 'Web Development']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO faculty (name, email, phone, designation, department, is_active) VALUES (?, ?, ?, ?, ?, 1)");
    
    foreach ($sample_faculty as $faculty) {
        $stmt->execute($faculty);
    }
    
    echo "✓ Sample faculty data inserted\n";
    
    // Commit transaction
    $pdo->commit();
    
    echo "\n✅ Faculty Management System installed successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Access Faculty Management at: admin/manage_faculty.php\n";
    echo "2. Add faculty members through the interface\n";
    echo "3. Assign faculty to batches in the admission order generation\n";
    
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollback();
    echo "\n❌ Error installing Faculty Management System: " . $e->getMessage() . "\n";
    exit(1);
}
?>