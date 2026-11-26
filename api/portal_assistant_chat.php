<?php
/**
 * Gyansetu AI Campus & Portal Assistant API (JSON POST).
 */
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/portal_assistant_helper.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST only']);
    exit;
}

if (!portal_assistant_rate_limit_ok()) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Too many messages. Please wait a while and try again.']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($payload)) {
    $payload = $_POST;
}

$action = strtolower(trim((string) ($payload['action'] ?? 'chat')));
if ($action === 'reset') {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    unset($_SESSION['saarthi_reg']);
    $lang = saarthi_normalize_lang((string) ($payload['lang'] ?? ($_SESSION['saarthi_lang'] ?? 'en')));
    $_SESSION['saarthi_lang'] = $lang;
    $assistantConfig = portal_assistant_load_config($conn);
    $assistantName = trim((string) ($assistantConfig['assistant_name'] ?? 'Gyansetu')) ?: 'Gyansetu';
    echo json_encode([
        'ok' => true,
        'reply' => saarthi_localized_text(
            $lang,
            "Chat reset! I'm **{$assistantName}** — ask me how this portal works, check ongoing courses & locations, or start a course registration.",
            "चैट रीसेट हो गई! मैं **{$assistantName}** हूँ — पोर्टल रोडमैप, उपलब्ध कोर्स, कैंपस या नए रजिस्ट्रेशन के बारे में पूछें।",
            "ଚାଟ୍ ରିସେଟ୍ ହୋଇଛି! ମୁଁ **{$assistantName}** — ପୋର୍ଟାଲ୍ ରୋଡ୍‌ମ୍ୟାପ୍, ଚାଲୁଥିବା କୋର୍ସ, କ୍ୟାମ୍ପସ୍ କିମ୍ବା ନୂଆ ପଞ୍ଜୀକରଣ ବିଷୟରେ ପଚାରନ୍ତୁ।"
        ),
        'mode' => 'local',
        'avatar_state' => 'wave',
        'lang' => $lang,
        'ui_action' => 'NONE',
        'quick_replies' => saarthi_default_quick_replies($lang),
        'cards' => [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$message = trim((string) ($payload['message'] ?? ''));
$history = $payload['history'] ?? [];
$langHint = isset($payload['lang']) ? (string) $payload['lang'] : null;
if (!is_array($history)) {
    $history = [];
}

if ($message === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Message is required']);
    exit;
}

$conn = isset($conn) && $conn instanceof mysqli ? $conn : null;
$result = portal_assistant_chat($message, $history, $conn, $langHint);

echo json_encode([
    'ok' => true,
    'reply' => (string) ($result['reply'] ?? ''),
    'mode' => (string) ($result['mode'] ?? 'local'),
    'avatar_state' => (string) ($result['avatar_state'] ?? 'happy'),
    'lang' => (string) ($result['lang'] ?? 'en'),
    'ui_action' => (string) ($result['ui_action'] ?? 'NONE'),
    'quick_replies' => is_array($result['quick_replies'] ?? null) ? $result['quick_replies'] : [],
    'cards' => is_array($result['cards'] ?? null) ? $result['cards'] : [],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
