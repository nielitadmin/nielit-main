<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: login_new.php");
    exit();
}

// Get student ID
if (!isset($_GET['id'])) {
    header("Location: students.php");
    exit();
}

$student_id = $_GET['id'];

// Fetch student data
$sql = "SELECT * FROM students WHERE student_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['message'] = "Student not found!";
    header("Location: students.php");
    exit();
}

$student = $result->fetch_assoc();

// Fetch education details from separate table
$sql_education = "SELECT * FROM education_details WHERE student_id = ? ORDER BY id ASC";
$stmt_education = $conn->prepare($sql_education);
$education_records = [];
if ($stmt_education) {
    $stmt_education->bind_param("s", $student_id);
    $stmt_education->execute();
    $education_result = $stmt_education->get_result();
    while ($row = $education_result->fetch_assoc()) {
        $education_records[] = $row;
    }
    $stmt_education->close();
}

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

// Set margins - Balanced for readability
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(TRUE, 15);

// Add first page
$pdf->AddPage();

// ============================================
// HEADER SECTION - READABLE SIZE
// ============================================

// Header border
$pdf->SetDrawColor(0, 0, 0);
$pdf->SetLineWidth(0.5);
$pdf->Rect(15, 15, 180, 35, 'D');

// Logo
$logo_size = 28;
$header_y = 18;

// Add NIELIT Logo
$nielit_logo_x = 20;
$logo_path = __DIR__ . '/../assets/images/bhubaneswar_logo.png';
if (file_exists($logo_path)) {
    $pdf->Image($logo_path, $nielit_logo_x, $header_y, $logo_size, 0, 'PNG', '', '', true, 300, '', false, false, 0, false, false, false);
}

// Header Text - Centered
$text_start_x = $nielit_logo_x + $logo_size + 5;
$text_width = 195 - $text_start_x - 15;

$pdf->SetTextColor(0, 0, 0);

// Title
$pdf->SetFont('helvetica', 'B', 13);
$pdf->SetXY($text_start_x, $header_y + 3);
$pdf->Cell($text_width, 5, 'ADMISSION FORM', 0, 1, 'C');

// Organization
$pdf->SetFont('helvetica', '', 9);
$pdf->SetXY($text_start_x, $header_y + 10);
$pdf->Cell($text_width, 4, 'National Institute of Electronics & Information Technology', 0, 1, 'C');

$pdf->SetFont('helvetica', '', 8);
$pdf->SetXY($text_start_x, $header_y + 15);
$pdf->Cell($text_width, 4, 'Bhubaneswar | Ministry of Electronics & IT', 0, 1, 'C');

// Student ID Badge
$pdf->SetXY(145, 52);
$pdf->SetFillColor(255, 193, 7);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', 'B', 7);
$pdf->Cell(45, 4, 'STUDENT ID', 0, 1, 'C', true);

$pdf->SetXY(145, 56);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(255, 255, 255);
$pdf->SetDrawColor(255, 193, 7);
$pdf->Cell(45, 6, $student['student_id'], 1, 1, 'C', true);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetDrawColor(0, 0, 0);

$pdf->Ln(4);

// ============================================
// PHOTO AND BASIC INFO SECTION - OPTIMIZED LAYOUT
// ============================================

$start_y = $pdf->GetY();

// Right side - Photo Card
$photo_x = 152;
$photo_card_width = 43;

$pdf->SetFillColor(240, 248, 255);
$pdf->RoundedRect($photo_x, $start_y, $photo_card_width, 60, 2, '1111', 'F');

// Photo frame
$pdf->SetDrawColor(120, 120, 120);
$pdf->SetLineWidth(0.4);
$pdf->RoundedRect($photo_x + 2, $start_y + 2, 39, 44, 2, '1111', 'D');

// Add passport photo
$photo_path = '';
if (!empty($student['passport_photo'])) {
    $photo_path = __DIR__ . '/../' . $student['passport_photo'];
}

if (!empty($photo_path) && file_exists($photo_path)) {
    $pdf->Image($photo_path, $photo_x + 3, $start_y + 3, 37, 42, '', '', '', true, 300, '', false, false, 0);
} else {
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetXY($photo_x + 2, $start_y + 22);
    $pdf->Cell(39, 6, 'No Photo', 0, 0, 'C');
}

// Signature frame
$pdf->SetFont('helvetica', 'B', 7);
$pdf->SetXY($photo_x + 2, $start_y + 47);
$pdf->Cell(39, 4, 'Signature', 0, 1, 'C');

