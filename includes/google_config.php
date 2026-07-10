<?php
/**
 * Google Sign-In configuration (tracked in git — works on production without /config/).
 *
 * Optional server override: create config/google.local.php (gitignored) and define
 * GOOGLE_CLIENT_ID before this file is loaded, or override constants there.
 *
 * Console: https://console.cloud.google.com/apis/credentials?project=poetic-shell-452710-d2
 */

if (!defined('GOOGLE_CONFIG_LOADED')) {
    define('GOOGLE_CONFIG_LOADED', true);

    if (!defined('GOOGLE_CLIENT_ID')) {
        define('GOOGLE_CLIENT_ID', '76168270580-bhdvlpsvid9s6sn36s61a7g4t6oiq2kk.apps.googleusercontent.com');
    }

    if (!defined('GOOGLE_OAUTH_ENABLED')) {
        define('GOOGLE_OAUTH_ENABLED', GOOGLE_CLIENT_ID !== '');
    }

    if (!defined('GOOGLE_CLOUD_PROJECT_ID')) {
        define('GOOGLE_CLOUD_PROJECT_ID', 'poetic-shell-452710-d2');
    }

    if (!defined('GOOGLE_CLOUD_CREDENTIALS_URL')) {
        define('GOOGLE_CLOUD_CREDENTIALS_URL', 'https://console.cloud.google.com/apis/credentials?project=' . GOOGLE_CLOUD_PROJECT_ID);
    }
}
