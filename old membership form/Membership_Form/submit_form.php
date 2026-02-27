<?php
// Start a session for feedback
session_start();

// Database configuration
$dbHost = 'localhost';
$dbUsername = 'root';
$dbPassword = '';
$dbName = 'nielit_bhubaneswar';

// Create a database connection
$conn = new mysqli($dbHost, $dbUsername, $dbPassword, $dbName);

// Check the database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables and error handling
$errors = [];
$photoPath = "";

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize and validate inputs
    $schoolName = trim($_POST['schoolName']);
    $class = trim($_POST['class']);
    $section = trim($_POST['section']);
    $trainingDate = trim($_POST['trainingDate']);
    $fullName = trim($_POST['fullName']);
    $fatherName = trim($_POST['father_name']);
    $birthDate = $_POST['dob'];
    $gender = $_POST['gender'];
    $phoneNumber = trim($_POST['phoneNumber']);
    $aadhar = trim($_POST['aadhar']);
    $email = trim($_POST['email']);
    $physicalHandicap = $_POST['handicap'];
    $address = trim($_POST['address']);
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    $nationality = trim($_POST['nationality']); // New nationality field
    $capturedPhoto = $_POST['capturedPhoto'];

    // Validate mandatory fields
    if (empty($schoolName)) $errors[] = "School Name is required.";
    if (empty($class)) $errors[] = "Class is required.";
    if (empty($trainingDate)) $errors[] = "Date of Training is required.";
    if (empty($fullName)) $errors[] = "Full Name is required.";
    if (empty($fatherName)) $errors[] = "Father's Name is required.";
    if (empty($birthDate)) $errors[] = "Birth Date is required.";
    if (empty($gender)) $errors[] = "Gender is required.";
    if (empty($address)) $errors[] = "Address is required.";
    if (empty($nationality)) $errors[] = "Nationality is required."; // Nationality validation
    if (empty($phoneNumber) || !preg_match('/^\d{10}$/', $phoneNumber)) {
        $errors[] = "Valid Phone Number is required (10 digits).";
    }
    if (empty($aadhar) || !preg_match('/^\d{12}$/', $aadhar)) {
        $errors[] = "Valid Aadhar Number is required (12 digits).";
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid Email Address.";
    }
    if (empty($category)) $errors[] = "Category is required.";

    // Handle webcam photo
    if (!empty($capturedPhoto)) {
        $imageParts = explode(";base64,", $capturedPhoto);
        if (count($imageParts) === 2) {
            $imageBase64 = base64_decode($imageParts[1]);
            $uniqueFileName = uniqid() . '.png';
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $photoPath = $uploadDir . $uniqueFileName;
            if (!file_put_contents($photoPath, $imageBase64)) {
                $errors[] = "Failed to save the captured photo.";
            }
        } else {
            $errors[] = "Invalid photo format.";
        }
    }

    // If there are no errors, insert into the database
    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO members 
            (school_name, class, section, training_date, full_name, father_name, 
            birth_date, gender, phone_number, aadhar_number, email, physical_handicap, 
            address, category, nationality, photograph) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "ssssssssssssssss",
            $schoolName, $class, $section, $trainingDate, $fullName, $fatherName,
            $birthDate, $gender, $phoneNumber, $aadhar, $email, $physicalHandicap,
            $address, $category, $nationality, $photoPath
        );

        if ($stmt->execute()) {
            $_SESSION['success'] = "Form submitted successfully!";
        } else {
            $_SESSION['error'] = "Error submitting the form: " . $stmt->error;
        }

        $stmt->close();
        $conn->close();

        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
        header("Location: index.php");
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
?>
