<?php
/**
 * Module / semester exam results for NSQF courses (e.g. O-Level M1–M4).
 * Run once: php migrations/install_nsqf_exam_results.php
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/nsqf_exam_results_helper.php';

ensureNsqfExamResultsSchema($conn);
echo "NSQF exam results schema OK.\n";