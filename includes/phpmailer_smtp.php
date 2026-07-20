<?php
/**
 * Shared PHPMailer SMTP settings for the application.
 */

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../config/email.php';

if (!function_exists('configurePhpMailerSmtp')) {
    function configurePhpMailerSmtp(PHPMailer $mail, array $options = []): void
    {
        $port = (int) (defined('SMTP_PORT') ? SMTP_PORT : 587);
        $encryption = defined('SMTP_ENCRYPTION') ? strtolower(trim((string) SMTP_ENCRYPTION)) : '';

        if (defined('SMTP_SECURE') && SMTP_SECURE !== '') {
            $secure = SMTP_SECURE;
        } elseif ($encryption === 'ssl' || $encryption === 'smtps') {
            $secure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls' || $encryption === 'starttls') {
            $secure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($port === 465) {
            $secure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $secure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = $secure;
        $mail->Port = $port;
        $mail->Timeout = (int) ($options['timeout'] ?? 15);
        $mail->SMTPKeepAlive = (bool) ($options['keep_alive'] ?? false);
        $mail->SMTPAutoTLS = ($secure !== PHPMailer::ENCRYPTION_SMTPS);
        $mail->CharSet = 'UTF-8';
    }
}
