<?php

/**
 * SMTP mailer for OTP emails.
 * Uses hub_settings to store SMTP configuration.
 * Settings keys: smtp_host, smtp_port, smtp_username, smtp_password, smtp_from_email, smtp_from_name, smtp_encryption
 */

function hub_smtp_config(mysqli $conn): array
{
    return [
        'host'       => (string) (hub_setting_get($conn, 'smtp_host') ?: ''),
        'port'       => (int) (hub_setting_get($conn, 'smtp_port') ?: 465),
        'username'   => (string) (hub_setting_get($conn, 'smtp_username') ?: ''),
        'password'   => (string) (hub_setting_get($conn, 'smtp_password') ?: ''),
        'from_email' => (string) (hub_setting_get($conn, 'smtp_from_email') ?: ''),
        'from_name'  => (string) (hub_setting_get($conn, 'smtp_from_name') ?: HUB_APP_NAME),
        'encryption' => (string) (hub_setting_get($conn, 'smtp_encryption') ?: 'ssl'),
    ];
}

/** Path to MIS config/email.php when payment-portal sits inside the main repo. */
function hub_smtp_mis_email_config_path(): string
{
    return dirname(__DIR__, 2) . '/config/email.php';
}

/** Read SMTP constants from MIS email.php (not committed; server-only). */
function hub_smtp_mis_constants(): ?array
{
    $path = hub_smtp_mis_email_config_path();
    if (!is_file($path)) {
        return null;
    }
    require_once $path;
    if (!defined('SMTP_HOST') || !defined('SMTP_USERNAME')) {
        return null;
    }
    $secure = defined('SMTP_SECURE') ? strtolower((string) SMTP_SECURE) : 'ssl';
    $encryption = $secure === 'tls' ? 'tls' : ($secure === 'ssl' ? 'ssl' : 'none');

    return [
        'host'       => (string) SMTP_HOST,
        'port'       => defined('SMTP_PORT') ? (int) SMTP_PORT : ($encryption === 'ssl' ? 465 : 587),
        'username'   => (string) SMTP_USERNAME,
        'password'   => defined('SMTP_PASSWORD') ? (string) SMTP_PASSWORD : '',
        'from_email' => defined('SMTP_FROM_EMAIL') ? (string) SMTP_FROM_EMAIL : (string) SMTP_USERNAME,
        'from_name'  => defined('SMTP_FROM_NAME') ? (string) SMTP_FROM_NAME : 'NIELIT Bhubaneswar',
        'encryption' => $encryption,
    ];
}

/** Suggested SMTP values (Hostinger / NIELIT) when nothing saved yet — override via secrets.php on the server. */
function hub_smtp_install_defaults(): array
{
    global $hub_secrets;

    $defaults = [
        'host'       => 'smtp.hostinger.com',
        'port'       => 465,
        'username'   => 'noreply@nielitbhubaneswar.in',
        'from_email' => 'noreply@nielitbhubaneswar.in',
        'from_name'  => 'NIELIT Bhubaneswar',
        'encryption' => 'ssl',
    ];

    $mis = hub_smtp_mis_constants();
    if ($mis !== null) {
        $defaults = array_merge($defaults, array_intersect_key($mis, $defaults));
    }

    if (!is_array($hub_secrets)) {
        return $defaults;
    }

    $map = [
        'host'       => 'smtp_host',
        'port'       => 'smtp_port',
        'username'   => 'smtp_username',
        'from_email' => 'smtp_from_email',
        'from_name'  => 'smtp_from_name',
        'encryption' => 'smtp_encryption',
    ];
    foreach ($map as $key => $secretKey) {
        $v = trim((string) ($hub_secrets[$secretKey] ?? ''));
        if ($v === '') {
            continue;
        }
        if ($key === 'port') {
            $defaults['port'] = (int) $v;
        } else {
            $defaults[$key] = $v;
        }
    }

    return $defaults;
}

/** Values for the admin SMTP form (saved settings, else install defaults). */
function hub_smtp_form_config(mysqli $conn): array
{
    $saved = hub_smtp_config($conn);
    $install = hub_smtp_install_defaults();
    $usingDefaults = trim($saved['host']) === '';

    return [
        'host'       => $saved['host'] !== '' ? $saved['host'] : $install['host'],
        'port'       => $saved['host'] !== '' ? $saved['port'] : $install['port'],
        'username'   => $saved['username'] !== '' ? $saved['username'] : $install['username'],
        'password'   => $saved['password'],
        'from_email' => $saved['from_email'] !== '' ? $saved['from_email'] : $install['from_email'],
        'from_name'  => $saved['from_name'] !== '' ? $saved['from_name'] : $install['from_name'],
        'encryption' => $saved['encryption'] !== '' ? $saved['encryption'] : $install['encryption'],
        'using_defaults' => $usingDefaults,
    ];
}

