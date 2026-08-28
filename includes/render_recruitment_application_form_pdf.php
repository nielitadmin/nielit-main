<?php
/**
 * Official-style 2-page NIELIT FORM OF APPLICATION (blank or filled).
 */
require_once __DIR__ . '/institute_branding.php';
require_once __DIR__ . '/tcpdf_devanagari_font.php';
require_once __DIR__ . '/../libraries/tcpdf/tcpdf.php';

if (!class_exists('RecruitmentApplicationFormPdf')) {
    class RecruitmentApplicationFormPdf extends TCPDF
    {
        public function Header()
        {
            $this->SetDrawColor(40, 40, 40);
            $this->SetLineWidth(0.55);
            $this->Rect(8, 8, 194, 281, 'D');
            $this->SetLineWidth(0.2);
            $this->Rect(9.2, 9.2, 191.6, 278.6, 'D');
        }

        public function Footer()
        {
            $this->SetY(-18);
            $this->SetFont('helvetica', '', 7);
            $this->SetTextColor(90, 90, 90);
            $this->Cell(93, 5, 'NIELIT Bhubaneswar — FORM OF APPLICATION', 0, 0, 'L');
            $this->Cell(93, 5, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'R');
            $this->SetTextColor(0, 0, 0);
        }
    }
}

if (!function_exists('recruitmentFormPdfText')) {
    function recruitmentFormPdfText($value): string
    {
        $value = trim((string) $value);
        return ($value === '' || $value === '—') ? '' : $value;
    }
}

if (!function_exists('recruitmentFormPdfFile')) {
    function recruitmentFormPdfFile(string $rel): string
    {
        $rel = ltrim(str_replace('\\', '/', $rel), '/');
        if ($rel === '' || strpos($rel, '..') !== false) {
            return '';
        }
        $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        return (is_file($path) && @getimagesize($path)) ? $path : '';
    }
}

if (!function_exists('recruitmentFormPdfCell')) {
    function recruitmentFormPdfCell(TCPDF $pdf, string $label, string $value, float $w, float $h = 8.2, int $ln = 1, float $fontSize = 9): void
    {
        $pdf->SetFont('helvetica', '', $fontSize);
        $txt = '  ' . $label;
        if ($value !== '') {
            $txt .= ':  ' . $value;
        } else {
            $txt .= ':';
        }
        $size = $fontSize;
        while ($size > 6.5 && $pdf->GetStringWidth($txt) > ($w - 2.2)) {
            $size -= 0.4;
            $pdf->SetFont('helvetica', '', $size);
        }
        $pdf->Cell($w, $h, $txt, 1, $ln, 'L');
    }
}

if (!function_exists('recruitmentFormPdfFitImage')) {
    function recruitmentFormPdfFitImage(TCPDF $pdf, string $file, float $x, float $y, float $boxW, float $boxH): void
    {
        $info = @getimagesize($file);
        $iw = ($info && !empty($info[0])) ? (float) $info[0] : 0.0;
        $ih = ($info && !empty($info[1])) ? (float) $info[1] : 0.0;
        if ($iw <= 0 || $ih <= 0) {
            $pdf->Image($file, $x, $y, $boxW, 0, '', '', '', true, 150);
            return;
        }
        $scale = min($boxW / $iw, $boxH / $ih);
        $dw = $iw * $scale;
        $dh = $ih * $scale;
        $pdf->Image($file, $x + (($boxW - $dw) / 2), $y + (($boxH - $dh) / 2), $dw, $dh, '', '', '', true, 150);
    }
}

if (!function_exists('recruitmentFormPdfBox')) {
    function recruitmentFormPdfBox(TCPDF $pdf, string $text, float $w, float $h): void
    {
        $pdf->SetFont('helvetica', '', 9);
        $pdf->MultiCell($w, $h, $text !== '' ? '  ' . $text : '  ', 1, 'L', false, 1, '', '', true, 0, false, true, $h, 'T');
    }
}

