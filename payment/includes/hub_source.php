<?php

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

function hub_source_return_allowed(array $source, string $returnUrl): bool
{
    $prefixes = json_decode((string) ($source['allowed_return_prefixes'] ?? '[]'), true);
    if (!is_array($prefixes)) {
        return false;
    }
    foreach ($prefixes as $prefix) {
        $prefix = (string) $prefix;
        if ($prefix !== '' && strpos($returnUrl, $prefix) === 0) {
            return true;
        }
    }
    return false;
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
