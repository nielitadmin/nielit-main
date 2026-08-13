<?php
/**
 * Faculty login is the same as admin login. Keep this URL for old bookmarks.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/url_helper.php';
header('Location: ' . app_url('admin/login'), true, 302);
exit;
