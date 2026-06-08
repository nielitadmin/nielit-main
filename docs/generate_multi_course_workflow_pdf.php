<?php
/**
 * Generate PDF: NIELIT Multi-Course Student System — Complete Workflow + Diagrams
 * Run: php docs/generate_multi_course_workflow_pdf.php
 * Browser: /docs/generate_multi_course_workflow_pdf.php
 */

require_once __DIR__ . '/../libraries/tcpdf/tcpdf.php';

$outputDir = __DIR__;
$outputFile = $outputDir . '/NIELIT_Multi_Course_Student_System_Workflow.pdf';

class WorkflowPDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 10);
        $this->SetTextColor(26, 38, 64);
        $this->Cell(0, 8, 'NIELIT Bhubaneswar — Multi-Course Student System', 0, false, 'L');
        $this->SetFont('helvetica', '', 8);
        $this->Cell(0, 8, 'Workflow & Structure Diagrams | ' . date('d M Y'), 0, false, 'R');
        $this->Ln(10);
        $this->SetDrawColor(26, 86, 219);
        $this->SetLineWidth(0.5);
        $this->Line(15, 18, 195, 18);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, false, 'C');
    }

    /** Draw a flowchart box */
    public function flowBox($x, $y, $w, $h, $text, $fill = [240, 249, 255], $border = [26, 86, 219], $fontSize = 8) {
        $this->SetFillColor($fill[0], $fill[1], $fill[2]);
        $this->SetDrawColor($border[0], $border[1], $border[2]);
        $this->SetLineWidth(0.4);
        $this->RoundedRect($x, $y, $w, $h, 2, '1111', 'DF');
        $this->SetTextColor(26, 38, 64);
        $this->SetFont('helvetica', '', $fontSize);
        $this->setXY($x + 2, $y + 2);
        $this->MultiCell($w - 4, $h - 4, $text, 0, 'C', false, 1, '', '', true, 0, false, true, $h - 4, 'M');
    }

    /** Diamond decision box */
    public function decisionBox($cx, $cy, $w, $h, $text) {
        $this->SetFillColor(255, 251, 235);
        $this->SetDrawColor(245, 158, 11);
        $this->SetLineWidth(0.4);
        $points = [$cx, $cy - $h/2, $cx + $w/2, $cy, $cx, $cy + $h/2, $cx - $w/2, $cy];
        $this->Polygon($points, 'DF');
        $this->SetTextColor(120, 53, 15);
        $this->SetFont('helvetica', 'B', 7);
        $this->setXY($cx - $w/2 + 4, $cy - 6);
        $this->MultiCell($w - 8, 12, $text, 0, 'C');
    }

    /** Down arrow */
    public function arrowDown($x, $y1, $y2) {
        $this->SetDrawColor(107, 114, 128);
        $this->SetLineWidth(0.35);
        $this->Line($x, $y1, $x, $y2 - 3);
        $this->Polygon([$x, $y2, $x - 2, $y2 - 4, $x + 2, $y2 - 4], 'F');
    }

    /** Right arrow */
    public function arrowRight($x1, $y, $x2) {
        $this->SetDrawColor(107, 114, 128);
        $this->SetLineWidth(0.35);
        $this->Line($x1, $y, $x2 - 3, $y);
        $this->Polygon([$x2, $y, $x2 - 4, $y - 2, $x2 - 4, $y + 2], 'F');
    }

    /** ER entity box */
    public function entityBox($x, $y, $w, $title, $fields, $color = [10, 22, 40]) {
        $lineH = 5;
        $h = 8 + count($fields) * $lineH;
        $this->SetFillColor($color[0], $color[1], $color[2]);
        $this->Rect($x, $y, $w, 7, 'F');
        $this->SetDrawColor(26, 86, 219);
        $this->SetLineWidth(0.4);
        $this->Rect($x, $y, $w, $h, 'D');
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('helvetica', 'B', 8);
        $this->setXY($x + 2, $y + 1.5);
        $this->Cell($w - 4, 5, $title, 0, 0, 'C');
        $this->SetTextColor(31, 41, 55);
        $this->SetFont('helvetica', '', 7);
        $fy = $y + 8;
        foreach ($fields as $f) {
            $this->setXY($x + 2, $fy);
            $this->Cell($w - 4, $lineH, $f, 0, 0, 'L');
            $fy += $lineH;
        }
        return $h;
    }

    public function relationLine($x1, $y1, $x2, $y2, $label = '') {
        $this->SetDrawColor(107, 114, 128);
        $this->SetLineWidth(0.3);
        $this->Line($x1, $y1, $x2, $y2);
        if ($label) {
            $mx = ($x1 + $x2) / 2;
            $my = ($y1 + $y2) / 2;
            $this->SetFont('helvetica', 'I', 6);
            $this->SetTextColor(75, 85, 99);
            $this->setXY($mx - 8, $my - 3);
            $this->Cell(16, 4, $label, 0, 0, 'C');
        }
    }

    public function sectionTitle($title) {
        $this->SetFont('helvetica', 'B', 14);
        $this->SetTextColor(10, 22, 40);
        $this->Cell(0, 10, $title, 0, 1, 'L');
        $this->SetDrawColor(26, 86, 219);
        $this->Line(15, $this->GetY(), 80, $this->GetY());
        $this->Ln(4);
    }
}

