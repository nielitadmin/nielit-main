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
require_once __DIR__ . '/phpmailer_smtp.php';

/**
 * Resolve a production-safe base URL for links inside outbound emails.
 * Avoids broken links when APP_URL still points to localhost or /public_html.
 */
function getEmailBaseUrl(): string {
    $fallback = 'https://nielitbhubaneswar.in';

    if (!empty($_SERVER['HTTP_HOST'])) {
        $host = strtolower((string) $_SERVER['HTTP_HOST']);
        if (!in_array($host, ['localhost', '127.0.0.1'], true) && strpos($host, '.') !== false) {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
            return ($https ? 'https' : 'http') . '://' . $host;
        }
    }

    if (defined('APP_URL')) {
        $base = rtrim((string) APP_URL, '/');
        if ($base !== '') {
            $base = preg_replace('#/public_html$#', '', $base);
            $host = parse_url($base, PHP_URL_HOST);
            if ($host && !in_array(strtolower((string) $host), ['localhost', '127.0.0.1'], true)) {
                return $base;
            }
        }
    }

    return $fallback;
}

/**
 * Build an absolute public page URL for email templates.
 */
function getEmailPublicPageUrl(string $path): string {
    $path = ltrim(str_replace('\\', '/', trim($path)), '/');
    return getEmailBaseUrl() . '/' . $path;
}

function registrationEmailAsyncSecret(): string
{
    return hash('sha256', (defined('SMTP_USERNAME') ? SMTP_USERNAME : 'nielit') . '|registration|' . (defined('APP_URL') ? APP_URL : ''));
}

function isDeliverableRegistrationEmail(string $email): bool
{
    $email = trim($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    if (preg_match('/@(workshop\.nielit\.local|localhost)$/i', $email)) {
        return false;
    }
    return true;
}

function buildRegistrationEmailJobToken(array $job): string
{
    $body = json_encode([
        'email' => $job['email'] ?? '',
        'student_id' => $job['student_id'] ?? '',
        'ts' => $job['ts'] ?? 0,
    ], JSON_UNESCAPED_UNICODE);
    return hash_hmac('sha256', $body, registrationEmailAsyncSecret());
}

function verifyRegistrationEmailJobToken(array $job): bool
{
    $expected = buildRegistrationEmailJobToken($job);
    $given = (string) ($job['token'] ?? '');
    if ($given === '' || !hash_equals($expected, $given)) {
        return false;
    }
    $ts = (int) ($job['ts'] ?? 0);
    return $ts > 0 && (time() - $ts) <= 900;
}

/**
 * Queue confirmation email without blocking the registration response.
 */
function dispatchRegistrationEmailAsync(
    string $to_email,
    string $student_name,
    string $student_id,
    string $password,
    string $course_name,
    string $training_center
): bool {
    if (!isDeliverableRegistrationEmail($to_email)) {
        return false;
    }

    $job = [
        'email' => $to_email,
        'name' => $student_name,
        'student_id' => $student_id,
        'password' => $password,
        'course_name' => $course_name,
        'training_center' => $training_center,
        'ts' => time(),
    ];
    $job['token'] = buildRegistrationEmailJobToken($job);
    $encoded = base64_encode(json_encode($job, JSON_UNESCAPED_UNICODE));

    if (function_exists('curl_init')) {
        $url = getEmailBaseUrl() . '/student/async_send_registration_email.php';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['payload' => $encoded]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT_MS => 300,
            CURLOPT_TIMEOUT_MS => 500,
            CURLOPT_NOSIGNAL => 1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER => ['X-Registration-Email: 1'],
        ]);
        @curl_exec($ch);
        curl_close($ch);
        return true;
    }

    register_shutdown_function(static function () use ($job) {
        sendRegistrationEmail(
            $job['email'],
            $job['name'],
            $job['student_id'],
            $job['password'],
            $job['course_name'],
            $job['training_center']
        );
    });
    return true;
}

function finalizeRegistrationRedirect(string $url): void
{
    header('Location: ' . $url);
    if (function_exists('session_write_close')) {
        session_write_close();
    }
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
        @flush();
    }
    exit;
}

/**
 * Send registration confirmation email
 * 
 * @param string $to_email Recipient email address
 * @param string $student_name Student's full name
 * @param string $student_id Generated student ID
 * @param string $password Generated password
 * @param string $course_name Course name
 * @param string $training_center Training centre name
 * @return bool True on success, false on failure
 */
