<?php
/**
 * Migration: Create saswat table
 *
 * Custom table (saswat1–saswat4 integer columns).
 * Safe to run multiple times — skips if the table already exists.
 *
 * Run from browser:
 *   http://localhost/public_html_1/migrations/install_saswat_table.php
 *
 * Or CLI:
 *   php migrations/install_saswat_table.php
 */

require_once __DIR__ . '/../config/config.php';

$isCli = (PHP_SAPI === 'cli');
$lineBreak = $isCli ? "\n" : '<br>';
$heading = function (string $text) use ($isCli, $lineBreak) {
    if ($isCli) {
        echo "==============================================\n{$text}\n==============================================\n\n";
        return;
    }
    echo "<h2>{$text}</h2>";
};

$heading('Install saswat table');

try {
    $check = $conn->query("SHOW TABLES LIKE 'saswat'");

    if ($check && $check->num_rows > 0) {
        echo "✓ Table 'saswat' already exists.{$lineBreak}";
    } else {
        $sql = "CREATE TABLE `saswat` (
            `saswat1` int(11) NOT NULL,
            `saswat2` smallint(6) NOT NULL,
            `saswat3` int(11) NOT NULL,
            `saswat4` int(11) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if ($conn->query($sql)) {
            echo "✓ Table 'saswat' created successfully.{$lineBreak}";
        } else {
            throw new RuntimeException('Create failed: ' . $conn->error);
        }
    }

    $columns = $conn->query("SHOW COLUMNS FROM saswat");
    if ($columns) {
        echo "{$lineBreak}Columns:{$lineBreak}";
        while ($col = $columns->fetch_assoc()) {
            echo "  - {$col['Field']} ({$col['Type']}){$lineBreak}";
        }
    }

    $count = $conn->query('SELECT COUNT(*) AS c FROM saswat');
    if ($count && ($row = $count->fetch_assoc())) {
        echo "{$lineBreak}Row count: " . (int) $row['c'] . "{$lineBreak}";
    }

    echo "{$lineBreak}Done.{$lineBreak}";
} catch (Throwable $e) {
    echo "✗ Error: " . htmlspecialchars($e->getMessage()) . $lineBreak;
    exit(1);
}

$conn->close();

if (!$isCli) {
    echo '<p><a href="../admin/dashboard.php">← Back to Dashboard</a></p>';
}
