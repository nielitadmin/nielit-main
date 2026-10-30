<?php

function hub_financial_year(?DateTime $when = null): string
{
    $when = $when ?? new DateTime('now', new DateTimeZone('Asia/Kolkata'));
    $year = (int) $when->format('Y');
    $month = (int) $when->format('n');
    if ($month >= 4) {
        $start = $year;
        $end = $year + 1;
    } else {
        $start = $year - 1;
        $end = $year;
    }
    return sprintf('%d-%02d', $start, $end % 100);
}
