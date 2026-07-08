<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/url_helper.php';

if (!isset($_SESSION['admin'])) {
    header('Location: ' . relative_url('login.php'));
    exit();
}

header('Location: ' . relative_url('manage_homepage.php#homepage-news'));
exit();
