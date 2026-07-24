<?php
/**
 * Legacy Management page removed from the public site.
 * Keep this file as a redirect so old bookmarks/links still work.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/url_helper.php';

header('Location: ' . app_url('public/team'), true, 301);
exit;