function sendRegistrationEmail($to_email, $student_name, $student_id, $password, $course_name, $training_center) {
    $mail = new PHPMailer(true);
    
    try {
        configurePhpMailerSmtp($mail, ['timeout' => 8, 'keep_alive' => false]);
        
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
                        <td style="background: linear-gradient(135deg, #0a1628 0%, #112240 100%); padding: 30px; text-align: center;">
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
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #e3f2fd; border-left: 4px solid #0a1628; border-radius: 4px; margin: 20px 0;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <table width="100%" cellpadding="8" cellspacing="0">
                                            <tr>
                                                <td style="color: #0a1628; font-weight: 700; font-size: 14px; width: 40%;">
                                                    Student ID:
                                                </td>
                                                <td style="color: #333; font-size: 16px; font-weight: 700; font-family: 'Courier New', monospace;">
                                                    {$student_id}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="color: #0a1628; font-weight: 700; font-size: 14px;">
                                                    Password:
                                                </td>
                                                <td style="color: #333; font-size: 16px; font-weight: 700; font-family: 'Courier New', monospace;">
                                                    {$password}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="color: #0a1628; font-weight: 700; font-size: 14px;">
                                                    Course:
                                                </td>
                                                <td style="color: #333; font-size: 14px;">
                                                    {$course_name}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="color: #0a1628; font-weight: 700; font-size: 14px;">
                                                    Training Centre:
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
                                           style="display: inline-block; background: linear-gradient(135deg, #0a1628 0%, #112240 100%); color: #ffffff; text-decoration: none; padding: 14px 40px; border-radius: 6px; font-weight: 700; font-size: 16px; box-shadow: 0 4px 6px rgba(13, 71, 161, 0.3);">
                                            Login to Student Portal
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="color: #666; font-size: 14px; line-height: 1.6; margin: 20px 0 0 0;">
                                If you have any questions or need assistance, please contact us at:
                            </p>
                            
                            <p style="color: #0a1628; font-size: 14px; margin: 10px 0 0 0;">
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
Training Centre: {$training_center}

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
        configurePhpMailerSmtp($mail);
        
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
 * Send registration rejection email with reason and reapply instructions.
 */
function sendRegistrationRejectionEmail(
    $to_email,
    $student_name,
    $student_id,
    $course_label,
    $rejection_reason,
    $rejection_note = ''
) {
    if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        configurePhpMailerSmtp($mail);

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to_email, $student_name);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

        $mail->isHTML(true);
        $mail->Subject = 'Registration Update - NIELIT Bhubaneswar';
        $mail->Body = getRegistrationRejectionEmailTemplate(
            $student_name,
            $student_id,
            $course_label,
            $rejection_reason,
            $rejection_note
        );
        $mail->AltBody = getRegistrationRejectionEmailPlainText(
            $student_name,
            $student_id,
            $course_label,
            $rejection_reason,
            $rejection_note
        );

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Rejection email failed: {$mail->ErrorInfo}");
        return false;
    }
}

