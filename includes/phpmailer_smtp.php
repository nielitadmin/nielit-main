<?php
/**
 * Shared PHPMailer SMTP settings for the application.
 */

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../libraries/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../libraries/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../libraries/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/mail_logger.php';

if (!function_exists('nielitSmtpMailbox')) {
    /**
     * Mailbox used for SMTP login and From. Maps the old noreplay@ typo
     * to noreply@ so live config that was not updated still authenticates.
     */
    function nielitSmtpMailbox(): string
    {
        $canonical = 'noreply@nielitbhubaneswar.in';
        $legacy = 'noreplay@nielitbhubaneswar.in';
        $user = defined('SMTP_USERNAME') ? strtolower(trim((string) SMTP_USERNAME)) : '';
        $from = defined('SMTP_FROM_EMAIL') ? strtolower(trim((string) SMTP_FROM_EMAIL)) : '';
        if ($user === '' || $user === $legacy || $from === $legacy) {
            return $canonical;
        }
        if ($from !== '' && strpos($from, '@') !== false) {
            return trim((string) SMTP_FROM_EMAIL);
        }
        if ($user !== '' && strpos($user, '@') !== false) {
            return trim((string) SMTP_USERNAME);
        }
        return $canonical;
    }
}

if (!function_exists('isLikelyHostingerSharedHosting')) {
    function isLikelyHostingerSharedHosting(): bool
    {
        if (defined('SMTP_FORCE_LOCAL') && SMTP_FORCE_LOCAL) {
            return true;
        }
        $docRoot = str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        if ($docRoot !== '' && (strpos($docRoot, '/home/u') === 0 || strpos($docRoot, '/domains/') !== false)) {
            return true;
        }
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        return $host !== '' && strpos($host, 'nielitbhubaneswar.in') !== false;
    }
}

