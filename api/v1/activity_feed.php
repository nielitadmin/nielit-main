<?php
/**
 * Poll student/admin activity for n8n / automations (API key required).
 *
 * GET ?since_id=0&limit=100&source=student_record_inspector&action=student_excel_update
 */
require_once __DIR__ . '/../config/api_config.php';
require_once __DIR__ . '/../../includes/activity_logger.php';
require_once __DIR__ . '/../../includes/automation_webhook_helper.php';

$apiData = authenticateApiRequest();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    sendApiError('Only GET is supported', 405, 'METHOD_NOT_ALLOWED');
}

$sinceId = isset($_GET['since_id']) ? (int) $_GET['since_id'] : 0;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
$filters = [
    'source' => trim((string) ($_GET['source'] ?? '')),
    'action' => trim((string) ($_GET['action'] ?? '')),
    'entity_type' => trim((string) ($_GET['entity_type'] ?? '')),
];

$feed = automationFetchActivityFeed($conn, $sinceId, $limit, $filters);

sendApiResponse([
    'since_id' => $sinceId,
    'next_since_id' => (int) ($feed['next_since_id'] ?? $sinceId),
    'count' => count($feed['events'] ?? []),
    'events' => $feed['events'] ?? [],
    'filters' => $filters,
    'hint' => 'Call again with since_id=next_since_id to receive only new events.',
], 200, 'Activity feed');