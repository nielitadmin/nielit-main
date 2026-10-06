<?php
/**
 * Online classroom video backend settings (free / open-source friendly).
 *
 * Why meet.jit.si embed fails after ~5 minutes:
 * Embedding the public meet.jit.si server via iframe is demo-only.
 * Opening the room as a normal full-page meeting is free and has no 5-minute cut.
 *
 * Modes:
 * - open   = open room in full page (default; free; no embed limit)
 * - embed  = iframe inside NIELIT page (ONLY use with your OWN Jitsi server)
 *
 * Self-hosted Jitsi (Google Cloud / VPS):
 * 1. Install Jitsi on a Linux VM — see scripts/jitsi/README.md
 * 2. Point DNS e.g. meet.nielitbhubaneswar.in → GCP static IP
 * 3. Copy online_class_config.local.php.example → online_class_config.local.php
 * 4. Set domain, mode, and optional JWT secret (must match Jitsi server .env)
 */

$localCfg = __DIR__ . '/online_class_config.local.php';
if (is_file($localCfg)) {
    require_once $localCfg;
}

if (!defined('ONLINE_CLASS_JITSI_DOMAIN')) {
    // Public free Jitsi (full-page open only — do not embed this domain)
    define('ONLINE_CLASS_JITSI_DOMAIN', 'meet.jit.si');
}

if (!defined('ONLINE_CLASS_VIDEO_MODE')) {
    // 'open' = full-page room (recommended for meet.jit.si)
    // 'embed' = iframe (requires your own Jitsi / JaaS domain)
    define('ONLINE_CLASS_VIDEO_MODE', 'open');
}

if (!defined('ONLINE_CLASS_JITSI_JWT_ENABLED')) {
    define('ONLINE_CLASS_JITSI_JWT_ENABLED', false);
}

if (!defined('ONLINE_CLASS_JITSI_JWT_APP_ID')) {
    define('ONLINE_CLASS_JITSI_JWT_APP_ID', 'nielit_meet');
}

if (!defined('ONLINE_CLASS_JITSI_JWT_APP_SECRET')) {
    define('ONLINE_CLASS_JITSI_JWT_APP_SECRET', '');
}