function drawSystemOverviewDiagram(WorkflowPDF $pdf) {
    $pdf->sectionTitle('Diagram 1 — System Overview Structure');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(75, 85, 99);
    $pdf->MultiCell(0, 5, 'One person (Aadhar) → One Student ID → Many course enrollments → Many batch assignments.', 0, 'L');
    $pdf->Ln(3);

    $pdf->flowBox(75, 42, 60, 14, "STUDENT ACCOUNT\n1 Aadhar = 1 ID\nNIELIT/2026/BBSR/####", [219, 234, 254], [26, 86, 219], 7.5);

    $pdf->arrowDown(105, 56, 64);
    $pdf->flowBox(30, 66, 50, 12, "ENROLLMENT\nCourse A\nStatus: Active", [236, 253, 245], [16, 185, 129]);
    $pdf->flowBox(75, 66, 50, 12, "ENROLLMENT\nCourse B\nStatus: Pending", [236, 253, 245], [16, 185, 129]);
    $pdf->flowBox(120, 66, 50, 12, "ENROLLMENT\nCourse C\n(future)", [243, 244, 246], [156, 163, 175]);

    $pdf->arrowDown(55, 78, 86);
    $pdf->arrowDown(100, 78, 86);
    $pdf->flowBox(25, 90, 45, 11, "BATCH 1\nBatch A-01", [255, 247, 237], [249, 115, 22]);
    $pdf->flowBox(72, 90, 45, 11, "BATCH 2\nBatch B-01", [255, 247, 237], [249, 115, 22]);

    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetTextColor(26, 86, 219);
    $pdf->setXY(15, 108);
    $pdf->Cell(0, 5, 'LOGIN: Same Student ID + Password → Dashboard shows ALL courses & batches', 0, 1, 'L');
}

function drawDatabaseErDiagram(WorkflowPDF $pdf) {
    $pdf->AddPage();
    $pdf->sectionTitle('Diagram 2 — Database Structure (ER Diagram)');

    $h1 = $pdf->entityBox(15, 38, 52, 'student_accounts', [
        'id (PK)',
        'student_id (UK)',
        'aadhar (UK)',
        'name, email, password',
        'mobile, dob, profile',
    ]);

    $h2 = $pdf->entityBox(78, 35, 52, 'student_enrollments', [
        'id (PK)',
        'account_id (FK)',
        'course_id (FK)',
        'status, registered_at',
        'UK: account + course',
    ]);

    $h3 = $pdf->entityBox(141, 38, 48, 'courses', [
        'id (PK)',
        'course_name',
        'course_code',
        'training_center',
    ]);

    $h4 = $pdf->entityBox(50, 88, 48, 'batch_students', [
        'id (PK)',
        'enrollment_id (FK)',
        'batch_id (FK)',
        'enrollment_date',
        'fees, attendance',
    ]);

    $h5 = $pdf->entityBox(115, 88, 48, 'batches', [
        'id (PK)',
        'course_id (FK)',
        'batch_name, code',
        'seats_total/filled',
        'start_date, end_date',
    ]);

    $pdf->relationLine(67, 55, 78, 52, '1 : N');
    $pdf->relationLine(130, 50, 141, 50, 'N : 1');
    $pdf->relationLine(90, 35 + $h2, 74, 88, '1 : N');
    $pdf->relationLine(98, 100, 115, 100, 'N : 1');
    $pdf->relationLine(163, 38 + $h3, 139, 88, '1 : N');

    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetTextColor(220, 38, 38);
    $pdf->setXY(15, 118);
    $pdf->MultiCell(180, 5, "KEY RULE: UNIQUE (aadhar) on accounts | UNIQUE (account_id + course_id) on enrollments — prevents same-course re-registration.", 0, 'L');
}

