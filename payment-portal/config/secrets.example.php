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
    /** Applied to hubadmin when email is empty (OTP login). Override in secrets.php on the server. */
    'admin_email' => 'admin@nielitbhubaneswar.in',
];
