<?php

/**
 * Copy to secrets.php (not committed). Used only to seed the first hub staff login.
 *
 * Razorpay Key ID, Key secret, and webhook secret are NOT stored here.
 * Configure them in Admin → Gateway (saved in the hub database).
 *
 * Optional SMTP block: if smtp_host and smtp_password are set, they are copied into
 * the hub database once (for OTP email). You can also set SMTP only in Admin → SMTP / Email.
 */
return [
    'admin_username' => 'hubadmin',
    'admin_password' => 'ChangeMeOnFirstLogin!',
    /** Applied to hubadmin when email is empty (OTP login). Override in secrets.php on the server. */
    'admin_email' => 'admin@nielitbhubaneswar.in',

    // Optional one-time SMTP seed (Hostinger mailbox — set smtp_password on the server only)
    'smtp_host' => 'smtp.hostinger.com',
    'smtp_port' => '587',
    'smtp_encryption' => 'tls',
    'smtp_username' => 'admin@nielitbhubaneswar.in',
    'smtp_password' => '',
    'smtp_from_email' => 'admin@nielitbhubaneswar.in',
    'smtp_from_name' => 'NIELIT Bhubaneswar Payment Portal',
];
