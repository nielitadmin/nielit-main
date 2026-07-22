<?php
/**
 * Blank printable PDF for Workshop / Awareness short registration
 * (same fields as student/register_workshop.php) — single A4 page only.
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
        $pdf->SetMargins(12, 8, 12);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();

        $hindiFont = function_exists('getTcpdfDevanagariFontName') ? getTcpdfDevanagariFontName() : null;
        $hindiTexts = function_exists('getAdmissionFormHindiTexts') ? getAdmissionFormHindiTexts() : [];

        $logoPath = __DIR__ . '/../assets/images/bhubaneswar_logo.png';
        $marginLeft = 12;
        $headerY = 8;
        $logoW = 16;
        $contentRight = 210 - 12;
        $textX = $marginLeft;
        $textW = $contentRight - $marginLeft;
        $logoH = 0;
        $rowH = 6.2;
        $fullW = 186;

        if (is_file($logoPath)) {
            $imgSize = @getimagesize($logoPath);
            $logoH = ($imgSize && !empty($imgSize[0])) ? $logoW * ($imgSize[1] / $imgSize[0]) : 15;
            $pdf->Image($logoPath, $marginLeft, $headerY, $logoW, 0, 'PNG');
            $textX = $marginLeft + $logoW + 3;
            $textW = $contentRight - $textX;
        }

        $pdf->SetXY($textX, $headerY);
        if ($hindiFont && !empty($hindiTexts['institute_line'])) {
            $pdf->SetFont($hindiFont, '', 7.5);
            $pdf->MultiCell($textW, 3.5, $hindiTexts['institute_line'], 0, 'C', false, 1, $textX, $headerY);
        }
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->MultiCell($textW, 3.5, INSTITUTE_NAME_EN, 0, 'C', false, 1, $textX, $pdf->GetY());
        $pdf->SetFont('helvetica', '', 6.5);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->MultiCell($textW, 3, 'Ministry of Electronics & Information Technology, Government of India', 0, 'C', false, 1, $textX, $pdf->GetY());
        $pdf->SetTextColor(0, 0, 0);

        $headerBottom = max($pdf->GetY(), $headerY + $logoH) + 1.5;
        $pdf->SetY($headerBottom);
        $pdf->SetDrawColor(40, 80, 140);
        $pdf->SetLineWidth(0.4);
        $pdf->Line(12, $pdf->GetY(), 198, $pdf->GetY());
        $pdf->Ln(2);

        $title = getWorkshopShortFormTitle() . ' — Short Registration Form';
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 5, $title, 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 6.5);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->Cell(0, 3, 'Physical / Offline copy — fill by hand (1 page)', 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(1);

        // Course + photo box
        $y = $pdf->GetY();
        $photoX = 168;
        $photoW = 30;
        $photoH = 34;
        $pdf->Rect($photoX, $y, $photoW, $photoH);
        $pdf->SetXY($photoX, $y + $photoH / 2 - 4);
        $pdf->SetFont('helvetica', '', 6);
        $pdf->MultiCell($photoW, 3, "Passport photo *\n(paste here)", 0, 'C');

        $leftW = 152;
        $pdf->SetXY(12, $y);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(232, 240, 254);
        $pdf->Cell($leftW, 5, '  Program / Course', 0, 1, 'L', true);
        workshopPdfLabeledLine($pdf, 'Course name', $courseName ?: '', $leftW, $rowH);
        workshopPdfLabeledLine($pdf, 'Course code', $courseCode ?: '', $leftW, $rowH);
        workshopPdfLabeledLine($pdf, 'Training centre', $trainingCentre, $leftW, $rowH);
        $pdf->SetY(max($pdf->GetY(), $y + $photoH) + 1.5);

        workshopPdfSection($pdf, '1. Student details');
        workshopPdfLabeledLine($pdf, 'Student full name *', '', $fullW, $rowH);
        workshopPdfTwoCol($pdf, 'Class / Level *', 'DOB * (DD/MM/YYYY)', $fullW, $rowH);
        workshopPdfTwoCol($pdf, 'Age', 'Gender *  M / F / Other', $fullW, $rowH);
        workshopPdfLabeledLine($pdf, 'Category  Gen / OBC / SC / ST / EWS', '', $fullW, $rowH);

        workshopPdfSection($pdf, '2. Parent / guardian & contact');
        workshopPdfTwoCol($pdf, "Father's name", "Mother's name", $fullW, $rowH);
        $pdf->SetFont('helvetica', 'I', 6);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 3, 'At least one parent / guardian name is required.', 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);
        workshopPdfTwoCol($pdf, 'Mobile (parent) *', 'Email *', $fullW, $rowH);

        workshopPdfSection($pdf, '3. Aadhar details (optional)');
        $pdf->SetFont('helvetica', '', 7);
        $aadharLabelW = 48;
        $boxH = 7;
        $pdf->Cell($aadharLabelW, $boxH, ' Aadhar no. (optional)', 1, 0);
        $digitAreaW = $fullW - $aadharLabelW;
        $digitBoxW = $digitAreaW / 12;
        $startX = $pdf->GetX();
        $startY = $pdf->GetY();
        for ($i = 0; $i < 12; $i++) {
            $pdf->Rect($startX + ($i * $digitBoxW), $startY, $digitBoxW, $boxH);
        }
        $pdf->SetXY($startX + $digitAreaW, $startY);
        $pdf->Ln($boxH);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->Cell(48, $rowH, ' Aadhar card copy', 1, 0);
        $pdf->Cell($fullW - 48, $rowH, '  [ ] Attached (photocopy)     [ ] Not available', 1, 1);

        workshopPdfSection($pdf, '4. School & address');
        workshopPdfLabeledLine($pdf, 'School / College name *', '', $fullW, $rowH);
        workshopPdfLabeledLine($pdf, 'Address *', '', $fullW, 9);
        workshopPdfTwoCol($pdf, 'State *', 'City / District *', $fullW, $rowH);
        workshopPdfLabeledLine($pdf, 'Pincode *', '', 90, $rowH);

        workshopPdfSection($pdf, '5. Declaration');
        $pdf->SetFont('helvetica', '', 6.5);
        $pdf->MultiCell(0, 3.2, 'I hereby declare that the information given above is true to the best of my knowledge. This form is for Workshop / Awareness short registration; payment / marksheets / signature / thumb impression are not required online.', 0, 'L');
        $pdf->Ln(1);
        workshopPdfTwoCol($pdf, 'Date', 'Place', $fullW, $rowH);
        $pdf->Ln(4);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->Cell(93, 4, '____________________________', 0, 0, 'C');
        $pdf->Cell(93, 4, '____________________________', 0, 1, 'C');
        $pdf->Cell(93, 3.5, 'Signature of Parent / Guardian', 0, 0, 'C');
        $pdf->Cell(93, 3.5, 'Signature of Student (if applicable)', 0, 1, 'C');

        $pdf->Ln(2);
        $pdf->SetFont('helvetica', 'I', 6);
        $pdf->SetTextColor(110, 110, 110);
        $pdf->MultiCell(0, 2.8, '* Mandatory. Aadhar optional. Passport photo mandatory. Office use: enter into NIELIT workshop registration system after verification.', 0, 'L');
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
        $pdf->Ln(1);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(232, 240, 254);
        $pdf->SetDrawColor(180, 200, 230);
        $pdf->Cell(0, 5, '  ' . $title, 1, 1, 'L', true);
    }
}

if (!function_exists('workshopPdfLabeledLine')) {
    function workshopPdfLabeledLine(TCPDF $pdf, string $label, string $value = '', float $width = 186, float $height = 6.2): void
    {
        $pdf->SetFont('helvetica', '', 7);
        if ($label === '') {
            $pdf->Cell($width, $height, ' ' . $value, 1, 1);
            return;
        }
        $labelW = min(50, max(24, min(50, $pdf->GetStringWidth($label) + 3)));
        $pdf->Cell($labelW, $height, ' ' . $label, 1, 0, '', false, '', 1);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($width - $labelW, $height, ' ' . $value, 1, 1);
        $pdf->SetFont('helvetica', '', 7);
    }
}

if (!function_exists('workshopPdfTwoCol')) {
    function workshopPdfTwoCol(TCPDF $pdf, string $leftLabel, string $rightLabel, float $totalW = 186, float $height = 6.2): void
    {
        $half = $totalW / 2;
        $pdf->SetFont('helvetica', '', 7);
        $leftLabelW = min(42, max(22, $pdf->GetStringWidth($leftLabel) + 3));
        $rightLabelW = min(42, max(22, $pdf->GetStringWidth($rightLabel) + 3));
        $pdf->Cell($leftLabelW, $height, ' ' . $leftLabel, 1, 0, '', false, '', 1);
        $pdf->Cell($half - $leftLabelW, $height, '', 1, 0);
        $pdf->Cell($rightLabelW, $height, ' ' . $rightLabel, 1, 0, '', false, '', 1);
        $pdf->Cell($half - $rightLabelW, $height, '', 1, 1);
    }
}
