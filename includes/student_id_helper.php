<?php
/**
 * Student ID Generation Helper
 * NIELIT Bhubaneswar Student Management System
 * 
 * Generates unique student IDs in format: NIELIT/YYYY/ABBR/####
 * Example: NIELIT/2026/PPI/0001
 */

/**
 * Generate next student ID for a course
 * 
 * @param int $course_id The course ID
 * @param mysqli $conn Database connection
 * @return string|null Generated student ID or null on error
 */
function generateStudentID($course_id, $conn) {
    // Get course abbreviation
    $stmt = $conn->prepare("SELECT course_abbreviation, course_name FROM courses WHERE id = ?");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $course = $result->fetch_assoc();
    
    if (!$course) {
        error_log("Course not found for ID: $course_id");
        return null;
    }
    
    if (empty($course['course_abbreviation'])) {
        error_log("Course abbreviation not set for course: " . $course['course_name']);
        return null;
    }
    
    $abbreviation = strtoupper($course['course_abbreviation']);
    $year = date('Y'); // Current year (e.g., 2026)
    
    // Get the last student ID for this course and year
    $prefix = "NIELIT/{$year}/{$abbreviation}/";
    $stmt = $conn->prepare("
        SELECT student_id FROM students 
        WHERE student_id LIKE ? 
        ORDER BY student_id DESC 
        LIMIT 1
    ");
    $search_pattern = $prefix . '%';
    $stmt->bind_param("s", $search_pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $last_student = $result->fetch_assoc();
        $last_id = $last_student['student_id'];
        
        // Extract sequence number
        $parts = explode('/', $last_id);
        if (count($parts) >= 4) {
            $last_sequence = intval($parts[3]);
            $next_sequence = $last_sequence + 1;
        } else {
            // Invalid format, start from 1
            $next_sequence = 1;
        }
    } else {
        // First student for this course/year
        $next_sequence = 1;
    }
    
    // Format: NIELIT/2026/PPI/0001
    $student_id = sprintf("%s%04d", $prefix, $next_sequence);
    
    return $student_id;
}

/**
 * Validate student ID format
 * 
 * @param string $student_id The student ID to validate
 * @return bool True if valid, false otherwise
 */
function validateStudentID($student_id) {
    // Pattern: NIELIT/YYYY/ABBR/####
    $pattern = '/^NIELIT\/\d{4}\/[A-Z0-9]{1,10}\/\d{4}$/';
    return preg_match($pattern, $student_id) === 1;
}

/**
 * Parse student ID into components
 * 
 * @param string $student_id The student ID to parse
 * @return array|null Array with components or null if invalid
 */
function parseStudentID($student_id) {
    if (!validateStudentID($student_id)) {
        return null;
    }
    
    $parts = explode('/', $student_id);
    
    return [
        'institute' => $parts[0],
        'year' => $parts[1],
        'course_abbreviation' => $parts[2],
        'sequence' => $parts[3]
    ];
}

/**
 * Get student count for a course in current year
 * 
 * @param int $course_id The course ID
 * @param mysqli $conn Database connection
 * @return int Number of students
 */
function getStudentCountForCourse($course_id, $conn) {
    // Get course abbreviation
    $stmt = $conn->prepare("SELECT course_abbreviation FROM courses WHERE id = ?");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $course = $result->fetch_assoc();
    
    if (!$course || empty($course['course_abbreviation'])) {
        return 0;
    }
    
    $abbreviation = strtoupper($course['course_abbreviation']);
    $year = date('Y');
    $prefix = "NIELIT/{$year}/{$abbreviation}/%";
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE student_id LIKE ?");
    $stmt->bind_param("s", $prefix);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return intval($row['count']);
}

/**
 * Check if student ID already exists
 * 
 * @param string $student_id The student ID to check
 * @param mysqli $conn Database connection
 * @return bool True if exists, false otherwise
 */
function studentIDExists($student_id, $conn) {
    $stmt = $conn->prepare("SELECT id FROM students WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0;
}

/**
 * Get next available student ID (with retry logic)
 * 
 * @param int $course_id The course ID
 * @param mysqli $conn Database connection
 * @param int $max_retries Maximum number of retries
 * @return string|null Generated student ID or null on error
 */
function getNextStudentID($course_id, $conn, $max_retries = 5) {
    for ($i = 0; $i < $max_retries; $i++) {
        $student_id = generateStudentID($course_id, $conn);
        
        if ($student_id === null) {
            return null;
        }
        
        // Check if ID already exists (race condition protection)
        if (!studentIDExists($student_id, $conn)) {
            return $student_id;
        }
        
        // ID exists, retry
        usleep(100000); // Wait 100ms before retry
    }
    
    error_log("Failed to generate unique student ID after $max_retries attempts");
    return null;
}

/**
 * Format student ID for display
 * 
 * @param string $student_id The student ID
 * @return string Formatted student ID with HTML
 */
function formatStudentIDDisplay($student_id) {
    if (!validateStudentID($student_id)) {
        return htmlspecialchars($student_id);
    }
    
    $parts = parseStudentID($student_id);
    
    return sprintf(
        '<span class="student-id-display">' .
        '<span class="institute">%s</span>/' .
        '<span class="year">%s</span>/' .
        '<span class="course">%s</span>/' .
        '<span class="sequence">%s</span>' .
        '</span>',
        htmlspecialchars($parts['institute']),
        htmlspecialchars($parts['year']),
        htmlspecialchars($parts['course_abbreviation']),
        htmlspecialchars($parts['sequence'])
    );
}

/**
 * Get statistics for student IDs
 * 
 * @param mysqli $conn Database connection
 * @return array Statistics array
 */
function getStudentIDStatistics($conn) {
    $year = date('Y');
    
    // Total students this year
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE student_id LIKE ?");
    $pattern = "NIELIT/{$year}/%";
    $stmt->bind_param("s", $pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_this_year = $result->fetch_assoc()['count'];
    
    // Students per course this year
    $stmt = $conn->query("
        SELECT 
            c.course_name,
            c.course_abbreviation,
            COUNT(s.id) as student_count
        FROM courses c
        LEFT JOIN students s ON s.course_id = c.id 
            AND s.student_id LIKE 'NIELIT/{$year}/%'
        WHERE c.course_abbreviation IS NOT NULL
        GROUP BY c.id
        ORDER BY student_count DESC
    ");
    
    $courses = [];
    while ($row = $stmt->fetch_assoc()) {
        $courses[] = $row;
    }
    
    return [
        'total_this_year' => $total_this_year,
        'courses' => $courses,
        'year' => $year
    ];
}
?>