function getRegistrationRejectionEmailTemplate(
    $student_name,
    $student_id,
    $course_label,
    $rejection_reason,
    $rejection_note = ''
) {
    $current_year = date('Y');
    $safe_name = htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8');
    $safe_id = htmlspecialchars($student_id, ENT_QUOTES, 'UTF-8');
    $safe_course = htmlspecialchars($course_label, ENT_QUOTES, 'UTF-8');
    $safe_reason = htmlspecialchars($rejection_reason, ENT_QUOTES, 'UTF-8');
    $safe_note = htmlspecialchars(trim($rejection_note), ENT_QUOTES, 'UTF-8');
    $courses_url = htmlspecialchars(getEmailPublicPageUrl('public/courses.php'), ENT_QUOTES, 'UTF-8');
    $contact_url = htmlspecialchars(getEmailPublicPageUrl('public/contact.php'), ENT_QUOTES, 'UTF-8');
    $courses_url_text = htmlspecialchars(getEmailPublicPageUrl('public/courses.php'), ENT_QUOTES, 'UTF-8');

    $note_block = '';
    if ($safe_note !== '') {
        $note_block = <<<HTML
                            <p style="color:#374151;font-size:15px;line-height:1.6;margin:0 0 16px 0;">
                                <strong>Additional details:</strong><br>{$safe_note}
                            </p>
HTML;
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Update</title>
</head>
<body style="margin:0;padding:0;font-family:Arial,sans-serif;background-color:#f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f4;padding:20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                    <tr>
                        <td style="background:linear-gradient(135deg,#991b1b 0%,#dc2626 100%);padding:28px;text-align:center;">
                            <h1 style="color:#ffffff;margin:0;font-size:24px;font-weight:700;">Registration Not Approved</h1>
                            <p style="color:#fee2e2;margin:10px 0 0 0;font-size:14px;">NIELIT Bhubaneswar</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 28px;">
                            <p style="color:#333;font-size:16px;line-height:1.6;margin:0 0 16px 0;">
                                Dear <strong>{$safe_name}</strong>,
                            </p>
                            <p style="color:#333;font-size:16px;line-height:1.6;margin:0 0 16px 0;">
                                Thank you for applying to NIELIT Bhubaneswar. After reviewing your registration, we are unable to approve your application at this time.
                            </p>
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#fef2f2;border-left:4px solid #dc2626;border-radius:4px;margin:18px 0;">
                                <tr>
                                    <td style="padding:18px;">
                                        <p style="margin:0 0 8px 0;color:#991b1b;font-size:14px;font-weight:700;">Application Details</p>
                                        <p style="margin:0 0 6px 0;color:#374151;font-size:14px;"><strong>Student ID:</strong> {$safe_id}</p>
                                        <p style="margin:0 0 6px 0;color:#374151;font-size:14px;"><strong>Course:</strong> {$safe_course}</p>
                                        <p style="margin:0;color:#374151;font-size:14px;"><strong>Reason:</strong> {$safe_reason}</p>
                                    </td>
                                </tr>
                            </table>
                            {$note_block}
                            <p style="color:#333;font-size:16px;line-height:1.6;margin:0 0 16px 0;">
                                You may submit a fresh application after correcting the issue mentioned above.
                            </p>
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#eff6ff;border-left:4px solid #2563eb;border-radius:4px;margin:0 0 18px 0;">
                                <tr>
                                    <td style="padding:16px;">
                                        <p style="margin:0 0 8px 0;color:#1e40af;font-size:14px;font-weight:700;">How to reapply</p>
                                        <p style="margin:0;color:#374151;font-size:14px;line-height:1.6;">
                                            Use the <strong>same Aadhar number</strong> and <strong>same Student ID ({$safe_id})</strong> when you register again.
                                            Upload corrected documents and update any details as needed.
                                            If you already have a portal account, your <strong>existing login password</strong> will continue to work after approval.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            <p style="text-align:center;margin:24px 0;">
                                <a href="{$courses_url}" style="display:inline-block;background:#0a1628;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:6px;font-weight:600;">
                                    View Courses &amp; Reapply
                                </a>
                            </p>
                            <p style="color:#64748b;font-size:13px;line-height:1.6;margin:0 0 16px 0;text-align:center;word-break:break-all;">
                                If the button does not work, open this link:<br>
                                <a href="{$courses_url}" style="color:#1a56db;">{$courses_url_text}</a>
                            </p>
                            <p style="color:#64748b;font-size:14px;line-height:1.6;margin:0;">
                                Open the course page, tap <strong>Apply Now</strong>, and complete registration again.
                                If you need help, visit our <a href="{$contact_url}" style="color:#1a56db;">contact page</a> or call 0674-2960354.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#f5f5f5;padding:18px;text-align:center;border-top:1px solid #e0e0e0;">
                            <p style="color:#666;font-size:12px;margin:0;">© {$current_year} NIELIT Bhubaneswar. All rights reserved.</p>
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

function getRegistrationRejectionEmailPlainText(
    $student_name,
    $student_id,
    $course_label,
    $rejection_reason,
    $rejection_note = ''
) {
    $courses_url = getEmailPublicPageUrl('public/courses.php');
    $contact_url = getEmailPublicPageUrl('public/contact.php');
    $note_text = trim($rejection_note) !== '' ? "\nAdditional details: {$rejection_note}\n" : '';

    return <<<TEXT
REGISTRATION UPDATE - NIELIT Bhubaneswar

Dear {$student_name},

Thank you for applying to NIELIT Bhubaneswar. After reviewing your registration, we are unable to approve your application at this time.

Student ID: {$student_id}
Course: {$course_label}
Reason: {$rejection_reason}
{$note_text}
You may submit a fresh application after correcting the issue mentioned above.

How to reapply:
- Use the same Aadhar number and same Student ID ({$student_id})
- Upload corrected documents and update details as needed
- Your existing portal login password will continue to work after approval

Reapply here: {$courses_url}
Contact us: {$contact_url}
Phone: 0674-2960354

© NIELIT Bhubaneswar. All rights reserved.
TEXT;
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
        configurePhpMailerSmtp($mail);
        
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

/**
 * Send faculty confirmation email
 * 
 * @param string $to_email Faculty email address
 * @param string $faculty_name Faculty full name
 * @param string $designation Faculty designation
 * @param string $department Faculty department
 * @return bool True on success, false on failure
 */
function sendFacultyConfirmationEmail($to_email, $faculty_name, $designation = '', $department = '') {
    $mail = new PHPMailer(true);
    
    try {
        configurePhpMailerSmtp($mail);
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to_email, $faculty_name);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Faculty Account Registered - NIELIT Bhubaneswar';
        $mail->Body = getFacultyEmailTemplate($faculty_name, $designation, $department, $to_email);
        $mail->AltBody = getFacultyEmailPlainText($faculty_name, $designation, $department);
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Faculty email sending failed: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Get HTML email template for faculty confirmation
 */
function getFacultyEmailTemplate($faculty_name, $designation = '', $department = '', $email = 'Registered') {
    $current_year = date('Y');
    
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Account Registration</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #0a1628 0%, #112240 100%); padding: 30px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 28px; font-weight: 700;">
                                👨‍🏫 Faculty Account Registered
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
                                Dear <strong>{$faculty_name}</strong>,
                            </p>
                            
                            <p style="color: #555; font-size: 15px; line-height: 1.8; margin: 0 0 20px 0;">
                                We are pleased to inform you that your faculty account has been successfully registered in the NIELIT Bhubaneswar Management System.
                            </p>
                            
                            <!-- Account Details -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; border-left: 4px solid #1a56db; margin: 20px 0;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <h3 style="color: #1a56db; margin: 0 0 15px 0; font-size: 16px;">Account Details</h3>
                                        <table cellpadding="8" cellspacing="0" width="100%">
                                            <tr>
                                                <td style="color: #666; font-weight: 600; width: 35%;">Name:</td>
                                                <td style="color: #333;">{$faculty_name}</td>
                                            </tr>
                                            <?php if (!empty($designation)): ?>
                                            <tr>
                                                <td style="color: #666; font-weight: 600;">Designation:</td>
                                                <td style="color: #333;">{$designation}</td>
                                            </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($department)): ?>
                                            <tr>
                                                <td style="color: #666; font-weight: 600;">Department:</td>
                                                <td style="color: #333;">{$department}</td>
                                            </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <td style="color: #666; font-weight: 600;">Email:</td>
                                                <td style="color: #333;">{$email}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="color: #555; font-size: 15px; line-height: 1.8; margin: 20px 0;">
                                You can now access the faculty portal and view your assigned batches, students, and other administrative functions.
                            </p>
                            
                            <p style="color: #555; font-size: 15px; line-height: 1.8; margin: 20px 0;">
                                <strong>Access Details:</strong><br>
                                Portal URL: <a href="https://nielitbhubaneswar.in" style="color: #1a56db; text-decoration: none;">https://nielitbhubaneswar.in</a>
                            </p>
                            
                            <!-- Call to Action -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 30px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="https://nielitbhubaneswar.in" style="background-color: #1a56db; color: #ffffff; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: 600; display: inline-block;">
                                            Access Faculty Portal
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="color: #666; font-size: 14px; line-height: 1.6; margin: 20px 0; padding: 15px; background-color: #eff6ff; border-left: 4px solid #0284c7; border-radius: 4px;">
                                <strong>Note:</strong> If you did not register an account with NIELIT Bhubaneswar, please ignore this email or contact our support team immediately.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #e5e7eb;">
                            <p style="color: #666; font-size: 12px; margin: 0;">
                                © {$current_year} NIELIT Bhubaneswar. All rights reserved.
                            </p>
                            <p style="color: #999; font-size: 11px; margin: 10px 0 0 0;">
                                Email: admin@nielitbhubaneswar.in | Phone: 0674-2960354
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
 * Get plain text version of faculty confirmation email
 */
function getFacultyEmailPlainText($faculty_name, $designation = '', $department = '') {
    return <<<TEXT
FACULTY ACCOUNT REGISTERED - NIELIT Bhubaneswar

Dear {$faculty_name},

We are pleased to inform you that your faculty account has been successfully registered in the NIELIT Bhubaneswar Management System.

ACCOUNT DETAILS:
================
Name: {$faculty_name}
{$designation} Designation: {$designation}
{$department} Department: {$department}

You can now access the faculty portal and view your assigned batches, students, and other administrative functions.

Access Faculty Portal: https://nielitbhubaneswar.in

If you have any questions or need assistance, please contact us at:
Email: admin@nielitbhubaneswar.in
Phone: 0674-2960354

© 2026 NIELIT Bhubaneswar. All rights reserved.
This is an automated email. Please do not reply to this message.
TEXT;
}
?>