if (!function_exists('recruitmentFormPdfOptions')) {
    /**
     * @param list<string> $options
     */
    function recruitmentFormPdfOptions(TCPDF $pdf, string $label, array $options, string $selected, float $w, float $h = 8.2): void
    {
        $selected = trim($selected);
        $bits = [];
        foreach ($options as $opt) {
            $mark = (strcasecmp($selected, $opt) === 0) ? '[X]' : '[ ]';
            $bits[] = $mark . ' ' . $opt;
        }
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell($w, $h, '  ' . $label . ':   ' . implode('    ', $bits), 1, 1, 'L');
    }
}

if (!function_exists('recruitmentFormPdfTable')) {
    /**
     * @param list<string> $headers
     * @param list<float> $widths
     * @param list<list<string>> $rows
     */
    function recruitmentFormPdfTable(TCPDF $pdf, array $headers, array $widths, array $rows, float $rowH = 8.5, float $headH = 7.4, float $fontSize = 8.5): void
    {
        $pdf->SetFont('helvetica', 'B', $fontSize);
        $pdf->SetFillColor(232, 240, 254);
        foreach ($headers as $i => $h) {
            $ln = ($i === count($headers) - 1) ? 1 : 0;
            $pdf->Cell($widths[$i], $headH, ' ' . $h, 1, $ln, 'C', true);
        }
        $pdf->SetFont('helvetica', '', $fontSize);
        $pdf->SetFillColor(255, 255, 255);
        foreach ($rows as $row) {
            foreach ($widths as $i => $colW) {
                $ln = ($i === count($widths) - 1) ? 1 : 0;
                $pdf->Cell($colW, $rowH, ' ' . ($row[$i] ?? ''), 1, $ln, 'L');
            }
        }
    }
}

