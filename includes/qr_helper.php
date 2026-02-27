<?php
/**
 * QR Code Helper Functions
 * NIELIT Bhubaneswar - Course Registration System
 */

// Include phpqrcode library
require_once __DIR__ . '/../phpqrcode/qrlib.php';

/**
 * Generate QR Code for Course Registration Link
 * 
 * @param int $course_id - Course ID from database
 * @param string $course_code - Course code for URL (e.g., 'DBC', 'PPI')
 * @return array - ['success' => bool, 'path' => string, 'url' => string, 'message' => string]
 */
function generateCourseQRCode($course_id, $course_code = '') {
    try {
        // Create QR codes directory if it doesn't exist
        $qr_dir = __DIR__ . '/../assets/qr_codes/';
        if (!file_exists($qr_dir)) {
            mkdir($qr_dir, 0777, true);
        }

        // Generate registration URL using course CODE (not course ID)
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
        $registration_url = $base_url . "/student/register.php?course=" . urlencode($course_code);

        // Create filename
        $safe_name = !empty($course_code) ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $course_code) : 'course_' . $course_id;
        $filename = 'qr_' . $safe_name . '_' . $course_id . '.png';
        $qr_file_path = $qr_dir . $filename;

        // Generate QR Code
        // Parameters: data, filename, error_correction_level, pixel_size, margin
        QRcode::png($registration_url, $qr_file_path, QR_ECLEVEL_L, 10, 2);

        // Verify file was created
        if (file_exists($qr_file_path)) {
            return [
                'success' => true,
                'path' => 'assets/qr_codes/' . $filename,
                'full_path' => $qr_file_path,
                'url' => $registration_url,
                'filename' => $filename,
                'message' => 'QR Code generated successfully'
            ];
        } else {
            return [
                'success' => false,
                'path' => '',
                'url' => $registration_url,
                'message' => 'QR Code file was not created'
            ];
        }

    } catch (Exception $e) {
        return [
            'success' => false,
            'path' => '',
            'url' => '',
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Generate Registration Link for Course
 * 
 * @param string $course_code - Course code (e.g., 'DBC', 'PPI')
 * @return string - Full registration URL
 */
function generateRegistrationLink($course_code) {
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
    return $base_url . "/student/register.php?course=" . urlencode($course_code);
}

/**
 * Delete QR Code File
 * 
 * @param string $qr_path - Relative path to QR code file
 * @return bool - Success status
 */
function deleteQRCode($qr_path) {
    if (empty($qr_path)) {
        return false;
    }

    $full_path = __DIR__ . '/../' . $qr_path;
    
    if (file_exists($full_path)) {
        return unlink($full_path);
    }
    
    return false;
}

/**
 * Check if QR Code exists
 * 
 * @param string $qr_path - Relative path to QR code file
 * @return bool - True if file exists
 */
function qrCodeExists($qr_path) {
    if (empty($qr_path)) {
        return false;
    }

    $full_path = __DIR__ . '/../' . $qr_path;
    return file_exists($full_path);
}

/**
 * Get QR Code file size
 * 
 * @param string $qr_path - Relative path to QR code file
 * @return string - Formatted file size or 'N/A'
 */
function getQRCodeSize($qr_path) {
    if (empty($qr_path)) {
        return 'N/A';
    }

    $full_path = __DIR__ . '/../' . $qr_path;
    
    if (file_exists($full_path)) {
        $bytes = filesize($full_path);
        return number_format($bytes / 1024, 2) . ' KB';
    }
    
    return 'N/A';
}

/**
 * Regenerate QR Code for existing course
 * 
 * @param int $course_id - Course ID
 * @param string $old_qr_path - Old QR code path to delete
 * @param string $course_code - Course code (e.g., 'DBC', 'PPI')
 * @return array - Result array
 */
function regenerateQRCode($course_id, $old_qr_path = '', $course_code = '') {
    // Delete old QR code if exists
    if (!empty($old_qr_path)) {
        deleteQRCode($old_qr_path);
    }

    // Generate new QR code
    return generateCourseQRCode($course_id, $course_code);
}

/**
 * Generate QR Code with custom URL
 * 
 * @param string $url - Custom URL to encode
 * @param string $filename - Custom filename (without extension)
 * @return array - Result array
 */
function generateCustomQRCode($url, $filename) {
    try {
        $qr_dir = __DIR__ . '/../assets/qr_codes/';
        if (!file_exists($qr_dir)) {
            mkdir($qr_dir, 0777, true);
        }

        $safe_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
        $qr_file = $qr_dir . $safe_filename . '.png';

        QRcode::png($url, $qr_file, QR_ECLEVEL_L, 10, 2);

        if (file_exists($qr_file)) {
            return [
                'success' => true,
                'path' => 'assets/qr_codes/' . $safe_filename . '.png',
                'url' => $url,
                'message' => 'Custom QR Code generated successfully'
            ];
        } else {
            return [
                'success' => false,
                'path' => '',
                'url' => $url,
                'message' => 'QR Code file was not created'
            ];
        }

    } catch (Exception $e) {
        return [
            'success' => false,
            'path' => '',
            'url' => $url,
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Batch generate QR codes for all active courses
 * 
 * @param mysqli $conn - Database connection
 * @return array - Results for each course
 */
function batchGenerateQRCodes($conn) {
    $results = [];
    
    $query = "SELECT id, course_name, course_code FROM courses WHERE status = 'active'";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($course = $result->fetch_assoc()) {
            $qr_result = generateCourseQRCode($course['id'], $course['course_code']);
            
            if ($qr_result['success']) {
                // Update database with QR path and registration link
                $stmt = $conn->prepare("UPDATE courses SET qr_code_path = ?, registration_link = ? WHERE id = ?");
                $stmt->bind_param("ssi", $qr_result['path'], $qr_result['url'], $course['id']);
                $stmt->execute();
            }
            
            $results[] = [
                'course_id' => $course['id'],
                'course_name' => $course['course_name'],
                'result' => $qr_result
            ];
        }
    }
    
    return $results;
}

/**
 * Get QR Code HTML img tag
 * 
 * @param string $qr_path - Relative path to QR code
 * @param string $alt_text - Alt text for image
 * @param string $css_class - CSS class for styling
 * @param int $width - Image width in pixels
 * @return string - HTML img tag or error message
 */
function getQRCodeHTML($qr_path, $alt_text = 'QR Code', $css_class = '', $width = 200) {
    if (empty($qr_path) || !qrCodeExists($qr_path)) {
        return '<div class="alert alert-warning">QR Code not available</div>';
    }

    $class_attr = !empty($css_class) ? ' class="' . htmlspecialchars($css_class) . '"' : '';
    $width_attr = $width > 0 ? ' width="' . $width . '"' : '';
    
    return '<img src="' . htmlspecialchars($qr_path) . '" alt="' . htmlspecialchars($alt_text) . '"' . $class_attr . $width_attr . '>';
}
