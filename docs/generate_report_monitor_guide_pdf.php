<?php
/**
 * Generate PDF: Admin Report Monitor — Complete Guide
 * CLI:  php docs/generate_report_monitor_guide_pdf.php
 * Web:  https://yoursite/docs/generate_report_monitor_guide_pdf.php
 */

require_once __DIR__ . '/../libraries/tcpdf/tcpdf.php';

$outputFile = __DIR__ . '/NIELIT_Report_Monitor_Complete_Guide.pdf';

class ReportMonitorGuidePDF extends TCPDF
{
    public function Header()
    {
        $this->SetFont('helvetica', 'B', 10);
        $this->SetTextColor(26, 38, 64);
        $this->Cell(0, 8, 'NIELIT Bhubaneswar — Report Monitor Guide', 0, false, 'L');
        $this->SetFont('helvetica', '', 8);
        $this->Cell(0, 8, date('d M Y'), 0, false, 'R');
        $this->Ln(10);
        $this->SetDrawColor(26, 86, 219);
        $this->SetLineWidth(0.4);
        $this->Line(15, 18, 195, 18);
    }

    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, false, 'C');
    }

    public function coverPage(): void
    {
        $this->SetFont('helvetica', 'B', 22);
        $this->SetTextColor(10, 22, 40);
        $this->Ln(35);
        $this->Cell(0, 12, 'Report Monitor', 0, 1, 'C');
        $this->SetFont('helvetica', '', 14);
        $this->SetTextColor(71, 85, 105);
        $this->Cell(0, 10, 'Complete Technical & Admin Guide', 0, 1, 'C');
        $this->Ln(8);
        $this->SetFont('helvetica', '', 11);
        $this->MultiCell(0, 6, "National Institute of Electronics & Information Technology\nBhubaneswar", 0, 'C');
        $this->Ln(20);
        $this->SetFont('helvetica', '', 10);
        $this->SetTextColor(100, 116, 139);
        $this->MultiCell(0, 6, "Document covers: access control, linked pages, database tables, data flow,\nreport sections, filters, and production URLs.", 0, 'C');
    }

    public function sectionTitle(string $title): void
    {
        $this->Ln(2);
        $this->SetFont('helvetica', 'B', 13);
        $this->SetTextColor(10, 22, 40);
        $this->Cell(0, 9, $title, 0, 1, 'L');
        $this->SetDrawColor(26, 86, 219);
        $this->Line(15, $this->GetY(), 90, $this->GetY());
        $this->Ln(4);
    }

    public function subTitle(string $title): void
    {
        $this->Ln(1);
        $this->SetFont('helvetica', 'B', 10);
        $this->SetTextColor(30, 41, 59);
        $this->Cell(0, 7, $title, 0, 1, 'L');
        $this->Ln(1);
    }

    public function bodyText(string $text): void
    {
        $this->SetFont('helvetica', '', 9);
        $this->SetTextColor(51, 65, 85);
        $this->MultiCell(0, 5, $text, 0, 'L');
        $this->Ln(2);
    }

    public function bulletList(array $items): void
    {
        $this->SetFont('helvetica', '', 9);
        $this->SetTextColor(51, 65, 85);
        foreach ($items as $item) {
            $this->MultiCell(0, 5, '• ' . $item, 0, 'L');
        }
        $this->Ln(2);
    }

    public function tableBlock(array $headers, array $rows, array $widths = [55, 125]): void
    {
        $this->SetFont('helvetica', 'B', 8);
        $this->SetFillColor(248, 250, 252);
        $this->SetTextColor(30, 41, 59);
        foreach ($headers as $i => $h) {
            $w = $widths[$i] ?? 40;
            $this->Cell($w, 7, $h, 1, 0, 'L', true);
        }
        $this->Ln();
        $this->SetFont('helvetica', '', 8);
        $fill = false;
        foreach ($rows as $row) {
            $rowHeights = [];
            foreach ($row as $i => $cell) {
                $w = $widths[$i] ?? 40;
                $rowHeights[] = max(7, $this->getStringHeight($w, (string) $cell) + 2);
            }
            $h = max($rowHeights);
            $startX = $this->GetX();
            $startY = $this->GetY();
            foreach ($row as $i => $cell) {
                $w = $widths[$i] ?? 40;
                $this->SetXY($startX, $startY);
                if ($i < count($row) - 1) {
                    $this->MultiCell($w, $h, (string) $cell, 1, 'L', $fill, 0);
                } else {
                    $this->MultiCell($w, $h, (string) $cell, 1, 'L', $fill, 1);
                }
                $startX += $w;
            }
            $this->SetY($startY + $h);
            $fill = !$fill;
        }
        $this->Ln(3);
    }
}