if (!function_exists('outputRecruitmentApplicationFormPdf')) {
    /**
     * Always two A4 pages, matching the official NIELIT FORM OF APPLICATION.
     *
     * @param array<string,mixed>|null $app
     */
    function outputRecruitmentApplicationFormPdf(?array $app = null, string $dest = 'I'): string
    {
        $app = $app ?? [];
        $filled = trim((string) ($app['application_no'] ?? '')) !== '';
        $w = 186.0;
        $half = 93.0;
        $third = 62.0;

        $pdf = new RecruitmentApplicationFormPdf('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('NIELIT Bhubaneswar');
        $pdf->SetAuthor('NIELIT Bhubaneswar');
        $pdf->SetTitle('FORM OF APPLICATION');
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(true);
        $pdf->SetMargins(12, 12, 12);
        $pdf->SetHeaderMargin(0);
        $pdf->SetFooterMargin(18);
        $pdf->SetAutoPageBreak(false, 20);
        $pdf->AddPage();

        $h = 8.2;
        $pageLeft = 12.0;
        $pageRight = 198.0;
        $logoPath = __DIR__ . '/../assets/images/bhubaneswar_logo.png';
        $headerY = 12.5;
        $logoH = 13.0;
        $logoW = 32.0;
        if (is_file($logoPath)) {
            $imgSize = @getimagesize($logoPath);
            if ($imgSize && !empty($imgSize[1])) {
                $logoW = $logoH * ($imgSize[0] / $imgSize[1]);
            }
            $pdf->Image($logoPath, $pageLeft, $headerY + 3.5, $logoW, $logoH, 'PNG');
        }
        $textX = $pageLeft + $logoW + 4.0;
        $textW = $pageRight - $textX;

        $hindiFont = getTcpdfDevanagariFontName();
        $pdf->SetXY($textX, $headerY);
        if ($hindiFont) {
            $pdf->SetFont($hindiFont, '', 9);
            $pdf->Cell($textW, 4.4, INSTITUTE_NAME_HI, 0, 1, 'C');
            $pdf->SetX($textX);
        }
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell($textW, 5.0, 'National Institute of Electronics & Information Technology (NIELIT)', 0, 1, 'C');
        $pdf->SetX($textX);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell($textW, 4.0, 'Bhubaneswar  |  Raipur  |  Baleshwar', 0, 1, 'C');
        $pdf->SetX($textX);
        $pdf->SetFont('helvetica', '', 7.4);
        $pdf->SetTextColor(70, 70, 70);
        $pdf->Cell($textW, 3.5, 'An Autonomous Scientific Society under Ministry of Electronics & Information Technology, Government of India', 0, 1, 'C');
        $pdf->SetX($textX);
        $pdf->Cell($textW, 3.5, 'Office: 3rd Floor, North Side, OCAC Tower, Acharya Vihar, Bhubaneswar, Odisha – 751013', 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);

        $headerBottom = max($pdf->GetY(), $headerY + $logoH) + 1.6;
        $pdf->SetY($headerBottom);
        $pdf->SetDrawColor(40, 40, 40);
        $pdf->SetLineWidth(0.45);
        $pdf->Line($pageLeft, $headerBottom, $pageRight, $headerBottom);
        $pdf->Ln(2.6);

        $photoW = 32.0;
        $photoH = 42.0;
        $photoX = $pageRight - $photoW;
        $photoY = $pdf->GetY();
        $leftW = $photoX - $pageLeft - 2.0;
        $nameThird = $leftW / 3;

        $pdf->SetDrawColor(80, 80, 80);
        $pdf->SetLineWidth(0.25);
        $pdf->Rect($photoX, $photoY, $photoW, $photoH);
        $photoFile = recruitmentFormPdfFile((string) ($app['photo_path'] ?? ''));
        if ($photoFile !== '') {
            recruitmentFormPdfFitImage($pdf, $photoFile, $photoX + 0.6, $photoY + 0.6, $photoW - 1.2, $photoH - 1.2);
        } else {
            $pdf->SetXY($photoX, $photoY + 12);
            $pdf->SetFont('helvetica', '', 6.5);
            $pdf->SetTextColor(110, 110, 110);
            $pdf->MultiCell($photoW, 3.5, "Affix recent\npassport size\nphotograph", 0, 'C');
            $pdf->SetTextColor(0, 0, 0);
        }

        $pdf->SetXY($pageLeft, $photoY);
        $pdf->SetFont('helvetica', 'BU', 13);
        $pdf->Cell($leftW, 6.4, 'APPLICATION FORM', 0, 1, 'C');
        $pdf->SetX($pageLeft);
        $pdf->SetFont('helvetica', 'I', 7.5);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell($leftW, 3.6, 'To be filled in CAPITAL letters', 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetX($pageLeft);
        $pdf->Ln(0.6);

        recruitmentFormPdfCell($pdf, '1. Post applied for', recruitmentFormPdfText($app['job_title'] ?? ''), $leftW, $h);
        recruitmentFormPdfCell($pdf, 'Advertisement no.', recruitmentFormPdfText($app['advt_no'] ?? ''), $leftW / 2, $h, 0);
        recruitmentFormPdfCell($pdf, 'Application no.', $filled ? recruitmentFormPdfText($app['application_no'] ?? '') : '', $leftW / 2, $h);

        $first = recruitmentFormPdfText($app['name_first'] ?? '');
        $middle = recruitmentFormPdfText($app['name_middle'] ?? '');
        $last = recruitmentFormPdfText($app['name_last'] ?? '');
        if ($first === '' && $middle === '' && $last === '') {
            $full = recruitmentFormPdfText($app['name'] ?? '');
            $parts = preg_split('/\s+/', $full) ?: [];
            $first = (string) ($parts[0] ?? '');
            $last = (string) (count($parts) > 1 ? array_pop($parts) : '');
            $middle = trim(implode(' ', array_slice($parts, 1)));
        }
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->Cell($leftW, 7.2, '  2. Name in full (in Block Letters)  —  First / Middle / Last', 1, 1, 'L');
        recruitmentFormPdfCell($pdf, 'First', $first, $nameThird, $h, 0, 8.5);
        recruitmentFormPdfCell($pdf, 'Middle', $middle, $nameThird, $h, 0, 8.5);
        recruitmentFormPdfCell($pdf, 'Last', $last, $leftW - (2 * $nameThird), $h, 1, 8.5);

        $pdf->SetY(max($pdf->GetY(), $photoY + $photoH + 1.6));

        $father = strtoupper(recruitmentFormPdfText($app['father_name'] ?? ''));
        $mother = strtoupper(recruitmentFormPdfText($app['mother_name'] ?? ''));
        recruitmentFormPdfCell($pdf, "3. Father's / Husband's name", $father, $w, $h);
        recruitmentFormPdfCell($pdf, "Mother's name", $mother, $w, $h);

        $dob = recruitmentFormPdfText($app['dob'] ?? '');
        if ($dob !== '' && function_exists('recruitmentFormatDate')) {
            $fmt = recruitmentFormatDate($dob, 'd-m-Y');
            if ($fmt !== '—') {
                $dob = $fmt;
            }
        }
        $ageY = $filled ? (string) (int) ($app['age_years'] ?? 0) : '';
        $ageM = $filled ? (string) (int) ($app['age_months'] ?? 0) : '';
        $ageD = $filled ? (string) (int) ($app['age_days'] ?? 0) : '';
        recruitmentFormPdfCell($pdf, '4. (a) Date of Birth (as per Class X / Aadhaar)', $dob, $w, $h);
        $pdf->SetFont('helvetica', '', 9);
        $ageLine = '  4. (b) Age as on last date of application:    Years: ' . ($ageY !== '' ? $ageY : '______')
            . '      Months: ' . ($ageM !== '' ? $ageM : '______')
            . '      Days: ' . ($ageD !== '' ? $ageD : '______');
        $pdf->Cell($w, $h, $ageLine, 1, 1, 'L');

        recruitmentFormPdfOptions($pdf, '5. Gender', ['Male', 'Female', 'Other'], recruitmentFormPdfText($app['gender'] ?? ''), $w, $h);
        recruitmentFormPdfOptions($pdf, '6. Marital status', ['Unmarried', 'Married', 'Divorcee', 'Other'], recruitmentFormPdfText($app['marital_status'] ?? ''), $w, $h);
        recruitmentFormPdfCell($pdf, '7. Nationality', recruitmentFormPdfText($app['nationality'] ?? ''), $w, $h);
        recruitmentFormPdfOptions($pdf, '8. Category', ['General', 'OBC', 'SC', 'ST', 'EWS'], recruitmentFormPdfText($app['category'] ?? ''), $w, $h);
        $pwd = recruitmentFormPdfText($app['pwd_status'] ?? '');
        $pdf->SetFont('helvetica', '', 9);
        $pwdYes = '[ ]';
        $pwdNo = '[ ]';
        if (!$filled) {
            $pwdYes = '[ ]';
            $pwdNo = '[ ]';
        } elseif (strcasecmp($pwd, 'Yes') === 0) {
            $pwdYes = '[X]';
            $pwdNo = '[ ]';
        } else {
            $pwdYes = '[ ]';
            $pwdNo = '[X]';
        }
        $pwdExtra = '';
        if (recruitmentFormPdfText($app['pwd_type'] ?? '') !== '') {
            $pwdExtra .= '    Type: ' . $app['pwd_type'];
        } else {
            $pwdExtra .= '    Type: __________';
        }
        if (recruitmentFormPdfText($app['pwd_percent'] ?? '') !== '') {
            $pwdExtra .= '    % of disability: ' . $app['pwd_percent'];
        } else {
            $pwdExtra .= '    % of disability: ______';
        }
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell($w, $h, '     Whether belong to PwD:   ' . $pwdYes . ' Yes     ' . $pwdNo . ' No' . $pwdExtra, 1, 1, 'L');

        recruitmentFormPdfCell($pdf, '9. Aadhaar number', recruitmentFormPdfText($app['aadhar'] ?? ''), $w, $h);
        recruitmentFormPdfCell($pdf, '10. (a) Mobile', recruitmentFormPdfText($app['mobile'] ?? ''), $half, $h, 0);
        recruitmentFormPdfCell($pdf, 'Alternate mobile', recruitmentFormPdfText($app['alt_mobile'] ?? ''), $half, $h);
        recruitmentFormPdfCell($pdf, '10. (b) Email', recruitmentFormPdfText($app['email'] ?? ''), $w, $h);

        $addr = recruitmentFormPdfText($app['address'] ?? '');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell($w, 8.0, '  11. Address for correspondence', 1, 1, 'L');
        recruitmentFormPdfBox($pdf, $addr, $w, 12.5);
        recruitmentFormPdfCell($pdf, 'City', recruitmentFormPdfText($app['city'] ?? ''), 70, $h, 0);
        recruitmentFormPdfCell($pdf, 'State', recruitmentFormPdfText($app['state'] ?? ''), 70, $h, 0);
        recruitmentFormPdfCell($pdf, 'PIN', recruitmentFormPdfText($app['pincode'] ?? ''), 46, $h);

        $perm = recruitmentFormPdfText($app['permanent_address'] ?? '');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell($w, 8.0, '  12. Permanent address', 1, 1, 'L');
        recruitmentFormPdfBox($pdf, $perm, $w, 12.5);
        recruitmentFormPdfCell($pdf, 'Permanent PIN', recruitmentFormPdfText($app['permanent_pincode'] ?? ''), $w, $h);

        $page1Bottom = 270.0;
        $eduHeaderH = 8.0;
        $tableHeadH = 7.6;
        $needEdu = 4;
        $gap = 1.4;
        $remain = $page1Bottom - $pdf->GetY() - $gap - $eduHeaderH - $tableHeadH;
        while ($needEdu > 2 && $remain / $needEdu < 6.4) {
            $needEdu--;
        }
        $eduRowH = $remain / max(1, $needEdu);
        if ($eduRowH > 9.5) {
            $eduRowH = 9.5;
        }
        if ($eduRowH < 5.8) {
            $eduRowH = max(5.4, $remain / max(1, $needEdu));
        }

        $pdf->Ln($gap);
        $pdf->SetFont('helvetica', 'B', 9.5);
        $pdf->SetFillColor(232, 240, 254);
        $pdf->Cell($w, $eduHeaderH, '  13. Particulars of all examinations passed / degrees / technical qualifications (from Class X)', 1, 1, 'L', true);

        $edu = function_exists('recruitmentDecodeJsonList') ? recruitmentDecodeJsonList($app['education_json'] ?? '') : [];
        $eduRows = [];
        for ($i = 0; $i < $needEdu; $i++) {
            $ed = $edu[$i] ?? [];
            $eduRows[] = [
                (string) ($ed['exam'] ?? ''),
                (string) ($ed['board'] ?? ''),
                (string) ($ed['year'] ?? ''),
                (string) ($ed['percent'] ?? ''),
                (string) ($ed['subjects'] ?? ''),
            ];
        }
        recruitmentFormPdfTable(
            $pdf,
            ['Examination / Degree', 'University / Board', 'Year', '% / CGPA', 'Subjects'],
            [46, 48, 22, 24, 46],
            $eduRows,
            $eduRowH,
            $tableHeadH,
            9
        );

        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 8.5);
        $pdf->SetFillColor(232, 240, 254);
        $totExp = recruitmentFormPdfText($app['experience_years'] ?? '');
        $pdf->Cell($w, 6.2, '  14. Experience (start with the latest)     Total experience: ' . ($totExp !== '' ? $totExp : '________________'), 1, 1, 'L', true);

        $exp = function_exists('recruitmentDecodeJsonList') ? recruitmentDecodeJsonList($app['experience_json'] ?? '') : [];
        $expRows = [];
        for ($i = 0; $i < 4; $i++) {
            $er = $exp[$i] ?? [];
            $expRows[] = [
                (string) ($er['org'] ?? ''),
                (string) ($er['post'] ?? ''),
                (string) ($er['from'] ?? ''),
                (string) ($er['to'] ?? ''),
                (string) ($er['duration'] ?? ''),
                (string) ($er['nature'] ?? ''),
            ];
        }
        recruitmentFormPdfTable(
            $pdf,
            ['Name of organisation', 'Post held', 'From', 'To', 'Duration', 'Nature of duties / pay'],
            [42, 32, 24, 24, 22, 42],
            $expRows,
            7.2
        );
        $expNotes = recruitmentFormPdfText($app['experience_details'] ?? '');
        if ($expNotes !== '') {
            $pdf->SetFont('helvetica', '', 8);
            $pdf->MultiCell($w, 4.5, '  Additional experience details: ' . $expNotes, 1, 'L');
        }

        $pdf->Ln(1.6);
        $pdf->SetFont('helvetica', 'B', 8.5);
        $pdf->SetFillColor(232, 240, 254);
        $pdf->Cell($w, 6.2, '  15. Knowledge of computers / any other information', 1, 1, 'L', true);
        $pdf->SetFont('helvetica', '', 8);
        $comp = recruitmentFormPdfText($app['computer_knowledge'] ?? '');
        $other = recruitmentFormPdfText($app['additional_info'] ?? '');
        $pdf->MultiCell($w, 5, '  Computer knowledge: ' . ($comp !== '' ? $comp : ''), 1, 'L');
        $pdf->MultiCell($w, 5, '  Any other information: ' . ($other !== '' ? $other : ''), 1, 'L');

        $pdf->Ln(1.6);
        $pdf->SetFont('helvetica', 'B', 8.5);
        $pdf->SetFillColor(232, 240, 254);
        $pdf->Cell($w, 6.2, '  16. Documents attached (self-attested copies)', 1, 1, 'L', true);
        $pdf->SetFont('helvetica', 'I', 7);
        $pdf->Cell($w, 4.6, '  Tick Yes if the document is enclosed with this application / uploaded online.', 1, 1, 'L');

        $docLines = [
            ['i', 'Marksheet of Class X', 'marksheet_x_path'],
            ['ii', 'Marksheet of Class XII', 'marksheet_xii_path'],
            ['iii', 'Qualification degree / certificate, final marksheet, and CGPA-to-% formula (if CGPA is awarded)', 'degree_doc_path'],
            ['iv', 'Self-attested experience certificates (including current place of working)', 'experience_doc_path'],
            ['v', 'Last three-month payslip or bank statement showing salary credited', 'payslip_path'],
            ['vi', 'Date of Birth certificate / Class X certificate as proof of age', 'dob_cert_path'],
            ['vii', 'Aadhaar card', 'aadhaar_doc_path'],
            ['viii', 'CV / Resume of the candidate', 'resume_path'],
            ['', 'Recent passport size photograph', 'photo_path'],
            ['', 'Signature of the candidate', 'signature_path'],
            ['', 'Caste / category certificate (SC / ST / OBC / EWS) — if applicable', 'category_cert_path'],
            ['', 'PwD certificate — if applicable', 'pwd_cert_path'],
        ];
        foreach ($docLines as $doc) {
            $has = $filled && trim((string) ($app[$doc[2]] ?? '')) !== '';
            if ($doc[2] === 'degree_doc_path' && $filled && trim((string) ($app['cgpa_formula_path'] ?? '')) !== '') {
                $has = true;
            }
            $yes = $has ? '[X] Yes' : '[ ] Yes';
            $no = $filled ? ($has ? '[ ] No' : '[X] No') : '[ ] No';
            $prefix = $doc[0] !== '' ? $doc[0] . ') ' : '';
            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->Cell(148, 5.8, '  ' . $prefix . $doc[1], 1, 0, 'L');
            $pdf->Cell(38, 5.8, ' ' . $yes . '   ' . $no, 1, 1, 'L');
        }

        $pdf->Ln(2.2);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(232, 240, 254);
        $pdf->Cell($w, 6.4, '  UNDERTAKING', 1, 1, 'C', true);
        $pdf->SetFont('helvetica', '', 7.6);
        $pdf->MultiCell($w, 4.2, '  I hereby declare and confirm each of the following:', 1, 'L');
        $points = [
            '1. I have attached / uploaded the documents I have available for this application.',
            '2. I have gone through the Terms & Conditions / instructions for this post and shall abide by the same.',
            '3. All information furnished above is true, complete and correct to the best of my knowledge and belief.',
            '4. I have submitted only one application for this position. I have never been debarred by any organisation for illegal activity during my education / service. I understand that false / suppressed information will cancel my candidature, and that NIELIT may accept or reject this application without assigning a reason.',
        ];
        foreach ($points as $pt) {
            $h = (strpos($pt, '4.') === 0) ? 10.2 : 6.4;
            $pdf->MultiCell($w, $h, '  ' . $pt, 1, 'L');
        }

        $pdf->Ln(3);
        $place = recruitmentFormPdfText($app['application_place'] ?? '');
        $dateVal = '';
        if ($filled && !empty($app['created_at']) && function_exists('recruitmentFormatDate')) {
            $dateVal = recruitmentFormatDate((string) $app['created_at'], 'd-m-Y');
            if ($dateVal === '—') {
                $dateVal = '';
            }
        }
        recruitmentFormPdfCell($pdf, 'Place', $place, $half, 8.5, 0);
        recruitmentFormPdfCell($pdf, 'Date', $dateVal, $half, 8.5);

        $sigFile = recruitmentFormPdfFile((string) ($app['signature_path'] ?? ''));
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', '', 8);
        $sigY = $pdf->GetY();
        $pdf->Cell(100, 6, '  Signature of the candidate', 0, 0, 'L');
        $pdf->Rect(118, $sigY, 68, 18);
        if ($sigFile !== '') {
            $pdf->Image($sigFile, 121, $sigY + 1.5, 62, 15, '', '', '', true, 150);
        } else {
            $pdf->SetXY(118, $sigY + 6);
            $pdf->SetFont('helvetica', 'I', 7);
            $pdf->SetTextColor(130, 130, 130);
            $pdf->Cell(68, 6, 'Sign here', 0, 0, 'C');
            $pdf->SetTextColor(0, 0, 0);
        }
        $pdf->SetY($sigY + 20);
        $pdf->SetFont('helvetica', 'I', 7);
        $pdf->Cell($w, 4, '  (Signature must match the uploaded / affixed signature)', 0, 1, 'L');

        $pdf->Ln(2);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(248, 250, 252);
        $pdf->Cell($w, 5.8, '  For office use only', 1, 1, 'L', true);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell($half, 7.2, '  Application received on: ____________________', 1, 0, 'L');
        $pdf->Cell($half, 7.2, '  Status: ____________________', 1, 1, 'L');
        $pdf->Cell($w, 7.2, '  Remarks: __________________________________________________________________________', 1, 1, 'L');

        $no = recruitmentFormPdfText($app['application_no'] ?? 'blank');
        $safeNo = preg_replace('/[^A-Za-z0-9_-]+/', '_', $no) ?: 'form';
        $filename = 'NIELIT_Application_Form_' . $safeNo . '.pdf';
        return $pdf->Output($filename, $dest);
    }
}
