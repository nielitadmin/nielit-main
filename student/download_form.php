<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

// Get student ID from session
$student_id = $_SESSION['student_id'];

// Fetch student data
$sql = "SELECT * FROM students WHERE student_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Student not found!");
}

$student = $result->fetch_assoc();

// Include TCPDF library
require_once __DIR__ . '/../libraries/tcpdf/tcpdf.php';

// Extend TCPDF for custom header/footer
class MYPDF extends TCPDF {
    public function Header() {
        // No header
    }
    
    public function Footer() {
        // No footer
    }
}

// Create new PDF document
$pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('NIELIT Bhubaneswar');
$pdf->SetAuthor('NIELIT Bhubaneswar');
$pdf->SetTitle('Candidate Details Form - ' . $student['name']);
$pdf->SetSubject('Student Registration Form');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(TRUE, 15);

// Add first page
$pdf->AddPage();

// ============================================
// MODERN HEADER SECTION - SINGLE LINE LAYOUT WITH EMBLEMS
// ============================================

// Blue gradient header background (compact single-line layout)
$pdf->SetFillColor(13, 71, 161); // Deep Blue
$pdf->Rect(15, 15, 180, 40, 'F');

// Logo size for single-line layout (slightly larger for better visibility)
$logo_size = 28;
$header_y = 19; // Vertical position for all elements

// Add NIELIT Logo (left side) - preserve aspect ratio
$nielit_logo_x = 20;
$logo_path = __DIR__ . '/../assets/images/bhubaneswar_logo.png';
if (file_exists($logo_path)) {
    // Use 0 for height to preserve aspect ratio
    $pdf->Image($logo_path, $nielit_logo_x, $header_y, $logo_size, 0, 'PNG', '', '', true, 300, '', false, false, 0, false, false, false);
}

// Add National Emblem (right side) - preserve aspect ratio, smaller size
$emblem_size = 20; // Smaller than logo
$emblem_x = 195 - $emblem_size - 5; // Right side position
$emblem_path = __DIR__ . '/../assets/images/National-Emblem.png';
if (file_exists($emblem_path)) {
    // Use 0 for height to preserve aspect ratio
    $pdf->Image($emblem_path, $emblem_x, $header_y, $emblem_size, 0, 'PNG', '', '', true, 300, '', false, false, 0, false, false, false);
}

// Header Text - CENTERED BETWEEN LOGOS (single line layout)
$text_start_x = $nielit_logo_x + $logo_size + 5; // Start after NIELIT logo
$text_width = $emblem_x - $text_start_x - 5; // Width until National Emblem

$pdf->SetTextColor(255, 255, 255); // White

// Title
$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetXY($text_start_x, $header_y + 3);
$pdf->Cell($text_width, 5, 'CANDIDATE DETAILS', 0, 1, 'C');

// Organization line 1
$pdf->SetFont('helvetica', '', 9);
$pdf->SetXY($text_start_x, $header_y + 9);
$pdf->Cell($text_width, 4, 'National Institute of Electronics & Information Technology', 0, 1, 'C');

// Organization line 2
$pdf->SetFont('helvetica', '', 8);
$pdf->SetXY($text_start_x, $header_y + 14);
$pdf->Cell($text_width, 4, 'Bhubaneswar | Ministry of Electronics & IT', 0, 1, 'C');

$pdf->SetTextColor(0, 0, 0); // Reset to black

// Student ID Badge - BELOW HEADER
$pdf->SetXY(140, 57);
$pdf->SetFillColor(255, 193, 7); // Gold
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', 'B', 7);
$pdf->Cell(50, 4, 'STUDENT ID', 0, 1, 'C', true);

$pdf->SetXY(140, 61);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(255, 255, 255); // White
$pdf->SetDrawColor(255, 193, 7); // Gold border
$pdf->Cell(50, 6, $student['student_id'], 1, 1, 'C', true);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetDrawColor(0, 0, 0);

$pdf->Ln(5); // Spacing after header

// ============================================
// PHOTO AND BASIC INFO SECTION - OPTIMIZED FOR 2 PAGES
// ============================================

$start_y = $pdf->GetY();

// Left side - Photo Card (adjusted position for better spacing)
$photo_x = 15; // Start closer to margin
$photo_card_width = 60; // Slightly narrower

$pdf->SetFillColor(240, 248, 255);
$pdf->RoundedRect($photo_x, $start_y, $photo_card_width, 85, 3, '1111', 'F');

// Photo frame
$pdf->SetDrawColor(13, 71, 161);
$pdf->SetLineWidth(0.5);
$pdf->RoundedRect($photo_x + 3, $start_y + 3, 54, 64, 2, '1111', 'D');