$pdf = new ReportMonitorGuidePDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('NIELIT Bhubaneswar');
$pdf->SetAuthor('NIELIT Admin System');
$pdf->SetTitle('Report Monitor Complete Guide');
$pdf->SetMargins(15, 25, 15);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();
$pdf->coverPage();

$pdf->AddPage();
$pdf->sectionTitle('1. Overview');
$pdf->bodyText(
    'Report Monitor is a read-only analytics dashboard for Master Admin. It combines statistics from courses, student registrations, batches, training centres, and faculty assignments in one place. It does not create, edit, or delete any records.'
);

$pdf->subTitle('Production URLs');
$pdf->bulletList([
    'https://nielitbhubaneswar.in/admin/report_monitor',
    'https://nielitbhubaneswar.in/admin/report_monitor.php',
    'Local (XAMPP): http://localhost/public_html/admin/report_monitor',
]);

$pdf->subTitle('Purpose');
$pdf->bulletList([
    'Monitor total applications, pending approvals, and batch enrollments',
    'View centre-wise and category-wise breakdowns',
    'Track monthly batch and application trends (charts)',
    'Review faculty training assignments across batches',
    'Inspect batch fill rates and coordinator details',
]);

$pdf->sectionTitle('2. Access Control');
$pdf->tableBlock(['Role', 'Access'], [
    ['master_admin', 'Full access — only role that can open Report Monitor'],
    ['course_coordinator', 'Denied — redirected to dashboard with error message'],
    ['front_office_desk', 'Denied'],
    ['nsqf_course_manager', 'Denied'],
    ['placement_coordinator', 'Denied'],
]);

$pdf->bodyText('Access flow:');
$pdf->bulletList([
    'Not logged in → redirect to admin/login.php',
    'Logged in but not master_admin → redirect to admin/dashboard.php with "Access denied" message',
    'master_admin → page loads normally',
]);

$pdf->sectionTitle('3. File Structure');
$pdf->tableBlock(['File / Path', 'Description'], [
    ['admin/report_monitor.php', 'Main UI page: filters, KPI cards, charts, tables'],
    ['includes/report_monitor_helper.php', 'All SQL queries and data aggregation logic'],
    ['includes/course_category_options.php', 'Course category definitions (included by helper)'],
    ['admin/includes/sidebar.php', 'Navigation link (Master Admin menu block only)'],
    ['config/config.php', 'Database connection, APP_URL, session'],
    ['includes/theme_loader.php', 'Admin theme and branding CSS'],
    ['assets/css/admin-theme.css', 'Shared admin styling'],
]);

$pdf->bodyText('Important: report_monitor_helper.php is only used by Report Monitor. No other admin page includes it.');

$pdf->AddPage();
$pdf->sectionTitle('4. Navigation & Linked Pages');

$pdf->subTitle('Pages that link TO Report Monitor');
$pdf->tableBlock(['Source', 'Link'], [
    ['Admin Sidebar', 'app_url(\'admin/report_monitor\') — visible only to Master Admin'],
    ['Reset button on page', 'report_monitor.php — clears all filters'],
]);

$pdf->subTitle('Pages linked FROM Report Monitor (outbound only)');
$pdf->tableBlock(['Button on page', 'Destination'], [
    ['Manage Batches', 'batch_module/admin/manage_batches.php'],
    ['Manage Faculty in Batches', 'batch_module/admin/manage_batches.php'],
]);

$pdf->bodyText('Report Monitor does NOT link directly to: Students list, Courses management, Registration form, or public website pages. Data from those modules is read via the database only.');

$pdf->subTitle('Related admin modules (data sources, not direct links)');
$pdf->tableBlock(['Admin module', 'Path', 'Feeds Report Monitor'], [
    ['Student registrations', 'student/register.php', 'students table rows'],
    ['Students admin', 'admin/students.php', 'Status, batch assignment'],
    ['Courses', 'admin/manage_courses.php', 'courses table'],
    ['Batch module', 'batch_module/admin/manage_batches.php', 'batches, batch_students'],
    ['Faculty', 'admin/manage_faculty.php', 'faculty, batch_faculty'],
    ['Centres', 'admin/manage_centres.php', 'centres table'],
], [45, 55, 80]);

