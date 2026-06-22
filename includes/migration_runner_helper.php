<?php
/**
 * Admin web runner for migrations/ scripts (blocked from direct HTTP by root .htaccess).
 */

if (!function_exists('migration_runner_dir')) {
    function migration_runner_dir() {
        return realpath(__DIR__ . '/../migrations') ?: (__DIR__ . '/../migrations');
    }

    function migration_runner_ensure_tracking_table($conn) {
        $sql = "CREATE TABLE IF NOT EXISTS migration_runs (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            migration_file VARCHAR(255) NOT NULL,
            status ENUM('success','failed') NOT NULL DEFAULT 'success',
            run_by VARCHAR(120) DEFAULT NULL,
            output MEDIUMTEXT,
            error_message TEXT,
            run_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_migration_file (migration_file),
            KEY idx_run_at (run_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        return (bool) $conn->query($sql);
    }

    function migration_runner_validate_filename($filename) {
        $filename = basename(str_replace('\\', '/', (string) $filename));
        if ($filename === '' || !preg_match('/^[A-Za-z0-9._-]+\.php$/', $filename)) {
            throw new InvalidArgumentException('Invalid migration file name.');
        }

        $path = migration_runner_dir() . DIRECTORY_SEPARATOR . $filename;
        if (!is_file($path)) {
            throw new InvalidArgumentException('Migration file not found.');
        }

        $realDir = realpath(migration_runner_dir());
        $realFile = realpath($path);
        if (!$realDir || !$realFile || strpos($realFile, $realDir) !== 0) {
            throw new InvalidArgumentException('Migration path is not allowed.');
        }

        return $filename;
    }

    function migration_runner_category($filename) {
        $base = strtolower($filename);
        if (strpos($base, 'install_') === 0) {
            return 'install';
        }
        if (strpos($base, 'add_') === 0 || strpos($base, 'create_') === 0 || strpos($base, 'setup_') === 0) {
            return 'schema';
        }
        if (strpos($base, 'fix_') === 0 || strpos($base, 'urgent_') === 0) {
            return 'fix';
        }
        if (preg_match('/^(populate_|assign_|migrate_)/', $base)) {
            return 'data';
        }
        if (preg_match('/^(check_|verify_|manual_test_)/', $base)) {
            return 'check';
        }
        return 'other';
    }

    function migration_runner_category_label($category) {
        $labels = [
            'install' => 'Install',
            'schema' => 'Schema / Add',
            'fix' => 'Fix',
            'data' => 'Data / Sample',
            'check' => 'Check / Verify',
            'other' => 'Other',
        ];
        return $labels[$category] ?? 'Other';
    }

    function migration_runner_is_sensitive($filename) {
        return (bool) preg_match('/^(populate_|assign_test_|assign_courses_)/i', $filename);
    }

    function migration_runner_needs_command($filename) {
        return in_array($filename, ['install_rbac.php', 'install_document_categories.php'], true);
    }

    function migration_runner_extract_description($filepath) {
        $contents = @file_get_contents($filepath, false, null, 0, 4096);
        if ($contents === false) {
            return '';
        }

        if (preg_match('/\/\*\*(.*?)\*\//s', $contents, $matches)) {
            $block = preg_replace('/^\s*\*\s?/m', '', $matches[1]);
            $lines = array_values(array_filter(array_map('trim', explode("\n", $block))));
            return $lines[0] ?? '';
        }

        return '';
    }

    function migration_runner_list_files() {
        $dir = migration_runner_dir();
        $files = glob($dir . '/*.php') ?: [];
        $items = [];

        foreach ($files as $path) {
            $filename = basename($path);
            $items[] = [
                'filename' => $filename,
                'description' => migration_runner_extract_description($path),
                'category' => migration_runner_category($filename),
                'category_label' => migration_runner_category_label(migration_runner_category($filename)),
                'sensitive' => migration_runner_is_sensitive($filename),
                'needs_command' => migration_runner_needs_command($filename),
                'modified_at' => @filemtime($path) ?: null,
            ];
        }

        usort($items, static function ($a, $b) {
            $order = ['install' => 0, 'schema' => 1, 'fix' => 2, 'data' => 3, 'check' => 4, 'other' => 5];
            $catA = $order[$a['category']] ?? 99;
            $catB = $order[$b['category']] ?? 99;
            if ($catA !== $catB) {
                return $catA <=> $catB;
            }
            return strcasecmp($a['filename'], $b['filename']);
        });

        return $items;
    }

    function migration_runner_get_latest_runs($conn) {
        migration_runner_ensure_tracking_table($conn);

        $runs = [];
        $sql = "SELECT mr.*
                FROM migration_runs mr
                INNER JOIN (
                    SELECT migration_file, MAX(id) AS max_id
                    FROM migration_runs
                    GROUP BY migration_file
                ) latest ON latest.max_id = mr.id";

        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $runs[$row['migration_file']] = $row;
            }
        }

        return $runs;
    }

    function migration_runner_reconnect_db() {
        global $conn;
        require __DIR__ . '/../config/database.php';
        return $conn;
    }

    function migration_runner_record_run($conn, $filename, $runBy, $status, $output, $errorMessage = '') {
        migration_runner_ensure_tracking_table($conn);

        $stmt = $conn->prepare(
            'INSERT INTO migration_runs (migration_file, status, run_by, output, error_message)
             VALUES (?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('sssss', $filename, $status, $runBy, $output, $errorMessage);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    function migration_runner_prepare_context($filename, $command = 'install') {
        if (!defined('MIGRATION_WEB_RUNNER')) {
            define('MIGRATION_WEB_RUNNER', true);
        }

        $command = preg_replace('/[^a-z_]/', '', strtolower((string) $command));
        if ($command === '') {
            $command = 'install';
        }

        $GLOBALS['migration_web_command'] = $command;

        if ($filename === 'install_document_categories.php') {
            $_GET['action'] = $command;
        }
    }

    function migration_runner_execute($conn, $filename, $runBy = '', $command = 'install') {
        $filename = migration_runner_validate_filename($filename);
        $path = migration_runner_dir() . DIRECTORY_SEPARATOR . $filename;

        migration_runner_prepare_context($filename, $command);

        $state = [
            'finished' => false,
            'recorded' => false,
            'success' => true,
            'output' => '',
            'error' => '',
            'filename' => $filename,
            'runBy' => $runBy,
        ];

        $recordRun = null;
        $recordRun = static function () use (&$state, &$conn, &$recordRun) {
            if ($state['recorded']) {
                return;
            }
            migration_runner_reconnect_db();
            migration_runner_record_run(
                $conn,
                $state['filename'],
                $state['runBy'],
                $state['success'] ? 'success' : 'failed',
                $state['output'],
                $state['error']
            );
            $state['recorded'] = true;
        };

        ob_start();
        register_shutdown_function(static function () use (&$state, $recordRun) {
            if ($state['finished']) {
                return;
            }

            while (ob_get_level() > 0) {
                $state['output'] = ob_get_contents() . $state['output'];
                ob_end_clean();
            }

            if ($state['output'] === '') {
                $state['output'] = 'Migration finished (script exited).';
            }

            $recordRun();
        });

        try {
            include $path;
            $state['output'] = (string) ob_get_clean();
            if ($state['output'] === '') {
                $state['output'] = 'Migration finished with no output.';
            }
        } catch (Throwable $e) {
            if (ob_get_level() > 0) {
                $state['output'] = (string) ob_get_clean() . $state['output'];
            }
            $state['success'] = false;
            $state['error'] = $e->getMessage();
        }

        $recordRun();
        $state['finished'] = true;

        return [
            'success' => $state['success'],
            'output' => $state['output'],
            'error' => $state['error'],
            'filename' => $state['filename'],
        ];
    }
}
