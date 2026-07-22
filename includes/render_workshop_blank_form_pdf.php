<?php
/**
 * Blank printable PDF for Workshop / Awareness short registration
 * (same fields as student/register_workshop.php).
 *
 * Usage:
 *   require this file after setting optional $workshopCourseName / $workshopCourseCode
 *   OR call outputWorkshopBlankRegistrationPdf($courseName, $courseCode, 'D', $trainingCentre)
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
        ?string $trainingCentre = null
    ): string {
        $trainingCentre = trim((string) $trainingCentre);
        if ($trainingCentre === '') {
            $trainingCentre = 'NIELIT Bhubaneswar';
        }
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('NIELIT Bhubaneswar');
        $pdf->SetAuthor('NIELIT Bhubaneswar');
        $pdf->SetTitle('Workshop Registration Form (Physical)');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(14, 12, 14);
        $pdf->SetAutoPageBreak(true, 14);
        $pdf->AddPage();

        $hindiFont = function_exists('getTcpdfDevanagariFontName') ? getTcpdfDevanagariFontName() : null;
        $hindiTexts = function_exists('getAdmissionFormHindiTexts') ? getAdmissionFormHindiTexts() : [];

        $logoPath = __DIR__ . '/../assets/images/bhubaneswar_logo.png';
        $marginLeft = 14;
        $headerY = 12;
        $logoW = 22;
        $contentRight = 210 - 14;
        $textX = $marginLeft;
        $textW = $contentRight - $marginLeft;
        $logoH = 0;

        if (is_file($logoPath)) {
            $imgSize = @getimagesize($logoPath);
            $logoH = ($imgSize && !empty($imgSize[0])) ? $logoW * ($imgSize[1] / $imgSize[0]) : 20;
            $pdf->Image($logoPath, $marginLeft, $headerY, $logoW, 0, 'PNG');
            $textX = $marginLeft + $logoW + 4;
            $textW = $contentRight - $textX;
        }

        $pdf->SetXY($textX, $headerY);
        if ($hindiFont && !empty($hindiTexts['institute_line'])) {
            $pdf->SetFont($hindiFont, '', 9);
            $pdf->MultiCell($textW, 4.5, $hindiTexts['institute_line'], 0, 'C', false, 1, $textX, $headerY);
        }
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->MultiCell($textW, 4.5, INSTITUTE_NAME_EN, 0, 'C', false, 1, $textX, $pdf->GetY());
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->MultiCell($textW, 4, 'Ministry of Electronics & Information Technology, Government of India', 0, 'C', false, 1, $textX, $pdf->GetY());
        $pdf->SetTextColor(0, 0, 0);

        $headerBottom = max($pdf->GetY(), $headerY + $logoH) + 3;
        $pdf->SetY($headerBottom);
        $pdf->SetDrawColor(40, 80, 140);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(14, $pdf->GetY(), 196, $pdf->GetY());
        $pdf->Ln(4);

        $title = getWorkshopShortFormTitle() . ' — Short Registration Form';
        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->Cell(0, 7, $title, 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->Cell(0, 4, 'Physical / Offline copy — fill by hand (same fields as online workshop registration)', 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(2);

        // Course + photo box
        $y = $pdf->GetY();
        $photoX = 160;
        $photoW = 36;
        $photoH = 42;
        $pdf->Rect($photoX, $y, $photoW, $photoH);
        $pdf->SetXY($photoX, $y + $photoH / 2 - 4);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->MultiCell($photoW, 3.5, "Passport-size\nphoto *\n(paste here)", 0, 'C');

        $leftW = 142;
        $pdf->SetXY(14, $y);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(232, 240, 254);
        $pdf->Cell($leftW, 6, '  Program / Course', 0, 1, 'L', true);
        $pdf->SetFont('helvetica', '', 9);
        workshopPdfLabeledLine($pdf, 'Course name', $courseName ?: '', $leftW);
        workshopPdfLabeledLine($pdf, 'Course code', $courseCode ?: '', $leftW);
        workshopPdfLabeledLine($pdf, 'Training centre', $trainingCentre, $leftW);
        $pdf->SetY(max($pdf->GetY(), $y + $photoH) + 3);

        workshopPdfSection($pdf, '1. Student details');
        workshopPdfLabeledLine($pdf, 'Student full name *');
        workshopPdfTwoCol($pdf, 'Class / Level *', 'Date of birth * (DD/MM/YYYY)');
        workshopPdfTwoCol($pdf, 'Age', 'Gender *  Male / Female / Other');
        workshopPdfLabeledLine($pdf, 'Category   General / OBC / SC / ST / EWS');

        workshopPdfSection($pdf, '2. Parent / guardian & contact');
        workshopPdfTwoCol($pdf, "Father's name", "Mother's name");
        $pdf->SetFont('helvetica', 'I', 7);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 4, 'At least one parent / guardian name is required.', 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);
        workshopPdfTwoCol($pdf, 'Mobile (parent) * 10 digits', 'Email *');

        workshopPdfSection($pdf, '3. Aadhar details (optional)');
        workshopPdfLabeledLine($pdf, 'Aadhar number (student or parent) — 12 digits, optional');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(55, 8, ' Aadhar card copy (optional)', 1, 0);
        $pdf->Cell(127, 8, '  Attach photocopy if available  □ Attached   □ Not available', 1, 1);

        workshopPdfSection($pdf, '4. School & address');
        workshopPdfLabeledLine($pdf, 'School / College name *');
        workshopPdfLabeledLine($pdf, 'Address *', '', 182, 10);
        workshopPdfLabeledLine($pdf, '', '', 182, 8);
        workshopPdfTwoCol($pdf, 'State *', 'City / District *');
        workshopPdfLabeledLine($pdf, 'Pincode * (6 digits)', '', 80);

        workshopPdfSection($pdf, '5. Declaration');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->MultiCell(0, 4, 'I hereby declare that the information given above is true to the best of my knowledge. I understand that this form is for Workshop / Awareness Program short registration and that payment / marksheets / signature / thumb impression are not required.', 0, 'L');
        $pdf->Ln(6);
        workshopPdfTwoCol($pdf, 'Date: ______________', 'Place: ______________');
        $pdf->Ln(10);
        $pdf->Cell(90, 6, '______________________________', 0, 0, 'C');
        $pdf->Cell(92, 6, '______________________________', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(90, 4, 'Signature of Parent / Guardian', 0, 0, 'C');
        $pdf->Cell(92, 4, 'Signature of Student (if applicable)', 0, 1, 'C');

        $pdf->Ln(8);
        $pdf->SetFont('helvetica', 'I', 7);
        $pdf->SetTextColor(110, 110, 110);
        $pdf->MultiCell(0, 3.5, '* Mandatory fields. Aadhar number and Aadhar card are optional. Passport-size photo is mandatory. For office use: enter data into NIELIT workshop registration system after verification.', 0, 'L');
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

if (!function_exists('workshopPdfSection')) {
    function workshopPdfSection(TCPDF $pdf, string $title): void
    {
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(232, 240, 254);
        $pdf->SetDrawColor(180, 200, 230);
        $pdf->Cell(0, 6, '  ' . $title, 1, 1, 'L', true);
        $pdf->Ln(1);
    }
}

if (!function_exists('workshopPdfLabeledLine')) {
    function workshopPdfLabeledLine(TCPDF $pdf, string $label, string $value = '', float $width = 182, float $height = 8): void
    {
        $pdf->SetFont('helvetica', '', 9);
        $labelW = min(55, max(28, $pdf->GetStringWidth($label) + 6));
        if ($label === '') {
            $pdf->Cell($width, $height, ' ' . $value, 1, 1);
            return;
        }
        $pdf->Cell($labelW, $height, ' ' . $label, 1, 0);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell($width - $labelW, $height, ' ' . $value, 1, 1);
        $pdf->SetFont('helvetica', '', 9);
    }
}

if (!function_exists('workshopPdfTwoCol')) {
    function workshopPdfTwoCol(TCPDF $pdf, string $leftLabel, string $rightLabel, float $totalW = 182, float $height = 8): void
    {
        $half = $totalW / 2;
        $pdf->SetFont('helvetica', '', 8);
        $leftLabelW = min(48, max(28, $pdf->GetStringWidth($leftLabel) + 4));
        $rightLabelW = min(48, max(28, $pdf->GetStringWidth($rightLabel) + 4));
        $pdf->Cell($leftLabelW, $height, ' ' . $leftLabel, 1, 0);
        $pdf->Cell($half - $leftLabelW, $height, '', 1, 0);
        $pdf->Cell($rightLabelW, $height, ' ' . $rightLabel, 1, 0);
        $pdf->Cell($half - $rightLabelW, $height, '', 1, 1);
    }
}
