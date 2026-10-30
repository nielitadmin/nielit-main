<?php

function hub_dashboard_stats(mysqli $conn): array
{
    $stats = [
        'today_count' => 0,
        'today_amount' => 0,
        'month_count' => 0,
        'month_amount' => 0,
        'failed_today' => 0,
        'refunded_month' => 0,
        'open_checkouts' => 0,
    ];

    $q = $conn->query("SELECT COUNT(*) AS c, COALESCE(SUM(amount_paise),0) AS s FROM hub_payments WHERE status IN ('paid','partial_refund') AND DATE(paid_at) = CURDATE()");
    if ($q && ($r = $q->fetch_assoc())) {
        $stats['today_count'] = (int) $r['c'];
        $stats['today_amount'] = (int) $r['s'];
    }

    $q = $conn->query("SELECT COUNT(*) AS c, COALESCE(SUM(amount_paise),0) AS s FROM hub_payments WHERE status IN ('paid','partial_refund') AND paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
    if ($q && ($r = $q->fetch_assoc())) {
        $stats['month_count'] = (int) $r['c'];
        $stats['month_amount'] = (int) $r['s'];
    }

    $q = $conn->query("SELECT COUNT(*) AS c FROM hub_payments WHERE status = 'failed' AND DATE(created_at) = CURDATE()");
    if ($q && ($r = $q->fetch_assoc())) {
        $stats['failed_today'] = (int) $r['c'];
    }

    $q = $conn->query("SELECT COUNT(*) AS c FROM hub_payments WHERE status IN ('refunded','partial_refund') AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
    if ($q && ($r = $q->fetch_assoc())) {
        $stats['refunded_month'] = (int) $r['c'];
    }

    $q = $conn->query("SELECT COUNT(*) AS c FROM hub_checkouts WHERE status IN ('created','attempted') AND expires_at > NOW()");
    if ($q && ($r = $q->fetch_assoc())) {
        $stats['open_checkouts'] = (int) $r['c'];
    }

    return $stats;
}

function hub_dashboard_today_by_source(mysqli $conn): array
{
    $rows = [];
    $sql = "SELECT s.code, s.display_name,
        COALESCE(SUM(CASE WHEN p.status IN ('paid','partial_refund') AND DATE(p.paid_at) = CURDATE() THEN p.amount_paise ELSE 0 END), 0) AS paid_amount,
        COALESCE(SUM(CASE WHEN p.status IN ('paid','partial_refund') AND DATE(p.paid_at) = CURDATE() THEN 1 ELSE 0 END), 0) AS paid_count,
        COALESCE(SUM(CASE WHEN p.status = 'failed' AND DATE(p.created_at) = CURDATE() THEN 1 ELSE 0 END), 0) AS failed_count
        FROM hub_sources s
        LEFT JOIN hub_payments p ON p.source_id = s.id
        WHERE s.is_active = 1
        GROUP BY s.id, s.code, s.display_name
        ORDER BY s.display_name ASC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    $pendingSql = "SELECT s.code, COUNT(*) AS pending_count, COALESCE(SUM(c.amount_paise), 0) AS pending_amount
        FROM hub_checkouts c
        INNER JOIN hub_sources s ON s.id = c.source_id
        WHERE c.status IN ('created','attempted') AND c.expires_at > NOW()
        GROUP BY s.code";
    $pendingByCode = [];
    $pq = $conn->query($pendingSql);
    if ($pq) {
        while ($pr = $pq->fetch_assoc()) {
            $pendingByCode[(string) $pr['code']] = $pr;
        }
    }
    foreach ($rows as &$row) {
        $code = (string) $row['code'];
        $pending = $pendingByCode[$code] ?? null;
        $row['pending_count'] = (int) ($pending['pending_count'] ?? 0);
        $row['pending_amount'] = (int) ($pending['pending_amount'] ?? 0);
    }
    unset($row);

    return $rows;
}

function hub_dashboard_recent_payments(mysqli $conn, int $limit = 8): array
{
    $rows = [];
    $limit = max(1, min(50, $limit));
    $sql = "SELECT p.*, s.code AS source_code FROM hub_payments p
            INNER JOIN hub_sources s ON s.id = p.source_id
            ORDER BY p.id DESC LIMIT " . $limit;
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}
