<?php
/**
 * Admin — print / download FORM OF APPLICATION (blank or filled).
 */
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/url_helper.php';
require_once __DIR__ . '/../includes/recruitment_helper.php';
require_once __DIR__ . '/../includes/render_recruitment_application_form_pdf.php';

recruitmentRequireAccess($conn);
ensureRecruitmentTables($conn);

$id = (int) ($_GET['id'] ?? 0);
$jobId = (int) ($_GET['job'] ?? 0);

if ($id <= 0) {
    $prefill = [];
    if ($jobId > 0) {
        $job = recruitmentGetJob($conn, $jobId);
        if ($job) {
            $prefill = [
                'job_title' => (string) ($job['title'] ?? ''),
                'advt_no' => (string) ($job['advt_no'] ?? ''),
            ];
        }
    }
    outputRecruitmentApplicationFormPdf($prefill !== [] ? $prefill : null, 'I');
    exit();
}

$app = recruitmentGetApplication($conn, $id);
if (!$app) {
    $_SESSION['message'] = 'Application not found.';
    $_SESSION['message_type'] = 'danger';
    header('Location: ' . app_url('admin/recruitment_applications'));
    exit();
}

outputRecruitmentApplicationFormPdf($app, 'I');
exit();
