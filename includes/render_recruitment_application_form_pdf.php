<?php
/**
 * Printable NIELIT FORM OF APPLICATION (blank or filled) for any recruitment post.
 */
require_once __DIR__ . '/institute_branding.php';
require_once __DIR__ . '/../libraries/tcpdf/tcpdf.php';

if (!function_exists('recruitmentFormPdfText')) {
    function recruitmentFormPdfText($value): string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '—') {
            return '';
        }
        return $value;
    }
}

if (!function_exists('recruitmentFormPdfCell')) {
    function recruitmentFormPdfCell(TCPDF $pdf, string $label, string $value, float $w, float $h = 7.2, int $ln = 1): void
    {
        $txt = $label . ': ' . ($value !== '' ? $value : '');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell($w, $h, '  ' . $txt, 1, $ln, 'L');
    }
}

if (!function_exists('outputRecruitmentApplicationFormPdf')) {
    /**
     * @param array<string,mixed>|null $app
     */
    function outputRecruitmentApplicationFormPdf(?array $app = null, string $dest = 'I'): string
    {
        $app = $app ?? [];
        $filled = trim((string) ($app['application_no'] ?? '')) !== '';
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('NIELIT Bhubaneswar');
        $pdf->SetAuthor('NIELIT Bhubaneswar');
        $pdf->SetTitle('FORM OF APPLICATION');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(12, 10, 12);
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->AddPage();

        $pdf->SetDrawColor(70, 70, 70);
        $pdf->SetLineWidth(0.5);
        $pdf->Rect(8, 8, 194, 281, 'D');

        $logoPath = __DIR__ . '/../assets/images/bhubaneswar_logo.png';
        if (is_file($logoPath)) {
            $pdf->Image($logoPath, 12, 11, 18, 0, 'PNG');
        }
        $pdf->SetXY(32, 11);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(130, 6, defined('INSTITUTE_NAME_EN') ? INSTITUTE_NAME_EN : 'NIELIT Bhubaneswar', 0, 1, 'C');
        $pdf->SetX(32);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(130, 4, 'Ministry of Electronics & Information Technology, Government of India', 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(1);
        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->Cell(0, 7, 'FORM OF APPLICATION', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->Cell(0, 4, $filled ? 'Filled application (for records / interview)' : 'Use this form for any advertised post — fill in CAPITAL letters', 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(1.5);

        $photoX = 162;
        $photoY = $pdf->GetY();
        $photoW = 32;
        $photoH = 38;
        $pdf->SetDrawColor(120, 120, 120);
        $pdf->Rect($photoX, $photoY, $photoW, $photoH);
        $photoFile = '';
        $rel = ltrim(str_replace('\\', '/', (string) ($app['photo_path'] ?? '')), '/');
        if ($rel !== '' && strpos($rel, '..') === false) {
            $candidate = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (is_file($candidate)) {
                $photoFile = $candidate;
            }
        }
        if ($photoFile !== '' && @getimagesize($photoFile)) {
            $pdf->Image($photoFile, $photoX + 0.6, $photoY + 0.6, $photoW - 1.2, $photoH - 1.2, '', '', '', true, 150);
        } else {
            $pdf->SetXY($photoX, $photoY + 14);
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetTextColor(120, 120, 120);
            $pdf->MultiCell($photoW, 4, "Affix recent\npassport photo", 0, 'C');
            $pdf->SetTextColor(0, 0, 0);
        }

        $leftW = 146;
        $pdf->SetY($photoY);
        recruitmentFormPdfCell($pdf, '1. Post applied for', recruitmentFormPdfText($app['job_title'] ?? ''), $leftW);
        recruitmentFormPdfCell($pdf, 'Advertisement no.', recruitmentFormPdfText($app['advt_no'] ?? ''), $leftW);
        if ($filled) {
            recruitmentFormPdfCell($pdf, 'Application no.', recruitmentFormPdfText($app['application_no'] ?? ''), $leftW);
        }
        $first = recruitmentFormPdfText($app['name_first'] ?? '');
        $middle = recruitmentFormPdfText($app['name_middle'] ?? '');
        $last = recruitmentFormPdfText($app['name_last'] ?? '');
        $full = trim($first . ' ' . $middle . ' ' . $last);
        if ($full === '') {
            $full = recruitmentFormPdfText($app['name'] ?? '');
        }
        recruitmentFormPdfCell($pdf, '2. Name in full (First / Middle / Last)', $full, $leftW);
        recruitmentFormPdfCell($pdf, "3. Father's / Husband's name", recruitmentFormPdfText($app['father_name'] ?? ''), $leftW);
        recruitmentFormPdfCell($pdf, "Mother's name", recruitmentFormPdfText($app['mother_name'] ?? ''), $leftW);

        $pdf->SetY(max($pdf->GetY(), $photoY + $photoH + 2));
        $w = 186;
        $half = 93;
        $dob = recruitmentFormPdfText($app['dob'] ?? '');
        if ($dob !== '' && function_exists('recruitmentFormatDate')) {
            $fmt = recruitmentFormatDate($dob);
            if ($fmt !== '—') {
                $dob = $fmt;
            }
        }
        $age = '';
        if (!empty($app['age_years']) || !empty($app['age_months']) || !empty($app['age_days'])) {
            $age = (int) ($app['age_years'] ?? 0) . ' years  ' . (int) ($app['age_months'] ?? 0) . ' months  ' . (int) ($app['age_days'] ?? 0) . ' days';
        }
        recruitmentFormPdfCell($pdf, '4. (a) Date of Birth', $dob, $half, 7.2, 0);
        recruitmentFormPdfCell($pdf, '4. (b) Age as on last date', $age, $half);
        recruitmentFormPdfCell($pdf, '5. Gender', recruitmentFormPdfText($app['gender'] ?? ''), $half, 7.2, 0);
        recruitmentFormPdfCell($pdf, '6. Marital status', recruitmentFormPdfText($app['marital_status'] ?? ''), $half);
        recruitmentFormPdfCell($pdf, '7. Nationality', recruitmentFormPdfText($app['nationality'] ?? ''), $half, 7.2, 0);
        recruitmentFormPdfCell($pdf, '8. Category', recruitmentFormPdfText($app['category'] ?? ''), $half);
        $pwd = recruitmentFormPdfText($app['pwd_status'] ?? '');
        if (recruitmentFormPdfText($app['pwd_type'] ?? '') !== '') {
            $pwd .= ' / ' . $app['pwd_type'];
        }
        if (recruitmentFormPdfText($app['pwd_percent'] ?? '') !== '') {
            $pwd .= ' (' . $app['pwd_percent'] . '%)';
        }
        recruitmentFormPdfCell($pdf, 'Whether PwD', $pwd, $half, 7.2, 0);
        recruitmentFormPdfCell($pdf, '9. Aadhaar number', recruitmentFormPdfText($app['aadhar'] ?? ''), $half);
        recruitmentFormPdfCell($pdf, '10. (a) Mobile', recruitmentFormPdfText($app['mobile'] ?? ''), $half, 7.2, 0);
        recruitmentFormPdfCell($pdf, 'Alternate mobile', recruitmentFormPdfText($app['alt_mobile'] ?? ''), $half);
        recruitmentFormPdfCell($pdf, '10. (b) Email', recruitmentFormPdfText($app['email'] ?? ''), $w);
        recruitmentFormPdfCell($pdf, '11. Address for correspondence', recruitmentFormPdfText($app['address'] ?? ''), $w);
        recruitmentFormPdfCell($pdf, 'City / State / PIN', trim(recruitmentFormPdfText($app['city'] ?? '') . '  ' . recruitmentFormPdfText($app['state'] ?? '') . '  ' . recruitmentFormPdfText($app['pincode'] ?? '')), $w);
        recruitmentFormPdfCell($pdf, '12. Permanent address', recruitmentFormPdfText($app['permanent_address'] ?? ''), $w);
        recruitmentFormPdfCell($pdf, 'Permanent PIN', recruitmentFormPdfText($app['permanent_pincode'] ?? ''), $w);

        $pdf->Ln(2);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(232, 240, 254);
        $pdf->Cell($w, 6.5, '  13. Particulars of examinations passed / degrees / technical qualifications', 1, 1, 'L', true);

        $edu = [];
        if (function_exists('recruitmentDecodeJsonList')) {
            $edu = recruitmentDecodeJsonList($app['education_json'] ?? '');
        }
        $html = '<table border="1" cellpadding="3" cellspacing="0" width="100%">
            <tr style="background-color:#f1f5f9;font-weight:bold;font-size:8px;">
                <td width="24%">Examination / Degree</td>
                <td width="24%">University / Board</td>
                <td width="14%">Year</td>
                <td width="14%">% / CGPA</td>
                <td width="24%">Subjects</td>
            </tr>';
        $rows = $edu !== [] ? $edu : [['exam' => '', 'board' => '', 'year' => '', 'percent' => '', 'subjects' => '']];
        while (count($rows) < 4) {
            $rows[] = ['exam' => '', 'board' => '', 'year' => '', 'percent' => '', 'subjects' => ''];
        }
        foreach (array_slice($rows, 0, 8) as $ed) {
            $html .= '<tr style="font-size:8px;"><td>'
                . htmlspecialchars((string) ($ed['exam'] ?? '')) . '</td><td>'
                . htmlspecialchars((string) ($ed['board'] ?? '')) . '</td><td>'
                . htmlspecialchars((string) ($ed['year'] ?? '')) . '</td><td>'
                . htmlspecialchars((string) ($ed['percent'] ?? '')) . '</td><td>'
                . htmlspecialchars((string) ($ed['subjects'] ?? '')) . '</td></tr>';
        }
        $html .= '</table>';
        $pdf->SetFont('helvetica', '', 8);
        $pdf->writeHTML($html, true, false, false, false, '');

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(232, 240, 254);
        $pdf->Cell($w, 6.5, '  14. Experience (start with the latest)    Total: ' . recruitmentFormPdfText($app['experience_years'] ?? ''), 1, 1, 'L', true);

        $exp = function_exists('recruitmentDecodeJsonList') ? recruitmentDecodeJsonList($app['experience_json'] ?? '') : [];
        $html = '<table border="1" cellpadding="3" cellspacing="0" width="100%">
            <tr style="background-color:#f1f5f9;font-weight:bold;font-size:8px;">
                <td width="24%">Organisation</td>
                <td width="18%">Post held</td>
                <td width="14%">From</td>
                <td width="14%">To</td>
                <td width="12%">Duration</td>
                <td width="18%">Nature / pay</td>
            </tr>';
        $erows = $exp !== [] ? $exp : [['org' => '', 'post' => '', 'from' => '', 'to' => '', 'duration' => '', 'nature' => '']];
        while (count($erows) < 3) {
            $erows[] = ['org' => '', 'post' => '', 'from' => '', 'to' => '', 'duration' => '', 'nature' => ''];
        }
        foreach (array_slice($erows, 0, 6) as $er) {
            $html .= '<tr style="font-size:8px;"><td>'
                . htmlspecialchars((string) ($er['org'] ?? '')) . '</td><td>'
                . htmlspecialchars((string) ($er['post'] ?? '')) . '</td><td>'
                . htmlspecialchars((string) ($er['from'] ?? '')) . '</td><td>'
                . htmlspecialchars((string) ($er['to'] ?? '')) . '</td><td>'
                . htmlspecialchars((string) ($er['duration'] ?? '')) . '</td><td>'
                . htmlspecialchars((string) ($er['nature'] ?? '')) . '</td></tr>';
        }
        $html .= '</table>';
        $pdf->writeHTML($html, true, false, false, false, '');

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(232, 240, 254);
        $pdf->Cell($w, 6.5, '  15. Computer knowledge / any other information', 1, 1, 'L', true);
        $pdf->SetFont('helvetica', '', 8);
        $extra = trim(recruitmentFormPdfText($app['computer_knowledge'] ?? '') . "\n" . recruitmentFormPdfText($app['additional_info'] ?? ''));
        $pdf->MultiCell($w, 12, $extra !== '' ? $extra : ' ', 1, 'L');

        $pdf->Ln(1);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(232, 240, 254);
        $pdf->Cell($w, 6.5, '  16. Documents attached (self-attested copies)', 1, 1, 'L', true);
        $docs = function_exists('recruitmentOfficialDocuments') ? recruitmentOfficialDocuments() : [];
        $bits = [];
        foreach ($docs as $doc) {
            if (($doc['key'] ?? '') === 'photo') {
                continue;
            }
            $path = trim((string) ($app[$doc['column'] ?? ''] ?? ''));
            $mark = $path !== '' ? '[Yes]' : '[  ]';
            $bits[] = $mark . ' ' . ($doc['item'] !== '' ? $doc['item'] . ') ' : '') . $doc['label'];
        }
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->MultiCell($w, 4, $filled ? implode("\n", $bits) : "i) Class X  ii) Class XII  iii) Degree / marksheet  iv) Experience  v) Payslip  vi) DOB proof  vii) Aadhaar  viii) CV / Resume  Photo  Signature  Caste / PwD if applicable", 1, 'L');

        $pdf->Ln(2);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell($w, 6, 'Undertaking', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->MultiCell($w, 4, "I have gone through the terms and conditions / instructions for this post and shall abide by the same.\nAll information furnished above is true, complete and correct to the best of my knowledge and belief.\nI have submitted only one application for this position. I understand that false / suppressed information will cancel my candidature, and that NIELIT may accept or reject this application without assigning a reason.", 0, 'L');
        $pdf->Ln(3);
        recruitmentFormPdfCell($pdf, 'Place', recruitmentFormPdfText($app['application_place'] ?? ''), $half, 8, 0);
        $dateVal = $filled && !empty($app['created_at']) && function_exists('recruitmentFormatDate')
            ? recruitmentFormatDate($app['created_at'] ?? '', 'd-m-Y')
            : '';
        recruitmentFormPdfCell($pdf, 'Date', $dateVal, $half, 8);
        $pdf->Ln(2);
        $sigRel = ltrim(str_replace('\\', '/', (string) ($app['signature_path'] ?? '')), '/');
        $sigFile = '';
        if ($sigRel !== '' && strpos($sigRel, '..') === false) {
            $cand = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sigRel);
            if (is_file($cand)) {
                $sigFile = $cand;
            }
        }
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(90, 6, 'Signature of the candidate', 0, 0, 'L');
        $sigY = $pdf->GetY();
        if ($sigFile !== '' && @getimagesize($sigFile)) {
            $pdf->Image($sigFile, 120, $sigY - 2, 40, 14, '', '', '', true, 150);
            $pdf->Ln(16);
        } else {
            $pdf->Cell(96, 12, '', 1, 1, 'L');
        }
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(110, 110, 110);
        $pdf->Cell(0, 6, 'NIELIT Bhubaneswar — FORM OF APPLICATION' . ($filled ? '  |  ' . recruitmentFormPdfText($app['application_no'] ?? '') : '  |  Blank form for any advertised post'), 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);

        $no = recruitmentFormPdfText($app['application_no'] ?? 'blank');
        $safeNo = preg_replace('/[^A-Za-z0-9_-]+/', '_', $no) ?: 'form';
        $filename = 'NIELIT_Application_Form_' . $safeNo . '.pdf';
        return $pdf->Output($filename, $dest);
    }
}
