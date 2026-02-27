<?php
// Start a session for feedback
session_start();

// Database configuration
$dbHost = 'localhost';
$dbUsername = 'ojxwfxpi_KVnielit';
$dbPassword = 'Saswat@123';
$dbName = 'ojxwfxpi_membership_form';

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
    $birthDate = $_POST['dob'];
    $gender = $_POST['gender'];
    $nationality = trim($_POST['nationality']);
    $phoneNumber = trim($_POST['phoneNumber']);
    $aadhar = trim($_POST['aadhar']);
    $email = trim($_POST['email']);
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';

    // Validate mandatory fields
    if (empty($schoolName)) $errors[] = "School Name is required.";
    if (empty($class)) $errors[] = "Class is required.";
    if (empty($trainingDate)) $errors[] = "Date of Training is required.";
    if (empty($fullName)) $errors[] = "Full Name is required.";
    if (empty($birthDate)) $errors[] = "Birth Date is required.";
    if (empty($gender)) $errors[] = "Gender is required.";
    if (empty($nationality)) $errors[] = "Nationality is required.";
    
    // Validate phone number
    if (empty($phoneNumber)) {
        $errors[] = "Phone Number is required.";
    } elseif (!preg_match('/^\d{10}$/', $phoneNumber)) {
        $errors[] = "Phone Number must be exactly 10 digits.";
    }

    // Validate Aadhar number
    if (empty($aadhar)) {
        $errors[] = "Aadhar Number is required.";
    } elseif (!preg_match('/^\d{12}$/', $aadhar)) {
        $errors[] = "Aadhar Number must be exactly 12 digits.";
    }

    // Validate category
    if (empty($category)) $errors[] = "Category is required.";

    // Validate email (if provided)
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    // Handle photograph upload
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileTmpPath = $_FILES['photo']['tmp_name'];
        $fileName = basename($_FILES['photo']['name']);
        $fileType = mime_content_type($fileTmpPath);
        $fileSize = $_FILES['photo']['size'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Validate file type and size
        if (!in_array($fileType, $allowedTypes)) {
            $errors[] = "Only JPG, PNG, and GIF files are allowed for the photograph.";
        } elseif ($fileSize > 2 * 1024 * 1024) { // 2MB limit
            $errors[] = "Photograph size should not exceed 2MB.";
        } else {
            // Move uploaded file to the 'uploads' directory
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true); // Create directory if it doesn't exist
            }
            $uniqueFileName = uniqid() . '.' . $fileExtension;
            $photoPath = $uploadDir . $uniqueFileName;

            if (!move_uploaded_file($fileTmpPath, $photoPath)) {
                $errors[] = "Failed to upload photograph.";
            }
        }
    }

    // If there are no errors, proceed with database operations
    if (empty($errors)) {
        // Check if the record exists based on Aadhar Number
        $stmt = $conn->prepare("SELECT id FROM members WHERE aadhar_number = ?");
        $stmt->bind_param("s", $aadhar);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Update existing record
            $stmt = $conn->prepare("
                UPDATE members 
                SET school_name = ?, class = ?, section = ?, training_date = ?, 
                    photograph = IFNULL(?, photograph), full_name = ?, birth_date = ?, 
                    gender = ?, nationality = ?, phone_number = ?, category = ?, email = ? 
                WHERE aadhar_number = ?
            ");
            $stmt->bind_param(
                "ssssssssssss", 
                $schoolName, $class, $section, $trainingDate, $photoPath, 
                $fullName, $birthDate, $gender, $nationality, 
                $phoneNumber, $category, $email, $aadhar
            );

            if ($stmt->execute()) {
                $_SESSION['success'] = "Record updated successfully!";
            } else {
                $_SESSION['error'] = "Error updating the record: " . $stmt->error;
            }
        } else {
            // Insert a new record
            $stmt = $conn->prepare("
                INSERT INTO members 
                (school_name, class, section, training_date, photograph, full_name, 
                birth_date, gender, nationality, phone_number, category, 
                aadhar_number, email) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "sssssssssssss", 
                $schoolName, $class, $section, $trainingDate, $photoPath, 
                $fullName, $birthDate, $gender, $nationality, 
                $phoneNumber, $category, $aadhar, $email
            );

            if ($stmt->execute()) {
                $_SESSION['success'] = "Form submitted successfully!";
            } else {
                $_SESSION['error'] = "Error submitting the form: " . $stmt->error;
            }
        }

        $stmt->close();
        $conn->close();

        // Redirect to the form page with success or error messages
        header("Location: index.php");
        exit();
    } else {
        // Save errors to the session and redirect back
        $_SESSION['error'] = implode("<br>", $errors);
        header("Location: index.php");
        exit();
    }
} else {
    // Redirect to the form if accessed directly
    header("Location: index.php");
    exit();
}
