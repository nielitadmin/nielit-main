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
 * To run fully on your own infrastructure later (free & open source):
 * 1. Rent a small VPS (not shared Hostinger web hosting)
 * 2. Install Jitsi Meet (Docker): https://jitsi.github.io/handbook/docs/devops-guide/devops-guide-docker
 * 3. Point DNS e.g. meet.nielitbhubaneswar.in → that VPS
 * 4. Set ONLINE_CLASS_JITSI_DOMAIN to that host and VIDEO_MODE to 'embed' or 'open'
 */

if (!defined('ONLINE_CLASS_JITSI_DOMAIN')) {
    // Public free Jitsi (full-page open only — do not embed this domain)
    define('ONLINE_CLASS_JITSI_DOMAIN', 'meet.jit.si');
}

if (!defined('ONLINE_CLASS_VIDEO_MODE')) {
    // 'open' = full-page room (recommended for meet.jit.si)
    // 'embed' = iframe (requires your own Jitsi / JaaS domain)
    define('ONLINE_CLASS_VIDEO_MODE', 'open');
}
