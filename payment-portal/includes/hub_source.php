<?php

function hub_source_code_valid(string $code): bool
{
    $code = strtolower(trim($code));
    return (bool) preg_match('/^[a-z][a-z0-9_]{1,31}$/', $code);
}

function hub_source_by_code(mysqli $conn, string $code): ?array
{
    $code = strtolower(trim($code));
    $stmt = $conn->prepare('SELECT * FROM hub_sources WHERE code = ? AND is_active = 1 LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function hub_source_by_id(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare('SELECT * FROM hub_sources WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function hub_source_return_allowed(array $source, string $returnUrl): bool
{
    $prefixes = hub_source_prefixes_array($source);
    foreach ($prefixes as $prefix) {
        if ($prefix !== '' && strpos($returnUrl, $prefix) === 0) {
            return true;
        }
    }
    return false;
}

function hub_source_prefixes_array(array $source): array
{
    $prefixes = json_decode((string) ($source['allowed_return_prefixes'] ?? '[]'), true);
    return is_array($prefixes) ? array_values(array_filter(array_map('strval', $prefixes))) : [];
}

function hub_source_prefixes_from_text(string $text): array
{
    $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
    $out = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (!preg_match('#^https?://#i', $line)) {
            continue;
        }
        $out[] = rtrim($line, '/') . '/';
    }
    return array_values(array_unique($out));
}

function hub_source_list(mysqli $conn): array
{
    $rows = [];
    $result = $conn->query('SELECT id, code, display_name, is_active, created_at FROM hub_sources ORDER BY code ASC');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function hub_source_list_full(mysqli $conn): array
{
    $rows = [];
    $result = $conn->query('SELECT * FROM hub_sources ORDER BY is_active DESC, display_name ASC');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function hub_source_create(mysqli $conn, string $code, string $displayName, array $prefixes): array
{
    $code = strtolower(trim($code));
    $displayName = trim($displayName);
    if (!hub_source_code_valid($code)) {
        return ['ok' => false, 'error' => 'Source code must be 2–32 chars: lowercase letters, numbers, underscore; start with a letter.'];
    }
    if ($displayName === '') {
        return ['ok' => false, 'error' => 'Display name is required.'];
    }
    if ($prefixes === []) {
        return ['ok' => false, 'error' => 'Add at least one allowed return URL prefix (https://…).'];
    }
    $existing = hub_source_by_id_for_code($conn, $code);
    if ($existing) {
        return ['ok' => false, 'error' => 'Source code already exists.'];
    }
    $secret = bin2hex(random_bytes(24));
    $jsonPrefixes = json_encode($prefixes, JSON_UNESCAPED_SLASHES);
    $stmt = $conn->prepare('INSERT INTO hub_sources (code, display_name, allowed_return_prefixes, shared_secret, is_active) VALUES (?, ?, ?, ?, 1)');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Database error'];
    }
    $stmt->bind_param('ssss', $code, $displayName, $jsonPrefixes, $secret);
    if (!$stmt->execute()) {
        $stmt->close();
        return ['ok' => false, 'error' => 'Could not create source (duplicate code?)'];
    }
    $id = (int) $stmt->insert_id;
    $stmt->close();
    return ['ok' => true, 'id' => $id, 'secret' => $secret];
}

function hub_source_by_id_for_code(mysqli $conn, string $code): ?array
{
    $code = strtolower(trim($code));
    $stmt = $conn->prepare('SELECT id FROM hub_sources WHERE code = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function hub_source_update(mysqli $conn, int $id, string $displayName, array $prefixes, bool $isActive): array
{
    $row = hub_source_by_id($conn, $id);
    if (!$row) {
        return ['ok' => false, 'error' => 'Source not found'];
    }
    $displayName = trim($displayName);
    if ($displayName === '') {
        return ['ok' => false, 'error' => 'Display name is required.'];
    }
    if ($prefixes === []) {
        return ['ok' => false, 'error' => 'Add at least one allowed return URL prefix.'];
    }
    $jsonPrefixes = json_encode($prefixes, JSON_UNESCAPED_SLASHES);
    $active = $isActive ? 1 : 0;
    $stmt = $conn->prepare('UPDATE hub_sources SET display_name = ?, allowed_return_prefixes = ?, is_active = ? WHERE id = ?');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Database error'];
    }
    $stmt->bind_param('ssii', $displayName, $jsonPrefixes, $active, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'Update failed'];
}

function hub_source_rotate_secret(mysqli $conn, int $id): array
{
    $row = hub_source_by_id($conn, $id);
    if (!$row) {
        return ['ok' => false, 'error' => 'Source not found'];
    }
    $secret = bin2hex(random_bytes(24));
    $stmt = $conn->prepare('UPDATE hub_sources SET shared_secret = ? WHERE id = ?');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Database error'];
    }
    $stmt->bind_param('si', $secret, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok ? ['ok' => true, 'secret' => $secret] : ['ok' => false, 'error' => 'Rotate failed'];
}

function hub_source_prefixes_to_text(array $source): string
{
    return implode("\n", hub_source_prefixes_array($source));
}
