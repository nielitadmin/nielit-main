<?php
/**
 * Blank printable PDF for Workshop / Awareness short registration
 * (same fields as student/register_workshop.php) — single full A4 page.
 *
 * Usage:
 *   outputWorkshopBlankRegistrationPdf($courseName, $courseCode, 'D', $trainingCentre)
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
        $pdf->SetMargins(12, 10, 12);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();

        // Full A4 page border
        $pdf->SetDrawColor(80, 80, 80);
        $pdf->SetLineWidth(0.6);
        $pdf->Rect(8, 8, 194, 281, 'D');
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.2);

        $hindiFont = function_exists('getTcpdfDevanagariFontName') ? getTcpdfDevanagariFontName() : null;
        $hindiTexts = function_exists('getAdmissionFormHindiTexts') ? getAdmissionFormHindiTexts() : [];

        // Layout tuned to fill ~A4 usable height (~277mm) on one page
        $fullW = 186;
        $rowH = 8.5;
        $sectionH = 7;
        $gap = 2.2;

        $logoPath = __DIR__ . '/../assets/images/bhubaneswar_logo.png';
        $marginLeft = 12;
        $headerY = 10;
        $logoW = 20;
        $contentRight = 210 - 12;
        $textX = $marginLeft;
        $textW = $contentRight - $marginLeft;
        $logoH = 0;

        if (is_file($logoPath)) {
            $imgSize = @getimagesize($logoPath);
            $logoH = ($imgSize && !empty($imgSize[0])) ? $logoW * ($imgSize[1] / $imgSize[0]) : 18;
            $pdf->Image($logoPath, $marginLeft, $headerY, $logoW, 0, 'PNG');
            $textX = $marginLeft + $logoW + 4;
            $textW = $contentRight - $textX;
        }

        $pdf->SetXY($textX, $headerY);
        if ($hindiFont && !empty($hindiTexts['institute_line'])) {
            $pdf->SetFont($hindiFont, '', 8.5);
            $pdf->MultiCell($textW, 4, $hindiTexts['institute_line'], 0, 'C', false, 1, $textX, $headerY);
        }
        $pdf->SetFont('helvetica', 'B', 8.5);
        $pdf->MultiCell($textW, 4, INSTITUTE_NAME_EN, 0, 'C', false, 1, $textX, $pdf->GetY());
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->MultiCell($textW, 3.5, 'Ministry of Electronics & Information Technology, Government of India', 0, 'C', false, 1, $textX, $pdf->GetY());
        $pdf->SetTextColor(0, 0, 0);

        $headerBottom = max($pdf->GetY(), $headerY + $logoH) + 2;
        $pdf->SetY($headerBottom);
        $pdf->SetDrawColor(40, 80, 140);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(12, $pdf->GetY(), 198, $pdf->GetY());
        $pdf->Ln(3);

        $title = getWorkshopShortFormTitle() . ' — Short Registration Form';
        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->Cell(0, 7, $title, 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->Cell(0, 4, 'Physical / Offline copy — fill by hand (single A4 page)', 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(2);

        // Course + photo box
        $y = $pdf->GetY();
        $photoX = 162;
        $photoW = 36;
        $photoH = 42;
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->Rect($photoX, $y, $photoW, $photoH);
        $pdf->SetXY($photoX, $y + $photoH / 2 - 5);
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->MultiCell($photoW, 3.5, "Passport-size\nphoto *\n(paste here)", 0, 'C');

        $leftW = 146;
        $pdf->SetXY(12, $y);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(232, 240, 254);
        $pdf->Cell($leftW, 6.5, '  Program / Course', 0, 1, 'L', true);
        workshopPdfLabeledLine($pdf, 'Course name', $courseName ?: '', $leftW, $rowH);
        workshopPdfLabeledLine($pdf, 'Course code', $courseCode ?: '', $leftW, $rowH);
        workshopPdfLabeledLine($pdf, 'Training centre', $trainingCentre, $leftW, $rowH);
        $pdf->SetY(max($pdf->GetY(), $y + $photoH) + $gap);

        workshopPdfSection($pdf, '1. Student details', $sectionH);
        workshopPdfLabeledLine($pdf, 'Student full name *', '', $fullW, $rowH);
        workshopPdfTwoCol($pdf, 'Class / Level *', 'Date of birth * (DD/MM/YYYY)', $fullW, $rowH);
        workshopPdfTwoCol($pdf, 'Age', 'Gender *  Male / Female / Other', $fullW, $rowH);
        workshopPdfLabeledLine($pdf, 'Category  Gen / OBC / SC / ST / EWS', '', $fullW, $rowH);
        $pdf->Ln($gap);

        workshopPdfSection($pdf, '2. Parent / guardian & contact', $sectionH);
        workshopPdfTwoCol($pdf, "Father's name", "Mother's name", $fullW, $rowH);
        $pdf->SetFont('helvetica', 'I', 7);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 4, 'At least one parent / guardian name is required.', 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);
        workshopPdfTwoCol($pdf, 'Mobile (parent) * 10 digits', 'Email *', $fullW, $rowH);
        $pdf->Ln($gap);

        workshopPdfSection($pdf, '3. Aadhar details (optional)', $sectionH);
        $pdf->SetFont('helvetica', '', 8);
        $aadharLabelW = 52;
        $boxH = 10;
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
        $pdf->SetFont('helvetica', 'I', 7);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 4, 'Write one digit in each box (student or parent). Leave blank if not available.', 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(52, $rowH, ' Aadhar card copy', 1, 0);
        $pdf->Cell($fullW - 52, $rowH, '  [ ] Attached (photocopy)          [ ] Not available', 1, 1);
        $pdf->Ln($gap);

        workshopPdfSection($pdf, '4. School & address', $sectionH);
        workshopPdfLabeledLine($pdf, 'School / College name *', '', $fullW, $rowH);
        workshopPdfLabeledLine($pdf, 'Address *', '', $fullW, 12);
        workshopPdfTwoCol($pdf, 'State *', 'City / District *', $fullW, $rowH);
        workshopPdfLabeledLine($pdf, 'Pincode * (6 digits)', '', 100, $rowH);
        $pdf->Ln($gap);

        workshopPdfSection($pdf, '5. Declaration', $sectionH);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->MultiCell(0, 3.8, 'I hereby declare that the information given above is true to the best of my knowledge. This form is for Workshop / Awareness Program short registration.', 0, 'L');
        $pdf->Ln(2);
        workshopPdfTwoCol($pdf, 'Date', 'Place', $fullW, $rowH);
        $pdf->Ln($gap);

        // Clear signature section with boxes (easy to find on physical print)
        workshopPdfSection($pdf, '6. Signatures', $sectionH);
        $sigBoxH = 28;
        $sigHalf = $fullW / 2;
        $sigX = $pdf->GetX();
        $sigY = $pdf->GetY();
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->Rect($sigX, $sigY, $sigHalf, $sigBoxH);
        $pdf->Rect($sigX + $sigHalf, $sigY, $sigHalf, $sigBoxH);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetXY($sigX, $sigY + 2);
        $pdf->Cell($sigHalf, 4, ' Sign here', 0, 0, 'L');
        $pdf->Cell($sigHalf, 4, ' Sign here', 0, 1, 'L');
        $pdf->SetY($sigY + $sigBoxH - 8);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($sigHalf, 5, 'Signature of Parent / Guardian *', 0, 0, 'C');
        $pdf->Cell($sigHalf, 5, 'Signature of Student (if applicable)', 0, 1, 'C');
        $pdf->SetY($sigY + $sigBoxH + 3);

        $pdf->SetFont('helvetica', 'I', 6.5);
        $pdf->SetTextColor(110, 110, 110);
        $pdf->MultiCell(0, 3, '* Mandatory fields. Aadhar number and Aadhar card are optional. Passport-size photo and Parent/Guardian signature are mandatory on this physical form. For office use: enter data into NIELIT workshop registration system after verification.', 0, 'L');
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
    function workshopPdfSection(TCPDF $pdf, string $title, float $height = 7): void
    {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(232, 240, 254);
        $pdf->SetDrawColor(180, 200, 230);
        $pdf->Cell(0, $height, '  ' . $title, 1, 1, 'L', true);
    }
}

if (!function_exists('workshopPdfLabeledLine')) {
    function workshopPdfLabeledLine(TCPDF $pdf, string $label, string $value = '', float $width = 186, float $height = 8.5): void
    {
        $pdf->SetFont('helvetica', '', 8);
        if ($label === '') {
            $pdf->Cell($width, $height, ' ' . $value, 1, 1);
            return;
        }
        $labelW = min(55, max(28, min(55, $pdf->GetStringWidth($label) + 4)));
        $pdf->Cell($labelW, $height, ' ' . $label, 1, 0, '', false, '', 1);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell($width - $labelW, $height, ' ' . $value, 1, 1);
        $pdf->SetFont('helvetica', '', 8);
    }
}

if (!function_exists('workshopPdfTwoCol')) {
    function workshopPdfTwoCol(TCPDF $pdf, string $leftLabel, string $rightLabel, float $totalW = 186, float $height = 8.5): void
    {
        $half = $totalW / 2;
        $pdf->SetFont('helvetica', '', 8);
        $leftLabelW = min(48, max(24, $pdf->GetStringWidth($leftLabel) + 4));
        $rightLabelW = min(48, max(24, $pdf->GetStringWidth($rightLabel) + 4));
        $pdf->Cell($leftLabelW, $height, ' ' . $leftLabel, 1, 0, '', false, '', 1);
        $pdf->Cell($half - $leftLabelW, $height, '', 1, 0);
        $pdf->Cell($rightLabelW, $height, ' ' . $rightLabel, 1, 0, '', false, '', 1);
        $pdf->Cell($half - $rightLabelW, $height, '', 1, 1);
    }
}
