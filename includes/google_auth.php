<?php
/**
 * Verify Google Identity Services ID tokens for admin login.
 */

if (!function_exists('getGoogleOAuthJavaScriptOrigins')) {
    /**
     * Origins that must be registered in Google Cloud Console for Sign-In to work.
     *
     * @return string[]
     */
    function getGoogleOAuthJavaScriptOrigins()
    {
        return [
            'https://nielitbhubaneswar.in',
            'https://www.nielitbhubaneswar.in',
            'http://localhost',
            'http://127.0.0.1',
        ];
    }
}

if (!function_exists('getAppUrlOriginHint')) {
    function getAppUrlOriginHint()
    {
        $appUrl = defined('APP_URL') ? (string) APP_URL : '';
        $parts = parse_url($appUrl);
        if (!is_array($parts) || empty($parts['host'])) {
            return 'http://localhost';
        }

        $origin = ($parts['scheme'] ?? 'http') . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return $origin;
    }
}

if (!function_exists('verifyGoogleIdToken')) {
    /**
     * @return array|null Decoded token payload on success
     */
    function verifyGoogleIdToken($idToken, $expectedClientId)
    {
        $idToken = trim($idToken);
        if ($idToken === '' || $expectedClientId === '') {
            return null;
        }

        $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($idToken);
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            error_log('Google token verification: HTTP request failed');
            return null;
        }

        $payload = json_decode($response, true);
        if (!is_array($payload) || isset($payload['error'])) {
            error_log('Google token verification: invalid response');
            return null;
        }

        if (($payload['aud'] ?? '') !== $expectedClientId) {
            error_log('Google token verification: audience mismatch');
            return null;
        }

        if (($payload['email_verified'] ?? 'false') !== 'true' && ($payload['email_verified'] ?? false) !== true) {
            error_log('Google token verification: email not verified');
            return null;
        }

        if (empty($payload['email'])) {
            return null;
        }

        $expiresAt = (int) ($payload['exp'] ?? 0);
        if ($expiresAt > 0 && $expiresAt < time()) {
            error_log('Google token verification: token expired');
            return null;
        }

        return $payload;
    }
}
