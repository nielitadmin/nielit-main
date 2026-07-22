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
        ?string $trainingCentre = null
    ): string {
        $trainingCentre = trim((string) $trainingCentre);
        if ($trainingCentre === '') {
            $trainingCentre = 'NIELIT Bhubaneswar';
        }

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
        $rowH = 8.5;
        $sectionH = 7;
        $gap = 2;

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

        // Course + photo
        $y = $pdf->GetY();
        $photoX = 162;
        $photoW = 36;
        $photoH = 40;
        $pdf->SetDrawColor(100, 100, 100);
        $pdf->SetLineWidth(0.3);
        $pdf->Rect($photoX, $y, $photoW, $photoH);
        $pdf->SetXY($photoX, $y + $photoH / 2 - 5);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->MultiCell($photoW, 3.5, "Passport photo *\n(paste here)", 0, 'C');

        $leftW = 146;
        $pdf->SetXY(12, $y);
        $pdf->SetFont('helvetica', 'B', 9.5);
        $pdf->SetFillColor(232, 240, 254);
        $pdf->SetDrawColor(180, 200, 230);
        $pdf->Cell($leftW, 6.5, '  Program / Course', 1, 1, 'L', true);
        workshopPdfLabeledLine($pdf, 'Course name', $courseName ?: '', $leftW, $rowH);
        workshopPdfLabeledLine($pdf, 'Course code', $courseCode ?: '', $leftW, $rowH);
        workshopPdfLabeledLine($pdf, 'Training centre', $trainingCentre, $leftW, $rowH);
        $pdf->SetY(max($pdf->GetY(), $y + $photoH) + $gap);

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
        workshopPdfLabeledLine($pdf, 'Address *', '', $fullW, 12);
        workshopPdfTwoCol($pdf, 'State *', 'City / District *', $fullW, $rowH);
        workshopPdfLabeledLine($pdf, 'Pincode *', '', 100, $rowH);
        $pdf->Ln($gap);

        workshopPdfSection($pdf, '5. Declaration', $sectionH);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->MultiCell(
            0,
            4,
            'I hereby declare that the information given above is true and correct to the best of my knowledge. I agree to abide by the rules and regulations of NIELIT. I understand that any false or incomplete information may lead to cancellation of my registration.',
            0,
            'L'
        );
        $pdf->Ln(1.5);
        workshopPdfTwoCol($pdf, 'Date', 'Place', $fullW, $rowH);
        $pdf->Ln($gap);

        // Signatures — empty boxes; labels below boxes; footer below labels (no overlap)
        workshopPdfSection($pdf, '6. Signatures', $sectionH);
        $sigHalf = $fullW / 2;
        $labelH = 5.5;
        $footerH = 8;
        $gapAfterBox = 1.5;
        $gapAfterLabel = 1.5;
        $ySig = $pdf->GetY();

        // Reserve space for labels + footer so nothing collides
        $reservedBelow = $gapAfterBox + $labelH + $gapAfterLabel + $footerH;
        $sigBoxH = $contentMaxY - $ySig - $reservedBelow;
        if ($sigBoxH > 26) {
            $sigBoxH = 26;
        }
        if ($sigBoxH < 16) {
            $sigBoxH = 16;
        }

        $x = 12;
        $pdf->SetDrawColor(180, 200, 230);
        $pdf->SetLineWidth(0.35);
        $pdf->Rect($x, $ySig, $sigHalf, $sigBoxH);
        $pdf->Rect($x + $sigHalf, $ySig, $sigHalf, $sigBoxH);

        // 1) Labels directly under the boxes
        $labelY = $ySig + $sigBoxH + $gapAfterBox;
        $pdf->SetXY($x, $labelY);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell($sigHalf, $labelH, 'Signature of Parent / Guardian *', 0, 0, 'C');
        $pdf->Cell($sigHalf, $labelH, 'Signature of Student (if applicable)', 0, 1, 'C');

        // 2) Footer under the labels only (never move upward into labels)
        $footerY = $labelY + $labelH + $gapAfterLabel;
        $pdf->SetXY(12, $footerY);
        $pdf->SetFont('helvetica', 'I', 7);
        $pdf->SetTextColor(110, 110, 110);
        $pdf->MultiCell(
            $fullW,
            3,
            '* Mandatory fields. Aadhar is optional. Passport photo and Parent/Guardian signature are mandatory. For office use: enter details into the NIELIT student system after verification.',
            0,
            'L',
            false,
            1,
            12,
            $footerY,
            true,
            0,
            false,
            true,
            $footerH,
            'T'
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
