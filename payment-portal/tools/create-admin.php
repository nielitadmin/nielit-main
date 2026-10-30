<?php

/**
 * CLI helper to create or update a payment-hub staff account.
 *
 * Usage (from payment-portal directory):
 *   php tools/create-admin.php <username> <email> <password> [admin|superadmin]
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}

require_once __DIR__ . '/../includes/bootstrap.php';

hub_schema_ensure($hub_conn);

$username = $argv[1] ?? '';
$email = trim(strtolower($argv[2] ?? ''));
$password = $argv[3] ?? '';
$role = $argv[4] ?? 'superadmin';

if ($username === '' || $email === '' || $password === '') {
    fwrite(STDERR, "Usage: php tools/create-admin.php <username> <email> <password> [admin|superadmin]\n");
    exit(1);
}

$existing = hub_admin_find_by_email($hub_conn, $email);
if ($existing) {
    $id = (int) $existing['id'];
    $upd = hub_admin_user_update($hub_conn, $id, $email, $role, true);
    if (!$upd['ok']) {
        fwrite(STDERR, $upd['error'] . "\n");
        exit(1);
    }
    if (!hub_admin_user_reset_password($hub_conn, $id, $password)) {
        fwrite(STDERR, "Could not reset password.\n");
        exit(1);
    }
    echo "Updated user id={$id} ({$email}).\n";
} else {
    $result = hub_admin_user_create($hub_conn, $username, $email, $password, $role);
    if (!$result['ok']) {
        fwrite(STDERR, ($result['error'] ?? 'Create failed') . "\n");
        exit(1);
    }
    echo "Created user id={$result['id']} ({$email}).\n";
}

$res = $hub_conn->query('SELECT id, username, email, role FROM hub_admin_users ORDER BY id');
while ($row = $res->fetch_assoc()) {
    echo sprintf("- %s <%s> [%s]\n", $row['username'], $row['email'], $row['role']);
}
