<?php
/**
 * Fix faculty rows that store '' in email — only one blank string is allowed by UNIQUE(email).
 * Run once: /migrations/fix_faculty_empty_emails.php
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain; charset=utf-8');

$result = $conn->query("UPDATE faculty SET email = NULL WHERE email = ''");
if ($result === false) {
    echo "Error: " . $conn->error . "\n";
    exit(1);
}

echo "Updated " . $conn->affected_rows . " faculty row(s): empty email set to NULL.\n";
echo "You can now add multiple staff members without an email address.\n";
