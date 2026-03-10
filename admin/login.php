<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../libraries/PHPMailer/src/Exception.php';
require __DIR__ . '/../libraries/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../libraries/PHPMailer/src/SMTP.php';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session_manager.php';
require_once __DIR__ . '/../includes/otp_logger.php';

$error_message = "";
$success_message = "";
$show_otp_form = false;

function sendOTP($toEmail, $otp, $username = null) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->Subject = 'Your OTP for Admin Login - NIELIT Bhubaneswar';
        $mail->Body = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background: #f8fafc; border-radius: 10px;">
            <div style="background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                <h2 style="color: white; margin: 0;">NIELIT Bhubaneswar</h2>
                <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0 0;">Admin Login Verification</p>
            </div>
            <div style="background: white; padding: 30px; border-radius: 0 0 10px 10px;">
                <p style="font-size: 16px; color: #1e293b;">Dear Admin,</p>
                <p style="font-size: 14px; color: #64748b;">Your One-Time Password (OTP) for admin login is:</p>
                <div style="background: #f1f5f9; padding: 20px; text-align: center; border-radius: 8px; margin: 20px 0;">
                    <h1 style="color: #2563eb; margin: 0; font-size: 36px; letter-spacing: 8px;">' . htmlspecialchars($otp) . '</h1>
                </div>
                <p style="font-size: 13px; color: #64748b;">This OTP is valid for 10 minutes. Do not share this code with anyone.</p>
                <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;">
                <p style="font-size: 12px; color: #94a3b8; margin: 0;">
                    National Institute of Electronics and Information Technology<br>
                    Bhubaneswar Center<br>
                    Ministry of Electronics & IT, Govt. of India
                </p>
            </div>
        </div>';

        $mail->send();
        
        // Log successful OTP sending
        logOTP($toEmail, $otp, 'Admin Login', $username, 'sent');
        
        return true;
    } catch (Exception $e) {
        // Log failed OTP sending
        logOTP($toEmail, $otp, 'Admin Login', $username, 'failed');
        
        return false;
    }
}

// Handle login form submission
if (isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT * FROM admin WHERE LOWER(username) = LOWER(?) LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        if (password_verify($password, $admin['password'])) {
            $_SESSION['temp_admin_username'] = $admin['username'];
            $_SESSION['temp_admin_email'] = $admin['email'] ?? $admin['username'];
            $otp = rand(100000, 999999);
            $_SESSION['login_otp'] = $otp;
            $_SESSION['otp_generated_time'] = time();

            $sent = sendOTP($_SESSION['temp_admin_email'], $otp, $admin['username']);

            if ($sent) {
                $success_message = "OTP sent successfully to your registered email.";
                $show_otp_form = true;
            } else {
                $error_message = "Failed to send OTP email. Please contact support.";
            }
        } else {
            $error_message = "Invalid username or password.";
        }
    } else {
        $error_message = "Invalid username or password.";
    }
}

// Handle OTP verification
if (isset($_POST['verify_otp'])) {
    $input_otp = $_POST['otp'] ?? '';

    if (!isset($_SESSION['login_otp']) || !isset($_SESSION['otp_generated_time'])) {
        $error_message = "No OTP generated. Please login again.";
    } elseif ((time() - $_SESSION['otp_generated_time']) > 600) {
        $error_message = "OTP expired. Please login again.";
        unset($_SESSION['login_otp'], $_SESSION['otp_generated_time'], $_SESSION['temp_admin_username'], $_SESSION['temp_admin_email']);
    } elseif ($input_otp == $_SESSION['login_otp']) {
        // OTP verified successfully - initialize RBAC session
        $username = $_SESSION['temp_admin_username'];
        
        // Initialize admin session with RBAC data (loads role, assigned courses, admin_id)
        if (init_admin_session($username)) {
            // Clean up temporary session variables
            unset($_SESSION['login_otp'], $_SESSION['otp_generated_time'], $_SESSION['temp_admin_username'], $_SESSION['temp_admin_email']);
            
            // Redirect to dashboard
            header("Location: dashboard.php");
            exit();
        } else {
            // Session initialization failed
            $error_message = "Failed to initialize session. Please contact support.";
            unset($_SESSION['login_otp'], $_SESSION['otp_generated_time'], $_SESSION['temp_admin_username'], $_SESSION['temp_admin_email']);
        }
    } else {
        $error_message = "Invalid OTP.";
        $show_otp_form = true;
    }
}

