<?php
// Fix duplicate Student ID Codes in the database
$conn = new mysqli('localhost', 'root', '', 'nielit_bhubaneswar');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== Fixing Duplicate Student ID Codes ===\n\n";

// Find all duplicate Student ID Codes
echo "Step 1: Finding duplicate Student ID Codes...\n";
echo "----------------------------------------------\n";

$query = "SELECT course_abbreviation, GROUP_CONCAT(id ORDER BY id) as course_ids, 
          GROUP_CONCAT(course_name ORDER BY id SEPARATOR ' | ') as course_names,
          COUNT(*) as count
          FROM courses
          WHERE course_abbreviation IS NOT NULL AND course_abbreviation != ''
          GROUP BY UPPER(course_abbreviation)
          HAVING COUNT(*) > 1
          ORDER BY count DESC, course_abbreviation";

$result = $conn->query($query);

if ($result->num_rows === 0) {
    echo "✓ No duplicate Student ID Codes found!\n";
    $conn->close();
    exit(0);
}

echo "Found duplicate Student ID Codes:\n\n";
$duplicates = [];
while ($row = $result->fetch_assoc()) {
    echo "Student ID Code: {$row['course_abbreviation']}\n";
    echo "  Used by {$row['count']} courses:\n";
    
    $ids = explode(',', $row['course_ids']);
    $names = explode(' | ', $row['course_names']);
    
    for ($i = 0; $i < count($ids); $i++) {
        echo "    - ID {$ids[$i]}: {$names[$i]}\n";
    }
    echo "\n";
    
    $duplicates[] = [
        'abbr' => $row['course_abbreviation'],
        'ids' => $ids,
        'names' => $names
    ];
}

echo "\nStep 2: Proposed fixes (keeping the first course, renaming others):\n";
echo "--------------------------------------------------------------------\n";

foreach ($duplicates as $dup) {
    $base_abbr = $dup['abbr'];
    echo "\nStudent ID Code '$base_abbr':\n";
    
    // Keep the first course as-is
    echo "  ✓ Keep ID {$dup['ids'][0]} ({$dup['names'][0]}) as '$base_abbr'\n";
    
    // Rename the others
    for ($i = 1; $i < count($dup['ids']); $i++) {
        $course_id = $dup['ids'][$i];
        $course_name = $dup['names'][$i];
        
        // Generate a new unique abbreviation
        // Try adding a suffix: SASW -> SASWT, SASW2, SASW3, etc.
        $new_abbr = null;
        
        // Try using more letters from the course name
        $name_upper = strtoupper($course_name);
        $name_letters = preg_replace('/[^A-Z]/', '', $name_upper);
        
        // Try first 5 letters
        if (strlen($name_letters) >= 5) {
            $test_abbr = substr($name_letters, 0, 5);
            $check = $conn->query("SELECT id FROM courses WHERE UPPER(course_abbreviation) = '$test_abbr' AND id != $course_id");
            if ($check->num_rows === 0) {
                $new_abbr = $test_abbr;
            }
        }
        
        // Try first 4 letters
        if (!$new_abbr && strlen($name_letters) >= 4) {
            $test_abbr = substr($name_letters, 0, 4);
            $check = $conn->query("SELECT id FROM courses WHERE UPPER(course_abbreviation) = '$test_abbr' AND id != $course_id");
            if ($check->num_rows === 0) {
                $new_abbr = $test_abbr;
            }
        }
        
        // Fallback: add numeric suffix
        if (!$new_abbr) {
            for ($j = 2; $j <= 10; $j++) {
                $test_abbr = $base_abbr . $j;
                $check = $conn->query("SELECT id FROM courses WHERE UPPER(course_abbreviation) = '$test_abbr'");
                if ($check->num_rows === 0) {
                    $new_abbr = $test_abbr;
                    break;
                }
            }
        }
        
        if ($new_abbr) {
            echo "  → Change ID $course_id ($course_name) to '$new_abbr'\n";
        } else {
            echo "  ✗ Could not find unique abbreviation for ID $course_id ($course_name)\n";
        }
    }
}

echo "\n\nStep 3: Apply fixes? (This will update the database)\n";
echo "---------------------------------------------------\n";

// Check if we should apply the fixes (command line or GET parameter)
$apply = false;
if (php_sapi_name() === 'cli') {
    // Command line: check for --apply argument
    $apply = in_array('--apply', $argv);
    if (!$apply) {
        echo "Run this script with --apply to apply the fixes\n";
        echo "Example: php fix_duplicate_student_id_codes.php --apply\n\n";
    }
} else {
    // Web: check for GET parameter
    $apply = isset($_GET['apply']) && $_GET['apply'] === 'yes';
    if (!$apply) {
        echo "Run this script with ?apply=yes to apply the fixes\n";
        echo "Example: php fix_duplicate_student_id_codes.php?apply=yes\n\n";
    }
}

// Check if we should apply the fixes
if ($apply) {
    echo "\n=== APPLYING FIXES ===\n\n";
    
    foreach ($duplicates as $dup) {
        $base_abbr = $dup['abbr'];
        
        for ($i = 1; $i < count($dup['ids']); $i++) {
            $course_id = $dup['ids'][$i];
            $course_name = $dup['names'][$i];
            
            // Generate new abbreviation (same logic as above)
            $new_abbr = null;
            $name_upper = strtoupper($course_name);
            $name_letters = preg_replace('/[^A-Z]/', '', $name_upper);
            
            if (strlen($name_letters) >= 5) {
                $test_abbr = substr($name_letters, 0, 5);
                $check = $conn->query("SELECT id FROM courses WHERE UPPER(course_abbreviation) = '$test_abbr' AND id != $course_id");
                if ($check->num_rows === 0) {
                    $new_abbr = $test_abbr;
                }
            }
            
            if (!$new_abbr && strlen($name_letters) >= 4) {
                $test_abbr = substr($name_letters, 0, 4);
                $check = $conn->query("SELECT id FROM courses WHERE UPPER(course_abbreviation) = '$test_abbr' AND id != $course_id");
                if ($check->num_rows === 0) {
                    $new_abbr = $test_abbr;
                }
            }
            
            if (!$new_abbr) {
                for ($j = 2; $j <= 10; $j++) {
                    $test_abbr = $base_abbr . $j;
                    $check = $conn->query("SELECT id FROM courses WHERE UPPER(course_abbreviation) = '$test_abbr'");
                    if ($check->num_rows === 0) {
                        $new_abbr = $test_abbr;
                        break;
                    }
                }
            }
            
            if ($new_abbr) {
                $stmt = $conn->prepare("UPDATE courses SET course_abbreviation = ? WHERE id = ?");
                $stmt->bind_param("si", $new_abbr, $course_id);
                if ($stmt->execute()) {
                    echo "✓ Updated ID $course_id ($course_name): '$base_abbr' → '$new_abbr'\n";
                } else {
                    echo "✗ Failed to update ID $course_id: " . $conn->error . "\n";
                }
                $stmt->close();
            }
        }
    }
    
    echo "\n✓ Fixes applied successfully!\n";
}

$conn->close();
?>
