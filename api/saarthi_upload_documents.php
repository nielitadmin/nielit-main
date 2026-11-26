<?php
/**
 * Saarthi Document Upload API (multipart/form-data & JSON status).
 * Authorizes logged-in students, admins, or a signed session token from Saarthi registration.
 */
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/student_document_upload_helper.php';
require_once __DIR__ . '/../includes/portal_assistant_tools.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$conn = isset($conn) && $conn instanceof mysqli ? $conn : null;
if (!$conn) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'message' => 'Database connection unavailable.']);
    exit;
}

$recordId = (int) ($_REQUEST['record_id'] ?? 0);
$token = trim((string) ($_REQUEST['token'] ?? ''));
$studentRow = studentDocumentAuthorizeAccess($conn, $recordId, $token);

if (!$studentRow) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Session expired or unauthorized. Please log in to Student Portal -> Upload Documents.']);
    exit;
}

$studentId = (string) ($studentRow['student_id'] ?? '');
$recordId = (int) ($studentRow['id'] ?? $recordId);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    echo json_encode([
        'ok' => true,
        'student_id' => $studentId,
        'record_id' => $recordId,
        'name' => (string) ($studentRow['name'] ?? ''),
        'course_name' => (string) ($studentRow['course'] ?? ''),
        'aadhar' => (string) ($studentRow['aadhar'] ?? ''),
        'checklist' => studentDocumentChecklist($conn, $studentRow),
        'download_pdf_url' => saarthi_issue_pdf_token($recordId, $studentId),
        'upload_portal_url' => saarthiBuildUploadPortalUrl($recordId, $studentId, $token !== '' ? $token : null),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$result = studentDocumentSaveUploads($conn, $studentRow, $_FILES, $_POST);
$result['student_id'] = $studentId;
$result['record_id'] = $recordId;
$result['aadhar'] = (string) (($result['student']['aadhar'] ?? $studentRow['aadhar'] ?? ''));
$result['download_pdf_url'] = saarthi_issue_pdf_token($recordId, $studentId);
$result['upload_portal_url'] = saarthiBuildUploadPortalUrl($recordId, $studentId, $token !== '' ? $token : null);
unset($result['student']);

if (empty($result['ok']) && empty($result['updated_fields'])) {
    http_response_code(422);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
