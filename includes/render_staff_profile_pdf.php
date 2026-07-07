<?php
/**
 * PDF renderer for NIELIT Centre staff profile (official single-page form).
 */
require_once __DIR__ . '/../libraries/tcpdf/tcpdf.php';
require_once __DIR__ . '/tcpdf_devanagari_font.php';

if (!defined('INSTITUTE_NAME_EN')) {
    require_once __DIR__ . '/institute_branding.php';
}

if (!function_exists('renderStaffProfilePdf')) {
    function renderStaffProfilePdf(array $staff): void
    {
        require_once __DIR__ . '/staff_profile_helper.php';

        $name = trim((string) ($staff['name'] ?? ''));

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('NIELIT Management System');
        $pdf->SetAuthor('NIELIT Bhubaneswar');
        $pdf->SetTitle('NIELIT Centre Staff Profile - ' . ($name !== '' ? $name : 'Staff'));
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();

        $labelW = 72.0;
        $valueW = 108.0;
        $contentW = $labelW + $valueW;

        $headerBottom = staffPdfRenderLetterhead($pdf, $contentW);
        $pdf->SetY($headerBottom + 2);
        staffPdfRenderCentrePhotoRow($pdf, $staff, $contentW);

        staffPdfSectionTitle($pdf, 'Basic Information');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Name', $staff['name'] ?? '');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Designation', $staff['designation'] ?? '');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Department/School', $staff['department'] ?? '');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Employment Type (Regular/Contractual/Project/Outsourced)', $staff['employment_type'] ?? '');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Date of Joining NIELIT', staffPdfFormatDate($staff['date_of_joining'] ?? ''));
        staffPdfFormRow($pdf, $labelW, $valueW, 'Official Email', $staff['email'] ?? '');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Mobile Number', $staff['phone'] ?? '');

        staffPdfSectionTitle($pdf, 'Academic Profile');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Highest Qualification', $staff['highest_qualification'] ?? '');
        staffPdfFormRow($pdf, $labelW, $valueW, 'University/Institute', $staff['university_institute'] ?? '');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Year of Passing', $staff['year_of_passing'] ?? '');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Specialization', $staff['specialization'] ?? '');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Areas of Expertise (Top 5 Keywords)', $staff['areas_of_expertise'] ?? '');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Experience (Years)', $staff['experience_years'] ?? '');

        staffPdfSectionTitle($pdf, 'Research & Professional Achievements');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Research Interests', $staff['research_interests'] ?? '');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Research Publications (Journals/Papers)', $staff['research_publications'] ?? '');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Books/Book Chapters', $staff['books_chapters'] ?? '');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Patents (Granted/Filed)', $staff['patents'] ?? '');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Sponsored Projects', $staff['sponsored_projects'] ?? '');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Consultancy Projects', $staff['consultancy_projects'] ?? '');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Technology/Product Developed', $staff['technology_developed'] ?? '');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Research Guidance (Ph.D./M.Tech.)', $staff['research_guidance'] ?? '');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Awards & Recognitions', $staff['awards_recognitions'] ?? '');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Professional Memberships', $staff['professional_memberships'] ?? '');

        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $name !== '' ? $name : 'Staff');
        $filename = 'NIELIT_Centre_Staff_Profile_' . $safeName . '.pdf';
        $pdf->Output($filename, 'I');
    }
}

if (!function_exists('staffPdfRenderLetterhead')) {
    function staffPdfRenderLetterhead(TCPDF $pdf, float $contentW): float
    {
        $marginLeft = 10.0;
        $startY = 10.0;
        $pad = 2.0;
        $logoPath = __DIR__ . '/../assets/images/bhubaneswar_logo.png';
        $logoW = 22.0;
        $logoH = 0.0;
        $textX = $marginLeft + $pad;
        $textW = $contentW - (2 * $pad);

        if (is_file($logoPath)) {
            $imgSize = @getimagesize($logoPath);
            $logoH = ($imgSize && !empty($imgSize[0]))
                ? $logoW * ($imgSize[1] / $imgSize[0])
                : 18.0;
            $pdf->Image($logoPath, $marginLeft + $pad, $startY + $pad, $logoW, 0, 'PNG');
            $textX = $marginLeft + $pad + $logoW + 3;
            $textW = $contentW - ($textX - $marginLeft) - $pad;
        }

        $hindiFont = getTcpdfDevanagariFontName();
        $textY = $startY + $pad;
        if ($hindiFont) {
            $pdf->SetFont($hindiFont, '', 8.5);
            $pdf->SetXY($textX, $textY);
            $pdf->MultiCell($textW, 4, INSTITUTE_NAME_HI, 0, 'C', false, 1, $textX, $textY);
            $textY = $pdf->GetY();
        }

        $pdf->SetFont('helvetica', 'B', 8.5);
        $pdf->SetXY($textX, $textY);
        $pdf->MultiCell($textW, 4, INSTITUTE_NAME_EN, 0, 'C', false, 1, $textX, $textY);

        $innerBottom = max($pdf->GetY(), $startY + $pad + $logoH) + $pad;
        $boxH = $innerBottom - $startY;

        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.35);
        $pdf->Rect($marginLeft, $startY, $contentW, $boxH, 'D');

        return $startY + $boxH;
    }
}

