<?php
/**
 * Shared logic for renaming legacy course sub-category labels in the database.
 */

require_once __DIR__ . '/course_category_options.php';

if (!function_exists('get_sub_category_label_migration_steps')) {

    function get_sub_category_label_migration_steps($conn) {
        $steps = [
            [
                'key' => 'courses_category',
                'label' => 'courses.category (Govt/Corporate Training)',
                'count_sql' => "SELECT COUNT(*) AS c FROM courses WHERE category = 'GOVT/CORPORATE Training'",
                'update_sql' => "UPDATE courses SET category = 'Govt/Corporate Training' WHERE category = 'GOVT/CORPORATE Training'",
            ],
            [
                'key' => 'courses_course_type',
                'label' => 'courses.course_type (Govt/Corporate Training)',
                'count_sql' => "SELECT COUNT(*) AS c FROM courses WHERE course_type = 'GOVT/CORPORATE Training'",
                'update_sql' => "UPDATE courses SET course_type = 'Govt/Corporate Training' WHERE course_type = 'GOVT/CORPORATE Training'",
            ],
        ];

        $templates_exists = $conn->query("SHOW TABLES LIKE 'nsqf_course_templates'");
        if ($templates_exists && $templates_exists->num_rows > 0) {
            $steps[] = [
                'key' => 'nsqf_templates',
                'label' => 'nsqf_course_templates.nsqf_type (Non-NSQF Course)',
                'count_sql' => "SELECT COUNT(*) AS c FROM nsqf_course_templates WHERE nsqf_type = 'NON-NSQF Course'",
                'update_sql' => "UPDATE nsqf_course_templates SET nsqf_type = 'Non-NSQF Course' WHERE nsqf_type = 'NON-NSQF Course'",
            ];
        }

        return $steps;
    }

    function get_sub_category_label_migration_pending($conn) {
        $pending = [];
        $total = 0;

        foreach (get_sub_category_label_migration_steps($conn) as $step) {
            $count = 0;
            $result = $conn->query($step['count_sql']);
            if ($result) {
                $count = (int) ($result->fetch_assoc()['c'] ?? 0);
            }
            $pending[$step['key']] = [
                'label' => $step['label'],
                'count' => $count,
            ];
            $total += $count;
        }

        return [
            'steps' => $pending,
            'total' => $total,
            'is_complete' => $total === 0,
        ];
    }

    function run_sub_category_label_migration($conn) {
        $results = [];

        foreach (get_sub_category_label_migration_steps($conn) as $step) {
            $entry = [
                'key' => $step['key'],
                'label' => $step['label'],
                'success' => false,
                'affected' => 0,
                'error' => '',
            ];

            if ($conn->query($step['update_sql'])) {
                $entry['success'] = true;
                $entry['affected'] = (int) $conn->affected_rows;
            } else {
                $entry['error'] = $conn->error;
            }

            $results[] = $entry;
        }

        return $results;
    }
}
