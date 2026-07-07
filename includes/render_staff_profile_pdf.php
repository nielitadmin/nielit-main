<?php
/**
 * PDF renderer for NIELIT Centre staff profile (official form layout).
 */
require_once __DIR__ . '/../libraries/tcpdf/tcpdf.php';

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
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->SetMargins(15, 15, 15);
        $pdf->AddPage();

        $labelW = 70.0;
        $valueW = 110.0;
        $pageW = $labelW + $valueW;

        staffPdfRenderHeader($pdf, $staff, $pageW);

        staffPdfSectionTitle($pdf, 'Basic Information');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Name', $staff['name'] ?? '');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Designation', $staff['designation'] ?? '');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Department/School', $staff['department'] ?? '');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Employment Type (Regular/Contractual/Project/Outsourced)', $staff['employment_type'] ?? '');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Date of Joining NIELIT', staffPdfFormatDate($staff['date_of_joining'] ?? ''));
        staffPdfFormRow($pdf, $labelW, $valueW, 'Official Email', $staff['email'] ?? '');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Mobile Number', $staff['phone'] ?? '');

        $pdf->Ln(4);
        staffPdfSectionTitle($pdf, 'Academic Profile');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Highest Qualification', $staff['highest_qualification'] ?? '');
        staffPdfFormRow($pdf, $labelW, $valueW, 'University/Institute', $staff['university_institute'] ?? '');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Year of Passing', $staff['year_of_passing'] ?? '');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Specialization', $staff['specialization'] ?? '');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Areas of Expertise (Top 5 Keywords)', $staff['areas_of_expertise'] ?? '', 12);
        staffPdfFormRow($pdf, $labelW, $valueW, 'Experience (Years)', $staff['experience_years'] ?? '');

        $pdf->Ln(4);
        staffPdfSectionTitle($pdf, 'Research & Professional Achievements');
        staffPdfFormRow($pdf, $labelW, $valueW, 'Research Interests', $staff['research_interests'] ?? '', 14);
        staffPdfFormRow($pdf, $labelW, $valueW, 'Research Publications (Journals/Papers)', $staff['research_publications'] ?? '', 16);
        staffPdfFormRow($pdf, $labelW, $valueW, 'Books/Book Chapters', $staff['books_chapters'] ?? '', 14);
        staffPdfFormRow($pdf, $labelW, $valueW, 'Patents (Granted/Filed)', $staff['patents'] ?? '', 12);
        staffPdfFormRow($pdf, $labelW, $valueW, 'Sponsored Projects', $staff['sponsored_projects'] ?? '', 14);
        staffPdfFormRow($pdf, $labelW, $valueW, 'Consultancy Projects', $staff['consultancy_projects'] ?? '', 14);
        staffPdfFormRow($pdf, $labelW, $valueW, 'Technology/Product Developed', $staff['technology_developed'] ?? '', 14);
        staffPdfFormRow($pdf, $labelW, $valueW, 'Research Guidance (Ph.D./M.Tech.)', $staff['research_guidance'] ?? '', 14);
        staffPdfFormRow($pdf, $labelW, $valueW, 'Awards & Recognitions', $staff['awards_recognitions'] ?? '', 14);
        staffPdfFormRow($pdf, $labelW, $valueW, 'Professional Memberships', $staff['professional_memberships'] ?? '', 14);

        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $name !== '' ? $name : 'Staff');
        $filename = 'NIELIT_Centre_Staff_Profile_' . $safeName . '.pdf';
        $pdf->Output($filename, 'I');
    }
}

if (!function_exists('staffPdfRenderHeader')) {
    function staffPdfRenderHeader(TCPDF $pdf, array $staff, float $contentW): void
    {
        $startY = $pdf->GetY();
        $photoW = 32.0;
        $photoH = 38.0;
        $photoX = 15.0 + $contentW - $photoW;
        $photoY = $startY;

        $pdf->SetFont('times', 'B', 12);
        $pdf->SetXY(15, $startY);
        $pdf->Cell($contentW - $photoW - 4, 8, 'NIELIT Centre:', 0, 1, 'L');

        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.3);
        $pdf->Rect($photoX, $photoY, $photoW, $photoH, 'D');

        $photoPath = staffPdfResolvePhotoPath($staff);
        if ($photoPath !== null) {
            $pdf->Image($photoPath, $photoX + 1, $photoY + 1, $photoW - 2, $photoH - 2, '', '', '', false, 300);
        } else {
            $pdf->SetFont('times', '', 11);
            $pdf->SetXY($photoX, $photoY + ($photoH / 2) - 3);
            $pdf->Cell($photoW, 6, 'Photo', 0, 0, 'C');
        }

        $pdf->SetY(max($startY + 10, $photoY + $photoH + 4));
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
        $pdf->SetFont('times', 'B', 11);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 7, $title, 0, 1, 'L');
        $pdf->Ln(1);
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
    function staffPdfFormRow(TCPDF $pdf, float $labelW, float $valueW, string $label, $value, float $minH = 8.0): void
    {
        $text = staffPdfFormatValue($value);
        $pdf->SetFont('times', '', 10);
        $lineH = 5.0;
        $valueLines = max(1, $pdf->getNumLines($text !== '' ? $text : ' ', $valueW - 2));
        $labelLines = max(1, $pdf->getNumLines($label, $labelW - 2));
        $rowH = max($minH, $lineH * max($valueLines, $labelLines));

        if ($pdf->GetY() + $rowH > 280) {
            $pdf->AddPage();
        }

        $x = $pdf->GetX();
        $y = $pdf->GetY();

        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.2);
        $pdf->SetFont('times', '', 10);
        $pdf->MultiCell($labelW, $rowH, $label, 1, 'L', false, 0, $x, $y, true, 0, false, true, $rowH, 'M');
        $pdf->MultiCell($valueW, $rowH, $text, 1, 'L', false, 1, $x + $labelW, $y, true, 0, false, true, $rowH, 'M');
    }
}
