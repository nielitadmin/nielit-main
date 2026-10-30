<?php

function hub_admin_session_start(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function hub_admin_logged_in(): bool
{
    hub_admin_session_start();
    return !empty($_SESSION['hub_admin_id']);
}

function hub_admin_require(): void
{
    if (!hub_admin_logged_in()) {
        header('Location: ' . hub_url('admin/login.php'));
        exit;
    }
}

function hub_admin_login(mysqli $conn, string $username, string $password): bool
{
    hub_admin_session_start();
    $username = trim($username);
    $stmt = $conn->prepare('SELECT id, password_hash FROM hub_admin_users WHERE username = ? AND is_active = 1 LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row || !password_verify($password, (string) $row['password_hash'])) {
        return false;
    }
    $_SESSION['hub_admin_id'] = (int) $row['id'];
    $_SESSION['hub_admin_user'] = $username;
    return true;
}

function hub_admin_logout(): void
{
    hub_admin_session_start();
    unset($_SESSION['hub_admin_id'], $_SESSION['hub_admin_user']);
}
