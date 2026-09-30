<?php
/**
 * Certificate synchronization API for external certificate issuers.
 *
 * POST accepts certificate metadata and a certificate_file upload.
 * GET returns all issued/revoked certificates for a student.
 */

require_once __DIR__ . '/../config/api_config.php';
require_once __DIR__ . '/../../batch_module/includes/batch_certificate_helper.php';

$apiData = authenticateApiRequest();
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    handleCertificateList();
}

if ($method === 'POST') {
    handleCertificatePush();
}

sendApiError('Method not allowed', 405, 'METHOD_NOT_ALLOWED');

function handleCertificateList(): void
{
    global $conn;

    $studentId = trim((string) ($_GET['student_id'] ?? ''));
    if ($studentId === '') {
        sendApiError('student_id is required', 400, 'MISSING_STUDENT_ID');
    }

    $certificates = getStudentPortalCertificates($conn, $studentId);
    sendApiResponse([
        'student_id' => $studentId,
        'count' => count($certificates),
        'certificates' => $certificates,
    ]);
}

function handleCertificatePush(): void
{
    global $conn;

    if (!ensureBatchCertificateSchema($conn)) {
        sendApiError('Certificate storage is not available', 500, 'CERTIFICATE_SCHEMA_UNAVAILABLE');
    }

    $input = [];
    if (isset($_FILES['certificate_file'])) {
        $input = $_POST;
    } else {
        $decoded = json_decode((string) file_get_contents('php://input'), true);
        if (is_array($decoded)) {
            $input = $decoded;
        }
    }

    $studentId = trim((string) ($input['student_id'] ?? ''));
    $studentRecordId = (int) ($input['student_record_id'] ?? 0);
    $courseId = (int) ($input['course_id'] ?? 0);
    $batchId = (int) ($input['batch_id'] ?? 0);
    $batchStudentId = (int) ($input['batch_student_id'] ?? 0);
    $certificateNumber = trim((string) ($input['certificate_number'] ?? ''));
    $certificateName = trim((string) ($input['certificate_name'] ?? 'Course Completion Certificate'));
    $courseName = trim((string) ($input['course_name'] ?? ''));
    $issueDate = trim((string) ($input['issue_date'] ?? date('Y-m-d')));
    $status = strtolower(trim((string) ($input['status'] ?? 'issued')));

    if ($studentId === '' || $studentRecordId <= 0 || $courseId <= 0 || $batchId <= 0 || $certificateNumber === '') {
        sendApiError(
            'student_id, student_record_id, course_id, batch_id, and certificate_number are required',
            400,
            'MISSING_CERTIFICATE_FIELDS'
        );
    }

    if (!in_array($status, ['issued', 'revoked', 'pending'], true)) {
        sendApiError('status must be issued, revoked, or pending', 400, 'INVALID_STATUS');
    }

    $dateObject = DateTime::createFromFormat('Y-m-d', $issueDate);
    if (!$dateObject || $dateObject->format('Y-m-d') !== $issueDate) {
        sendApiError('issue_date must use YYYY-MM-DD format', 400, 'INVALID_ISSUE_DATE');
    }

    $studentStmt = $conn->prepare(
        'SELECT s.id, s.student_id, s.course_id, s.batch_id, c.course_name, b.batch_code, b.batch_name
         FROM students s
         LEFT JOIN courses c ON c.id = s.course_id
         LEFT JOIN batches b ON b.id = s.batch_id
         WHERE s.id = ? AND s.student_id = ? AND s.course_id = ? AND s.batch_id = ?
         LIMIT 1'
    );
    if (!$studentStmt) {
        sendApiError('Failed to prepare enrollment validation query', 500, 'QUERY_PREPARE_FAILED');
    }

    $studentStmt->bind_param('isii', $studentRecordId, $studentId, $courseId, $batchId);
    $studentStmt->execute();
    $enrollment = $studentStmt->get_result()->fetch_assoc();
    $studentStmt->close();

    if (!$enrollment) {
        sendApiError('The student enrollment does not match the supplied course and batch', 409, 'ENROLLMENT_MISMATCH');
    }

    if ($courseName === '') {
        $courseName = trim((string) ($enrollment['course_name'] ?? 'Course'));
    }

    $relativePath = '';
    if (isset($_FILES['certificate_file'])) {
        $relativePath = storeCertificateUpload($_FILES['certificate_file'], $certificateNumber);
    } else {
        $relativePath = trim((string) ($input['file_path'] ?? ''));
        if ($relativePath === '' || batch_certificate_absolute_path($relativePath) === '') {
            sendApiError('Upload certificate_file as multipart/form-data or provide an existing local file_path', 400, 'MISSING_CERTIFICATE_FILE');
        }
    }

    $synced = batch_certificate_sync_portal_record($conn, [
        'student_id' => $studentId,
        'student_record_id' => $studentRecordId,
        'batch_id' => $batchId,
        'batch_student_id' => $batchStudentId,
        'certificate_name' => $certificateName,
        'course_name' => $courseName,
        'certificate_number' => $certificateNumber,
        'file_path' => $relativePath,
        'uploaded_by' => 0,
    ]);

    if (!$synced) {
        sendApiError('Certificate could not be saved', 500, 'CERTIFICATE_SAVE_FAILED');
    }

    $updateStmt = $conn->prepare(
        'UPDATE certificates
         SET issue_date = ?, status = ?, course_name = ?, certificate_name = ?
         WHERE student_record_id = ? AND batch_id = ? AND certificate_number = ?
         ORDER BY id DESC LIMIT 1'
    );
    if ($updateStmt) {
        $updateStmt->bind_param(
            'ssssiis',
            $issueDate,
            $status,
            $courseName,
            $certificateName,
            $studentRecordId,
            $batchId,
            $certificateNumber
        );
        $updateStmt->execute();
        $updateStmt->close();
    }

    sendApiResponse([
        'saved' => true,
        'student_id' => $studentId,
        'student_record_id' => $studentRecordId,
        'course_id' => $courseId,
        'batch_id' => $batchId,
        'certificate_number' => $certificateNumber,
        'file_path' => $relativePath,
        'status' => $status,
    ], 201, 'Certificate synchronized');
}

function storeCertificateUpload(array $file, string $certificateNumber): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        sendApiError('Certificate upload failed', 400, 'UPLOAD_FAILED');
    }

    if (($file['size'] ?? 0) <= 0 || (int) $file['size'] > 10 * 1024 * 1024) {
        sendApiError('Certificate file must be between 1 byte and 10 MB', 413, 'UPLOAD_TOO_LARGE');
    }

    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
    if (!in_array($extension, $allowedExtensions, true)) {
        sendApiError('Only PDF, JPG, JPEG, and PNG certificates are accepted', 415, 'INVALID_FILE_TYPE');
    }

    $temporaryPath = (string) ($file['tmp_name'] ?? '');
    if (!is_uploaded_file($temporaryPath)) {
        sendApiError('Invalid certificate upload', 400, 'INVALID_UPLOAD');
    }

    $directory = dirname(__DIR__, 2) . '/uploads/certificates';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        sendApiError('Certificate storage directory is unavailable', 500, 'STORAGE_UNAVAILABLE');
    }

    $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '-', $certificateNumber);
    $filename = trim((string) $safeName, '-') . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
    $absolutePath = $directory . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($temporaryPath, $absolutePath)) {
        sendApiError('Could not store certificate file', 500, 'STORAGE_FAILED');
    }

    return 'uploads/certificates/' . $filename;
}
