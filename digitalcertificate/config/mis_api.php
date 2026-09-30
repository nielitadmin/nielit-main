<?php
/**
 * MIS API Configuration
 * Used to pull student data from the NIELIT MIS into the Digital Certificate portal.
 *
 * Permission required: read_write
 * Generate this key at: https://nielitbhubaneswar.in/api/admin/manage_api_keys.php
 */

define('MIS_API_BASE',    'https://nielitbhubaneswar.in/api/v1/');
define('MIS_API_KEY',     '');   // ← Paste your API key here (read_write permission)
define('MIS_API_TIMEOUT', 15);   // seconds

/**
 * Call the MIS API and return decoded JSON data array.
 * Returns ['ok' => true, 'data' => [...]] or ['ok' => false, 'error' => '...']
 */
function mis_api_get(string $endpoint, array $params = []): array
{
    if (MIS_API_KEY === '') {
        return ['ok' => false, 'error' => 'MIS_API_KEY is not set in config/mis_api.php'];
    }

    $url = rtrim(MIS_API_BASE, '/') . '/' . ltrim($endpoint, '/');
    if ($params) {
        $url .= '?' . http_build_query($params);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => MIS_API_TIMEOUT,
        CURLOPT_HTTPHEADER     => [
            'X-API-Key: ' . MIS_API_KEY,
            'Accept: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['ok' => false, 'error' => 'cURL error: ' . $curlErr];
    }

    $json = json_decode($response, true);
    if (!is_array($json)) {
        return ['ok' => false, 'error' => 'Invalid JSON response (HTTP ' . $httpCode . ')'];
    }

    if ($httpCode !== 200) {
        return ['ok' => false, 'error' => $json['message'] ?? ('HTTP ' . $httpCode)];
    }

    // MIS wraps data inside response.data
    $data = $json['data'] ?? $json;
    return ['ok' => true, 'data' => $data];
}
