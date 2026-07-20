<?php
/**
 * Shared PHPMailer SMTP settings for the application.
 */

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../config/email.php';

if (!function_exists('configurePhpMailerSmtp')) {
    function configurePhpMailerSmtp(PHPMailer $mail, array $options = []): void
    {
        $port = (int) ($options['port'] ?? (defined('SMTP_PORT') ? SMTP_PORT : 587));
        $encryption = isset($options['encryption'])
            ? strtolower(trim((string) $options['encryption']))
            : (defined('SMTP_ENCRYPTION') ? strtolower(trim((string) SMTP_ENCRYPTION)) : '');

        if (!empty($options['secure'])) {
            $secure = $options['secure'];
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
        $mail->Host = $options['host'] ?? (defined('SMTP_HOST') ? SMTP_HOST : 'localhost');
        $mail->SMTPAuth = true;
        $mail->Username = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
        $mail->Password = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
        $mail->SMTPSecure = $secure;
        $mail->Port = $port;
        $mail->Timeout = (int) ($options['timeout'] ?? 15);
        $mail->SMTPKeepAlive = (bool) ($options['keep_alive'] ?? false);
        $mail->SMTPAutoTLS = ($secure !== PHPMailer::ENCRYPTION_SMTPS);
        $mail->CharSet = 'UTF-8';
    }
}

if (!function_exists('phpMailerSmtpProfiles')) {
    /**
     * Preferred SMTP profiles. Primary config first, then common Hostinger fallback.
     */
    function phpMailerSmtpProfiles(): array
    {
        $host = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.hostinger.com';
        $primaryPort = (int) (defined('SMTP_PORT') ? SMTP_PORT : 465);
        $primaryEnc = defined('SMTP_ENCRYPTION') ? strtolower(trim((string) SMTP_ENCRYPTION)) : 'ssl';

        $profiles = [
            [
                'label' => $host . ':' . $primaryPort . '/' . $primaryEnc,
                'host' => $host,
                'port' => $primaryPort,
                'encryption' => $primaryEnc,
            ],
        ];

        if (!($primaryPort === 587 && in_array($primaryEnc, ['tls', 'starttls'], true))) {
            $profiles[] = [
                'label' => $host . ':587/tls',
                'host' => $host,
                'port' => 587,
                'encryption' => 'tls',
            ];
        }

        if (!($primaryPort === 465 && in_array($primaryEnc, ['ssl', 'smtps'], true))) {
            $profiles[] = [
                'label' => $host . ':465/ssl',
                'host' => $host,
                'port' => 465,
                'encryption' => 'ssl',
            ];
        }

        $unique = [];
        foreach ($profiles as $profile) {
            $unique[$profile['label']] = $profile;
        }
        return array_values($unique);
    }
}

if (!function_exists('sendPhpMailerWithSmtpFallback')) {
    /**
     * Configure + send with SMTP profile fallback.
     *
     * @param callable(PHPMailer):void $configureMessage Sets From/To/Subject/Body on the mailer
     * @return array{ok:bool,error:string,profile:string}
     */
    function sendPhpMailerWithSmtpFallback(callable $configureMessage, array $options = []): array
    {
        $timeout = (int) ($options['timeout'] ?? 20);
        $errors = [];

        foreach (phpMailerSmtpProfiles() as $profile) {
            $mail = new PHPMailer(true);
            try {
                configurePhpMailerSmtp($mail, [
                    'timeout' => $timeout,
                    'keep_alive' => false,
                    'host' => $profile['host'],
                    'port' => $profile['port'],
                    'encryption' => $profile['encryption'],
                ]);
                $configureMessage($mail);
                $mail->send();
                return [
                    'ok' => true,
                    'error' => '',
                    'profile' => $profile['label'],
                ];
            } catch (Throwable $e) {
                $info = trim((string) ($mail->ErrorInfo ?: $e->getMessage()));
                $errors[] = $profile['label'] . ' => ' . $info;
                error_log('SMTP send failed via ' . $profile['label'] . ': ' . $info);
            }
        }

        return [
            'ok' => false,
            'error' => implode(' | ', $errors),
            'profile' => '',
        ];
    }
}
