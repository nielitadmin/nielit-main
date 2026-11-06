<?php
/**
 * Renders expandable scheme / project details for Report Monitor tables.
 *
 * Expected variables:
 * - $schemeDetails (array)
 * - $schemeNames (string, optional fallback)
 * - $schemeDetailBadge (bool, optional — use badge styling for training partner rows)
 */
$schemeDetails = is_array($schemeDetails ?? null) ? $schemeDetails : [];
$schemeNames = trim((string) ($schemeNames ?? ''));
$schemeDetailBadge = !empty($schemeDetailBadge);

if ($schemeDetails === [] && $schemeNames !== '' && $schemeNames !== '—') {
    $schemeDetails = report_monitor_scheme_detail_from_name($schemeNames);
}

if ($schemeDetails === []) {
    echo '—';
    return;
}

$schemeCount = count($schemeDetails);
if ($schemeCount === 1) {
    $summaryLabel = htmlspecialchars($schemeDetails[0]['scheme_name'] ?? 'Scheme / project');
} else {
    $summaryLabel = number_format($schemeCount) . ' schemes / projects';
}
?>
<details class="report-scheme-details text-start d-inline-block w-100">
    <summary class="fw-semibold report-scheme-block <?php echo $schemeDetailBadge ? 'badge bg-light text-dark border' : ''; ?>">
        <?php echo $summaryLabel; ?>
        <?php if ($schemeCount > 1): ?>
            <span class="text-muted fw-normal">(click for details)</span>
        <?php else: ?>
            <span class="text-muted fw-normal">(details)</span>
        <?php endif; ?>
    </summary>
    <ul class="list-unstyled small mb-0 mt-2 report-scheme-detail-list">
        <?php foreach ($schemeDetails as $detail): ?>
            <?php
            $name = trim((string) ($detail['scheme_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $code = trim((string) ($detail['scheme_code'] ?? ''));
            $description = trim((string) ($detail['description'] ?? ''));
            $sponsor = trim((string) ($detail['sponsor_agency'] ?? ''));
            $incharge = trim((string) ($detail['project_incharge_name'] ?? ''));
            $beneficiary = trim((string) ($detail['target_beneficiary'] ?? ''));
            $physicalTarget = $detail['physical_target'] ?? null;
            $startDate = trim((string) ($detail['start_date'] ?? ''));
            $endDate = trim((string) ($detail['end_date'] ?? ''));
            $status = trim((string) ($detail['status'] ?? ''));
            $batchCount = (int) ($detail['batch_count'] ?? 0);
            $batchLabels = trim((string) ($detail['batch_labels'] ?? ''));
            $partnerName = trim((string) ($detail['partner_name'] ?? ''));
            ?>
            <li class="mb-2 p-2 border rounded bg-white">
                <div class="fw-semibold"><?php echo htmlspecialchars($name); ?></div>
                <?php if ($code !== ''): ?>
                    <div><span class="badge bg-secondary"><?php echo htmlspecialchars($code); ?></span></div>
                <?php endif; ?>
                <?php if ($description !== ''): ?>
                    <div class="text-muted mt-1"><?php echo nl2br(htmlspecialchars($description)); ?></div>
                <?php endif; ?>
                <?php if ($sponsor !== ''): ?>
                    <div class="mt-1"><strong>Sponsor:</strong> <?php echo htmlspecialchars($sponsor); ?></div>
                <?php endif; ?>
                <?php if ($incharge !== ''): ?>
                    <div><strong>Project in-charge:</strong> <?php echo htmlspecialchars($incharge); ?></div>
                <?php endif; ?>
                <?php if ($beneficiary !== ''): ?>
                    <div><strong>Target beneficiary:</strong> <?php echo htmlspecialchars($beneficiary); ?></div>
                <?php endif; ?>
                <?php if ($physicalTarget !== null && $physicalTarget > 0): ?>
                    <div><strong>Physical target:</strong> <?php echo number_format($physicalTarget); ?></div>
                <?php endif; ?>
                <?php if ($startDate !== '' || $endDate !== ''): ?>
                    <div><strong>Period:</strong>
                        <?php echo htmlspecialchars(report_monitor_format_batch_period_label($startDate !== '' ? $startDate : null, $endDate !== '' ? $endDate : null)); ?>
                    </div>
                <?php endif; ?>
                <?php if ($status !== ''): ?>
                    <div><strong>Status:</strong> <?php echo htmlspecialchars($status); ?></div>
                <?php endif; ?>
                <?php if ($partnerName !== ''): ?>
                    <div><strong>Training partner:</strong> <?php echo htmlspecialchars($partnerName); ?></div>
                <?php endif; ?>
                <?php if ($batchCount > 0): ?>
                    <div class="mt-1"><strong>Batches (FY):</strong> <?php echo number_format($batchCount); ?>
                        <?php if ($batchLabels !== ''): ?>
                            <div class="text-muted"><?php echo htmlspecialchars($batchLabels); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</details>