$pdf->AddPage();
$pdf->sectionTitle('5. Database Tables Used');
$pdf->bodyText('Report Monitor READS from these tables (never writes):');

$pdf->tableBlock(['Table', 'Used for'], [
    ['courses', 'Course count, category grouping, centre/training_center, course names'],
    ['students', 'Applications, pending count, active/approved count, batch assignment flag'],
    ['batches', 'Batch count, monthly trends, batch details table, fill rate'],
    ['batch_students', 'Students enrolled in batches (if table exists)'],
    ['centres', 'Centre dropdown filter and centre-wise statistics'],
    ['faculty', 'Faculty training report — names, designation, department'],
    ['batch_faculty', 'Links faculty to batches for training report'],
    ['admin_course_assignments', 'Helper function exists for scoped courses (page uses all courses by default)'],
]);

$pdf->subTitle('Student counting rules');
$pdf->bulletList([
    'INCLUDED statuses: pending, active, approved (and similar non-terminal statuses)',
    'EXCLUDED statuses: rejected, inactive',
    'Student "in batch" = students.batch_id is set OR row exists in batch_students for that record',
    'Rejected applications do NOT appear in application totals',
]);

$pdf->sectionTitle('6. Report Sections & Data Functions');
$pdf->tableBlock(['UI Section', 'PHP Function', 'Shows'], [
    ['KPI cards (top)', 'report_monitor_get_overall_stats()', 'Total applications, in batches, pending, total batches'],
    ['Monthly trend chart', 'report_monitor_get_batch_monthly()', 'New batches, batch enrollments, applications per month'],
    ['Centre chart', 'report_monitor_get_centre_stats()', 'Applied vs batch-enrolled by centre'],
    ['Category chart', 'report_monitor_get_category_stats()', 'Applications by course category group'],
    ['Centre-wise table', 'report_monitor_get_centre_stats()', 'Courses, batches, applications, enrolled, unassigned per centre'],
    ['Faculty training report', 'report_monitor_get_faculty_stats()', 'Faculty → batches → students trained'],
    ['Batch module details', 'report_monitor_get_batch_details()', 'Latest 100 batches: coordinator, faculty, fill %'],
]);

$pdf->AddPage();
$pdf->sectionTitle('7. URL Filters & Parameters');
$pdf->tableBlock(['Parameter', 'Example', 'Effect'], [
    ['centre_id', '?centre_id=1', 'Filter all stats to one training centre'],
    ['report_month', '?report_month=2026-06', 'Month filter for category/centre/faculty tables'],
    ['months', '?months=12', 'Chart range: 6, 12, 18, or 24 months (default 12)'],
]);

$pdf->bodyText('Example full URL:');
$pdf->bodyText('https://nielitbhubaneswar.in/admin/report_monitor?centre_id=1&report_month=2026-06&months=12');

$pdf->subTitle('Filter notes');
$pdf->bulletList([
    'Centre filter uses centres.id joined to courses.centre_id (or courses.training_center name fallback)',
    'Monthly filter applies to registration dates and batch start/created dates depending on section',
    'Reset button clears all filters back to defaults',
]);

$pdf->sectionTitle('8. Course Category Groups');
$pdf->bodyText('Categories are mapped in report_monitor_helper.php to match public Courses page groups:');

$pdf->bulletList([
    'Skill Based (Long Term) Courses (> 500 hrs)',
    'Skill Based (Short Term) Courses (90-500 hrs)',
    'Short Term / Digital Competency Courses (<= 90 hrs)',
    'Internship Programs & Boot Camps',
    'Degree / Diploma / Postgraduate Courses',
    'NIELIT HQ Digital Literacy Courses (CCC / ECC / BCC / ACC)',
    'Govt/Corporate Training',
    'NSQF / Other Programs',
]);

$pdf->bodyText('Course category is read from courses.category or courses.course_type. O Level and CCC courses appear under their respective category groups set in Manage Courses.');

