<?php

/**
 * Checkout and payment status values used across the hub.
 */
function hub_checkout_statuses(): array
{
    return ['created', 'attempted', 'paid', 'failed', 'cancelled', 'expired'];
}

function hub_payment_statuses(): array
{
    return ['paid', 'failed', 'partial_refund', 'refunded'];
}

function hub_refund_statuses(): array
{
    return ['pending', 'processed', 'failed'];
}

function hub_checkout_status_label(string $status): string
{
    $labels = [
        'created' => 'Link created',
        'attempted' => 'Razorpay opened',
        'paid' => 'Paid',
        'failed' => 'Payment failed',
        'cancelled' => 'Cancelled by payer',
        'expired' => 'Link expired',
    ];
    return $labels[$status] ?? $status;
}

function hub_payment_status_label(string $status): string
{
    $labels = [
        'paid' => 'Paid',
        'failed' => 'Failed',
        'partial_refund' => 'Part refunded',
        'refunded' => 'Fully refunded',
    ];
    return $labels[$status] ?? $status;
}