$pdf->SetDrawColor(120, 120, 120);
$pdf->RoundedRect($photo_x + 2, $start_y + 52, 39, 8, 2, '1111', 'D');

// Add signature
$signature_path = '';
if (!empty($student['signature'])) {
    $signature_path = __DIR__ . '/../' . $student['signature'];
}

if (!empty($signature_path) && file_exists($signature_path)) {
    $pdf->Image($signature_path, $photo_x + 3, $start_y + 53, 37, 6, '', '', '', true, 300, '', false, false, 0);
} else {
    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetXY($photo_x + 2, $start_y + 54);
    $pdf->Cell(39, 6, 'No Signature', 0, 0, 'C');
}

// Left side - Info Grid
$card_x = 15;
$card_width = $photo_x - $card_x - 2;

// Name Card
$pdf->SetDrawColor(0, 0, 0);
$pdf->SetLineWidth(0.4);
$pdf->Rect($card_x, $start_y, $card_width, 16, 'D');
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetXY($card_x + 2, $start_y + 2);
$pdf->Cell($card_width - 4, 5, 'STUDENT NAME', 0, 1, 'L');

$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetXY($card_x + 2, $start_y + 8);
$pdf->Cell($card_width - 4, 8, strtoupper($student['name']), 0, 1, 'L');

// Info Grid - Readable fonts
$info_y = $start_y + 19;

// Course (Full Width)
$pdf->SetFillColor(227, 242, 253);
$pdf->SetFont('freesans', 'B', 6);
$pdf->SetXY($card_x, $info_y);
$pdf->Cell($card_width, 6, 'COURSE / पाठ्यक्रम', 0, 1, 'L', true);

$pdf->SetFont('helvetica', '', 8);
$pdf->SetXY($card_x, $info_y + 6);
$pdf->MultiCell($card_width, 5, $student['course'], 0, 'L');

// DOB & Age (2 columns)
$info_y += 16;
$col_width = $card_width / 2;

$pdf->SetFillColor(227, 242, 253);
$pdf->SetFont('freesans', 'B', 6);
$pdf->SetXY($card_x, $info_y);
$pdf->Cell($col_width, 6, 'DATE OF BIRTH / जन्म तिथि', 0, 0, 'L', true);
$pdf->SetXY($card_x + $col_width, $info_y);
$pdf->Cell($col_width, 6, 'AGE / आयु', 0, 1, 'L', true);

$pdf->SetFont('helvetica', '', 8);
$pdf->SetXY($card_x, $info_y + 6);
$pdf->Cell($col_width, 6, $student['dob'], 0, 0, 'L');
$pdf->SetXY($card_x + $col_width, $info_y + 6);
$pdf->Cell($col_width, 6, $student['age'] . ' years', 0, 1, 'L');

// Gender & Category (2 columns)
$info_y += 15;
$pdf->SetFillColor(227, 242, 253);
$pdf->SetFont('freesans', 'B', 6);
$pdf->SetXY($card_x, $info_y);
$pdf->Cell($col_width, 6, 'GENDER / लिंग', 0, 0, 'L', true);
$pdf->SetXY($card_x + $col_width, $info_y);
$pdf->Cell($col_width, 6, 'CATEGORY / श्रेणी', 0, 1, 'L', true);

$pdf->SetFont('helvetica', '', 8);
$pdf->SetXY($card_x, $info_y + 6);
$pdf->Cell($col_width, 6, $student['gender'], 0, 0, 'L');
$pdf->SetXY($card_x + $col_width, $info_y + 6);
$pdf->Cell($col_width, 6, $student['category'], 0, 1, 'L');

// Mobile & Email (2 columns)
$info_y += 15;
$pdf->SetFillColor(227, 242, 253);
$pdf->SetFont('freesans', 'B', 6);
$pdf->SetXY($card_x, $info_y);
$pdf->Cell($col_width, 6, 'MOBILE / मोबाइल', 0, 0, 'L', true);
$pdf->SetXY($card_x + $col_width, $info_y);
$pdf->Cell($col_width, 6, 'EMAIL / ईमेल', 0, 1, 'L', true);

$pdf->SetFont('helvetica', '', 8);
$pdf->SetXY($card_x, $info_y + 6);
$pdf->Cell($col_width, 6, $student['mobile'], 0, 0, 'L');
$pdf->SetFont('helvetica', '', 7);
$pdf->SetXY($card_x + $col_width, $info_y + 6);
$pdf->Cell($col_width, 6, $student['email'], 0, 1, 'L');

