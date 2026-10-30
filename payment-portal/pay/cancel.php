<?php

require_once __DIR__ . '/../includes/bootstrap.php';

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
hub_checkout_mark_cancelled($hub_conn, $token);
header('Content-Type: application/json');
echo json_encode(['ok' => true]);