if (!function_exists('staffPdfRenderCentrePhotoRow')) {
    function staffPdfRenderCentrePhotoRow(TCPDF $pdf, array $staff, float $contentW): void
    {
        $startY = $pdf->GetY();
        $photoW = 28.0;
        $photoH = 32.0;
        $photoX = 10.0 + $contentW - $photoW;
        $textW = $contentW - $photoW - 3;

        $pdf->SetFont('times', 'B', 11);
        $pdf->SetXY(10, $startY + 2);
        $pdf->Cell($textW, 6, 'NIELIT Centre:', 0, 0, 'L');

        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.25);
        $pdf->Rect($photoX, $startY, $photoW, $photoH, 'D');

        $photoPath = staffPdfResolvePhotoPath($staff);
        if ($photoPath !== null) {
            $pdf->Image($photoPath, $photoX + 0.5, $startY + 0.5, $photoW - 1, $photoH - 1, '', '', '', false, 300);
        } else {
            $pdf->SetFont('times', '', 9);
            $pdf->SetXY($photoX, $startY + ($photoH / 2) - 2.5);
            $pdf->Cell($photoW, 5, 'Photo', 0, 0, 'C');
        }

        $pdf->SetY($startY + $photoH + 2);
    }
}

if (!function_exists('staffPdfResolvePhotoPath')) {
    function staffPdfResolvePhotoPath(array $staff): ?string
    {
        $relative = trim((string) ($staff['profile_photo'] ?? ''));
        if ($relative === '') {
            return null;
        }

        $candidates = [
            __DIR__ . '/../' . ltrim($relative, '/'),
            __DIR__ . '/../uploads/staff_photos/' . basename($relative),
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}

if (!function_exists('staffPdfSectionTitle')) {
    function staffPdfSectionTitle(TCPDF $pdf, string $title): void
    {
        $pdf->Ln(1.5);
        $pdf->SetFont('times', 'B', 9.5);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 4.5, $title, 0, 1, 'L');
    }
}

if (!function_exists('staffPdfFormatValue')) {
    function staffPdfFormatValue($value): string
    {
        if ($value === null) {
            return '';
        }
        return trim((string) $value);
    }
}

if (!function_exists('staffPdfFormatDate')) {
    function staffPdfFormatDate($value): string
    {
        if ($value === null || trim((string) $value) === '') {
            return '';
        }
        $ts = strtotime((string) $value);
        return $ts ? date('d-m-Y', $ts) : trim((string) $value);
    }
}

if (!function_exists('staffPdfFormRow')) {
    function staffPdfFormRow(TCPDF $pdf, float $labelW, float $valueW, string $label, $value, float $minH = 5.5): void
    {
        $text = staffPdfFormatValue($value);
        $pdf->SetFont('times', '', 8);
        $lineH = 3.8;
        $valueLines = ($text !== '') ? max(1, $pdf->getNumLines($text, $valueW - 1.5)) : 1;
        $labelLines = max(1, $pdf->getNumLines($label, $labelW - 1.5));
        $rowH = max($minH, $lineH * max($valueLines, $labelLines));

        $x = 10.0;
        $y = $pdf->GetY();

        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.2);
        $pdf->SetFont('times', '', 8);
        $pdf->MultiCell($labelW, $rowH, $label, 1, 'L', false, 0, $x, $y, true, 0, false, true, $rowH, 'M');
        $pdf->MultiCell($valueW, $rowH, $text, 1, 'L', false, 1, $x + $labelW, $y, true, 0, false, true, $rowH, 'M');
    }
}
