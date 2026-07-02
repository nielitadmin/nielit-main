<?php
/**
 * QR Code Helper Functions
 * NIELIT Bhubaneswar - Course Registration System
 */

// Include phpqrcode library
require_once __DIR__ . '/../phpqrcode/qrlib.php';
require_once __DIR__ . '/url_helper.php';
require_once __DIR__ . '/course_public_display.php';
require_once __DIR__ . '/workshop_registration_helper.php';

/**
 * Resolve the canonical registration URL for a course (used by Apply links and QR codes).
 */
function getCourseRegistrationUrl($courseOrId, $registration_token = '') {
    require_once __DIR__ . '/url_helper.php';
    require_once __DIR__ . '/course_public_display.php';

    if (is_array($courseOrId)) {
        $course = $courseOrId;
    } else {
        global $conn;
        $course = null;
        $course_id = (int) $courseOrId;
        if ($course_id > 0 && $conn instanceof mysqli) {
            $stmt = $conn->prepare('SELECT * FROM courses WHERE id = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $course_id);
                $stmt->execute();
                $course = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }
        }
    }

    if (is_array($course) && $course) {
        if ($registration_token !== '') {
            $course['registration_token'] = $registration_token;
        }
        return course_registration_apply_url($course);
    }

    $token = trim((string) $registration_token);
    if ($token === '') {
        return '';
    }

    return app_url('student/register') . '?token=' . rawurlencode($token);
}

/**
 * Keep apply_link, registration_link, and QR image aligned with the current token.
 */
function syncCourseRegistrationLinkAndQr(mysqli $conn, int $course_id, bool $forceRegenerate = true) {
    $stmt = $conn->prepare('SELECT * FROM courses WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return ['success' => false, 'message' => 'Could not load course'];
    }
    $stmt->bind_param('i', $course_id);
    $stmt->execute();
    $course = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$course) {
        return ['success' => false, 'message' => 'Course not found'];
    }

    $apply_link = course_registration_apply_url($course);
    if ($apply_link === '') {
        return ['success' => false, 'message' => 'Course has no registration token'];
    }

    $old_qr_path = trim((string) ($course['qr_code_path'] ?? ''));

    // Reload course row so QR always uses the latest token from DB.
    $reload = $conn->prepare('SELECT * FROM courses WHERE id = ? LIMIT 1');
    if ($reload) {
        $reload->bind_param('i', $course_id);
        $reload->execute();
        $fresh = $reload->get_result()->fetch_assoc();
        $reload->close();
        if ($fresh) {
            $course = $fresh;
            $apply_link = course_registration_apply_url($course);
        }
    }

    $qr_result = generateCourseQRCode($course, $course['course_code'] ?? '', $course['registration_token'] ?? '');
    if (!$qr_result['success']) {
        return [
            'success' => false,
            'message' => $qr_result['message'],
            'apply_link' => $apply_link,
        ];
    }
    $qr_path = $qr_result['path'];

    if ($old_qr_path !== '' && $old_qr_path !== $qr_path) {
        deleteQRCode($old_qr_path);
    }

    $stmt_update = $conn->prepare(
        'UPDATE courses SET apply_link = ?, registration_link = ?, qr_code_path = ?, qr_generated_at = NOW() WHERE id = ?'
    );
    if (!$stmt_update) {
        return ['success' => false, 'message' => 'Could not update course links'];
    }
    $stmt_update->bind_param('sssi', $apply_link, $apply_link, $qr_path, $course_id);
    $ok = $stmt_update->execute();
    $stmt_update->close();

    return [
        'success' => $ok,
        'message' => $ok ? 'Registration link and QR code synced.' : 'Database update failed',
        'apply_link' => $apply_link,
        'qr_code_path' => $qr_path,
        'qr_code_url' => $qr_path !== '' ? rtrim(APP_URL, '/') . '/' . ltrim($qr_path, '/') : '',
        'qr_target_url' => $qr_result['url'] ?? registration_url_for_qr($course),
    ];
}

/**
 * Generate QR Code for Course Registration Link
 * 
 * @param array|int $courseOrId - Course row or course ID
 * @param string $course_code - Course code for filename (e.g., 'DBC', 'PPI')
 * @param string $registration_token - Registration token for URL (e.g., 'mmRWCOtf')
 * @return array - ['success' => bool, 'path' => string, 'url' => string, 'message' => string]
 */
