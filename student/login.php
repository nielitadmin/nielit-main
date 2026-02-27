<?php
// Start the session
session_start();

// Include the database connection
require_once __DIR__ . '/../config/config.php';

// Check if the student is already logged in
if (isset($_SESSION['student_id'])) {
    header("Location: dashboard.php");
    exit;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_id = trim($_POST['student_id']);
    $password = $_POST['password'];

    // Query to fetch student data including status
    $sql = "SELECT student_id, password, name, status FROM students WHERE student_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $student = $result->fetch_assoc();
        
        // Check if student is approved (active status)
        if (strtolower($student['status']) != 'active') {
            if (strtolower($student['status']) == 'pending') {
                $error_message = "Your account is pending admin approval. Please wait for approval before logging in.";
            } elseif (strtolower($student['status']) == 'rejected') {
                $error_message = "Your registration has been rejected. Please contact admin for more information.";
            } else {
                $error_message = "Your account is not active. Please contact admin.";
            }
        }
        // Verify the password
        elseif (password_verify($password, $student['password'])) {
            // Store student info in session
            $_SESSION['student_id'] = $student['student_id'];
            $_SESSION['student_name'] = $student['name'];
            
            // Redirect to dashboard
            header("Location: dashboard.php");
            exit;
        } else {
            $error_message = "Invalid Password.";
        }
    } else {
        $error_message = "Student ID not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login - NIELIT Bhubaneswar</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary-blue: #0d47a1;
            --secondary-blue: #1565c0;
            --accent-gold: #ffc107;
            --light-bg: #f8f9fa;
            --text-dark: #212529;
            --text-muted: #6c757d;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-bg);
            color: var(--text-dark);
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
        }

        /* Top Bar */
        .top-bar {
            background-color: #fff;
            border-bottom: 1px solid #e9ecef;
            padding: 8px 0;
            font-size: 0.85rem;
        }

        /* Navbar */
        .navbar {
            background-color: var(--primary-blue);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            padding: 0.5rem 1rem;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.2rem;
            color: #fff !important;
        }

        .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            margin: 0 5px;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            color: var(--accent-gold) !important;
        }

        .dropdown-menu {
            border: none;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
            border-radius: 8px;
            margin-top: 10px;
        }

        .dropdown-item:hover {
            background-color: #e3f2fd;
            color: var(--primary-blue);
        }

        /* Login Section */
        .login-section {
            min-height: calc(100vh - 400px);
            display: flex;
            align-items: center;
            padding: 60px 0;
        }

        .login-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }

        .login-header i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.9;
        }

        .login-header h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .login-header p {
            margin: 0;
            opacity: 0.9;
        }

        .login-body {
            padding: 40px;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 0.2rem rgba(13, 71, 161, 0.1);
        }

        .input-group-text {
            background: white;
            border: 2px solid #e9ecef;
            border-left: none;
            cursor: pointer;
            transition: all 0.3s;
        }

        .input-group .form-control {
            border-right: none;
        }

        .input-group-text:hover {
            color: var(--primary-blue);
        }

        .btn-login {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            border: none;
            color: white;
            padding: 14px;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 8px;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(13, 71, 161, 0.3);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(13, 71, 161, 0.4);
            color: white;
        }

        .alert {
            border-radius: 8px;
            border: none;
        }

        .alert-danger {
            background-color: #fee;
            color: #c33;
        }

        .info-cards {
            margin-top: 40px;
        }

        .info-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            border: 1px solid #e9ecef;
            transition: all 0.3s;
            height: 100%;
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            border-color: var(--primary-blue);
        }

        .info-card i {
            font-size: 2.5rem;
            color: var(--primary-blue);
            margin-bottom: 15px;
        }

        .info-card h5 {
            font-weight: 600;
            margin-bottom: 10px;
        }

        .info-card p {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin: 0;
        }

        /* Footer */
        footer {
            background-color: #1a202c;
            color: #cbd5e0;
            font-size: 0.95rem;
            margin-top: 60px;
        }

        footer h5 {
            color: #fff;
            font-weight: 600;
            margin-bottom: 1.5rem;
            position: relative;
        }

        footer h5::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -8px;
            width: 40px;
            height: 3px;
            background-color: var(--accent-gold);
        }

        footer a {
            color: #cbd5e0;
            text-decoration: none;
            transition: color 0.2s;
            display: block;
            margin-bottom: 8px;
        }

        footer a:hover {
            color: var(--accent-gold);
            padding-left: 5px;
        }

        .copyright-bar {
            background-color: #111827;
            padding: 15px 0;
            border-top: 1px solid #2d3748;
        }

        @media (max-width: 768px) {
            .login-header {
                padding: 30px 20px;
            }

            .login-header h2 {
                font-size: 1.5rem;
            }

            .login-header i {
                font-size: 48px;
            }

            .login-body {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>

    <!-- Top Bar -->
    <div class="top-bar">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8 d-flex align-items-center justify-content-md-start justify-content-center">
                    <img src="../assets/images/bhubaneswar_logo.png" alt="NIELIT Logo" class="me-3" style="height: 50px;">
                    <div>
                        <div class="fw-bold text-primary d-none d-sm-block" style="font-size: 0.85rem;">राष्ट्रीय इलेक्ट्रॉनिकी एवं सूचना प्रौद्योगिकी संस्थान, भुवनेश्वर</div>
                        <div class="fw-bold text-dark" style="font-size: 0.9rem;">National Institute of Electronics & Information Technology, Bhubaneswar</div>
                    </div>
                </div>
                <div class="col-md-4 d-flex justify-content-md-end justify-content-center">
                    <div class="text-end me-3 d-none d-lg-block">
                        <small class="d-block fw-bold text-secondary">Ministry of Electronics & IT</small>
                        <small class="d-block text-secondary">Government of India</small>
                    </div>
                    <img src="../assets/images/National-Emblem.png" alt="Gov India" style="height: 50px;">
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-university me-2"></i> NIELIT
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="../index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="../public/courses.php">Courses</a></li>
                    <li class="nav-item"><a class="nav-link active" href="login.php">Student Login</a></li>
                    <li class="nav-item"><a class="nav-link" href="../public/contact.php">Contact</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Login Section -->
    <section class="login-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-5 col-md-7">
                    <div class="login-card">
                        <div class="login-header">
                            <i class="fas fa-user-graduate"></i>
                            <h2>Student Portal</h2>
                            <p>Login to access your dashboard</p>
                        </div>

                        <div class="login-body">
                            <?php if (isset($error_message)): ?>
                                <div class="alert alert-danger" role="alert">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    <?php echo htmlspecialchars($error_message); ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="login.php">
                                <div class="mb-4">
                                    <label for="student_id" class="form-label">
                                        <i class="fas fa-id-card me-2"></i>Student ID
                                    </label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="student_id" 
                                           name="student_id" 
                                           placeholder="Enter your Student ID" 
                                           required 
                                           autofocus>
                                </div>

                                <div class="mb-4">
                                    <label for="password" class="form-label">
                                        <i class="fas fa-lock me-2"></i>Password
                                    </label>
                                    <div class="input-group">
                                        <input type="password" 
                                               class="form-control" 
                                               id="password" 
                                               name="password" 
                                               placeholder="Enter your Password" 
                                               required>
                                        <span class="input-group-text" onclick="togglePassword()">
                                            <i class="fas fa-eye" id="togglePasswordIcon"></i>
                                        </span>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-login w-100">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login to Portal
                                </button>
                            </form>

                            <div class="text-center mt-4">
                                <p class="text-muted small mb-2">Need help?</p>
                                <a href="../public/contact.php" class="text-decoration-none">
                                    <i class="fas fa-headset me-1"></i>Contact Support
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Info Cards -->
            <div class="info-cards">
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="info-card">
                            <i class="fas fa-tachometer-alt"></i>
                            <h5>Dashboard</h5>
                            <p>View your course progress, attendance, and important updates</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-card">
                            <i class="fas fa-certificate"></i>
                            <h5>Certificates</h5>
                            <p>Download your course certificates and achievements</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-card">
                            <i class="fas fa-rupee-sign"></i>
                            <h5>Fee Management</h5>
                            <p>Check fee status and download payment receipts</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="pt-5">
        <div class="container pb-4">
            <div class="row gy-4">
                <div class="col-lg-4 col-md-6">
                    <h5>Important Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="https://india.gov.in/" target="_blank"><i class="fas fa-chevron-right me-2 small"></i>National Portal of India</a></li>
                        <li><a href="https://www.mygov.in/" target="_blank"><i class="fas fa-chevron-right me-2 small"></i>MyGov</a></li>
                        <li><a href="https://rtionline.gov.in/" target="_blank"><i class="fas fa-chevron-right me-2 small"></i>RTI Online</a></li>
                        <li><a href="http://meity.gov.in/" target="_blank"><i class="fas fa-chevron-right me-2 small"></i>MeitY</a></li>
                        <li><a href="https://www.nielit.gov.in/" target="_blank"><i class="fas fa-chevron-right me-2 small"></i>NIELIT HQ</a></li>
                    </ul>
                </div>

                <div class="col-lg-4 col-md-6">
                    <h5>Quick Explore</h5>
                    <ul class="list-unstyled">
                        <li><a href="../index.php"><i class="fas fa-chevron-right me-2 small"></i>Home</a></li>
                        <li><a href="../public/courses.php"><i class="fas fa-chevron-right me-2 small"></i>Courses</a></li>
                        <li><a href="../public/contact.php"><i class="fas fa-chevron-right me-2 small"></i>Contact Us</a></li>
                    </ul>
                </div>

                <div class="col-lg-4 col-md-12">
                    <h5>Contact Info</h5>
                    <p class="small text-muted mb-3">National Institute of Electronics & Information Technology, Bhubaneswar</p>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-phone-alt me-2 text-warning"></i> 0674-2960354</li>
                        <li class="mb-2"><i class="fas fa-envelope me-2 text-warning"></i> dir-bbsr@nielit.gov.in</li>
                        <li class="mb-2"><i class="fas fa-clock me-2 text-warning"></i> Mon-Fri: 09:00 AM – 5:30 PM</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="copyright-bar text-center text-muted small">
            <div class="container">
                <div class="row">
                    <div class="col-md-6 text-md-start">
                        © 2025 NIELIT Bhubaneswar. All Rights Reserved.
                    </div>
                    <div class="col-md-6 text-md-end">
                        Designed & Developed by NIELIT Team
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById("password");
            const toggleIcon = document.getElementById("togglePasswordIcon");

            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                toggleIcon.classList.remove("fa-eye");
                toggleIcon.classList.add("fa-eye-slash");
            } else {
                passwordInput.type = "password";
                toggleIcon.classList.remove("fa-eye-slash");
                toggleIcon.classList.add("fa-eye");
            }
        }
    </script>
</body>
</html>