// PWD Status & Aadhar (2 columns)
$info_y += 15;
$pdf->SetFillColor(227, 242, 253);
$pdf->SetFont('freesans', 'B', 6);
$pdf->SetXY($card_x, $info_y);
$pdf->Cell($col_width, 6, 'PWD / दिव्यांग', 0, 0, 'L', true);
$pdf->SetXY($card_x + $col_width, $info_y);
$pdf->Cell($col_width, 6, 'AADHAR / आधार', 0, 1, 'L', true);

$pdf->SetFont('helvetica', '', 8);
$pdf->SetXY($card_x, $info_y + 6);
$pwd_display = (!empty($student['pwd_status']) && $student['pwd_status'] == 'Yes') ? 'Yes' : 'No';
$pdf->Cell($col_width, 6, $pwd_display, 0, 0, 'L');
$pdf->SetXY($card_x + $col_width, $info_y + 6);
$pdf->Cell($col_width, 6, $student['aadhar'], 0, 1, 'L');

// APAAR ID & Position (2 columns)
$info_y += 15;
$pdf->SetFillColor(227, 242, 253);
$pdf->SetFont('freesans', 'B', 6);
$pdf->SetXY($card_x, $info_y);
$pdf->Cell($col_width, 6, 'APAAR ID / अपार आईडी', 0, 0, 'L', true);
$pdf->SetXY($card_x + $col_width, $info_y);
$pdf->Cell($col_width, 6, 'POSITION / पद', 0, 1, 'L', true);

$pdf->SetFont('helvetica', '', 8);
$pdf->SetXY($card_x, $info_y + 6);
$apaar_display = !empty($student['apaar_id']) ? $student['apaar_id'] : 'Not Provided';
$pdf->Cell($col_width, 6, $apaar_display, 0, 0, 'L');
$pdf->SetXY($card_x + $col_width, $info_y + 6);
$pdf->Cell($col_width, 6, $student['position'], 0, 1, 'L');

// Distinguishing Marks (Full Width)
$info_y += 15;
$pdf->SetFillColor(227, 242, 253);
$pdf->SetFont('freesans', 'B', 6);
$pdf->SetXY($card_x, $info_y);
$pdf->Cell($card_width, 6, 'DISTINGUISHING MARKS / पहचान चिह्न', 0, 1, 'L', true);

$pdf->SetFont('helvetica', '', 8);
$pdf->SetXY($card_x, $info_y + 6);
$distinguishing_marks_text = !empty($student['distinguishing_marks']) ? $student['distinguishing_marks'] : 'None';
$pdf->Cell($card_width, 6, $distinguishing_marks_text, 0, 1, 'L');

// Move past photo card
$current_y = $pdf->GetY();
$photo_card_end = $start_y + 60;

if ($current_y < $photo_card_end) {
    $pdf->SetY($photo_card_end + 3);
} else {
    $pdf->Ln(3);
}

// ============================================
// FAMILY DETAILS SECTION
// ============================================

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('freesans', 'B', 9);
$pdf->Cell(0, 7, 'FAMILY DETAILS / पारिवारिक विवरण', 0, 1, 'L');
$pdf->SetDrawColor(0, 0, 0);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());

$pdf->Ln(2);