/**
 * One-time seed from config/secrets.php when smtp_host and smtp_password are set on the server.
 */
function hub_smtp_seed_from_secrets(mysqli $conn): void
{
    global $hub_secrets;
    if (!is_array($hub_secrets)) {
        return;
    }
    if (trim((string) (hub_setting_get($conn, 'smtp_host') ?: '')) !== '') {
        return;
    }

    $host = trim((string) ($hub_secrets['smtp_host'] ?? ''));
    $pass = (string) ($hub_secrets['smtp_password'] ?? '');
    if ($host === '' || $pass === '') {
        hub_smtp_seed_from_mis($conn);
        return;
    }

    $install = hub_smtp_install_defaults();
    hub_setting_set($conn, 'smtp_host', $host);
    hub_setting_set($conn, 'smtp_port', (string) ($hub_secrets['smtp_port'] ?? (string) $install['port']));
    hub_setting_set($conn, 'smtp_username', trim((string) ($hub_secrets['smtp_username'] ?? $install['username'])));
    hub_setting_set($conn, 'smtp_password', $pass);
    hub_setting_set($conn, 'smtp_from_email', trim((string) ($hub_secrets['smtp_from_email'] ?? $install['from_email'])));
    hub_setting_set($conn, 'smtp_from_name', trim((string) ($hub_secrets['smtp_from_name'] ?? $install['from_name'])));
    hub_setting_set($conn, 'smtp_encryption', trim((string) ($hub_secrets['smtp_encryption'] ?? $install['encryption'])));
}

/** One-time import from MIS ../config/email.php when hub SMTP is empty. */
function hub_smtp_seed_from_mis(mysqli $conn): void
{
    if (trim((string) (hub_setting_get($conn, 'smtp_host') ?: '')) !== '') {
        return;
    }
    $mis = hub_smtp_mis_constants();
    if ($mis === null || $mis['host'] === '' || $mis['password'] === '') {
        return;
    }
    hub_setting_set($conn, 'smtp_host', $mis['host']);
    hub_setting_set($conn, 'smtp_port', (string) $mis['port']);
    hub_setting_set($conn, 'smtp_username', $mis['username']);
    hub_setting_set($conn, 'smtp_password', $mis['password']);
    hub_setting_set($conn, 'smtp_from_email', $mis['from_email']);
    hub_setting_set($conn, 'smtp_from_name', $mis['from_name']);
    hub_setting_set($conn, 'smtp_encryption', $mis['encryption']);
}

function hub_smtp_ready(mysqli $conn): bool
{
    $c = hub_smtp_config($conn);
    return $c['host'] !== '' && $c['username'] !== '' && $c['password'] !== '' && $c['from_email'] !== '';
}

/**
 * Send an email via SMTP using PHP's mail() as fallback, or the built-in
 * socket-based SMTP client if configured.
 */
function hub_send_email(mysqli $conn, string $to, string $subject, string $htmlBody): bool
{
    $cfg = hub_smtp_config($conn);

    if ($cfg['host'] === '' || $cfg['username'] === '' || $cfg['password'] === '') {
        // Fallback to PHP mail()
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . $cfg['from_name'] . " <" . ($cfg['from_email'] ?: 'noreply@localhost') . ">\r\n";
        return @mail($to, $subject, $htmlBody, $headers);
    }

    return hub_smtp_send($cfg, $to, $subject, $htmlBody);
}

/**
 * Raw SMTP send using fsockopen — works on XAMPP without PHPMailer/Composer.
 */
