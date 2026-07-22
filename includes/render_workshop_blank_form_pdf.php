<?php
/**
 * Blank printable PDF for Workshop / Awareness short registration
 * — single A4 page, readable fonts, balanced signature space.
 */
require_once __DIR__ . '/institute_branding.php';
require_once __DIR__ . '/tcpdf_devanagari_font.php';
require_once __DIR__ . '/workshop_registration_helper.php';
require_once __DIR__ . '/../libraries/tcpdf/tcpdf.php';

if (!function_exists('outputWorkshopBlankRegistrationPdf')) {
    function outputWorkshopBlankRegistrationPdf(
        ?string $courseName = null,
        ?string $courseCode = null,
        string $dest = 'I',
        ?string $trainingCentre = null,
        ?string $courseDescription = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $eligibilityFallback = null
    ): string {
        $trainingCentre = trim((string) $trainingCentre);
        if ($trainingCentre === '') {
            $trainingCentre = 'NIELIT Bhubaneswar';
        }
        $courseDescription = trim((string) $courseDescription);
        // Only use real Course Description from Edit Course — do not fall back to eligibility
        $startDate = workshopPdfFormatDate($startDate);
        $endDate = workshopPdfFormatDate($endDate);

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('NIELIT Bhubaneswar');
        $pdf->SetAuthor('NIELIT Bhubaneswar');
        $pdf->SetTitle('Registration Form (Physical)');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(12, 10, 12);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();

        $contentMaxY = 284;
        $pdf->SetDrawColor(80, 80, 80);
        $pdf->SetLineWidth(0.6);
        $pdf->Rect(8, 8, 194, 281, 'D');
        $pdf->SetDrawColor(180, 200, 230);
        $pdf->SetLineWidth(0.3);

        $hindiFont = function_exists('getTcpdfDevanagariFontName') ? getTcpdfDevanagariFontName() : null;
        $hindiTexts = function_exists('getAdmissionFormHindiTexts') ? getAdmissionFormHindiTexts() : [];

        $fullW = 186;
        $rowH = 7.5;
        $sectionH = 6;
        $gap = 1.5;

        $logoPath = __DIR__ . '/../assets/images/bhubaneswar_logo.png';
        $marginLeft = 12;
        $headerY = 11;
        $logoW = 20;
        $contentRight = 198;
        $textX = $marginLeft;
        $textW = $contentRight - $marginLeft;
        $logoH = 0;

        if (is_file($logoPath)) {
            $imgSize = @getimagesize($logoPath);
            $logoH = ($imgSize && !empty($imgSize[0])) ? $logoW * ($imgSize[1] / $imgSize[0]) : 18;
            $pdf->Image($logoPath, $marginLeft, $headerY, $logoW, 0, 'PNG');
            $textX = $marginLeft + $logoW + 3;
            $textW = $contentRight - $textX;
        }

        $pdf->SetXY($textX, $headerY);
        if ($hindiFont && !empty($hindiTexts['institute_line'])) {
            $pdf->SetFont($hindiFont, '', 9);
            $pdf->MultiCell($textW, 4, $hindiTexts['institute_line'], 0, 'C', false, 1, $textX, $headerY);
        }
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->MultiCell($textW, 4, INSTITUTE_NAME_EN, 0, 'C', false, 1, $textX, $pdf->GetY());
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->MultiCell($textW, 3.5, 'Ministry of Electronics & Information Technology, Government of India', 0, 'C', false, 1, $textX, $pdf->GetY());
        $pdf->SetTextColor(0, 0, 0);

        $headerBottom = max($pdf->GetY(), $headerY + $logoH) + 2;
        $pdf->SetY($headerBottom);
        $pdf->SetDrawColor(40, 80, 140);
        $pdf->SetLineWidth(0.45);
        $pdf->Line(12, $pdf->GetY(), 198, $pdf->GetY());
        $pdf->Ln(2.5);

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 6.5, getWorkshopShortFormTitle() . ' — Short Registration Form', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->Cell(0, 4, 'Physical / Offline copy — please fill by hand clearly', 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(2);

        // Course + photo — Description / Start / End inside Program block (left of photo)
        $y = $pdf->GetY();
        $photoX = 162;
        $photoW = 36;
        $leftW = 146;
        $rowHLocal = 7.2;
        $descH = 9;

        $pdf->SetXY(12, $y);
        $pdf->SetFont('helvetica', 'B', 9.5);
        $pdf->SetFillColor(232, 240, 254);
        $pdf->SetDrawColor(180, 200, 230);
        $pdf->Cell($leftW, 6, '  Program / Course', 1, 1, 'L', true);
        workshopPdfLabeledLine($pdf, 'Course name', $courseName ?: '', $leftW, $rowHLocal);
        workshopPdfLabeledLine($pdf, 'Course code', $courseCode ?: '', $leftW, $rowHLocal);
        workshopPdfLabeledLine($pdf, 'Training centre', $trainingCentre, $leftW, $rowHLocal);

        // Course Description (write-in / auto value) inside left column
        $descPreview = $courseDescription !== ''
            ? (strlen($courseDescription) > 90 ? substr($courseDescription, 0, 87) . '...' : $courseDescription)
            : '';
        workshopPdfLabeledLine($pdf, 'Course Description', $descPreview, $leftW, $descH);

        // Start Date * | End Date * inside left column
        $halfL = $leftW / 2;
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetDrawColor(180, 200, 230);
        $pdf->Cell(32, $rowHLocal, ' Start Date *', 1, 0);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell($halfL - 32, $rowHLocal, ' ' . $startDate, 1, 0);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(30, $rowHLocal, ' End Date *', 1, 0);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell($halfL - 30, $rowHLocal, ' ' . $endDate, 1, 1);

        $blockBottom = $pdf->GetY();
        $photoH = max(40, $blockBottom - $y);
        $pdf->SetDrawColor(100, 100, 100);
        $pdf->SetLineWidth(0.3);
        $pdf->Rect($photoX, $y, $photoW, $photoH);
        $pdf->SetXY($photoX, $y + ($photoH / 2) - 5);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->MultiCell($photoW, 3.5, "Passport photo *\n(paste here)", 0, 'C');

        $pdf->SetY($blockBottom + $gap);

        workshopPdfSection($pdf, '1. Student details', $sectionH);
        workshopPdfLabeledLine($pdf, 'Student full name *', '', $fullW, $rowH);
        workshopPdfTwoCol($pdf, 'Class / Level *', 'DOB * (DD/MM/YYYY)', $fullW, $rowH);
        workshopPdfTwoCol($pdf, 'Age', 'Gender *  Male / Female / Other', $fullW, $rowH);
        workshopPdfLabeledLine($pdf, 'Category  Gen / OBC / SC / ST / EWS', '', $fullW, $rowH);
        $pdf->Ln($gap);

        workshopPdfSection($pdf, '2. Parent / guardian & contact', $sectionH);
        workshopPdfTwoCol($pdf, "Father's name", "Mother's name", $fullW, $rowH);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 4, 'At least one parent / guardian name is required.', 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);
        workshopPdfTwoCol($pdf, 'Mobile (parent) *', 'Email *', $fullW, $rowH);
        $pdf->Ln($gap);

        workshopPdfSection($pdf, '3. Aadhar details (optional)', $sectionH);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetDrawColor(180, 200, 230);
        $aadharLabelW = 55;
        $boxH = 9.5;
        $pdf->Cell($aadharLabelW, $boxH, ' Aadhar number (optional)', 1, 0);
        $digitAreaW = $fullW - $aadharLabelW;
        $digitBoxW = $digitAreaW / 12;
        $startX = $pdf->GetX();
        $startY = $pdf->GetY();
        for ($i = 0; $i < 12; $i++) {
            $pdf->Rect($startX + ($i * $digitBoxW), $startY, $digitBoxW, $boxH);
        }
        $pdf->SetXY($startX + $digitAreaW, $startY);
        $pdf->Ln($boxH);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(55, $rowH, ' Aadhar card copy', 1, 0);
        $pdf->Cell($fullW - 55, $rowH, '  [ ] Attached (photocopy)     [ ] Not available', 1, 1);
        $pdf->Ln($gap);

        workshopPdfSection($pdf, '4. School & address', $sectionH);
        workshopPdfLabeledLine($pdf, 'School / College name *', '', $fullW, $rowH);
        workshopPdfLabeledLine($pdf, 'Address *', '', $fullW, 8);
        workshopPdfTwoCol($pdf, 'State *', 'City / District *', $fullW, $rowH);
        workshopPdfLabeledLine($pdf, 'Pincode *', '', 100, $rowH);
        $pdf->Ln(1.2);

        // 5. Declaration — text, then Date/Place clearly below (no overlap)
        workshopPdfSection($pdf, '5. Declaration', $sectionH);
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->SetDrawColor(180, 200, 230);
        $decl = 'I hereby declare that the information given above is true and correct to the best of my knowledge. I agree to abide by the rules and regulations of NIELIT. I understand that any false or incomplete information may lead to cancellation of my registration.';
        $pdf->MultiCell($fullW, 3.8, $decl, 1, 'L', false, 1, 12, $pdf->GetY(), true);
        $pdf->Ln(2);
        workshopPdfTwoCol($pdf, 'Date', 'Place', $fullW, $rowH);
        $pdf->Ln(1.5);

        // 6 (left) + 7 (right) — each is one bordered block: header + signing space
        $sigHalf = $fullW / 2;
        $headerH = 7;
        $sigBoxH = 30;
        $room = 284 - $pdf->GetY() - 10;
        if ($room < ($headerH + $sigBoxH)) {
            $sigBoxH = max(18, $room - $headerH);
        }

        $x = 12;
        $y0 = $pdf->GetY();
        $pdf->SetDrawColor(180, 200, 230);
        $pdf->SetLineWidth(0.4);

        // Left block: 6. Student
        $pdf->SetFillColor(232, 240, 254);
        $pdf->Rect($x, $y0, $sigHalf, $headerH + $sigBoxH);
        $pdf->Rect($x, $y0, $sigHalf, $headerH, 'DF');
        $pdf->SetFont('helvetica', 'B', 8.5);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($x, $y0 + 1.2);
        $pdf->Cell($sigHalf, 4.5, '  6. Signature of Student (if applicable)', 0, 0, 'L');

        // Right block: 7. HoD / Principal / Coordinator
        $pdf->SetFillColor(232, 240, 254);
        $pdf->Rect($x + $sigHalf, $y0, $sigHalf, $headerH + $sigBoxH);
        $pdf->Rect($x + $sigHalf, $y0, $sigHalf, $headerH, 'DF');
        $pdf->SetXY($x + $sigHalf, $y0 + 1.2);
        $pdf->Cell($sigHalf, 4.5, '  7. Signature of HoD / Principal / Coordinator', 0, 0, 'L');

        // Divider line under headers (signing space below)
        $pdf->Line($x, $y0 + $headerH, $x + $fullW, $y0 + $headerH);
        $pdf->Line($x + $sigHalf, $y0, $x + $sigHalf, $y0 + $headerH + $sigBoxH);

        $pdf->SetY($y0 + $headerH + $sigBoxH + 1.5);
        $pdf->SetFont('helvetica', 'I', 7);
        $pdf->SetTextColor(110, 110, 110);
        $pdf->Cell(
            $fullW,
            4,
            '* Mandatory. Aadhar optional. Passport photo & Student signature mandatory. Office use: enter into NIELIT system after verification.',
            0,
            1,
            'L',
            false,
            '',
            1
        );
        $pdf->SetTextColor(0, 0, 0);

        $filename = 'NIELIT_Workshop_Registration_Form.pdf';
        if ($dest === 'S') {
            return $pdf->Output($filename, 'S');
        }
        if ($dest === 'F') {
            $out = __DIR__ . '/../assets/forms/' . $filename;
            $pdf->Output($out, 'F');
            return $out;
        }
        $pdf->Output($filename, $dest);
        return $filename;
    }
}

