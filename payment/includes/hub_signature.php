<?php

function hub_sign_body(string $body, string $secret): string
{
    return hash_hmac('sha256', $body, $secret);
}

function hub_verify_signature(string $body, string $secret, string $provided): bool
{
    if ($provided === '') {
        return false;
    }
    $expected = hub_sign_body($body, $secret);
    return hash_equals($expected, $provided);
}

function hub_header_signature(): string
{
    $sig = $_SERVER['HTTP_X_PAYMENT_SIGNATURE'] ?? '';
    if ($sig !== '') {
        return trim($sig);
    }
    return trim((string) ($_SERVER['HTTP_X_HUB_SIGNATURE'] ?? ''));
}
