<?php
/**
 * Migration: install lesson_plans tables
 */
require_once __DIR__ . '/../includes/lesson_plan_helper.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo "<div class='error'>No database connection.</div>";
    return;
}

if (ensureLessonPlanTables($conn)) {
    echo "<div class='success'>Lesson plan tables ready (lesson_plans, lesson_plan_rows, lesson_plan_daily_logs).</div>";
} else {
    echo "<div class='error'>Failed to create lesson plan tables. Check error log.</div>";
}