if (!function_exists('workshopPdfFormatDate')) {
    function workshopPdfFormatDate(?string $date): string
    {
        $date = trim((string) $date);
        if ($date === '' || $date === '0000-00-00') {
            return '';
        }
        $ts = strtotime($date);
        if ($ts === false) {
            return $date;
        }
        return date('d/m/Y', $ts);
    }
}

if (!function_exists('workshopPdfSection')) {
    function workshopPdfSection(TCPDF $pdf, string $title, float $height = 7.5): void
    {
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetFillColor(232, 240, 254);
        $pdf->SetDrawColor(180, 200, 230);
        $pdf->Cell(0, $height, '  ' . $title, 1, 1, 'L', true);
    }
}

if (!function_exists('workshopPdfLabeledLine')) {
    function workshopPdfLabeledLine(TCPDF $pdf, string $label, string $value = '', float $width = 186, float $height = 9): void
    {
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetDrawColor(180, 200, 230);
        if ($label === '') {
            $pdf->Cell($width, $height, ' ' . $value, 1, 1);
            return;
        }
        $labelW = min(58, max(30, min(58, $pdf->GetStringWidth($label) + 4)));
        $pdf->Cell($labelW, $height, ' ' . $label, 1, 0, '', false, '', 1);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell($width - $labelW, $height, ' ' . $value, 1, 1);
        $pdf->SetFont('helvetica', '', 9);
    }
}

if (!function_exists('workshopPdfTwoCol')) {
    function workshopPdfTwoCol(TCPDF $pdf, string $leftLabel, string $rightLabel, float $totalW = 186, float $height = 9): void
    {
        $half = $totalW / 2;
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetDrawColor(180, 200, 230);
        $leftLabelW = min(48, max(24, $pdf->GetStringWidth($leftLabel) + 4));
        $rightLabelW = min(48, max(24, $pdf->GetStringWidth($rightLabel) + 4));
        $pdf->Cell($leftLabelW, $height, ' ' . $leftLabel, 1, 0, '', false, '', 1);
        $pdf->Cell($half - $leftLabelW, $height, '', 1, 0);
        $pdf->Cell($rightLabelW, $height, ' ' . $rightLabel, 1, 0, '', false, '', 1);
        $pdf->Cell($half - $rightLabelW, $height, '', 1, 1);
    }
}