if (!function_exists('configurePhpMailerSmtp')) {
    function configurePhpMailerSmtp(PHPMailer $mail, array $options = []): void
    {
        $transport = strtolower((string) ($options['transport'] ?? 'smtp'));
        if ($transport === 'mail') {
            $mail->isMail();
            $mail->CharSet = 'UTF-8';
            return;
        }

        $port = (int) ($options['port'] ?? (defined('SMTP_PORT') ? SMTP_PORT : 587));
        $encryption = array_key_exists('encryption', $options)
            ? strtolower(trim((string) $options['encryption']))
            : (defined('SMTP_ENCRYPTION') ? strtolower(trim((string) SMTP_ENCRYPTION)) : '');

        if (!empty($options['secure'])) {
            $secure = $options['secure'];
        } elseif ($encryption === '' || $encryption === 'none') {
            $secure = false;
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
        $mail->SMTPAuth = array_key_exists('auth', $options)
            ? (bool) $options['auth']
            : true;
        $mail->Username = nielitSmtpMailbox();
        $mail->Password = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
        $mail->SMTPSecure = $secure;
        $mail->Port = $port;
        $mail->Timeout = (int) ($options['timeout'] ?? 15);
        $mail->SMTPKeepAlive = (bool) ($options['keep_alive'] ?? false);
        $mail->SMTPAutoTLS = ($secure !== false && $secure !== PHPMailer::ENCRYPTION_SMTPS);
        $mail->CharSet = 'UTF-8';
    }
}

if (!function_exists('applyPhpMailerIdentity')) {
    /**
     * Align envelope sender / Message-ID with the mailbox domain so Gmail
     * does not show "via srvXXXX.main-hosting.eu" and dump OTPs in spam.
     */
    function applyPhpMailerIdentity(PHPMailer $mail): void
    {
        $fromEmail = nielitSmtpMailbox();
        $fromName = defined('SMTP_FROM_NAME') ? (string) SMTP_FROM_NAME : 'NIELIT Bhubaneswar';
        if ($fromEmail !== '' && strpos($fromEmail, '@') !== false) {
            $mail->setFrom($fromEmail, $fromName, true);
            $mail->Sender = $fromEmail;
            $domain = strtolower(substr(strrchr($fromEmail, '@'), 1));
            if ($domain !== '') {
                $mail->Hostname = $domain;
            }
            $mail->XMailer = 'NIELIT Bhubaneswar';
        }
        if ($fromEmail !== '' && method_exists($mail, 'addReplyTo')) {
            try {
                $mail->clearReplyTos();
            } catch (Throwable $e) {
            }
            $mail->addReplyTo($fromEmail, $fromName);
        }
    }
}

if (!function_exists('phpMailerSmtpProfiles')) {
    /**
     * SMTP / mail profiles. Prefer authenticated SMTP so Hostinger can DKIM-sign.
     * Unauthenticated localhost:25 / php mail() is last because Gmail then shows
     * "via srvXXXX.main-hosting.eu" and often spam-folders OTP mail.
     */
    function phpMailerSmtpProfiles(array $options = []): array
    {
        $remoteHost = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.hostinger.com';
        $primaryPort = (int) (defined('SMTP_PORT') ? SMTP_PORT : 465);
        $primaryEnc = defined('SMTP_ENCRYPTION') ? strtolower(trim((string) SMTP_ENCRYPTION)) : 'ssl';

        // Authenticated local SMTP is signed by Hostinger (DKIM). Unauthenticated
        // port 25 / php mail() shows "via srvXXXX.main-hosting.eu" and Gmail spam-folders it.
        $localAuthProfiles = [
            [
                'label' => 'localhost:587/tls+auth',
                'transport' => 'smtp',
                'host' => 'localhost',
                'port' => 587,
                'encryption' => 'tls',
                'auth' => true,
            ],
            [
                'label' => 'localhost:465/ssl+auth',
                'transport' => 'smtp',
                'host' => 'localhost',
                'port' => 465,
                'encryption' => 'ssl',
                'auth' => true,
            ],
        ];
        $localUnauthProfiles = [
            [
                'label' => 'localhost:25/none',
                'transport' => 'smtp',
                'host' => 'localhost',
                'port' => 25,
                'encryption' => 'none',
                'auth' => false,
            ],
            [
                'label' => 'php-mail()',
                'transport' => 'mail',
            ],
        ];

        $remoteProfiles = [
            [
                'label' => $remoteHost . ':' . $primaryPort . '/' . $primaryEnc,
                'transport' => 'smtp',
                'host' => $remoteHost,
                'port' => $primaryPort,
                'encryption' => $primaryEnc,
                'auth' => true,
            ],
        ];

        if (!($primaryPort === 587 && in_array($primaryEnc, ['tls', 'starttls'], true))) {
            $remoteProfiles[] = [
                'label' => $remoteHost . ':587/tls',
                'transport' => 'smtp',
                'host' => $remoteHost,
                'port' => 587,
                'encryption' => 'tls',
                'auth' => true,
            ];
        }

        if (!($primaryPort === 465 && in_array($primaryEnc, ['ssl', 'smtps'], true))) {
            $remoteProfiles[] = [
                'label' => $remoteHost . ':465/ssl',
                'transport' => 'smtp',
                'host' => $remoteHost,
                'port' => 465,
                'encryption' => 'ssl',
                'auth' => true,
            ];
        }

        // Authenticated smtp.hostinger.com first so Hostinger can DKIM-sign as
        // noreply@nielitbhubaneswar.in. Unauthenticated php mail() shows
        // "via srvXXXX.main-hosting.eu" and Gmail often files it as spam.
        $preferRemote = array_key_exists('prefer_remote', $options)
            ? !empty($options['prefer_remote'])
            : true;
        if ($preferRemote) {
            $profiles = array_merge($remoteProfiles, $localAuthProfiles, $localUnauthProfiles);
        } elseif (isLikelyHostingerSharedHosting()) {
            $profiles = array_merge($localAuthProfiles, $remoteProfiles, $localUnauthProfiles);
        } else {
            $profiles = array_merge($remoteProfiles, $localAuthProfiles, $localUnauthProfiles);
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
     * Configure + send with SMTP / mail() profile fallback.
     *
     * @param callable(PHPMailer):void $configureMessage Sets From/To/Subject/Body on the mailer
     * @return array{ok:bool,error:string,profile:string}
     */
    function sendPhpMailerWithSmtpFallback(callable $configureMessage, array $options = []): array
    {
        $timeout = (int) ($options['timeout'] ?? 12);
        $errors = [];

        $skipUnauth = array_key_exists('authenticated_only', $options)
            ? !empty($options['authenticated_only'])
            : true;
        foreach (phpMailerSmtpProfiles($options) as $profile) {
            $isUnauth = (($profile['transport'] ?? 'smtp') === 'mail')
                || (array_key_exists('auth', $profile) && $profile['auth'] === false);
            if ($skipUnauth && $isUnauth) {
                continue;
            }
            $mail = new PHPMailer(true);
            try {
                $transport = $profile['transport'] ?? 'smtp';
                if ($transport === 'mail') {
                    configurePhpMailerSmtp($mail, ['transport' => 'mail']);
                } else {
                    configurePhpMailerSmtp($mail, [
                        'timeout' => $timeout,
                        'keep_alive' => false,
                        'host' => $profile['host'] ?? 'localhost',
                        'port' => $profile['port'] ?? 25,
                        'encryption' => $profile['encryption'] ?? 'none',
                        'auth' => $profile['auth'] ?? false,
                    ]);
                }
                $configureMessage($mail);
                applyPhpMailerIdentity($mail);
                $mail->send();
                // Log successful send attempt
                try {
                    logMailSend($profile['label'] ?? '', ($mail->getToAddresses()[0][0] ?? ''), ($mail->Subject ?? ''), 'ok', '');
                } catch (Throwable $e) {
                    error_log('mail_logger failed (success path): ' . $e->getMessage());
                }
                // File fallback logging
                try {
                    if (function_exists('fileMailDebugLog')) {
                        fileMailDebugLog(($profile['label'] ?? '') . ' => OK => ' . ($mail->getToAddresses()[0][0] ?? '') . ' / ' . ($mail->Subject ?? ''));
                    }
                } catch (Throwable $e) {}

                return [
                    'ok' => true,
                    'error' => '',
                    'profile' => $profile['label'],
                ];
            } catch (Throwable $e) {
                $info = trim((string) ($mail->ErrorInfo ?: $e->getMessage()));
                $errors[] = $profile['label'] . ' => ' . $info;
                error_log('Mail send failed via ' . $profile['label'] . ': ' . $info);
                // Log failed attempt with error details
                try {
                    logMailSend($profile['label'] ?? '', ($mail->getToAddresses()[0][0] ?? ''), ($mail->Subject ?? ''), 'failed', $info);
                } catch (Throwable $ee) {
                    error_log('mail_logger failed (failure path): ' . $ee->getMessage());
                }
                // File fallback logging
                try {
                    if (function_exists('fileMailDebugLog')) {
                        fileMailDebugLog(($profile['label'] ?? '') . ' => FAILED => ' . ($mail->getToAddresses()[0][0] ?? '') . ' / ' . ($mail->Subject ?? '') . ' => ' . $info);
                    }
                } catch (Throwable $e) {}
            }
        }

        return [
            'ok' => false,
            'error' => implode(' | ', $errors),
            'profile' => '',
        ];
    }
}
