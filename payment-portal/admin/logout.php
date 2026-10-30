<?php

require_once __DIR__ . '/../includes/bootstrap.php';
hub_admin_logout();
header('Location: ' . hub_url('admin/login.php'));
exit;