function drawRegistrationWorkflow(WorkflowPDF $pdf) {
    $pdf->AddPage();
    $pdf->sectionTitle('Diagram 3 — Registration Workflow');

    $pdf->flowBox(70, 32, 70, 10, 'Student opens course link & submits form', [240, 249, 255]);
    $pdf->arrowDown(105, 42, 48);
    $pdf->decisionBox(105, 56, 50, 18, 'Aadhar\nin system?');

    // No branch - left
    $pdf->arrowRight(80, 56, 48);
    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetTextColor(22, 101, 52);
    $pdf->setXY(48, 52);
    $pdf->Cell(12, 4, 'NO', 0, 0, 'C');
    $pdf->flowBox(15, 48, 32, 16, "NEW STUDENT\n• Create account\n• New ID\n• Full form\n• Enrollment", [220, 252, 231], [22, 163, 74], 6.5);

    // Yes branch - right
    $pdf->arrowRight(130, 56, 148);
    $pdf->SetTextColor(180, 83, 9);
    $pdf->setXY(132, 52);
    $pdf->Cell(12, 4, 'YES', 0, 0, 'C');
    $pdf->decisionBox(168, 56, 42, 18, 'Same\ncourse?');

    $pdf->flowBox(148, 78, 38, 12, "BLOCK\nAlready registered\nUse login", [254, 226, 226], [220, 38, 38], 6.5);
    $pdf->SetTextColor(220, 38, 38);
    $pdf->setXY(155, 74);
    $pdf->Cell(10, 4, 'YES', 0, 0, 'C');
    $pdf->arrowDown(168, 65, 76);

    $pdf->flowBox(148, 98, 38, 14, "RETURNING\n• Same ID\n• New enrollment\n• Short form", [220, 252, 231], [22, 163, 74], 6.5);
    $pdf->SetTextColor(22, 101, 52);
    $pdf->setXY(138, 94);
    $pdf->Cell(10, 4, 'NO', 0, 0, 'C');
    $pdf->Line(147, 65, 138, 65);
    $pdf->Line(138, 65, 138, 105);
    $pdf->arrowDown(138, 105, 96);

    $pdf->flowBox(60, 115, 90, 10, 'Admin approves → Assign batch → Student active', [237, 233, 254], [109, 40, 217]);
}