// Family grid
$pdf->SetFont('freesans', 'B', 7);
$pdf->SetFillColor(227, 242, 253);
$pdf->Cell(65, 6, 'FATHER\'S NAME / पिता का नाम', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(115, 6, $student['father_name'], 1, 1, 'L');

$pdf->SetFont('freesans', 'B', 7);
$pdf->SetFillColor(227, 242, 253);
$pdf->Cell(65, 6, 'MOTHER\'S NAME / माता का नाम', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(115, 6, $student['mother_name'], 1, 1, 'L');

// ============================================
// ADDRESS & LOCATION SECTION
// ============================================

$pdf->Ln(3);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('freesans', 'B', 9);
$pdf->Cell(0, 7, 'ADDRESS & LOCATION / पता और स्थान', 0, 1, 'L');
$pdf->SetDrawColor(0, 0, 0);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());

$pdf->Ln(2);

$pdf->SetFont('freesans', 'B', 7);
$pdf->SetFillColor(227, 242, 253);
$pdf->Cell(40, 6, 'ADDRESS / पता', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(0, 6, $student['address'], 1, 1, 'L');

$pdf->SetFont('freesans', 'B', 7);
$pdf->SetFillColor(227, 242, 253);
$pdf->Cell(40, 6, 'CITY / शहर', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(50, 6, $student['city'], 1, 0, 'L');
$pdf->SetFont('freesans', 'B', 7);
$pdf->SetFillColor(227, 242, 253);
$pdf->Cell(40, 6, 'STATE / राज्य', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(50, 6, $student['state'], 1, 1, 'L');

$pdf->SetFont('freesans', 'B', 7);
$pdf->SetFillColor(227, 242, 253);
$pdf->Cell(40, 6, 'PINCODE / पिनकोड', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(0, 6, $student['pincode'], 1, 1, 'L');

// ============================================
// ACADEMIC DETAILS SECTION
// ============================================

$pdf->Ln(3);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('freesans', 'B', 9);
$pdf->Cell(0, 7, 'ACADEMIC DETAILS / शैक्षणिक विवरण', 0, 1, 'L');
$pdf->SetDrawColor(0, 0, 0);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());

$pdf->Ln(2);

$pdf->SetFont('freesans', 'B', 7);
$pdf->SetFillColor(227, 242, 253);
$pdf->Cell(55, 6, 'TRAINING CENTER / प्रशिक्षण केंद्र', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(0, 6, $student['training_center'] ?? 'NIELIT BHUBANESWAR CENTER', 1, 1, 'L');

$pdf->SetFont('freesans', 'B', 7);
$pdf->SetFillColor(227, 242, 253);
$pdf->Cell(55, 6, 'COLLEGE NAME / कॉलेज का नाम', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(0, 6, $student['college_name'] ?? 'N/A', 1, 1, 'L');

$pdf->SetFont('freesans', 'B', 7);
$pdf->SetFillColor(227, 242, 253);
$pdf->Cell(55, 6, 'UTR NUMBER / यूटीआर नंबर', 1, 0, 'L', true);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(0, 6, $student['utr_number'] ?? 'N/A', 1, 1, 'L');

// ============================================
// PAGE 1 BORDER
// ============================================

$pdf->SetDrawColor(120, 120, 120);
$pdf->SetLineWidth(0.8);
$pdf->Rect(13, 13, 184, 269, 'D');

// ============================================
// PAGE BREAK - START PAGE 2
// ============================================

$pdf->AddPage();

// ============================================
// EDUCATIONAL QUALIFICATIONS TABLE
// ============================================

$pdf->Ln(5);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('freesans', 'B', 9);
$pdf->Cell(0, 7, 'EDUCATIONAL QUALIFICATIONS / शैक्षणिक योग्यता', 0, 1, 'L');
$pdf->SetDrawColor(0, 0, 0);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());

$pdf->Ln(2);

// Display education details
if (!empty($education_records) && is_array($education_records)) {
    // Table header
    $pdf->SetFillColor(227, 242, 253);
    $pdf->SetFont('freesans', 'B', 6);
    $pdf->Cell(10, 7, 'Sl.', 1, 0, 'C', true);
    $pdf->Cell(22, 7, 'Exam', 1, 0, 'C', true);
    $pdf->Cell(28, 7, 'Name', 1, 0, 'C', true);
    $pdf->Cell(14, 7, 'Year', 1, 0, 'C', true);
    $pdf->Cell(54, 7, 'Institute', 1, 0, 'C', true);
    $pdf->Cell(30, 7, 'Stream', 1, 0, 'C', true);
    $pdf->Cell(22, 7, '%/CGPA', 1, 1, 'C', true);
    
    // Table rows
    $pdf->SetFont('helvetica', '', 7);
    $row_num = 1;
    
    foreach ($education_records as $edu) {
        $heights = [];
        $heights[] = $pdf->getStringHeight(22, $edu['exam_passed'] ?? '');
        $heights[] = $pdf->getStringHeight(28, $edu['exam_name'] ?? '');
        $heights[] = $pdf->getStringHeight(54, $edu['institute_name'] ?? '');
        $heights[] = $pdf->getStringHeight(30, $edu['stream'] ?? '');
        
        $row_height = max(7, max($heights));
        
        $start_y = $pdf->GetY();
        $start_x = 15;
        
        $pdf->Rect($start_x, $start_y, 180, $row_height);
        
        $pdf->Line($start_x + 10, $start_y, $start_x + 10, $start_y + $row_height);
        $pdf->Line($start_x + 32, $start_y, $start_x + 32, $start_y + $row_height);
        $pdf->Line($start_x + 60, $start_y, $start_x + 60, $start_y + $row_height);
        $pdf->Line($start_x + 74, $start_y, $start_x + 74, $start_y + $row_height);
        $pdf->Line($start_x + 128, $start_y, $start_x + 128, $start_y + $row_height);
        $pdf->Line($start_x + 158, $start_y, $start_x + 158, $start_y + $row_height);
        
        $pdf->MultiCell(10, $row_height, $row_num, 0, 'C', false, 0, $start_x, $start_y, true, 0, false, true, $row_height, 'M');
        $pdf->MultiCell(22, $row_height, $edu['exam_passed'] ?? '', 0, 'L', false, 0, $start_x + 10, $start_y, true, 0, false, true, $row_height, 'M');
        $pdf->MultiCell(28, $row_height, $edu['exam_name'] ?? '', 0, 'L', false, 0, $start_x + 32, $start_y, true, 0, false, true, $row_height, 'M');
        $pdf->MultiCell(14, $row_height, $edu['year_of_passing'] ?? '', 0, 'C', false, 0, $start_x + 60, $start_y, true, 0, false, true, $row_height, 'M');
        $pdf->MultiCell(54, $row_height, $edu['institute_name'] ?? '', 0, 'L', false, 0, $start_x + 74, $start_y, true, 0, false, true, $row_height, 'M');
        $pdf->MultiCell(30, $row_height, $edu['stream'] ?? '', 0, 'L', false, 0, $start_x + 128, $start_y, true, 0, false, true, $row_height, 'M');
        $pdf->MultiCell(22, $row_height, $edu['percentage'] ?? '', 0, 'C', false, 0, $start_x + 158, $start_y, true, 0, false, true, $row_height, 'M');
        
        $pdf->SetY($start_y + $row_height);
        
        $row_num++;
    }
} else {
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(180, 7, 'No educational qualifications recorded', 1, 1, 'C');
}

// ============================================
// DECLARATION SECTION
// ============================================

$pdf->Ln(5);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('freesans', 'B', 9);
$pdf->Cell(0, 7, 'DECLARATION / घोषणा', 0, 1, 'L');
$pdf->SetDrawColor(0, 0, 0);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());

$pdf->Ln(3);

$pdf->SetFont('freesans', '', 8);
$declaration_text = 'I hereby declare that the information provided above is true and correct to the best of my knowledge. I understand that any false information may result in the cancellation of my admission/registration.

मैं एतद्द्वारा घोषणा करता/करती हूं कि ऊपर दी गई जानकारी मेरी जानकारी के अनुसार सत्य और सही है। मैं समझता/समझती हूं कि कोई भी गलत जानकारी मेरे प्रवेश/पंजीकरण को रद्द कर सकती है।';
$pdf->MultiCell(0, 5, $declaration_text, 0, 'L');

$pdf->Ln(5);

// Signature section
$pdf->SetFont('freesans', '', 8);
$pdf->Cell(90, 6, 'Place / स्थान: _______________________', 0, 0, 'L');
$pdf->Cell(0, 6, 'Date / तिथि: _______________________', 0, 1, 'L');

$pdf->Ln(8);

// Signature box
$pdf->SetFont('freesans', 'B', 8);
$pdf->Cell(90, 6, '', 0, 0, 'L');
$pdf->Cell(0, 6, 'Signature of Candidate / उम्मीदवार के हस्ताक्षर', 0, 1, 'R');

$pdf->Ln(2);

// Add signature image or box
if (!empty($signature_path) && file_exists($signature_path)) {
    $pdf->Image($signature_path, 148, $pdf->GetY(), 42, 16, '', '', '', true, 300, '', false, false, 0);
    $pdf->Ln(18);
} else {
    $pdf->SetDrawColor(120, 120, 120);
    $pdf->SetLineWidth(0.4);
    $pdf->Cell(90, 16, '', 0, 0, 'L');
    $pdf->Cell(0, 16, '', 1, 1, 'R');
}

// ============================================
// FOOTER
// ============================================

$pdf->Ln(6);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 5, 'For any enquiries, Email: admin@nielitbhubaneswar.in | Phone: 0674-2960354', 0, 1, 'C');

// Page 2 border
$pdf->SetDrawColor(120, 120, 120);
$pdf->SetLineWidth(0.8);
$pdf->Rect(13, 13, 184, 269, 'D');

// Close and output PDF document
$filename = 'Student_Form_' . str_replace('/', '_', $student['student_id']) . '.pdf';
$pdf->Output($filename, 'D'); // D = Download

$conn->close();
?>
