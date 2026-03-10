<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start the session
session_start();

// Include the database connection
require_once __DIR__ . '/../config/config.php';

// Check if the student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch student ID from session
$student_id = $_SESSION['student_id'];

// Fetch student details
$sql = "SELECT * FROM students WHERE student_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $student = $result->fetch_assoc();
} else {
    $error_message = "Student not found.";
}

// Fetch education details
$sql_education = "SELECT * FROM education_details WHERE student_id = ?";
$stmt_education = $conn->prepare($sql_education);
$stmt_education->bind_param("s", $student_id);
$stmt_education->execute();
$education_result = $stmt_education->get_result();

// Detect payment receipt type (image/pdf)
$receipt_path = $student['payment_receipt'];
$receipt_ext = strtolower(pathinfo($receipt_path, PATHINFO_EXTENSION));
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal - NIELIT Bhubaneswar</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link href="style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        .container {
            max-width: 1200px;
            margin-top: 30px;
        }
        .profile-section {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
        }
        .profile-left, .profile-right {
            width: 48%;
            margin-bottom: 20px;
        }
        .profile-left h5, .profile-right h5 {
            margin-bottom: 20px;
        }
        .profile-left p, .profile-right p {
            margin-bottom: 10px;
        }
        .documents-container {
            margin-top: 20px;
        }
        .documents-container img {
            width: 100%;
            max-width: 250px;
            height: auto;
            margin-bottom: 20px;
        }
        .documents-container iframe {
            width: 100%;
            height: 500px;
            border: none;
        }
        /* Modern Table Styling */
        .table {
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 30px;
            background-color: #fff;
        }
        .table th, .table td {
            padding: 12px;
            text-align: left;
            font-size: 14px;
            border-bottom: 1px solid #ddd;
        }
        .table th {
            background-color: #f6fafe;
            font-weight: bold;
        }
        .table td {
            color: #555;
        }
        /* For alternate row color */
        .table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
    </style>
</head>
<body>

<!-- Header Section -->
<header class="header py-2">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-8 d-flex align-items-center">
                <img src="bhubaneswar_logo.png" alt="Institute Logo" class="logo mr-3">
                <div>
                    <h5 class="mb-0 hindi-text">राष्ट्रीय इलेक्ट्रॉनिकी एवं सूचना प्रौद्योगिकी संस्थान , भुवनेश्वर</h5>
                    <h6 class="mb-0">National Institute of Electronics & Information Technology, Bhubaneswar</h6>
                </div>
            </div>
            <div class="col-md-4 text-right d-flex align-items-center justify-content-end">
                <div>
                    <h6 class="ministry-text mb-1">Ministry of Electronics & Information Technology</h6>
                    <h6 class="mb-1">Government of India</h6>
                </div>
                <img src="National-Emblem.png" alt="Government Emblem" class="gov-logo ml-3">
            </div>
        </div>
    </div>
</header>
<!-- Sliding Information Section -->
<div class="sliding-info">
    <div class="container">
        <p>NIELIT Bhubaneswar, established in 2021, offers industry-standard NSQF-aligned courses. It provides modern facilities, excellent transport links, and extends outreach through its Balasore Extension Center.</p>
    </div>
</div>  
<!-- Navigation Menu -->
<nav class="navbar navbar-expand-lg navbar-light" style="background-color: #356c9f;">
    <div class="container">
        <a class="navbar-brand" href="index.php">NIELIT Bhubaneswar</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link" href="student.portal.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Student Profile Section -->
<div class="container">
    <h2 class="text-center my-4">Welcome, <?php echo $student['name']; ?>!</h2>

    <!-- Display error messages if any -->
    <?php
    if (isset($error_message)) {
        echo '<div class="alert alert-danger">' . $error_message . '</div>';
    }
    ?>

    <div class="profile-section">
        <!-- Left Section: Personal Info in Modern Table -->
        <div class="profile-left">
            <h5>Personal Information</h5>
            <table class="table">
                <tbody>

                    <tr><th>Course Name</th><td><?php echo $student['course']; ?></td></tr>  <!-- Added Course Name -->
                    <tr><th>Student ID</th><td><?php echo $student['student_id']; ?></td></tr>
                    <tr><th>Name of the Candidate</th><td><?php echo $student['name']; ?></td></tr>
                    <tr><th>Father's Name</th><td><?php echo $student['father_name']; ?></td></tr>
                    <tr><th>Mother's Name</th><td><?php echo $student['mother_name']; ?></td></tr>
                    <tr><th>Date of Birth</th><td><?php echo $student['dob']; ?></td></tr>
                    <tr><th>Age</th><td><?php echo $student['age']; ?></td></tr>
                    <tr><th>Mobile Number</th><td><?php echo $student['mobile']; ?></td></tr>
                    <tr><th>Aadhar Number</th><td><?php echo $student['aadhar']; ?></td></tr>
                    <tr><th>Gender</th><td><?php echo $student['gender']; ?></td></tr>
                    <tr><th>Religion</th><td><?php echo $student['religion']; ?></td></tr>
                    <tr><th>Marital Status</th><td><?php echo $student['marital_status']; ?></td></tr>
                    <tr><th>Category</th><td><?php echo $student['category']; ?></td></tr>
                    <tr><th>Position</th><td><?php echo $student['position']; ?></td></tr>
                    <tr><th>Nationality</th><td><?php echo $student['nationality']; ?></td></tr>
                    <tr><th>Email</th><td><?php echo $student['email']; ?></td></tr>
                    <tr><th>State</th><td><?php echo $student['state']; ?></td></tr>
                    <tr><th>District/City</th><td><?php echo $student['city']; ?></td></tr>
                    <tr><th>Pincode</th><td><?php echo $student['pincode']; ?></td></tr>
                    <tr><th>Address for Communication</th><td><?php echo $student['address']; ?></td></tr>
                    <tr><th>Training Centre</th><td><?php echo $student['training_center']; ?></td></tr>
                    <tr><th>College Name</th><td><?php echo $student['college_name']; ?></td></tr>
                    <tr><th>UTR Number</th><td><?php echo $student['utr_number']; ?></td></tr>

                </tbody>
            </table>
        </div>

        <!-- Right Section: Documents -->
