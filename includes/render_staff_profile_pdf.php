<?php
/**
 * PDF renderer for NIELIT Centre staff profile.
 */
require_once __DIR__ . '/../libraries/tcpdf/tcpdf.php';

if (!defined('INSTITUTE_NAME_EN')) {
    require_once __DIR__ . '/institute_branding.php';
}

if (!function_exists('renderStaffProfilePdf')) {
    function renderStaffProfilePdf(array $staff): void
    {
        require_once __DIR__ . '/staff_profile_helper.php';

        $centre = defined('INSTITUTE_NAME_EN') ? INSTITUTE_NAME_EN : 'NIELIT Bhubaneswar';
        $name = trim((string) ($staff['name'] ?? 'Staff Member'));

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('NIELIT Management System');
        $pdf->SetAuthor($centre);
        $pdf->SetTitle('Staff Profile - ' . $name);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->SetMargins(15, 15, 15);
        $pdf->AddPage();

        $pdf->SetDrawColor(100, 100, 100);
        $pdf->SetLineWidth(0.4);
        $pdf->Rect(10, 10, 190, 277, 'D');

        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor(30, 58, 95);
        $pdf->Cell(0, 8, strtoupper($centre), 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(0, 5, 'NIELIT Centre — Staff Profile', 0, 1, 'C');
        $pdf->Ln(2);

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 8, $name, 0, 1, 'C');
        if (!empty($staff['designation'])) {
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(0, 5, $staff['designation'], 0, 1, 'C');
        }
        if (!empty($staff['staff_category'])) {
            $pdf->SetFont('helvetica', 'I', 9);
            $pdf->Cell(0, 5, 'Category: ' . $staff['staff_category'], 0, 1, 'C');
        }
        $pdf->Ln(4);

        $groups = staffProfileFieldGroups();
        unset($groups['basic']['fields']['staff_category']);

        foreach ($groups as $groupKey => $group) {
            staffPdfSectionHeader($pdf, $group['title']);

            if ($groupKey === 'basic') {
                staffPdfTwoColumnRow($pdf, 'Name', $staff['name'] ?? '', 'Designation', $staff['designation'] ?? '');
                staffPdfTwoColumnRow($pdf, 'Department / School', $staff['department'] ?? '', 'Employment Type', $staff['employment_type'] ?? '');
                staffPdfTwoColumnRow($pdf, 'Date of Joining NIELIT', staffPdfFormatDate($staff['date_of_joining'] ?? ''), 'Experience (Years)', staffPdfDisplay($staff['experience_years'] ?? ''));
                staffPdfTwoColumnRow($pdf, 'Official Email', $staff['email'] ?? '', 'Mobile Number', $staff['phone'] ?? '');
            } elseif ($groupKey === 'academic') {
                staffPdfTwoColumnRow($pdf, 'Highest Qualification', $staff['highest_qualification'] ?? '', 'University / Institute', $staff['university_institute'] ?? '');
                staffPdfTwoColumnRow($pdf, 'Year of Passing', $staff['year_of_passing'] ?? '', 'Specialization', $staff['specialization'] ?? '');
                staffPdfFullRow($pdf, 'Areas of Expertise (Top 5 Keywords)', $staff['areas_of_expertise'] ?? '');
            } else {
                $researchFields = [
                    'Research Interests' => 'research_interests',
                    'Research Publications (Journals / Papers)' => 'research_publications',
                    'Books / Book Chapters' => 'books_chapters',
                    'Patents (Granted / Filed)' => 'patents',
                    'Sponsored Projects' => 'sponsored_projects',
                    'Consultancy Projects' => 'consultancy_projects',
                    'Technology / Product Developed' => 'technology_developed',
                    'Research Guidance (Ph.D. / M.Tech.)' => 'research_guidance',
                    'Awards & Recognitions' => 'awards_recognitions',
                    'Professional Memberships' => 'professional_memberships',
                ];
                foreach ($researchFields as $label => $col) {
                    staffPdfFullRow($pdf, $label, $staff[$col] ?? '');
                }
            }
            $pdf->Ln(2);
        }

        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->SetTextColor(120, 120, 120);
        $updated = !empty($staff['profile_updated_at']) ? date('d M Y, h:i A', strtotime($staff['profile_updated_at'])) : date('d M Y, h:i A');
        $pdf->Cell(0, 6, 'Generated on ' . date('d M Y, h:i A') . ' | Profile last updated: ' . $updated, 0, 1, 'C');

        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $name);
        $filename = 'NIELIT_Staff_Profile_' . $safeName . '.pdf';
        $pdf->Output($filename, 'I');
    }
}

if (!function_exists('staffPdfSectionHeader')) {
    function staffPdfSectionHeader(TCPDF $pdf, string $title): void
    {
        $pdf->SetFillColor(30, 58, 95);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 7, '  ' . $title, 0, 1, 'L', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(1);
    }
}

if (!function_exists('staffPdfDisplay')) {
    function staffPdfDisplay($value): string
    {
        if ($value === null || trim((string) $value) === '') {
            return '—';
        }
        return trim((string) $value);
    }
}

if (!function_exists('staffPdfFormatDate')) {
    function staffPdfFormatDate($value): string
    {
        if (empty($value)) {
            return '—';
        }
        $ts = strtotime((string) $value);
        return $ts ? date('d-m-Y', $ts) : (string) $value;
    }
}

if (!function_exists('staffPdfTwoColumnRow')) {
    function staffPdfTwoColumnRow(TCPDF $pdf, string $label1, $value1, string $label2, $value2): void
    {
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(45, 5, $label1, 1, 0, 'L');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(50, 5, ' ' . staffPdfDisplay($value1), 1, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(45, 5, $label2, 1, 0, 'L');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(45, 5, ' ' . staffPdfDisplay($value2), 1, 1, 'L');
    }
}

if (!function_exists('staffPdfFullRow')) {
    function staffPdfFullRow(TCPDF $pdf, string $label, $value): void
    {
        $text = staffPdfDisplay($value);
        if ($text === '—') {
            return;
        }

        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(0, 5, $label, 1, 1, 'L');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->MultiCell(0, 5, $text, 1, 'L', false, 1);
        $pdf->Ln(1);
    }
}
