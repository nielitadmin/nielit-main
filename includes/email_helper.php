<?php
/**
 * Email Helper Functions
 * NIELIT Bhubaneswar Student Management System
 * 
 * Handles email sending using PHPMailer
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer
require_once __DIR__ . '/../libraries/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../libraries/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../libraries/PHPMailer/src/SMTP.php';

// Load email configuration
require_once __DIR__ . '/../config/email.php';

/**
 * Send registration confirmation email
 * 
 * @param string $to_email Recipient email address
 * @param string $student_name Student's full name
 * @param string $student_id Generated student ID
 * @param string $password Generated password
 * @param string $course_name Course name
 * @param string $training_center Training center name
 * @return bool True on success, false on failure
 */
function sendRegistrationEmail($to_email, $student_name, $student_id, $password, $course_name, $training_center) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to_email, $student_name);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Registration Successful - NIELIT Bhubaneswar';
        $mail->Body = getRegistrationEmailTemplate($student_name, $student_id, $password, $course_name, $training_center);
        $mail->AltBody = getRegistrationEmailPlainText($student_name, $student_id, $password, $course_name, $training_center);
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Get HTML email template for registration confirmation
 */
function getRegistrationEmailTemplate($student_name, $student_id, $password, $course_name, $training_center) {
    $current_year = date('Y');
    
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Successful</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #0d47a1 0%, #1976d2 100%); padding: 30px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 28px; font-weight: 700;">
                                🎓 Registration Successful!
                            </h1>
                            <p style="color: #e3f2fd; margin: 10px 0 0 0; font-size: 14px;">
                                NIELIT Bhubaneswar
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <p style="color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                Dear <strong>{$student_name}</strong>,
                            </p>
                            
                            <p style="color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                Congratulations! Your registration has been successfully completed. Below are your login credentials:
                            </p>
                            
                            <!-- Credentials Box -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #e3f2fd; border-left: 4px solid #0d47a1; border-radius: 4px; margin: 20px 0;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <table width="100%" cellpadding="8" cellspacing="0">
                                            <tr>
                                                <td style="color: #0d47a1; font-weight: 700; font-size: 14px; width: 40%;">
                                                    Student ID:
                                                </td>
                                                <td style="color: #333; font-size: 16px; font-weight: 700; font-family: 'Courier New', monospace;">
                                                    {$student_id}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="color: #0d47a1; font-weight: 700; font-size: 14px;">
                                                    Password:
                                                </td>
                                                <td style="color: #333; font-size: 16px; font-weight: 700; font-family: 'Courier New', monospace;">
                                                    {$password}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="color: #0d47a1; font-weight: 700; font-size: 14px;">
                                                    Course:
                                                </td>
                                                <td style="color: #333; font-size: 14px;">
                                                    {$course_name}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="color: #0d47a1; font-weight: 700; font-size: 14px;">
                                                    Training Center:
                                                </td>
                                                <td style="color: #333; font-size: 14px;">
                                                    {$training_center}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Important Notice -->
                            <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px; padding: 15px; margin: 20px 0;">
                                <p style="color: #856404; font-size: 14px; margin: 0; line-height: 1.6;">
                                    <strong>⚠️ Important:</strong> Please save these credentials securely. You will need them to access your student portal.
                                </p>
                            </div>
                            
                            <!-- Login Button -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 30px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="https://nielitbhubaneswar.in/student/login.php" 
                                           style="display: inline-block; background: linear-gradient(135deg, #0d47a1 0%, #1976d2 100%); color: #ffffff; text-decoration: none; padding: 14px 40px; border-radius: 6px; font-weight: 700; font-size: 16px; box-shadow: 0 4px 6px rgba(13, 71, 161, 0.3);">
                                            Login to Student Portal
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="color: #666; font-size: 14px; line-height: 1.6; margin: 20px 0 0 0;">
                                If you have any questions or need assistance, please contact us at:
                            </p>
                            
                            <p style="color: #0d47a1; font-size: 14px; margin: 10px 0 0 0;">
                                📧 Email: admin@nielitbhubaneswar.in<br>
                                📞 Phone: 0674-2960354
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f5f5f5; padding: 20px; text-align: center; border-top: 1px solid #e0e0e0;">
                            <p style="color: #666; font-size: 12px; margin: 0 0 10px 0;">
                                © {$current_year} NIELIT Bhubaneswar. All rights reserved.
                            </p>
                            <p style="color: #999; font-size: 11px; margin: 0;">
                                This is an automated email. Please do not reply to this message.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}

/**
 * Get plain text version of registration email
 */
function getRegistrationEmailPlainText($student_name, $student_id, $password, $course_name, $training_center) {
    return <<<TEXT
REGISTRATION SUCCESSFUL - NIELIT Bhubaneswar

Dear {$student_name},

Congratulations! Your registration has been successfully completed.

YOUR LOGIN CREDENTIALS:
========================
Student ID: {$student_id}
Password: {$password}
Course: {$course_name}
Training Center: {$training_center}

IMPORTANT: Please save these credentials securely. You will need them to access your student portal.

Login to Student Portal: https://nielitbhubaneswar.in/student/login.php

If you have any questions or need assistance, please contact us at:
Email: admin@nielitbhubaneswar.in
Phone: 0674-2960354

© 2026 NIELIT Bhubaneswar. All rights reserved.
This is an automated email. Please do not reply to this message.
TEXT;
}

/**
 * Send password reset email
 * 
 * @param string $to_email Recipient email address
 * @param string $student_name Student's full name
 * @param string $student_id Student ID
 * @param string $new_password New password
 * @return bool True on success, false on failure
 */
function sendPasswordResetEmail($to_email, $student_name, $student_id, $new_password) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to_email, $student_name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset - NIELIT Bhubaneswar';
        $mail->Body = "
            <h2>Password Reset Successful</h2>
            <p>Dear {$student_name},</p>
            <p>Your password has been reset successfully.</p>
            <p><strong>Student ID:</strong> {$student_id}</p>
            <p><strong>New Password:</strong> {$new_password}</p>
            <p>Please login with your new credentials and change your password immediately.</p>
            <p>Best regards,<br>NIELIT Bhubaneswar</p>
        ";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Test email configuration
 * 
 * @param string $test_email Email address to send test email
 * @return array Result array with success status and message
 */
function testEmailConfiguration($test_email) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($test_email);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Test Email - NIELIT Bhubaneswar';
        $mail->Body = '<h2>Email Configuration Test</h2><p>If you receive this email, your email configuration is working correctly!</p>';
        
        $mail->send();
        return ['success' => true, 'message' => 'Test email sent successfully!'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => "Email sending failed: {$mail->ErrorInfo}"];
    }
}
?>