// Add passport photo
$photo_path = '';
if (!empty($student['passport_photo'])) {
    $photo_path = __DIR__ . '/../' . $student['passport_photo'];
}

if (!empty($photo_path) && file_exists($photo_path)) {
    $pdf->Image($photo_path, $photo_x + 4, $start_y + 4, 52, 62, '', '', '', true, 300, '', false, false, 0);
} else {
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetXY($photo_x + 3, $start_y + 33);
    $pdf->Cell(54, 6, 'No Photo', 0, 0, 'C');
}

// Signature frame
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetXY($photo_x + 3, $start_y + 68);
$pdf->Cell(54, 4, 'Signature', 0, 1, 'C');

$pdf->SetDrawColor(13, 71, 161);
$pdf->RoundedRect($photo_x + 3, $start_y + 73, 54, 12, 2, '1111', 'D');

// Add signature
$signature_path = '';
if (!empty($student['signature'])) {
    $signature_path = __DIR__ . '/../' . $student['signature'];
}

if (!empty($signature_path) && file_exists($signature_path)) {
    $pdf->Image($signature_path, $photo_x + 6, $start_y + 75, 48, 8, '', '', '', true, 300, '', false, false, 0);
} else {
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetXY($photo_x + 3, $start_y + 77);
    $pdf->Cell(54, 6, 'No Signature', 0, 0, 'C');
}

// Right side - Basic Info Cards (adjusted to use more space)
$card_x = $photo_x + $photo_card_width + 3; // 3mm gap from photo card
$card_y = $start_y;
$card_width = 195 - $card_x - 15; // Use remaining width to right margin

// Name Card
$pdf->SetFillColor(13, 71, 161);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetXY($card_x, $card_y);
$pdf->Cell($card_width, 8, 'STUDENT NAME', 0, 1, 'L', true);

$pdf->SetFillColor(255, 255, 255);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetXY($card_x, $card_y + 8);
$pdf->Cell($card_width, 10, strtoupper($student['name']), 0, 1, 'L');

// Info Grid
$info_y = $card_y + 22;
$col_width = $card_width / 2; // Split into two equal columns

// Course (Full Width) - Fix for long course names
$pdf->SetFillColor(227, 242, 253);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetXY($card_x, $info_y);
$pdf->Cell($card_width, 6, 'COURSE', 0, 1, 'L', true);

$pdf->SetFont('helvetica', '', 8);
$pdf->SetXY($card_x, $info_y + 6);
$pdf->MultiCell($card_width, 5, $student['course'], 0, 'L');

// Status (below course)
$info_y += 18; // Move down after course
$pdf->SetFillColor(227, 242, 253);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetXY($card_x, $info_y);
$pdf->Cell($card_width, 6, 'STATUS', 0, 1, 'L', true);

$pdf->SetFont('helvetica', '', 9);
$pdf->SetXY($card_x, $info_y + 6);
$pdf->Cell($card_width, 6, $student['status'], 0, 1, 'L');

// DOB & Age
$info_y += 15;
$pdf->SetFillColor(227, 242, 253);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetXY($card_x, $info_y);
$pdf->Cell($col_width, 6, 'DATE OF BIRTH', 0, 0, 'L', true);

$pdf->SetFillColor(227, 242, 253);
$pdf->SetXY($card_x + $col_width, $info_y);
$pdf->Cell($col_width, 6, 'AGE', 0, 1, 'L', true);

$pdf->SetFont('helvetica', '', 9);
$pdf->SetXY($card_x, $info_y + 6);
$pdf->Cell($col_width, 6, $student['dob'], 0, 0, 'L');

$pdf->SetXY($card_x + $col_width, $info_y + 6);
$pdf->Cell($col_width, 6, $student['age'] . ' years', 0, 1, 'L');

// Mobile & Email
$info_y += 15;
$pdf->SetFillColor(227, 242, 253);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetXY($card_x, $info_y);
$pdf->Cell($col_width, 6, 'MOBILE', 0, 0, 'L', true);

$pdf->SetFillColor(227, 242, 253);
$pdf->SetXY($card_x + $col_width, $info_y);
$pdf->Cell($col_width, 6, 'EMAIL', 0, 1, 'L', true);

$pdf->SetFont('helvetica', '', 9);
$pdf->SetXY($card_x, $info_y + 6);
$pdf->Cell($col_width, 6, $student['mobile'], 0, 0, 'L');

$pdf->SetFont('helvetica', '', 8);
$pdf->SetXY($card_x + $col_width, $info_y + 6);
$pdf->Cell($col_width, 6, $student['email'], 0, 1, 'L');

