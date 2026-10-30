<?php

/**
 * MIS-style email config (reference only — copy values to secrets.php or MIS config/email.php).
 * Hostinger: smtp.hostinger.com · port 465 · SSL
 *
 * Fix typo: FROM address must be noreply@ (not noreplay@).
 */
return [
    'SMTP_HOST' => 'smtp.hostinger.com',
    'SMTP_PORT' => 465,
    'SMTP_SECURE' => 'ssl',
    'SMTP_USERNAME' => 'noreply@nielitbhubaneswar.in',
    'SMTP_PASSWORD' => 'set-on-server-only',
    'SMTP_FROM_EMAIL' => 'noreply@nielitbhubaneswar.in',
    'SMTP_FROM_NAME' => 'NIELIT Bhubaneswar',
];