function hub_smtp_send(array $cfg, string $to, string $subject, string $htmlBody): bool
{
    $host = $cfg['host'];
    $port = $cfg['port'];
    $user = $cfg['username'];
    $pass = $cfg['password'];
    $fromEmail = $cfg['from_email'];
    $fromName = $cfg['from_name'];
    $encryption = $cfg['encryption'];

    $connectHost = $host;
    if ($encryption === 'ssl') {
        $connectHost = 'ssl://' . $host;
    }

    $errno = 0;
    $errstr = '';
    $smtp = @fsockopen($connectHost, $port, $errno, $errstr, 15);
    if (!$smtp) {
        error_log("SMTP connect failed: {$errstr} ({$errno})");
        return false;
    }

    stream_set_timeout($smtp, 30);

    $response = hub_smtp_read($smtp);
    if (strpos($response, '220') !== 0) {
        fclose($smtp);
        return false;
    }

    // EHLO
    hub_smtp_cmd($smtp, 'EHLO ' . gethostname());

    // STARTTLS if tls
    if ($encryption === 'tls') {
        hub_smtp_cmd($smtp, 'STARTTLS');
        if (!stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($smtp);
            error_log('SMTP STARTTLS failed');
            return false;
        }
        hub_smtp_cmd($smtp, 'EHLO ' . gethostname());
    }

    // AUTH LOGIN
    hub_smtp_cmd($smtp, 'AUTH LOGIN');
    hub_smtp_cmd($smtp, base64_encode($user));
    $authResp = hub_smtp_cmd($smtp, base64_encode($pass));
    if (strpos($authResp, '235') === false) {
        fclose($smtp);
        error_log('SMTP AUTH failed: ' . $authResp);
        return false;
    }

    // MAIL FROM
    hub_smtp_cmd($smtp, 'MAIL FROM:<' . $fromEmail . '>');
    hub_smtp_cmd($smtp, 'RCPT TO:<' . $to . '>');
    hub_smtp_cmd($smtp, 'DATA');

    // Headers
    $boundary = md5(uniqid((string) time()));
    $msg = "From: {$fromName} <{$fromEmail}>\r\n";
    $msg .= "To: {$to}\r\n";
    $msg .= "Subject: {$subject}\r\n";
    $msg .= "MIME-Version: 1.0\r\n";
    $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
    $msg .= "X-Mailer: NIELIT-PaymentHub\r\n";
    $msg .= "\r\n";
    $msg .= $htmlBody . "\r\n";
    $msg .= ".";

    $dataResp = hub_smtp_cmd($smtp, $msg);

    // QUIT
    hub_smtp_cmd($smtp, 'QUIT');
    fclose($smtp);

    return strpos($dataResp, '250') !== false;
}

function hub_smtp_cmd($smtp, string $cmd): string
{
    fwrite($smtp, $cmd . "\r\n");
    return hub_smtp_read($smtp);
}

function hub_smtp_read($smtp): string
{
    $data = '';
    while ($line = fgets($smtp, 512)) {
        $data .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    return $data;
}

/**
 * Send OTP email to admin.
 */
function hub_send_otp_email(mysqli $conn, string $toEmail, string $otp, string $adminName = ''): bool
{
    $appName = defined('HUB_BRAND_SHORT') ? HUB_BRAND_SHORT : 'NIELIT Payment Hub';
    $subject = $otp . ' — Your sign-in code for ' . $appName;

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f0f4f8;font-family:\'DM Sans\',system-ui,sans-serif;">';
    $html .= '<div style="max-width:480px;margin:40px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(15,23,42,0.08);">';

    // Header
    $html .= '<div style="background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);padding:28px 32px;text-align:center;">';
    $html .= '<h1 style="margin:0;color:#fff;font-size:20px;font-weight:600;">🔐 ' . htmlspecialchars($appName) . '</h1>';
    $html .= '</div>';

    // Body
    $html .= '<div style="padding:32px;">';
    if ($adminName !== '') {
        $html .= '<p style="margin:0 0 16px;color:#334155;font-size:15px;">Hi <strong>' . htmlspecialchars($adminName) . '</strong>,</p>';
    }
    $html .= '<p style="margin:0 0 24px;color:#475569;font-size:14px;line-height:1.6;">Use the code below to sign in to the admin dashboard. This code expires in <strong>10 minutes</strong>.</p>';

    // OTP box
    $html .= '<div style="text-align:center;margin:0 0 24px;">';
    $html .= '<div style="display:inline-block;background:linear-gradient(135deg,#eff6ff,#ecfeff);border:2px dashed #0ea5e9;border-radius:12px;padding:16px 40px;">';
    $html .= '<span style="font-size:36px;font-weight:700;letter-spacing:8px;color:#0c4a6e;font-family:monospace;">' . htmlspecialchars($otp) . '</span>';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '<p style="margin:0 0 8px;color:#94a3b8;font-size:12px;">If you did not request this code, you can safely ignore this email.</p>';
    $html .= '<p style="margin:0;color:#94a3b8;font-size:12px;">Do not share this code with anyone.</p>';
    $html .= '</div>';

    // Footer
    $html .= '<div style="background:#f8fafc;padding:16px 32px;text-align:center;border-top:1px solid #e2e8f0;">';
    $html .= '<p style="margin:0;color:#94a3b8;font-size:11px;">' . htmlspecialchars($appName) . ' · Secure gateway &amp; money receipt control</p>';
    $html .= '</div>';

    $html .= '</div></body></html>';

    return hub_send_email($conn, $toEmail, $subject, $html);
}
