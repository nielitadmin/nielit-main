<?php
/**
 * Public course display helpers (project name / course level label)
 */

if (!function_exists('ensureCourseProjectLevelColumn')) {
    function ensureCourseProjectLevelColumn(mysqli $conn): void {
        $conn->query("ALTER TABLE courses ADD COLUMN IF NOT EXISTS project_level_label VARCHAR(255) DEFAULT NULL");
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