function drawAdminWorkflow(WorkflowPDF $pdf) {
    $pdf->AddPage();
    $pdf->sectionTitle('Diagram 4 — Admin Workflow');

    $pdf->flowBox(15, 32, 40, 10, 'Pending\nEnrollments', [254, 243, 199]);
    $pdf->arrowRight(55, 37, 62);
    $pdf->flowBox(62, 32, 38, 10, 'Review\nDocuments', [240, 249, 255]);
    $pdf->arrowRight(100, 37, 108);
    $pdf->decisionBox(127, 37, 36, 14, 'Approve?');

    $pdf->flowBox(108, 58, 32, 10, 'REJECT', [254, 226, 226], [220, 38, 38]);
    $pdf->SetTextColor(220, 38, 38);
    $pdf->setXY(118, 54);
    $pdf->Cell(8, 4, 'NO', 0, 0);
    $pdf->arrowDown(127, 44, 56);

    $pdf->flowBox(155, 32, 42, 10, 'Select Batch\nCheck seats', [236, 253, 245], [16, 185, 129]);
    $pdf->SetTextColor(22, 101, 52);
    $pdf->setXY(145, 33);
    $pdf->Cell(8, 4, 'YES', 0, 0);
    $pdf->arrowRight(163, 37, 153);

    $pdf->flowBox(155, 58, 42, 10, 'Add to\nbatch_students', [236, 253, 245], [16, 185, 129]);
    $pdf->arrowDown(176, 42, 56);

    $pdf->Ln(35);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(10, 22, 40);
    $pdf->Cell(0, 8, 'Admin: Assign to Additional Course / Batch', 0, 1);
    $pdf->Ln(2);

    $steps = [
        ['Search student', 'By Aadhar / ID / Name'],
        ['View profile', 'All enrollments + batches'],
        ['Assign course', 'Block if duplicate course'],
        ['Assign batch', 'Check seat capacity'],
        ['Done', 'Same Student ID kept'],
    ];
    $x = 15;
    foreach ($steps as $i => $s) {
        $pdf->flowBox($x, $pdf->GetY(), 34, 14, $s[0] . "\n" . $s[1], [240, 249, 255], [26, 86, 219], 6.5);
        if ($i < count($steps) - 1) {
            $pdf->arrowRight($x + 34, $pdf->GetY() + 7, $x + 40);
        }
        $x += 36;
    }
}

function drawStudentPortalFlow(WorkflowPDF $pdf) {
    $pdf->AddPage();
    $pdf->sectionTitle('Diagram 5 — Student Portal & Batch Flow');

    $pdf->flowBox(75, 32, 60, 10, 'Login\nStudent ID + Password', [219, 234, 254]);
    $pdf->arrowDown(105, 42, 48);
    $pdf->flowBox(60, 50, 90, 12, 'Dashboard — All Courses & Batches', [237, 233, 254], [109, 40, 217]);

    $y = 68;
    $pdf->flowBox(15, $y, 55, 18, "Course A / Batch 1\n• Fees\n• Attendance\n• Certificate", [240, 253, 244], [16, 185, 129], 7);
    $pdf->flowBox(72, $y, 55, 18, "Course B / Batch 2\n• Fees\n• Attendance\n• Certificate", [240, 253, 244], [16, 185, 129], 7);
    $pdf->flowBox(129, $y, 55, 18, "Shared Profile\n• Personal info\n• Aadhar\n• Documents", [243, 244, 246], [107, 114, 128], 7);

    $pdf->arrowDown(105, 62, 66);

    $pdf->Ln(55);
    $pdf->sectionTitle('Diagram 6 — Implementation Phases');
    $phases = [
        'P1' => 'DB + Global ID',
        'P2' => 'Registration + Aadhar',
        'P3' => 'Admin assign',
        'P4' => 'Multi-batch',
        'P5' => 'Student portal',
        'P6' => 'Data migration',
    ];
    $px = 15;
    $py = $pdf->GetY();
    foreach ($phases as $k => $v) {
        $pdf->flowBox($px, $py, 28, 16, "$k\n$v", [255, 251, 235], [245, 158, 11], 6.5);
        if ($k !== 'P6') {
            $pdf->arrowRight($px + 28, $py + 8, $px + 32);
        }
        $px += 30;
    }
}