function generateCourseQRCode($courseOrId, $course_code = '', $registration_token = '') {
    try {
        global $conn;

        $dirCheck = ensureQrCodesDirectory();
        if (!$dirCheck['ok']) {
            return [
                'success' => false,
                'path' => '',
                'url' => '',
                'message' => $dirCheck['message'],
            ];
        }
        $qr_dir = $dirCheck['path'];

        $course_id = is_array($courseOrId) ? (int) ($courseOrId['id'] ?? 0) : (int) $courseOrId;

        if (is_array($courseOrId)) {
            $course = $courseOrId;
            if ($registration_token !== '') {
                $course['registration_token'] = $registration_token;
            }
            $registration_url = registration_url_for_qr($course);
        } else {
            $course = null;
            if ($conn instanceof mysqli && $course_id > 0) {
                $course_query = $conn->prepare('SELECT * FROM courses WHERE id = ? LIMIT 1');
                if ($course_query) {
                    $course_query->bind_param('i', $course_id);
                    $course_query->execute();
                    $course = $course_query->get_result()->fetch_assoc();
                    $course_query->close();
                }
            }
            if (!is_array($course) || !$course) {
                return [
                    'success' => false,
                    'path' => '',
                    'url' => '',
                    'message' => 'Course not found',
                ];
            }
            if ($registration_token !== '') {
                $course['registration_token'] = $registration_token;
            }
            $registration_url = registration_url_for_qr($course);
        }

        if ($registration_url === '') {
            return [
                'success' => false,
                'path' => '',
                'url' => '',
                'message' => 'Registration URL could not be built (missing token?)',
            ];
        }

        $safe_name = !empty($course_code) ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $course_code) : 'course_' . $course_id;
        $filename = 'qr_' . $safe_name . '_' . $course_id . '_' . gmdate('YmdHis') . '.png';
        $qr_file_path = $qr_dir . $filename;
        $tmp_path = $qr_dir . '.tmp_' . uniqid('', true) . '.png';

        QRcode::png($registration_url, $tmp_path, QR_ECLEVEL_L, 10, 2);

        if (!is_file($tmp_path) || filesize($tmp_path) < 100) {
            @unlink($tmp_path);
            return [
                'success' => false,
                'path' => '',
                'url' => $registration_url,
                'message' => 'QR PNG was not created. Check write permissions on assets/qr_codes/.',
            ];
        }

        if (!@rename($tmp_path, $qr_file_path)) {
            @unlink($tmp_path);
            return [
                'success' => false,
                'path' => '',
                'url' => $registration_url,
                'message' => 'Could not save QR PNG. The assets/qr_codes/ folder may not be writable on the server.',
            ];
        }

        return [
            'success' => true,
            'path' => 'assets/qr_codes/' . $filename,
            'full_path' => $qr_file_path,
            'url' => $registration_url,
            'filename' => $filename,
            'message' => 'QR Code generated successfully',
        ];

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
 * Ensure QR output directory exists and is writable.
 *
 * @return array{ok:bool,path:string,message:string}
 */
function ensureQrCodesDirectory(): array
{
    $qr_dir = __DIR__ . '/../assets/qr_codes/';
    if (!file_exists($qr_dir)) {
        if (!@mkdir($qr_dir, 0755, true) && !is_dir($qr_dir)) {
            return [
                'ok' => false,
                'path' => $qr_dir,
                'message' => 'Could not create assets/qr_codes/ folder on the server.',
            ];
        }
    }
    if (!is_writable($qr_dir)) {
        return [
            'ok' => false,
            'path' => $qr_dir,
            'message' => 'assets/qr_codes/ is not writable. Set folder permissions to 755 or 775 on the server, then try again.',
        ];
    }
    return ['ok' => true, 'path' => $qr_dir, 'message' => ''];
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
 * @param string $registration_token - Registration token for URL
 * @return array - Result array
 */
function regenerateQRCode($course_id, $old_qr_path = '', $course_code = '', $registration_token = '') {
    // Delete old QR code if exists
    if (!empty($old_qr_path)) {
        deleteQRCode($old_qr_path);
    }

    global $conn;
    if ($conn instanceof mysqli) {
        return syncCourseRegistrationLinkAndQr($conn, (int) $course_id, true);
    }

    // Generate new QR code
    return generateCourseQRCode($course_id, $course_code, $registration_token);
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
    
    $query = "SELECT id, course_name, course_code, registration_token FROM courses WHERE status = 'active'";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($course = $result->fetch_assoc()) {
            $qr_result = generateCourseQRCode($course['id'], $course['course_code'], $course['registration_token']);
            
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
