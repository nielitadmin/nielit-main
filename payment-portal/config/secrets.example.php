<?php

/**
 * Copy to secrets.php (not committed). Used only to seed the first hub staff login.
 *
 * Razorpay Key ID, Key secret, and webhook secret are NOT stored here.
 * Configure them in Admin → Gateway (saved in the hub database).
 */
return [
    'admin_username' => 'hubadmin',
    'admin_password' => 'ChangeMeOnFirstLogin!',
    /** Optional: applied to hubadmin when email column was empty (OTP login). */
    'admin_email' => '',
];
