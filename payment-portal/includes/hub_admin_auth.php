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

function hub_admin_require_superadmin(): void
{
    hub_admin_require();
    hub_admin_session_start();
    if (($_SESSION['hub_admin_role'] ?? '') !== 'superadmin') {
        http_response_code(403);
        exit('Access denied. Superadmin privileges required.');
    }
}

function hub_admin_is_superadmin(): bool
{
    hub_admin_session_start();
    return ($_SESSION['hub_admin_role'] ?? '') === 'superadmin';
}

/**
 * Legacy password login — kept as fallback.
 */
function hub_admin_login(mysqli $conn, string $username, string $password): bool
{
    hub_admin_session_start();
    $username = trim($username);
    $stmt = $conn->prepare('SELECT id, email, role, password_hash FROM hub_admin_users WHERE username = ? AND is_active = 1 LIMIT 1');
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
    $_SESSION['hub_admin_email'] = (string) $row['email'];
    $_SESSION['hub_admin_role'] = (string) $row['role'];
    return true;
}

/**
 * Find admin by email for OTP login.
 */
function hub_admin_find_by_email(mysqli $conn, string $email): ?array
{
    $email = trim(strtolower($email));
    $stmt = $conn->prepare('SELECT id, username, email, role FROM hub_admin_users WHERE email = ? AND is_active = 1 LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Generate a 6-digit OTP, store in DB, return it.
 */
function hub_admin_otp_generate(mysqli $conn, int $adminId): ?string
{
    // Invalidate old unused OTPs for this admin
    $conn->query("UPDATE hub_admin_otp SET is_used = 1 WHERE admin_id = {$adminId} AND is_used = 0");

    $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $stmt = $conn->prepare('INSERT INTO hub_admin_otp (admin_id, otp_code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('is', $adminId, $otp);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }
    $stmt->close();
    return $otp;
}

/**
 * Verify OTP and log in the admin.
 */
function hub_admin_otp_verify(mysqli $conn, int $adminId, string $otpCode): bool
{
    $otpCode = trim($otpCode);
    $stmt = $conn->prepare('SELECT id FROM hub_admin_otp WHERE admin_id = ? AND otp_code = ? AND is_used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('is', $adminId, $otpCode);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return false;
    }

    // Mark OTP as used
    $otpId = (int) $row['id'];
    $conn->query("UPDATE hub_admin_otp SET is_used = 1 WHERE id = {$otpId}");

    // Load admin details for session
    $stmt2 = $conn->prepare('SELECT id, username, email, role FROM hub_admin_users WHERE id = ? AND is_active = 1 LIMIT 1');
    if (!$stmt2) {
        return false;
    }
    $stmt2->bind_param('i', $adminId);
    $stmt2->execute();
    $admin = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();

    if (!$admin) {
        return false;
    }

    hub_admin_session_start();
    $_SESSION['hub_admin_id'] = (int) $admin['id'];
    $_SESSION['hub_admin_user'] = (string) $admin['username'];
    $_SESSION['hub_admin_email'] = (string) $admin['email'];
    $_SESSION['hub_admin_role'] = (string) $admin['role'];
    return true;
}

/**
 * Clean up expired OTPs (call periodically).
 */
function hub_admin_otp_cleanup(mysqli $conn): void
{
    $conn->query('DELETE FROM hub_admin_otp WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)');
}

function hub_admin_logout(): void
{
    hub_admin_session_start();
    unset($_SESSION['hub_admin_id'], $_SESSION['hub_admin_user'], $_SESSION['hub_admin_email'], $_SESSION['hub_admin_role']);
}

// --- Admin user CRUD functions ---

function hub_admin_user_list(mysqli $conn): array
{
    $result = $conn->query('SELECT id, username, email, role, is_active, created_at FROM hub_admin_users ORDER BY id ASC');
    $users = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
    return $users;
}

function hub_admin_user_get(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare('SELECT id, username, email, role, is_active, created_at FROM hub_admin_users WHERE id = ? LIMIT 1');
    if (!$stmt) return null;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function hub_admin_user_create(mysqli $conn, string $username, string $email, string $password, string $role = 'admin'): array
{
    $username = trim($username);
    $email = trim(strtolower($email));
    $role = in_array($role, ['admin', 'superadmin'], true) ? $role : 'admin';

    if ($username === '' || $email === '') {
        return ['ok' => false, 'error' => 'Username and email are required.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid email address.'];
    }

    // Check duplicates
    $stmt = $conn->prepare('SELECT id FROM hub_admin_users WHERE username = ? OR email = ? LIMIT 1');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Database error.'];
    }
    $stmt->bind_param('ss', $username, $email);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        $stmt->close();
        return ['ok' => false, 'error' => 'Username or email already exists.'];
    }
    $stmt->close();

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('INSERT INTO hub_admin_users (username, email, password_hash, role) VALUES (?, ?, ?, ?)');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Database error.'];
    }
    $stmt->bind_param('ssss', $username, $email, $hash, $role);
    if (!$stmt->execute()) {
        $stmt->close();
        return ['ok' => false, 'error' => 'Could not create user.'];
    }
    $newId = (int) $stmt->insert_id;
    $stmt->close();
    return ['ok' => true, 'id' => $newId];
}

function hub_admin_user_update(mysqli $conn, int $id, string $email, string $role, bool $isActive): array
{
    $email = trim(strtolower($email));
    $role = in_array($role, ['admin', 'superadmin'], true) ? $role : 'admin';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid email address.'];
    }

    // Check email duplicate (excluding self)
    $stmt = $conn->prepare('SELECT id FROM hub_admin_users WHERE email = ? AND id != ? LIMIT 1');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Database error.'];
    }
    $stmt->bind_param('si', $email, $id);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        $stmt->close();
        return ['ok' => false, 'error' => 'Email already used by another admin.'];
    }
    $stmt->close();

    $active = $isActive ? 1 : 0;
    $stmt = $conn->prepare('UPDATE hub_admin_users SET email = ?, role = ?, is_active = ? WHERE id = ?');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Database error.'];
    }
    $stmt->bind_param('ssii', $email, $role, $active, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return ['ok' => $ok];
}

function hub_admin_user_reset_password(mysqli $conn, int $id, string $newPassword): bool
{
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('UPDATE hub_admin_users SET password_hash = ? WHERE id = ?');
    if (!$stmt) return false;
    $stmt->bind_param('si', $hash, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function hub_admin_user_delete(mysqli $conn, int $id): bool
{
    // Don't allow deleting yourself
    hub_admin_session_start();
    if ((int) ($_SESSION['hub_admin_id'] ?? 0) === $id) {
        return false;
    }
    $stmt = $conn->prepare('DELETE FROM hub_admin_users WHERE id = ?');
    if (!$stmt) return false;
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok) {
        // Clean up OTPs for deleted user
        $conn->query("DELETE FROM hub_admin_otp WHERE admin_id = {$id}");
    }
    return $ok;
}
