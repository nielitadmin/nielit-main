<?php
/**
 * Public course display helpers (project name / course level label)
 */

if (!function_exists('ensureCourseProjectLevelColumn')) {
    function ensureCourseProjectLevelColumn(mysqli $conn): void {
        $check = $conn->query("SHOW COLUMNS FROM courses LIKE 'project_level_label'");
        if ($check && $check->num_rows > 0) {
            return;
        }
        $sql = "ALTER TABLE courses ADD COLUMN project_level_label VARCHAR(255) DEFAULT NULL";
        $conn->query($sql);
    }
}

if (!function_exists('renderCourseProjectLevelHeader')) {
    function renderCourseProjectLevelHeader(array $row): string {
        $label = trim($row['project_level_label'] ?? '');
        if ($label === '') {
            return '';
        }
        return '<div class="course-project-level"><i class="fas fa-layer-group"></i>'
            . htmlspecialchars($label) . '</div>';
    }
}

if (!function_exists('renderCourseProjectLevelInfoItem')) {
    function renderCourseProjectLevelInfoItem(array $row): string {
        $label = trim($row['project_level_label'] ?? '');
        if ($label === '') {
            return '';
        }
        return '<div class="info-item"><i class="fas fa-layer-group"></i><div>'
            . '<span class="info-label">Project / Level</span>'
            . '<span class="info-value">' . htmlspecialchars($label) . '</span>'
            . '</div></div>';
    }
}
