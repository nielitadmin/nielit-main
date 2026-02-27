<?php
/**
 * Helper Functions
 * NIELIT Bhubaneswar Student Management System
 */

/**
 * Sanitize input data
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Generate random password
 */
function generate_password($length = 8) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Generate OTP
 */
function generate_otp($length = 6) {
    return rand(pow(10, $length - 1), pow(10, $length) - 1);
}

/**
 * Calculate age from date of birth
 */
function calculate_age($dob) {
    if (empty($dob)) return '';
    
    $dob_date = new DateTime($dob);
    $current_date = new DateTime();
    return $dob_date->diff($current_date)->y;
}

/**
 * Format date
 */
function format_date($date, $format = 'd-m-Y') {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['student_id']);
}

/**
 * Check if admin is logged in
 */
function is_admin_logged_in() {
    return isset($_SESSION['admin']);
}

/**
 * Redirect to page
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Display alert message
 */
function show_alert($message, $type = 'success') {
    return '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">
                ' . htmlspecialchars($message) . '
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>';
}

/**
 * Validate file upload
 */
function validate_file_upload($file, $allowed_types, $max_size = MAX_FILE_SIZE) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error'];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File size exceeds limit'];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }
    
    return ['success' => true];
}

/**
 * Upload file
 */
function upload_file($file, $destination_dir) {
    $file_name = time() . '_' . basename($file['name']);
    $destination = $destination_dir . $file_name;
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => true, 'path' => $destination];
    }
    
    return ['success' => false, 'message' => 'Failed to upload file'];
}

/**
 * Generate Student ID
 */
function generate_student_id($conn, $course_abbr) {
    $current_year = date('Y');
    $like_pattern = "NIELIT/{$current_year}/{$course_abbr}/%";
    
    $stmt = $conn->prepare("SELECT student_id FROM students WHERE student_id LIKE ? ORDER BY student_id DESC LIMIT 1");
    $stmt->bind_param("s", $like_pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $last_id = $result->fetch_assoc()['student_id'];
        $last_number = (int)substr($last_id, strrpos($last_id, '/') + 1);
        $new_number = str_pad($last_number + 1, 4, "0", STR_PAD_LEFT);
    } else {
        $new_number = "0001";
    }
    
    return "NIELIT/{$current_year}/{$course_abbr}/{$new_number}";
}

/**
 * Get course abbreviation
 */
function get_course_abbreviation($course_name) {
    $abbreviations = [
        'Drone Boot Camp N0-18' => 'DBC18',
        'Drone Boot Camp N0-19' => 'DBC19',
        // Add more course abbreviations here
    ];
    
    return $abbreviations[$course_name] ?? 'GEN';
}
?>