// Move past the photo card height to avoid overlap with signature
// Photo card total height is 85mm, ensure we're below it
$current_y = $pdf->GetY();
$photo_card_end = $start_y + 85; // Photo card ends at start_y + 85mm

if ($current_y < $photo_card_end) {
    $pdf->SetY($photo_card_end + 4); // Move 4mm below photo card
} else {
    $pdf->Ln(4);
}

// ============================================
// FAMILY DETAILS SECTION - OPTIMIZED
// ============================================

$pdf->SetFillColor(13, 71, 161);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 8, '  FAMILY DETAILS', 0, 1, 'L', true);
$pdf->SetTextColor(0, 0, 0);

$pdf->Ln(2);

// Family info grid
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(227, 242, 253);
$pdf->Cell(90, 7, 'FATHER\'S NAME', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(90, 7, $student['father_name'], 1, 1, 'L');

$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(227, 242, 253);
$pdf->Cell(90, 7, 'MOTHER\'S NAME', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(90, 7, $student['mother_name'], 1, 1, 'L');

// ============================================
// ADDRESS & LOCATION SECTION - OPTIMIZED
// ============================================

$pdf->Ln(4);
$pdf->SetFillColor(13, 71, 161);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 8, '  ADDRESS & LOCATION', 0, 1, 'L', true);
$pdf->SetTextColor(0, 0, 0);

$pdf->Ln(2);

$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(227, 242, 253);
$pdf->Cell(45, 7, 'ADDRESS', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 7, $student['address'], 1, 1, 'L');

$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(227, 242, 253);
$pdf->Cell(45, 7, 'CITY', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(45, 7, $student['city'], 1, 0, 'L');
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(227, 242, 253);
$pdf->Cell(45, 7, 'STATE', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(45, 7, $student['state'], 1, 1, 'L');

$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(227, 242, 253);
$pdf->Cell(45, 7, 'PINCODE', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 7, $student['pincode'], 1, 1, 'L');

// ============================================
// PERSONAL INFORMATION SECTION - OPTIMIZED
// ============================================

$pdf->Ln(4);
$pdf->SetFillColor(13, 71, 161);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 8, '  PERSONAL INFORMATION', 0, 1, 'L', true);
$pdf->SetTextColor(0, 0, 0);

$pdf->Ln(2);

// Row 1
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(227, 242, 253);
$pdf->Cell(45, 7, 'GENDER', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(45, 7, $student['gender'], 1, 0, 'L');
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(227, 242, 253);
$pdf->Cell(45, 7, 'RELIGION', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(45, 7, $student['religion'], 1, 1, 'L');

// Row 2
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(227, 242, 253);
$pdf->Cell(45, 7, 'CATEGORY', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(45, 7, $student['category'], 1, 0, 'L');
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(227, 242, 253);
$pdf->Cell(45, 7, 'MARITAL STATUS', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(45, 7, $student['marital_status'], 1, 1, 'L');

// Row 3
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(227, 242, 253);
$pdf->Cell(45, 7, 'NATIONALITY', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(45, 7, $student['nationality'], 1, 0, 'L');
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(227, 242, 253);
$pdf->Cell(45, 7, 'AADHAR NUMBER', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(45, 7, $student['aadhar'], 1, 1, 'L');

// ============================================
// PAGE 1 BORDER
// ============================================

// Add blue border to page 1
$pdf->SetDrawColor(13, 71, 161);
$pdf->SetLineWidth(1);
$pdf->Rect(13, 13, 184, 269, 'D');

// ============================================
// PAGE BREAK - START PAGE 2
// ============================================

$pdf->AddPage();

// ============================================
// ACADEMIC DETAILS SECTION - PAGE 2
// ============================================

$pdf->SetFillColor(13, 71, 161);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 8, '  ACADEMIC DETAILS', 0, 1, 'L', true);
$pdf->SetTextColor(0, 0, 0);

$pdf->Ln(2);

$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(227, 242, 253);
$pdf->Cell(55, 7, 'TRAINING CENTER', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 7, $student['training_center'] ?? 'NIELIT BHUBANESWAR CENTER', 1, 1, 'L');

$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(227, 242, 253);
$pdf->Cell(55, 7, 'COLLEGE NAME', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 7, $student['college_name'] ?? 'N/A', 1, 1, 'L');

$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(227, 242, 253);
$pdf->Cell(55, 7, 'UTR NUMBER', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 7, $student['utr_number'] ?? 'N/A', 1, 1, 'L');

// ============================================
// EDUCATIONAL QUALIFICATIONS TABLE
// ============================================

$pdf->Ln(6);
$pdf->SetFillColor(13, 71, 161);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 8, '  EDUCATIONAL QUALIFICATIONS', 0, 1, 'L', true);
$pdf->SetTextColor(0, 0, 0);

$pdf->Ln(2);

// Fetch education details from database
$education_sql = "SELECT * FROM student_education WHERE student_id = ? ORDER BY id ASC";
$education_stmt = $conn->prepare($education_sql);
if ($education_stmt) {
    $education_stmt->bind_param("s", $student_id);
    $education_stmt->execute();
    $education_result = $education_stmt->get_result();
    
    if ($education_result->num_rows > 0) {
        // Table header
        $pdf->SetFillColor(227, 242, 253);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->Cell(10, 7, 'Sl.', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'Exam Passed', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'Exam Name', 1, 0, 'C', true);
        $pdf->Cell(15, 7, 'Year', 1, 0, 'C', true);
        $pdf->Cell(40, 7, 'Institute/Board', 1, 0, 'C', true);
        $pdf->Cell(30, 7, 'Stream', 1, 0, 'C', true);
        $pdf->Cell(20, 7, '%/CGPA', 1, 1, 'C', true);
        
        // Table rows
        $pdf->SetFont('helvetica', '', 7);
        $sl_no = 1;
        while ($edu = $education_result->fetch_assoc()) {
            $pdf->Cell(10, 6, $sl_no++, 1, 0, 'C');
            $pdf->Cell(25, 6, $edu['exam_passed'] ?? '', 1, 0, 'L');
            $pdf->Cell(25, 6, $edu['exam_name'] ?? '', 1, 0, 'L');
            $pdf->Cell(15, 6, $edu['year_of_passing'] ?? '', 1, 0, 'C');
            $pdf->Cell(40, 6, $edu['institute_name'] ?? '', 1, 0, 'L');
            $pdf->Cell(30, 6, $edu['stream'] ?? '', 1, 0, 'L');
            $pdf->Cell(20, 6, $edu['percentage'] ?? '', 1, 1, 'C');
        }
    } else {
        $pdf->SetFont('helvetica', 'I', 9);
        $pdf->Cell(0, 7, 'No educational qualifications recorded', 1, 1, 'C');
    }
    $education_stmt->close();
} else {
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->Cell(0, 7, 'Unable to fetch educational qualifications', 1, 1, 'C');
}

// ============================================
// DECLARATION SECTION - OPTIMIZED FOR PAGE 2
// ============================================

$pdf->Ln(6);
$pdf->SetFillColor(13, 71, 161);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 8, '  DECLARATION', 0, 1, 'L', true);
$pdf->SetTextColor(0, 0, 0);

$pdf->Ln(3);

$pdf->SetFont('helvetica', '', 10);
$declaration_text = 'I hereby declare that the information provided above is true and correct to the best of my knowledge. I understand that any false information may result in the cancellation of my admission/registration.';
$pdf->MultiCell(0, 5, $declaration_text, 0, 'L');

$pdf->Ln(6);

// Signature section
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(90, 6, 'Place: _______________________', 0, 0, 'L');
$pdf->Cell(0, 6, 'Date: _______________________', 0, 1, 'L');

$pdf->Ln(10);

// Signature box
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(90, 6, '', 0, 0, 'L');
$pdf->Cell(0, 6, 'Signature of Candidate', 0, 1, 'R');

$pdf->Ln(2);

// Add signature image or box
if (!empty($signature_path) && file_exists($signature_path)) {
    $pdf->Image($signature_path, 145, $pdf->GetY(), 45, 18, '', '', '', true, 300, '', false, false, 0);
    $pdf->Ln(20);
} else {
    $pdf->SetDrawColor(13, 71, 161);
    $pdf->SetLineWidth(0.3);
    $pdf->Cell(90, 18, '', 0, 0, 'L');
    $pdf->Cell(0, 18, '', 1, 1, 'R');
}

// ============================================
// FOOTER
// ============================================

$pdf->Ln(8);
$pdf->SetFont('helvetica', 'I', 9);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 5, 'For any enquiries, Email: admin@nielitbhubaneswar.in | Phone: 0674-2960354', 0, 1, 'C');

// Modern border on both pages
$pdf->SetDrawColor(13, 71, 161);
$pdf->SetLineWidth(1);
$pdf->Rect(13, 13, 184, 269, 'D');

// Close and output PDF document
$filename = 'Student_Form_' . str_replace('/', '_', $student['student_id']) . '.pdf';
$pdf->Output($filename, 'D'); // D = Download

$conn->close();
?>
