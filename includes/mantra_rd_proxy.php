<?php
/**
 * Same-origin proxy to Mantra RD Service on this Windows PC (127.0.0.1:11100–11120).
 * Avoids Chrome CORS: the browser talks to PHP; PHP talks to the scanner.
 * Only works when this site is served on the kiosk PC (XAMPP/localhost), not a remote server.
 */

if (!function_exists('mantraRdPidOptionsXml')) {
    function mantraRdPidOptionsXml(): string
    {
        return '<?xml version="1.0"?><PidOptions ver="1.0"><Opts fCount="1" fType="0" iCount="0" pCount="0" format="0" pidVer="2.0" timeout="20000" posh="UNKNOWN" env="P"/></PidOptions>';
    }
}

if (!function_exists('mantraRdLooksLikeXml')) {
    function mantraRdLooksLikeXml(string $text): bool
    {
        return $text !== '' && preg_match('/RDService|rdsVer|status|DeviceInfo|PidData/i', $text) === 1;
    }
}

if (!function_exists('mantraRdOriginIsAllowed')) {
    function mantraRdOriginIsAllowed(string $origin): bool
    {
        $origin = rtrim(trim($origin), '/');
        if (!preg_match('#^https?://127\.0\.0\.1:(\d+)$#', $origin, $m)) {
            return false;
        }
        $port = (int) $m[1];
        return $port >= 11100 && $port <= 11120;
    }
}

if (!function_exists('mantraRdHttp')) {
    /**
     * @return array{ok:bool,body:string,error:string,http_code:int}
     */
    function mantraRdHttp(string $url, string $method, string $body = '', int $timeout = 8): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'body' => '', 'error' => 'PHP cURL is not enabled in XAMPP.', 'http_code' => 0];
        }
        $ch = curl_init($url);
        $headers = [
            'Accept: text/xml, application/xml, */*',
            'Cache-Control: no-cache',
        ];
        if ($body !== '') {
            $headers[] = 'Content-Type: text/xml';
            $headers[] = 'Content-Length: ' . (string) strlen($body);
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ]);
        if ($body !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $raw = curl_exec($ch);
        $err = (string) curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $text = is_string($raw) ? $raw : '';
        if ($text === '' && $err !== '') {
            return ['ok' => false, 'body' => '', 'error' => $err, 'http_code' => $code];
        }
        return ['ok' => true, 'body' => $text, 'error' => $err, 'http_code' => $code];
    }
}

if (!function_exists('mantraRdDiscoverLocal')) {
    /**
     * @return array{origin:string,infoXml:string}|null
     */
    function mantraRdDiscoverLocal(): ?array
    {
        if (!function_exists('curl_multi_init')) {
            $res = mantraRdHttp('https://127.0.0.1:11100/', 'RDSERVICE', '', 2);
            if ($res['ok'] && mantraRdLooksLikeXml($res['body'])) {
                return ['origin' => 'https://127.0.0.1:11100', 'infoXml' => $res['body']];
            }
            return null;
        }
        $found = mantraRdDiscoverWithMethod('RDSERVICE');
        if ($found) {
            return $found;
        }
        return mantraRdDiscoverWithMethod('GET');
    }
}

if (!function_exists('mantraRdDiscoverWithMethod')) {
    /**
     * @return array{origin:string,infoXml:string}|null
     */
    function mantraRdDiscoverWithMethod(string $method): ?array
    {
        $mh = curl_multi_init();
        $handles = [];
        for ($port = 11100; $port <= 11120; $port++) {
            foreach (['https', 'http'] as $scheme) {
                $origin = $scheme . '://127.0.0.1:' . $port;
                $ch = curl_init($origin . '/');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CUSTOMREQUEST => $method,
                    CURLOPT_TIMEOUT => 1,
                    CURLOPT_CONNECTTIMEOUT => 1,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_NOSIGNAL => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_HTTPHEADER => ['Accept: text/xml, application/xml, */*'],
                ]);
                curl_multi_add_handle($mh, $ch);
                $handles[] = ['ch' => $ch, 'origin' => $origin];
            }
        }
        $running = 0;
        do {
            curl_multi_exec($mh, $running);
            if ($running > 0) {
                curl_multi_select($mh, 0.2);
            }
        } while ($running > 0);

        $found = null;
        foreach ($handles as $item) {
            $body = (string) curl_multi_getcontent($item['ch']);
            if ($found === null && mantraRdLooksLikeXml($body)) {
                $found = ['origin' => $item['origin'], 'infoXml' => $body];
            }
            curl_multi_remove_handle($mh, $item['ch']);
            curl_close($item['ch']);
        }
        curl_multi_close($mh);
        return $found;
    }
}

if (!function_exists('mantraRdCaptureLocal')) {
    /**
     * @return array{ok:bool,xml:string,message:string}
     */
    function mantraRdCaptureLocal(string $origin): array
    {
        $origin = rtrim(trim($origin), '/');
        if (!mantraRdOriginIsAllowed($origin)) {
            return ['ok' => false, 'xml' => '', 'message' => 'Invalid RD Service address.'];
        }
        $url = $origin . '/rd/capture';
        $xml = mantraRdPidOptionsXml();
        $res = mantraRdHttp($url, 'CAPTURE', $xml, 25);
        if ($res['ok'] && mantraRdLooksLikeXml($res['body'])) {
            return ['ok' => true, 'xml' => $res['body'], 'message' => 'ok'];
        }
        if ($res['ok'] && $res['body'] !== '') {
            return ['ok' => false, 'xml' => '', 'message' => 'RD Service did not return a fingerprint PID. Try Capture again.'];
        }
        if (!$res['ok'] && stripos($res['error'], 'timed out') !== false) {
            return ['ok' => false, 'xml' => '', 'message' => 'Fingerprint capture timed out. Place the finger on the scanner and try again.'];
        }
        $resPost = ['ok' => false, 'body' => '', 'error' => '', 'http_code' => 0];
        if ($res['http_code'] === 405 || $res['http_code'] === 404 || stripos($res['error'], 'Failed to connect') !== false) {
            $resPost = mantraRdHttp($url, 'POST', $xml, 25);
            if ($resPost['ok'] && mantraRdLooksLikeXml($resPost['body'])) {
                return ['ok' => true, 'xml' => $resPost['body'], 'message' => 'ok'];
            }
        }
        $err = $res['error'] !== '' ? $res['error'] : $resPost['error'];
        if ($err !== '') {
            return [
                'ok' => false,
                'xml' => '',
                'message' => 'Could not reach Mantra RD Service from this PC (' . $err . '). Open Fingerprint Attendance on the scanner PC via http://localhost/public_html/',
            ];
        }
        return ['ok' => false, 'xml' => '', 'message' => 'RD Service returned an empty capture. Place the finger and try again.'];
    }
}