<div class="profile-right">
    <h5>Documents</h5>

    <div class="d-flex justify-content-between flex-wrap">
    <!-- Passport Photo -->
    <div class="documents-container" style="width: 48%;">
        <p><strong>Passport Size Photo:</strong></p>
        <img src="<?php echo $student['passport_photo']; ?>" alt="Passport Photo" style="width: 150px; height: auto;">
    </div>

    <!-- Payment Receipt -->
    <?php if (!empty($receipt_path)): ?>
    <div class="documents-container" style="width: 48%;">
        <p><strong>Payment Receipt:</strong></p>
        <?php if (in_array($receipt_ext, ['jpg', 'jpeg', 'png'])): ?>
            <img src="<?php echo $receipt_path; ?>" alt="Payment Receipt" style="width: 150px; height: auto; border: 1px solid #ccc; padding: 4px;">
        <?php elseif ($receipt_ext === 'pdf'): ?>
            <iframe src="<?php echo $receipt_path; ?>" style="width:100%; height:200px; border:1px solid #ccc;"></iframe>
        <?php else: ?>
            <p>Unsupported file format.</p>
        <?php endif; ?>
        <a href="<?php echo $receipt_path; ?>" download class="btn btn-sm btn-primary mt-2">Download Receipt</a>
    </div>
    <?php endif; ?>
</div>


    <!-- Signature -->
    <div class="documents-container mt-3">
        <p><strong>Signature:</strong></p>
        <img src="<?php echo $student['signature']; ?>" alt="Signature">
    </div>

    <!-- Educational Documents -->
    <div class="documents-container mt-3">
        <p><strong>Educational & Other Documents:</strong></p>
        <iframe src="<?php echo $student['documents']; ?>" frameborder="0"></iframe>
    </div>
</div>



    <!-- Educational Qualification Details -->
    <div class="my-4">
        <h4>Educational Qualification Details</h4>
        <table class="table">
            <thead>
                <tr>
                    <th>Sl. No.</th>
                    <th>Exam Passed</th>
                    <th>Name of Exam</th>
                    <th>Year of Passing</th>
                    <th>Institute/Board</th>
                    <th>Stream/Branch</th>
                    <th>Percentage/CGPA</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sl_no = 1;
                while ($row = $education_result->fetch_assoc()) {
                    echo "<tr>
                            <td>{$sl_no}</td>
                            <td>{$row['exam_passed']}</td>
                            <td>{$row['exam_name']}</td>
                            <td>{$row['year_of_passing']}</td>
                            <td>{$row['institute_name']}</td>
                            <td>{$row['stream']}</td>
                            <td>{$row['percentage']}</td>
                        </tr>";
                    $sl_no++;
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<div class="text-right mb-3">
    <a href="generate_pdf.php?id=<?php echo $student['student_id']; ?>" target="_blank" class="btn btn-danger">
        <i class="fas fa-file-pdf"></i> Download application form    as PDF
    </a>
</div>

<!-- Footer Section -->
<footer class="footer text-white py-4" style="background-color: #356c9f;">
    <div class="container">
        <div class="row">
            <div class="col-md-4">
                <h5>Important Links</h5>
                <ul class="list-unstyled">
                    <li><a href="https://india.gov.in/" class="text-white">https://india.gov.in/</a></li>
                    <li><a href="https://www.mygov.in/" class="text-white">https://www.mygov.in/</a></li>
                    <li><a href="https://rtionline.gov.in/" class="text-white">https://rtionline.gov.in/</a></li>
                    <li><a href="http://meity.gov.in/" class="text-white">http://meity.gov.in/</a></li>
                    <li><a href="https://www.nielit.gov.in/" class="text-white">NIELIT HQ</a></li>
                </ul>
            </div>
            <div class="col-md-4">
                <h5>Explore NIELIT Bhubaneswar</h5>
                <ul class="list-unstyled">
                    <li><a href="#" class="text-white">About Us</a></li>
                    <li><a href="#" class="text-white">Privacy Policy</a></li>
                    <li><a href="#" class="text-white">Terms & Condition</a></li>
                    <li><a href="#" class="text-white">Cancellation/Refund</a></li>
                    <li><a href="#" class="text-white">Contact Us</a></li>
                </ul>
            </div>
            <div class="col-md-4">
                <h5>Contact Us</h5>
                <p>National Institute of Electronics and Information Technology (NIELIT)</p>
                <p>Phone no: 0674-2960354</p>
                <p>Email: <a href="mailto:dir-bbsr@nielit.gov.in" class="text-white">dir-bbsr@nielit.gov.in</a></p>
                <h6>Working Hours</h6>
                <p><strong>(09:00 AM To 5:30 PM, Monday-Friday only)</strong></p>
            </div>
        </div>
    </div>
</footer>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.min.js"></script>

</body>
</html>

<?php
// Close the database connection
$conn->close();
?>
