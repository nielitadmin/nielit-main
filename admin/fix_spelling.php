<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login_new.php');
    exit();
}

require_once '../config/database.php';

$output = '';
$fixed = false;

if (isset($_POST['fix_spelling'])) {
    ob_start();
    
    echo "<h4>🔧 Training Centre Spelling Fix</h4>";
    echo "<p>Checking courses table for American spelling in training_center field...</p>";
    
    try {
        // First, let's see what we have
        $query = "SELECT id, course_name, training_center FROM courses WHERE training_center LIKE '%Center%'";
        $result = $conn->query($query);
        
        echo "<h5>Courses with 'Center' spelling:</h5>";
        echo "<table class='table table-sm'>";
        echo "<tr><th>ID</th><th>Course Name</th><th>Training Center</th></tr>";
        
        $courses_to_fix = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<tr><td>" . $row['id'] . "</td><td>" . htmlspecialchars($row['course_name']) . "</td><td>" . htmlspecialchars($row['training_center']) . "</td></tr>";
                
                $courses_to_fix[] = [
                    'id' => $row['id'],
                    'course_name' => $row['course_name'],
                    'old_training_center' => $row['training_center'],
                    'new_training_center' => str_replace('Center', 'Centre', $row['training_center'])
                ];
            }
        } else {
            echo "<tr><td colspan='3'>No courses found with 'Center' spelling.</td></tr>";
        }
        echo "</table>";
        
        if (empty($courses_to_fix)) {
            echo "<div class='alert alert-success'>✅ No courses found with American spelling 'Center'. All good!</div>";
        } else {
            echo "<div class='alert alert-info'>Found " . count($courses_to_fix) . " course(s) with American spelling.</div>";
            
            echo "<h5>Changes to be made:</h5>";
            echo "<table class='table table-sm'>";
            echo "<tr><th>Course</th><th>Old</th><th>New</th></tr>";
            
            foreach ($courses_to_fix as $course) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($course['course_name']) . "</td>";
                echo "<td>" . htmlspecialchars($course['old_training_center']) . "</td>";
                echo "<td>" . htmlspecialchars($course['new_training_center']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            echo "<p><strong>Updating courses to use British spelling...</strong></p>";
            
            $updated_count = 0;
            foreach ($courses_to_fix as $course) {
                $stmt = $conn->prepare("UPDATE courses SET training_center = ? WHERE id = ?");
                $stmt->bind_param("si", $course['new_training_center'], $course['id']);
                
                if ($stmt->execute()) {
                    echo "<div class='alert alert-success'>✅ Updated Course ID " . $course['id'] . ": " . htmlspecialchars($course['old_training_center']) . " → " . htmlspecialchars($course['new_training_center']) . "</div>";
                    $updated_count++;
                } else {
                    echo "<div class='alert alert-danger'>❌ Failed to update Course ID " . $course['id'] . ": " . $conn->error . "</div>";
                }
                $stmt->close();
            }
            
            echo "<div class='alert alert-success'><h5>Summary</h5>";
            echo "Updated $updated_count out of " . count($courses_to_fix) . " courses.</div>";
            
            $fixed = true;
        }
        
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>❌ Error: " . $e->getMessage() . "</div>";
    }
    
    $output = ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Training Centre Spelling - NIELIT Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-spell-check"></i> Fix Training Centre Spelling</h3>
                        <p class="mb-0">This tool will change "Training Center" to "Training Centre" in the courses table.</p>
                    </div>
                    <div class="card-body">
                        <?php if ($output): ?>
                            <div class="mb-4">
                                <?php echo $output; ?>
                            </div>
                            
                            <?php if ($fixed): ?>
                                <div class="alert alert-success">
                                    <h5>✅ Fix Complete!</h5>
                                    <p>The spelling has been updated. Now go back to your course management page and the dropdown should show "Training Centre" instead of "Training Center".</p>
                                    <a href="manage_courses.php" class="btn btn-primary">
                                        <i class="fas fa-arrow-left"></i> Back to Manage Courses
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <form method="POST">
                                <div class="alert alert-info">
                                    <h5><i class="fas fa-info-circle"></i> What this does:</h5>
                                    <ul>
                                        <li>Scans the courses table for any "Training Center" entries</li>
                                        <li>Changes them to "Training Centre" (British spelling)</li>
                                        <li>Updates the dropdown options to show correct spelling</li>
                                    </ul>
                                </div>
                                
                                <button type="submit" name="fix_spelling" class="btn btn-primary">
                                    <i class="fas fa-magic"></i> Fix Spelling Now
                                </button>
                                <a href="manage_courses.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>