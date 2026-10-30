<?php

/**
 * Copy to secrets.php (not committed). Used only to seed the first hub staff login.
 *
 * Razorpay Key ID, Key secret, and webhook secret are NOT stored here.
 * Configure them in Admin → Gateway (saved in the hub database).
 *
 * SMTP: match MIS config/email.php (Hostinger). Set smtp_password here on the server,
 * or place email.php at ../../config/email.php for one-time auto-import.
 */
return [
    'admin_username' => 'hubadmin',
    'admin_password' => 'ChangeMeOnFirstLogin!',
    'admin_email' => 'admin@nielitbhubaneswar.in',

    'smtp_host' => 'smtp.hostinger.com',
    'smtp_port' => '465',
    'smtp_encryption' => 'ssl',
    'smtp_username' => 'noreply@nielitbhubaneswar.in',
    'smtp_password' => '',
    'smtp_from_email' => 'noreply@nielitbhubaneswar.in',
    'smtp_from_name' => 'NIELIT Bhubaneswar',
];