$pdf->AddPage();
$pdf->sectionTitle('9. Data Flow Diagram (Text)');
$pdf->bodyText(
    "PUBLIC REGISTRATION\n" .
    "  student/register.php → submit_registration.php\n" .
    "       ↓\n" .
    "  INSERT into students (+ student_enrollments if multi-course)\n\n" .
    "ADMIN OPERATIONS\n" .
    "  admin/students.php → approve, reject, assign batch\n" .
    "  batch_module/admin/manage_batches.php → create batches, assign students\n" .
    "  admin/manage_courses.php → course category, centre, status\n" .
    "  admin/manage_faculty.php → faculty records\n" .
    "       ↓\n" .
    "  All updates write to MySQL tables\n\n" .
    "REPORT MONITOR (READ ONLY)\n" .
    "  admin/report_monitor.php\n" .
    "       ↓\n" .
    "  includes/report_monitor_helper.php runs SELECT queries\n" .
    "       ↓\n" .
    "  Displays KPIs, charts, and tables"
);

$pdf->sectionTitle('10. Master Admin Sidebar Context');
$pdf->bodyText('Report Monitor appears in the sidebar under "Admin Management (Master Admin Only)" section, alongside:');
$pdf->bulletList([
    'Add Admin, Manage Admins, Reset Password',
    'Course Assignments',
    'QR Attendance Scanner, Attendance Reports',
    'Report Monitor ← this page',
    'OTP Logs, API Management',
]);

$pdf->sectionTitle('11. Production Verification SQL');
$pdf->bodyText('Use these queries in phpMyAdmin to cross-check Report Monitor numbers:');

$pdf->subTitle('Total applications (excluding rejected/inactive)');
$pdf->bodyText(
    "SELECT COUNT(DISTINCT s.id) AS total_applications\n" .
    "FROM students s\n" .
    "INNER JOIN courses c ON c.id = s.course_id\n" .
    "WHERE LOWER(COALESCE(s.status,'')) NOT IN ('rejected','inactive');"
);

$pdf->subTitle('Pending applications');
$pdf->bodyText(
    "SELECT COUNT(DISTINCT s.id) AS pending\n" .
    "FROM students s\n" .
    "WHERE LOWER(COALESCE(s.status,'')) = 'pending';"
);

$pdf->subTitle('Students in batches');
$pdf->bodyText(
    "SELECT COUNT(DISTINCT s.id) AS in_batch\n" .
    "FROM students s\n" .
    "WHERE LOWER(COALESCE(s.status,'')) NOT IN ('rejected','inactive')\n" .
    "  AND ((s.batch_id IS NOT NULL AND s.batch_id > 0)\n" .
    "       OR EXISTS (SELECT 1 FROM batch_students bs\n" .
    "                  WHERE bs.student_record_id = s.id OR bs.student_id = s.id));"
);

$pdf->subTitle('Total batches');
$pdf->bodyText("SELECT COUNT(*) AS total_batches FROM batches;");

$pdf->AddPage();
$pdf->sectionTitle('12. Troubleshooting');
$pdf->tableBlock(['Issue', 'Likely cause / fix'], [
    ['Page not visible in menu', 'Login as master_admin, not course_coordinator'],
    ['Access denied redirect', 'Role is not master_admin — check admin table role'],
    ['Zero faculty data', 'No batch_faculty assignments — assign in Manage Batches'],
    ['Zero batch data', 'batches table empty or batch module not set up'],
    ['Numbers differ from Students page', 'Report excludes rejected; Students page may show rejected filter'],
    ['Category shows Uncategorized', 'Set courses.category in Manage Courses / Edit Course'],
]);

$pdf->sectionTitle('13. Summary Checklist');
$pdf->bulletList([
    'Report Monitor = Master Admin only, read-only analytics',
    'Entry: Sidebar → Report Monitor',
    'Main outbound link: batch_module/admin/manage_batches.php',
    'Data from: courses, students, batches, batch_students, centres, faculty, batch_faculty',
    'Rejected students are excluded from all application counts',
    'Filters: centre_id, report_month, months (URL parameters)',
    'Regenerate this PDF: php docs/generate_report_monitor_guide_pdf.php',
]);

$pdf->Ln(6);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->SetTextColor(100, 116, 139);
$pdf->MultiCell(0, 5, 'Generated by docs/generate_report_monitor_guide_pdf.php | NIELIT Bhubaneswar Admin System | ' . date('Y-m-d H:i:s'), 0, 'C');

$pdf->Output($outputFile, 'F');

if (PHP_SAPI === 'cli') {
    echo "PDF created: {$outputFile}\n";
} else {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="NIELIT_Report_Monitor_Complete_Guide.pdf"');
    readfile($outputFile);
    exit;
}