// Handle resend OTP
if (isset($_POST['resend_otp'])) {
    if (!isset($_SESSION['temp_admin_email'])) {
        $error_message = "Session expired. Please login again.";
    } else {
        $otp = rand(100000, 999999);
        $_SESSION['login_otp'] = $otp;
        $_SESSION['otp_generated_time'] = time();

        $sent = sendOTP($_SESSION['temp_admin_email'], $otp, $_SESSION['temp_admin_username']);
        if ($sent) {
            $success_message = "OTP resent successfully.";
            $show_otp_form = true;
        } else {
            $error_message = "Failed to resend OTP. Please contact support.";
            $show_otp_form = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - NIELIT Bhubaneswar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="<?php echo APP_URL; ?>/assets/images/favicon.ico" type="image/x-icon">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Animated Gradient Background */
        body::before {
            content: '';
            position: absolute;
            width: 200%;
            height: 200%;
            background: 
                radial-gradient(circle at 20% 50%, rgba(102, 126, 234, 0.4) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(118, 75, 162, 0.4) 0%, transparent 50%),
                radial-gradient(circle at 40% 20%, rgba(255, 255, 255, 0.2) 0%, transparent 50%),
                radial-gradient(circle at 60% 70%, rgba(102, 126, 234, 0.3) 0%, transparent 50%);
            animation: gradientShift 15s ease-in-out infinite;
        }

        @keyframes gradientShift {
            0%, 100% { 
                transform: translate(0, 0) rotate(0deg);
            }
            25% { 
                transform: translate(-5%, -5%) rotate(90deg);
            }
            50% { 
                transform: translate(-10%, 5%) rotate(180deg);
            }
            75% { 
                transform: translate(-5%, -5%) rotate(270deg);
            }
        }

        /* Moving Wave Pattern */
        body::after {
            content: '';
            position: absolute;
            width: 200%;
            height: 200%;
            background: 
                repeating-linear-gradient(
                    45deg,
                    transparent,
                    transparent 50px,
                    rgba(255, 255, 255, 0.03) 50px,
                    rgba(255, 255, 255, 0.03) 100px
                ),
                repeating-linear-gradient(
                    -45deg,
                    transparent,
                    transparent 50px,
                    rgba(255, 255, 255, 0.02) 50px,
                    rgba(255, 255, 255, 0.02) 100px
                );
            animation: waveMove 20s linear infinite;
        }

        @keyframes waveMove {
            0% { transform: translate(0, 0); }
            100% { transform: translate(100px, 100px); }
        }

        /* Floating Particles - More and Bigger */
        .particle {
            position: absolute;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.3) 0%, rgba(255, 255, 255, 0.1) 50%, transparent 100%);
            border-radius: 50%;
            pointer-events: none;
            animation: floatParticle linear infinite;
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.3);
        }

        .particle:nth-child(1) {
            width: 120px;
            height: 120px;
            left: 5%;
            animation-duration: 18s;
            animation-delay: 0s;
        }

        .particle:nth-child(2) {
            width: 90px;
            height: 90px;
            right: 10%;
            animation-duration: 15s;
            animation-delay: 2s;
        }

        .particle:nth-child(3) {
            width: 150px;
            height: 150px;
            left: 60%;
            animation-duration: 22s;
            animation-delay: 4s;
        }

        .particle:nth-child(4) {
            width: 80px;
            height: 80px;
            left: 25%;
            animation-duration: 16s;
            animation-delay: 1s;
        }

        .particle:nth-child(5) {
            width: 110px;
            height: 110px;
            right: 20%;
            animation-duration: 20s;
            animation-delay: 3s;
        }

        .particle:nth-child(6) {
            width: 100px;
            height: 100px;
            left: 45%;
            animation-duration: 19s;
            animation-delay: 5s;
        }

        .particle:nth-child(7) {
            width: 130px;
            height: 130px;
            right: 35%;
            animation-duration: 17s;
            animation-delay: 2.5s;
        }

        .particle:nth-child(8) {
            width: 95px;
            height: 95px;
            left: 15%;
            animation-duration: 21s;
            animation-delay: 4.5s;
        }

        @keyframes floatParticle {
            0% {
                transform: translateY(100vh) translateX(0) rotate(0deg) scale(0);
                opacity: 0;
            }
            10% {
                opacity: 0.6;
                transform: translateY(90vh) translateX(20px) rotate(45deg) scale(1);
            }
            50% {
                opacity: 0.8;
                transform: translateY(50vh) translateX(-30px) rotate(180deg) scale(1.2);
            }
            90% {
                opacity: 0.6;
            }
            100% {
                transform: translateY(-20vh) translateX(50px) rotate(360deg) scale(0.8);
                opacity: 0;
            }
        }

        /* Geometric Shapes - Bigger and More Animated */
        .shape {
            position: absolute;
            pointer-events: none;
            filter: drop-shadow(0 0 20px rgba(255, 255, 255, 0.2));
        }

        .shape.circle {
            width: 250px;
            height: 250px;
            border: 3px solid rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            right: 5%;
            top: 10%;
            animation: rotateAndPulse 12s ease-in-out infinite;
        }

        .shape.square {
            width: 200px;
            height: 200px;
            border: 3px solid rgba(255, 255, 255, 0.15);
            left: 3%;
            bottom: 10%;
            animation: rotateAndFloat 15s ease-in-out infinite;
            transform-origin: center;
        }

        .shape.triangle {
            width: 0;
            height: 0;
            border-left: 100px solid transparent;
            border-right: 100px solid transparent;
            border-bottom: 170px solid rgba(255, 255, 255, 0.12);
            left: 45%;
            top: 3%;
            animation: triangleFloat 18s ease-in-out infinite;
        }

        .shape.hexagon {
            width: 180px;
            height: 180px;
            background: rgba(255, 255, 255, 0.08);
            clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);
            right: 30%;
            bottom: 15%;
            animation: hexagonSpin 20s linear infinite;
        }

        @keyframes rotateAndPulse {
            0%, 100% { 
                transform: rotate(0deg) scale(1);
                opacity: 0.3;
            }
            25% { 
                transform: rotate(90deg) scale(1.2);
                opacity: 0.5;
            }
            50% { 
                transform: rotate(180deg) scale(1);
                opacity: 0.3;
            }
            75% { 
                transform: rotate(270deg) scale(1.2);
                opacity: 0.5;
            }
        }

        @keyframes rotateAndFloat {
            0%, 100% { 
                transform: rotate(0deg) translateY(0) scale(1);
            }
            25% { 
                transform: rotate(90deg) translateY(-30px) scale(1.1);
            }
            50% { 
                transform: rotate(180deg) translateY(0) scale(1);
            }
            75% { 
                transform: rotate(270deg) translateY(-30px) scale(1.1);
            }
        }

        @keyframes triangleFloat {
            0%, 100% { 
                transform: translateY(0) rotate(0deg);
                opacity: 0.4;
            }
            50% { 
                transform: translateY(-50px) rotate(180deg);
                opacity: 0.6;
            }
        }

        @keyframes hexagonSpin {
            0% { 
                transform: rotate(0deg) scale(1);
            }
            50% { 
                transform: rotate(180deg) scale(1.15);
            }
            100% { 
                transform: rotate(360deg) scale(1);
            }
        }

        /* Mouse Tracking Light Effect */
        .mouse-light {
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
            transition: left 0.1s ease-out, top 0.1s ease-out;
            z-index: 1;
            left: -400px;
            top: -400px;
        }

        .login-wrapper {
            width: 100%;
            max-width: 450px;
            position: relative;
            z-index: 1;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Mascot Styles */
        .mascot-container {
            margin: 30px 0 20px 0;
            position: relative;
            z-index: 2;
        }

        .mascot {
            width: 140px;
            height: 140px;
            margin: 0 auto;
            position: relative;
        }

        .mascot-face {
            width: 100%;
            height: 100%;
            background: linear-gradient(145deg, #ffffff 0%, #f0f0f0 100%);
            border-radius: 50%;
            position: relative;
            box-shadow: 
                0 10px 30px rgba(0, 0, 0, 0.2),
                inset 0 -5px 15px rgba(0, 0, 0, 0.05);
            overflow: visible;
        }

        /* Ears */
        .mascot-face::before,
        .mascot-face::after {
            content: '';
            position: absolute;
            width: 35px;
            height: 45px;
            background: linear-gradient(145deg, #ffffff 0%, #f0f0f0 100%);
            border-radius: 50% 50% 0 0;
            top: -15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .mascot-face::before {
            left: 10px;
            transform: rotate(-15deg);
        }

        .mascot-face::after {
            right: 10px;
            transform: rotate(15deg);
        }

        .mascot-eyes {
            position: absolute;
            top: 42%;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 28px;
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        .eye {
            width: 28px;
            height: 32px;
            background: white;
            border-radius: 50% 50% 45% 45%;
            border: 3px solid #2c3e50;
            position: relative;
            overflow: hidden;
            box-shadow: inset 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .pupil {
            width: 14px;
            height: 14px;
            background: radial-gradient(circle at 30% 30%, #34495e 0%, #1a252f 100%);
            border-radius: 50%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            transition: all 0.15s ease-out;
        }

        .pupil::after {
            content: '';
            position: absolute;
            width: 5px;
            height: 5px;
            background: white;
            border-radius: 50%;
            top: 3px;
            left: 3px;
            opacity: 0.9;
        }

        /* Eyebrows */
        .mascot-eyes::before,
        .mascot-eyes::after {
            content: '';
            position: absolute;
            width: 30px;
            height: 4px;
            background: #2c3e50;
            border-radius: 2px;
            top: -12px;
            transition: all 0.3s ease;
        }

        .mascot-eyes::before {
            left: -5px;
            transform: rotate(-10deg);
        }

        .mascot-eyes::after {
            right: -5px;
            transform: rotate(10deg);
        }

        /* Nose */
        .mascot-face .nose {
            position: absolute;
            width: 16px;
            height: 12px;
            background: #e74c3c;
            border-radius: 50% 50% 50% 50% / 60% 60% 40% 40%;
            bottom: 42%;
            left: 50%;
            transform: translateX(-50%);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        /* Mouth */
        .mascot-face .mouth {
            position: absolute;
            width: 40px;
            height: 20px;
            border: 3px solid #2c3e50;
            border-top: none;
            border-radius: 0 0 40px 40px;
            bottom: 30%;
            left: 50%;
            transform: translateX(-50%);
            transition: all 0.3s ease;
        }

        .mascot-face .mouth.smile {
            height: 25px;
            border-radius: 0 0 50px 50px;
        }

        .mascot-hands {
            position: absolute;
            width: 100%;
            top: 50%;
            left: 0;
            display: flex;
            justify-content: center;
            gap: 8px;
            opacity: 0;
            transform: translateY(20px) scale(0.8);
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            pointer-events: none;
            z-index: 10;
        }

        .mascot-hands.covering {
            opacity: 1;
            top: 41%;
            transform: translateY(0) scale(1);
        }

        .hand {
            font-size: 38px;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
            transform-origin: center;
        }

        .left-hand {
            animation: handSlideInLeft 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            transform: rotate(-15deg);
        }

        .right-hand {
            animation: handSlideInRight 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            transform: rotate(15deg);
        }

        @keyframes handSlideInLeft {
            0% {
                transform: translateX(-80px) translateY(-20px) rotate(-45deg) scale(0.5);
                opacity: 0;
            }
            60% {
                transform: translateX(-5px) translateY(0) rotate(-20deg) scale(1.1);
            }
            100% {
                transform: translateX(0) translateY(0) rotate(-15deg) scale(1);
                opacity: 1;
            }
        }

        @keyframes handSlideInRight {
            0% {
                transform: translateX(80px) translateY(-20px) rotate(45deg) scale(0.5);
                opacity: 0;
            }
            60% {
                transform: translateX(5px) translateY(0) rotate(20deg) scale(1.1);
            }
            100% {
                transform: translateX(0) translateY(0) rotate(15deg) scale(1);
                opacity: 1;
            }
        }

        /* Eye movements */
        .mascot-eyes.looking-left .pupil {
            transform: translate(-90%, -50%);
        }

        .mascot-eyes.looking-right .pupil {
            transform: translate(-10%, -50%);
        }

        .mascot-eyes.looking-up .pupil {
            transform: translate(-50%, -90%);
        }

        .mascot-eyes.closed {
            transition: all 0.3s ease;
        }

        .mascot-eyes.closed .eye {
            height: 6px;
            border-radius: 50%;
            transform: scaleY(0.15);
            border-width: 2px 3px;
            transition: all 0.3s ease;
        }

        .mascot-eyes.closed .pupil {
            opacity: 0;
        }

        .mascot-eyes.closed::before,
        .mascot-eyes.closed::after {
            top: -6px;
            transition: all 0.3s ease;
        }

        .mascot-eyes.closed::before {
            transform: rotate(-30deg);
        }

        .mascot-eyes.closed::after {
            transform: rotate(30deg);
        }

        /* Happy state */
        .mascot-eyes.happy::before {
            transform: rotate(-20deg);
        }

        .mascot-eyes.happy::after {
            transform: rotate(20deg);
        }

        .login-header h2 {
            color: white;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            position: relative;
            z-index: 2;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .login-header p {
            color: rgba(255, 255, 255, 0.95);
            font-size: 14px;
            position: relative;
            z-index: 2;
        }

        .login-body {
            padding: 40px 30px;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .alert-danger {
            background: #fee;
            color: #c33;
            border-left: 4px solid #c33;
        }

        .alert-success {
            background: #efe;
            color: #3c3;
            border-left: 4px solid #3c3;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #334155;
            font-weight: 600;
            font-size: 14px;
        }

        .form-label i {
            margin-right: 6px;
            color: #667eea;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #f8fafc;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .btn {
            padding: 14px 24px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-primary.loading {
            pointer-events: none;
            opacity: 0.8;
        }

        .btn-primary.loading .btn-text {
            opacity: 0;
        }

        .btn-primary .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
        }

        .btn-primary.loading .spinner {
            display: block;
        }

        @keyframes spin {
            to { transform: translate(-50%, -50%) rotate(360deg); }
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #475569;
        }

        .btn-secondary:hover {
            background: #cbd5e1;
        }

        .w-100 {
            width: 100%;
        }

        .btn-lg {
            padding: 16px 24px;
            font-size: 16px;
        }

        .mt-3 {
            margin-top: 16px;
        }

        .text-center {
            text-align: center;
        }

        .login-footer {
            background: #f8fafc;
            padding: 24px 30px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }

        .login-footer p {
            color: #64748b;
            font-size: 13px;
            margin: 6px 0;
        }

        .login-footer i {
            color: #667eea;
            margin-right: 6px;
        }

        small {
            color: #64748b;
            font-size: 12px;
            display: block;
            margin-top: 8px;
        }

        small i {
            margin-right: 4px;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #64748b;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: #667eea;
        }

        /* OTP Input Boxes */
        .otp-input-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-bottom: 8px;
        }

        .otp-input {
            width: 50px;
            height: 56px;
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            background: #f8fafc;
            color: #1e293b;
            transition: all 0.3s ease;
            caret-color: #667eea;
        }

        .otp-input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            transform: scale(1.05);
        }

        .otp-input:not(:placeholder-shown) {
            border-color: #10b981;
            background: #f0fdf4;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        @media (max-width: 480px) {
            .login-card {
                border-radius: 16px;
            }

            .login-header {
                padding: 30px 20px;
            }

            .login-body {
                padding: 30px 20px;
            }

            .login-logo {
                width: 70px;
                height: 70px;
            }

            .celebration-icon {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>

<!-- Mouse Tracking Light -->
<div class="mouse-light" id="mouseLight"></div>

<!-- Animated Background Elements -->
<div class="particle"></div>
<div class="particle"></div>
<div class="particle"></div>
<div class="particle"></div>
<div class="particle"></div>
<div class="particle"></div>
<div class="particle"></div>
<div class="particle"></div>

<div class="shape circle"></div>
<div class="shape square"></div>
<div class="shape triangle"></div>
<div class="shape hexagon"></div>

<div class="login-wrapper">
    <div class="login-card">
        <div class="login-header">
            <!-- Animated Mascot -->
            <div class="mascot-container">
                <div class="mascot">
                    <div class="mascot-face">
                        <div class="mascot-eyes" id="mascotEyes">
                            <div class="eye left-eye">
                                <div class="pupil"></div>
                            </div>
                            <div class="eye right-eye">
                                <div class="pupil"></div>
                            </div>
                        </div>
                        <div class="nose"></div>
                        <div class="mouth" id="mascotMouth"></div>
                        <div class="mascot-hands" id="mascotHands">
                            <div class="hand left-hand">✋</div>
                            <div class="hand right-hand">✋</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <h2>Admin Portal</h2>
            <p>NIELIT Bhubaneswar Management System</p>
        </div>
        
        <div class="login-body">
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($show_otp_form): ?>
                <!-- OTP Verification Form -->
                <form method="POST" action="login.php" id="otpForm">
                    <div class="form-group">
                        <label for="otp-1" class="form-label">
                            <i class="fas fa-key"></i> Enter OTP
                        </label>
                        <div class="otp-input-group">
                            <input type="text" class="otp-input" id="otp-1" maxlength="1" pattern="\d" required autofocus>
                            <input type="text" class="otp-input" id="otp-2" maxlength="1" pattern="\d" required>
                            <input type="text" class="otp-input" id="otp-3" maxlength="1" pattern="\d" required>
                            <input type="text" class="otp-input" id="otp-4" maxlength="1" pattern="\d" required>
                            <input type="text" class="otp-input" id="otp-5" maxlength="1" pattern="\d" required>
                            <input type="text" class="otp-input" id="otp-6" maxlength="1" pattern="\d" required>
                        </div>
                        <input type="hidden" name="otp" id="otp-hidden">
                        <small>
                            <i class="fas fa-info-circle"></i> OTP sent to your registered email
                        </small>
                    </div>
                    
                    <button type="submit" name="verify_otp" class="btn btn-primary w-100 btn-lg" id="verifyBtn">
                        <span class="btn-text">
                            <i class="fas fa-check"></i> Verify OTP
                        </span>
                        <span class="spinner"></span>
                    </button>
                </form>
                
                <form method="POST" action="login.php" class="mt-3 text-center">
                    <button type="submit" name="resend_otp" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Resend OTP
                    </button>
                </form>
            <?php else: ?>
                <!-- Login Form -->
                <form method="POST" action="login.php">
                    <div class="form-group">
                        <label for="username" class="form-label">
                            <i class="fas fa-user"></i> Username
                        </label>
                        <input type="text" class="form-control" id="username" name="username" 
                               placeholder="Enter your username" required autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <div style="position: relative;">
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Enter your password" required style="padding-right: 45px;">
                            <span onclick="togglePassword()" class="password-toggle">
                                <i class="fas fa-eye" id="togglePasswordIcon"></i>
                            </span>
                        </div>
                    </div>
                    
                    <button type="submit" name="login" class="btn btn-primary w-100 btn-lg" id="loginBtn">
                        <span class="btn-text">
                            <i class="fas fa-sign-in-alt"></i> Login to Dashboard
                        </span>
                        <span class="spinner"></span>
                    </button>
                </form>
            <?php endif; ?>
        </div>
        
        <div class="login-footer">
            <p><i class="fas fa-shield-alt"></i> Secure Admin Access with OTP Verification</p>
            <p>&copy; 2025 NIELIT Bhubaneswar. All rights reserved.</p>
        </div>
    </div>
</div>

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

// Mascot Animation
document.addEventListener('DOMContentLoaded', function() {
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const mascotEyes = document.getElementById('mascotEyes');
    const mascotHands = document.getElementById('mascotHands');
    const mascotMouth = document.getElementById('mascotMouth');
    
    // Loading button animation
    const loginBtn = document.getElementById('loginBtn');
    const verifyBtn = document.getElementById('verifyBtn');
    
    if (loginBtn) {
        loginBtn.closest('form').addEventListener('submit', function() {
            // Show loading state but don't disable to allow form submission
            loginBtn.classList.add('loading');
        });
    }
    
    if (verifyBtn) {
        verifyBtn.closest('form').addEventListener('submit', function() {
            // Show loading state but don't disable to allow form submission
            verifyBtn.classList.add('loading');
        });
    }
    
    // Initial happy state
    if (mascotMouth) {
        mascotMouth.classList.add('smile');
    }
    
    if (usernameInput) {
        // Username field - mascot looks at it
        usernameInput.addEventListener('focus', function() {
            mascotEyes.classList.remove('closed', 'looking-right', 'looking-up');
            mascotEyes.classList.add('looking-left', 'happy');
            mascotHands.classList.remove('covering');
            if (mascotMouth) mascotMouth.classList.add('smile');
        });
        
        usernameInput.addEventListener('blur', function() {
            mascotEyes.classList.remove('looking-left', 'happy');
            if (mascotMouth) mascotMouth.classList.remove('smile');
        });
        
        // Track typing in username
        usernameInput.addEventListener('input', function() {
            mascotEyes.classList.add('happy');
            if (mascotMouth) mascotMouth.classList.add('smile');
        });
    }
    
    if (passwordInput) {
        // Password field - mascot covers eyes
        passwordInput.addEventListener('focus', function() {
            mascotEyes.classList.remove('looking-left', 'looking-right', 'happy');
            mascotEyes.classList.add('closed');
            mascotHands.classList.add('covering');
            if (mascotMouth) mascotMouth.classList.remove('smile');
        });
        
        passwordInput.addEventListener('blur', function() {
            mascotEyes.classList.remove('closed');
            mascotHands.classList.remove('covering');
            if (mascotMouth) mascotMouth.classList.add('smile');
        });
    }
    
    // OTP input boxes - handle 6-digit input
    const otpInputs = document.querySelectorAll('.otp-input');
    const otpHidden = document.getElementById('otp-hidden');
    
    if (otpInputs.length > 0) {
        // Mascot closes eyes when any OTP box is focused (like password)
        otpInputs.forEach((input, index) => {
            input.addEventListener('focus', function() {
                mascotEyes.classList.remove('looking-left', 'looking-right', 'happy');
                mascotEyes.classList.add('closed');
                mascotHands.classList.add('covering');
                if (mascotMouth) mascotMouth.classList.remove('smile');
            });
            
            input.addEventListener('blur', function() {
                // Only remove closed state if all inputs are blurred
                setTimeout(() => {
                    if (!document.querySelector('.otp-input:focus')) {
                        mascotEyes.classList.remove('closed');
                        mascotHands.classList.remove('covering');
                        if (mascotMouth) mascotMouth.classList.add('smile');
                    }
                }, 100);
            });
            
            input.addEventListener('input', function(e) {
                const value = this.value;
                
                // Only allow digits
                if (!/^\d*$/.test(value)) {
                    this.value = '';
                    return;
                }
                
                // Move to next input if digit entered
                if (value.length === 1 && index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                }
                
                // Update hidden field with combined OTP
                updateOTPHidden();
                
                // Keep eyes closed even when all 6 digits are entered
                if (isOTPComplete()) {
                    // Eyes stay closed, no celebration animation
                }
            });
            
            input.addEventListener('keydown', function(e) {
                // Handle backspace - move to previous input
                if (e.key === 'Backspace' && !this.value && index > 0) {
                    otpInputs[index - 1].focus();
                    otpInputs[index - 1].value = '';
                    updateOTPHidden();
                }
                
                // Handle arrow keys
                if (e.key === 'ArrowLeft' && index > 0) {
                    otpInputs[index - 1].focus();
                }
                if (e.key === 'ArrowRight' && index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                }
            });
            
            // Handle paste
            input.addEventListener('paste', function(e) {
                e.preventDefault();
                const pastedData = e.clipboardData.getData('text').trim();
                
                // Only process if it's 6 digits
                if (/^\d{6}$/.test(pastedData)) {
                    pastedData.split('').forEach((digit, i) => {
                        if (otpInputs[i]) {
                            otpInputs[i].value = digit;
                        }
                    });
                    updateOTPHidden();
                    otpInputs[5].focus();
                    
                    // Eyes stay closed, no celebration
                }
            });
        });
        
        // Helper function to update hidden field
        function updateOTPHidden() {
            if (otpHidden) {
                const otp = Array.from(otpInputs).map(input => input.value).join('');
                otpHidden.value = otp;
            }
        }
        
        // Helper function to check if OTP is complete
        function isOTPComplete() {
            return Array.from(otpInputs).every(input => input.value.length === 1);
        }
    }
    
    // Mouse tracking - mascot follows cursor when not focused on inputs
    document.addEventListener('mousemove', function(e) {
        const activeElement = document.activeElement;
        const isOTPInput = activeElement && activeElement.classList.contains('otp-input');
        
        if (activeElement !== usernameInput && 
            activeElement !== passwordInput && 
            !isOTPInput) {
            
            const mascotFace = document.querySelector('.mascot-face');
            if (!mascotFace) return;
            
            const mascotRect = mascotFace.getBoundingClientRect();
            const mascotCenterX = mascotRect.left + mascotRect.width / 2;
            const mascotCenterY = mascotRect.top + mascotRect.height / 2;
            
            const deltaX = e.clientX - mascotCenterX;
            const deltaY = e.clientY - mascotCenterY;
            
            const pupils = document.querySelectorAll('.pupil');
            pupils.forEach(pupil => {
                const maxMove = 6;
                const moveX = Math.max(-maxMove, Math.min(maxMove, deltaX / 20));
                const moveY = Math.max(-maxMove, Math.min(maxMove, deltaY / 20));
                
                pupil.style.transform = `translate(calc(-50% + ${moveX}px), calc(-50% + ${moveY}px))`;
            });
        }
    });
    
    // Mouse tracking light effect for background
    const mouseLight = document.getElementById('mouseLight');
    if (mouseLight) {
        document.addEventListener('mousemove', function(e) {
            // Center the light on the cursor (light is 400px, so offset by half = 200px)
            const x = e.clientX - 200;
            const y = e.clientY - 200;
            mouseLight.style.left = x + 'px';
            mouseLight.style.top = y + 'px';
        });
    }
});
</script>

</body>
</html>
<?php $conn->close(); ?>
