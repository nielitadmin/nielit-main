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
$action = strtolower(trim((string) ($_GET['action'] ?? '')));

if ($method === 'GET') {
    if (in_array($action, ['eligible', 'certified', 'passed', 'list_certified'], true)) {
        handleCertifiedEligibleList();
    }
    handleCertificateList();
}

if ($method === 'POST') {
    handleCertificatePush();
}

sendApiError('Method not allowed', 405, 'METHOD_NOT_ALLOWED');

function certificateCertifiedStatusSql(string $alias = 'bs'): string
{
    $col = "LOWER(REPLACE(REPLACE(TRIM(IFNULL({$alias}.result_status,'')), ' ', '_'), '-', '_'))";
    return "{$col} IN ('pass','certified','pass_certified')";
}

function handleCertifiedEligibleList(): void
{
    global $conn;

    require_once __DIR__ . '/../../batch_module/includes/batch_result_helper.php';
    require_once __DIR__ . '/../../batch_module/includes/batch_certificate_helper.php';
    ensureBatchResultSchema($conn);
    ensureBatchCertificateSchema($conn);

    if (!batch_result_column_exists($conn, 'result_status')) {
        sendApiError('Result status is not available on batch records', 500, 'RESULT_STATUS_UNAVAILABLE');
    }

    $limit = (int) ($_GET['limit'] ?? 50);
    $offset = (int) ($_GET['offset'] ?? 0);
    $limit = max(1, min($limit, defined('API_MAX_RESULTS') ? API_MAX_RESULTS : 1000));
    $offset = max(0, $offset);
    $batchId = (int) ($_GET['batch_id'] ?? 0);
    $courseId = (int) ($_GET['course_id'] ?? 0);
    $centreId = (int) ($_GET['centre_id'] ?? 0);
    $hasCertificate = strtolower(trim((string) ($_GET['has_certificate'] ?? '')));

    $hasCourseCentre = false;
    $col = @$conn->query("SHOW COLUMNS FROM courses LIKE 'centre_id'");
    if ($col && $col->num_rows > 0) {
        $hasCourseCentre = true;
    }
    $hasBatchLocation = false;
    $loc = @$conn->query("SHOW COLUMNS FROM batches LIKE 'location'");
    if ($loc && $loc->num_rows > 0) {
        $hasBatchLocation = true;
    }
    $hasCertFile = false;
    $cf = @$conn->query("SHOW COLUMNS FROM batch_students LIKE 'certificate_file'");
    if ($cf && $cf->num_rows > 0) {
        $hasCertFile = true;
    }

    $centreSelect = $hasCourseCentre
        ? 'cen.name AS course_centre_name, c.centre_id'
        : 'NULL AS course_centre_name, NULL AS centre_id';
    $centreJoin = $hasCourseCentre ? 'LEFT JOIN centres cen ON cen.id = c.centre_id' : '';
    $locationSelect = $hasBatchLocation ? 'b.location' : 'NULL AS location';
    $certSelect = $hasCertFile
        ? 'bs.certificate_file, bs.certificate_number'
        : 'NULL AS certificate_file, NULL AS certificate_number';

    $fromWhere = "FROM students s
            INNER JOIN batch_students bs ON bs.batch_id > 0
                AND (bs.student_record_id = s.id OR bs.student_id = s.id)
            INNER JOIN batches b ON b.id = bs.batch_id
            LEFT JOIN courses c ON c.id = COALESCE(NULLIF(s.course_id, 0), b.course_id)
            {$centreJoin}
            WHERE LOWER(COALESCE(s.status, '')) NOT IN ('rejected', 'inactive')
              AND " . certificateCertifiedStatusSql('bs');

    $params = [];
    $types = '';
    if ($batchId > 0) {
        $fromWhere .= ' AND b.id = ?';
        $types .= 'i';
        $params[] = $batchId;
    }
    if ($courseId > 0) {
        $fromWhere .= ' AND COALESCE(NULLIF(s.course_id, 0), b.course_id) = ?';
        $types .= 'i';
        $params[] = $courseId;
    }
    if ($centreId > 0 && $hasCourseCentre) {
        $fromWhere .= ' AND c.centre_id = ?';
        $types .= 'i';
        $params[] = $centreId;
    }
    if ($hasCertificate === '0' || $hasCertificate === 'no' || $hasCertificate === 'false') {
        $fromWhere .= $hasCertFile
            ? " AND (bs.certificate_file IS NULL OR TRIM(bs.certificate_file) = '')"
            : '';
    } elseif ($hasCertificate === '1' || $hasCertificate === 'yes' || $hasCertificate === 'true') {
        $fromWhere .= $hasCertFile
            ? " AND bs.certificate_file IS NOT NULL AND TRIM(bs.certificate_file) <> ''"
            : ' AND 1=0';
    }

    $countSql = "SELECT COUNT(DISTINCT bs.id) AS total {$fromWhere}";
    $countStmt = $conn->prepare($countSql);
    if (!$countStmt) {
        sendApiError('Failed to prepare certified student count', 500, 'QUERY_PREPARE_FAILED');
    }
    if ($types !== '') {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $total = (int) ($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $countStmt->close();

    $sql = "SELECT s.id AS student_record_id, s.student_id, s.name, s.email, s.mobile, s.training_center,
                   COALESCE(NULLIF(s.course_id, 0), b.course_id) AS course_id,
                   c.course_name, c.course_code,
                   b.id AS batch_id, b.batch_name, b.batch_code, b.start_date AS batch_start_date, b.end_date AS batch_end_date,
                   bs.id AS batch_student_id, bs.result_status, bs.result_updated_at,
                   {$certSelect}, {$locationSelect}, {$centreSelect}
            {$fromWhere}
            ORDER BY bs.result_updated_at DESC, s.name ASC, s.id ASC
            LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        sendApiError('Failed to prepare certified student list', 500, 'QUERY_PREPARE_FAILED');
    }
    $listTypes = $types . 'ii';
    $listParams = array_merge($params, [$limit, $offset]);
    $stmt->bind_param($listTypes, ...$listParams);
    $stmt->execute();
    $result = $stmt->get_result();

    $branding = dirname(__DIR__, 2) . '/includes/institute_branding.php';
    if (file_exists($branding)) {
        require_once $branding;
    }

    $students = [];
    while ($row = $result->fetch_assoc()) {
        $centreName = trim((string) ($row['location'] ?? ''));
        if ($centreName === '') {
            $centreName = trim((string) ($row['course_centre_name'] ?? ''));
        }
        if ($centreName === '') {
            $centreName = trim((string) ($row['training_center'] ?? ''));
        }
        if ($centreName !== '' && function_exists('normalize_nielit_centre_name')) {
            $centreName = normalize_nielit_centre_name($centreName);
        }
        $certFile = trim((string) ($row['certificate_file'] ?? ''));
        $resultStatus = function_exists('batch_result_normalize_status')
            ? batch_result_normalize_status($row['result_status'] ?? 'pass')
            : 'pass';
        $students[] = [
            'student_id' => (string) ($row['student_id'] ?? ''),
            'student_record_id' => (int) ($row['student_record_id'] ?? 0),
            'batch_student_id' => (int) ($row['batch_student_id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'mobile' => (string) ($row['mobile'] ?? ''),
            'course_id' => (int) ($row['course_id'] ?? 0),
            'course_code' => (string) ($row['course_code'] ?? ''),
            'course_name' => (string) ($row['course_name'] ?? ''),
            'batch_id' => (int) ($row['batch_id'] ?? 0),
            'batch_code' => (string) ($row['batch_code'] ?? ''),
            'batch_name' => (string) ($row['batch_name'] ?? ''),
            'batch_start_date' => $row['batch_start_date'] ?? null,
            'batch_end_date' => $row['batch_end_date'] ?? null,
            'centre_name' => $centreName,
            'result_status' => $resultStatus,
            'result_status_label' => 'Pass / Certified',
            'result_updated_at' => $row['result_updated_at'] ?? null,
            'certificate_number' => (string) ($row['certificate_number'] ?? ''),
            'has_certificate' => $certFile !== '',
            'eligible_for_digital_certificate' => true,
        ];
    }
    $stmt->close();

    sendApiResponse([
        'filter' => 'pass_certified',
        'students' => $students,
        'pagination' => [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total,
        ],
    ], 200, 'Certified / passed students');
}

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