function writeTextSections(WorkflowPDF $pdf) {
    $pdf->AddPage();
    $css = '
    <style>
    h2 { color: #1a56db; font-size: 12px; margin-top: 10px; border-bottom: 1px solid #e5e7eb; }
    p, li, td { font-size: 9px; line-height: 1.4; color: #1f2937; }
    table { border-collapse: collapse; width: 100%; margin: 6px 0; }
    th { background-color: #0a1628; color: #fff; font-size: 8px; padding: 4px; }
    td { border: 1px solid #d1d5db; font-size: 8px; padding: 3px 4px; }
    .box { background-color: #f3f4f6; padding: 6px; border-left: 3px solid #1a56db; }
    </style>';

    $html = $css . '
    <h2>Reference Tables</h2>
    <h2>Aadhar + Course Validation Matrix</h2>
    <table>
    <tr><th>Aadhar</th><th>Course</th><th>Action</th></tr>
    <tr><td>New</td><td>Any</td><td>Full registration → new account + new ID</td></tr>
    <tr><td>Exists</td><td>New course</td><td>New enrollment only → same ID</td></tr>
    <tr><td>Exists</td><td>Same course</td><td><strong>BLOCK</strong> — cannot re-register</td></tr>
    </table>

    <h2>Student ID Numbering</h2>
    <table>
    <tr><th>Event</th><th>Student ID</th></tr>
    <tr><td>1st new person in 2026</td><td>NIELIT/2026/BBSR/0001</td></tr>
    <tr><td>Same person, 2nd course</td><td>Same ID (no new number)</td></tr>
    <tr><td>After 9999 in same year</td><td>10000+ or wait for 2027/0001</td></tr>
    </table>

    <h2>Example Lifecycle</h2>
    <table>
    <tr><th>Step</th><th>Action</th><th>ID</th></tr>
    <tr><td>1</td><td>Register Course A (new Aadhar)</td><td>NIELIT/2026/BBSR/0042</td></tr>
    <tr><td>2</td><td>Admin → Batch 1</td><td>Same</td></tr>
    <tr><td>3</td><td>Register Course B</td><td>Same</td></tr>
    <tr><td>4</td><td>Try Course A again</td><td>BLOCKED</td></tr>
    </table>

    <h2>Role Permissions</h2>
    <table>
    <tr><th>Role</th><th>Can Do</th></tr>
    <tr><td>Student</td><td>Register, login, view own courses</td></tr>
    <tr><td>Course Coordinator</td><td>Approve, assign batch (assigned courses)</td></tr>
    <tr><td>Master Admin</td><td>All courses, assign course/batch, config</td></tr>
    </table>

    <div class="box">
    <strong>Summary:</strong> One Aadhar → One Student ID → One login → Many enrollments (one per course) → Many batches.
    Same course re-registration is never allowed.
    </div>';
    $pdf->writeHTML($html, true, false, true, false, '');
}

// --- Build PDF ---
$pdf = new WorkflowPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('NIELIT Bhubaneswar');
$pdf->SetAuthor('NIELIT Admin');
$pdf->SetTitle('Multi-Course Student System — Workflow & Diagrams');
$pdf->SetMargins(15, 25, 15);
$pdf->SetAutoPageBreak(true, 20);

// Cover page
$pdf->AddPage();
$pdf->SetFont('helvetica', 'B', 22);
$pdf->SetTextColor(10, 22, 40);
$pdf->Ln(25);
$pdf->Cell(0, 12, 'NIELIT Bhubaneswar', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 14);
$pdf->SetTextColor(26, 86, 219);
$pdf->Cell(0, 10, 'Multi-Course Student System', 0, 1, 'C');
$pdf->SetFont('helvetica', 'B', 16);
$pdf->SetTextColor(55, 65, 81);
$pdf->Cell(0, 10, 'Complete Workflow & Structure Diagrams', 0, 1, 'C');
$pdf->Ln(8);
$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor(107, 114, 128);
$pdf->MultiCell(0, 6, "Document Version 2.0\nDate: " . date('d F Y') . "\n\nIncludes: System overview, database ER diagram, registration workflow, admin workflow, student portal flow, and implementation phases.", 0, 'C');
$pdf->Ln(10);
$pdf->SetFillColor(240, 249, 255);
$pdf->Rect(40, $pdf->GetY(), 130, 35, 'F');
$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor(26, 38, 64);
$pdf->setXY(45, $pdf->GetY() + 5);
$pdf->MultiCell(120, 5, "• One Aadhar = One Student ID\n• Multiple courses per student\n• Multiple batches per student\n• No re-registration for same course\n• Admin can assign courses & batches", 0, 'L');

// Diagram pages
$pdf->AddPage();
drawSystemOverviewDiagram($pdf);
drawDatabaseErDiagram($pdf);
drawRegistrationWorkflow($pdf);
drawAdminWorkflow($pdf);
drawStudentPortalFlow($pdf);
writeTextSections($pdf);

$pdf->Output($outputFile, 'F');

if (php_sapi_name() === 'cli') {
    echo "PDF created: $outputFile\n";
} else {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="NIELIT_Multi_Course_Student_System_Workflow.pdf"');
    readfile($outputFile);
}
