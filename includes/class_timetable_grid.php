<?php
/**
 * Spreadsheet-style weekly timetable grid (days × time periods).
 *
 * Expected vars:
 * - $slots (list)
 * - $ctGridEditable (bool) admin edit/delete/add-on-empty
 * - $ctGridCsrf (string) when editable
 * - $ctGridFilterBatch (int)
 * - $ctGridShowLegends (bool) default true
 */
if (!isset($slots) || !is_array($slots)) {
    $slots = [];
}
$ctGridEditable = !empty($ctGridEditable);
$ctGridCsrf = (string) ($ctGridCsrf ?? '');
$ctGridFilterBatch = (int) ($ctGridFilterBatch ?? 0);
$ctGridShowLegends = !isset($ctGridShowLegends) || $ctGridShowLegends;

$built = classTimetableBuildGrid($slots);
$days = $built['days'];
$periods = $built['periods'];
$grid = $built['grid'];
$unplaced = $built['unplaced'];
$legends = classTimetableBuildLegends($slots);
?>
<style>
.ct-sheet-wrap { overflow-x: auto; padding: 0 0 1rem; }
.ct-sheet {
    width: 100%;
    min-width: 980px;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 0.78rem;
    background: #fff;
}
.ct-sheet th,
.ct-sheet td {
    border: 1px solid #0f172a;
    text-align: center;
    vertical-align: middle;
    padding: 6px 4px;
}
.ct-sheet thead th {
    background: #f8fafc;
    font-weight: 700;
    color: #0f172a;
    white-space: pre-line;
    line-height: 1.25;
    min-width: 72px;
}
.ct-sheet .ct-day-col {
    width: 88px;
    min-width: 88px;
    background: #f1f5f9;
    font-weight: 700;
    text-align: left;
    padding-left: 10px;
}
.ct-sheet .ct-cell {
    min-height: 52px;
    position: relative;
}
.ct-sheet .ct-cell-empty {
    background: #fff;
    color: #cbd5e1;
}
.ct-sheet .ct-cell-filled {
    background: #fffbeb;
    font-weight: 600;
    color: #0f172a;
    line-height: 1.3;
}
.ct-sheet .ct-cell-entry { display: block; margin: 2px 0; }
.ct-sheet .ct-cell-block {
    padding: 4px 2px;
    border-bottom: 1px dashed #e2e8f0;
}
.ct-sheet .ct-cell-block:last-of-type { border-bottom: 0; }
.ct-sheet .ct-cell-batch,
.ct-sheet .ct-cell-room {
    display: block;
    font-size: 0.68rem;
    font-weight: 500;
    color: #64748b;
}
.ct-sheet .ct-cell-actions {
    display: flex;
    gap: 4px;
    justify-content: center;
    margin-top: 4px;
}
.ct-sheet .ct-cell-actions .btn { padding: 0 5px; line-height: 1.4; font-size: 0.7rem; }
.ct-sheet .ct-add-cell {
    cursor: pointer;
    border: 0;
    background: transparent;
    color: #94a3b8;
    font-size: 0.85rem;
    width: 100%;
    min-height: 40px;
}
.ct-sheet .ct-add-more {
    min-height: 24px;
    font-size: 0.72rem;
    color: #0369a1;
    margin-top: 2px;
}
.ct-sheet .ct-add-cell:hover { color: #0c2340; background: #eff6ff; }
.ct-legend {
    border: 1px solid #0f172a;
    border-top: 0;
    padding: 8px 12px;
    font-size: 0.82rem;
    background: #fff;
    color: #0f172a;
}
.ct-legend + .ct-legend { border-top: 1px solid #0f172a; }
.ct-legend strong { margin-right: 6px; }
.ct-unplaced { margin-top: 1rem; }
</style>

<div class="ct-sheet-wrap">
    <table class="ct-sheet" aria-label="Weekly class timetable">
        <thead>
            <tr>
                <th class="ct-day-col"></th>
                <?php foreach ($periods as $period): ?>
                    <th><?php echo htmlspecialchars($period['label']); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($days as $dayNum => $dayName): ?>
                <tr>
                    <th class="ct-day-col" scope="row"><?php echo htmlspecialchars($dayName); ?></th>
                    <?php foreach ($periods as $period): ?>
                        <?php
                        $cellSlots = $grid[$dayNum][$period['key']] ?? [];
                        $filled = !empty($cellSlots);
                        ?>
                        <td class="ct-cell <?php echo $filled ? 'ct-cell-filled' : 'ct-cell-empty'; ?>">
                            <?php if ($filled): ?>
                                <?php foreach ($cellSlots as $slot): ?>
                                    <div class="ct-cell-block">
                                        <span class="ct-cell-entry"><?php echo htmlspecialchars(classTimetableCellLabel($slot)); ?></span>
                                        <?php if ($ctGridFilterBatch <= 0 && !empty($slot['batch_name'])): ?>
                                            <span class="ct-cell-batch"><?php echo htmlspecialchars($slot['batch_name']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($slot['room'])): ?>
                                            <span class="ct-cell-room"><?php echo htmlspecialchars($slot['room']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($ctGridEditable): ?>
                                            <div class="ct-cell-actions">
                                                <button type="button" class="btn btn-sm btn-primary" title="Edit"
                                                        onclick='openSlotModal(<?php echo json_encode($slot, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>)'>
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="post" style="margin:0;display:inline;" onsubmit="return confirm('Delete this timetable slot?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($ctGridCsrf); ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo (int) $slot['id']; ?>">
                                                    <input type="hidden" name="redirect_batch_id" value="<?php echo (int) $ctGridFilterBatch; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <?php if ($ctGridEditable): ?>
                                    <button type="button" class="ct-add-cell ct-add-more"
                                            title="Add another class in this same period (another faculty/batch)"
                                            onclick="openSlotModalForPeriod(<?php echo (int) $dayNum; ?>, '<?php echo htmlspecialchars($period['start'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($period['end'], ENT_QUOTES); ?>')">
                                        + add
                                    </button>
                                <?php endif; ?>
                            <?php elseif ($ctGridEditable): ?>
                                <button type="button" class="ct-add-cell"
                                        title="Add class in this period"
                                        onclick="openSlotModalForPeriod(<?php echo (int) $dayNum; ?>, '<?php echo htmlspecialchars($period['start'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($period['end'], ENT_QUOTES); ?>')">
                                    +
                                </button>
                            <?php else: ?>
                                &nbsp;
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($ctGridShowLegends && (!empty($legends['faculty']) || !empty($legends['subjects']))): ?>
        <?php if (!empty($legends['faculty'])): ?>
            <div class="ct-legend">
                <strong>Faculty:</strong>
                <?php
                $parts = [];
                foreach ($legends['faculty'] as $ini => $full) {
                    $parts[] = htmlspecialchars($ini) . '-' . htmlspecialchars(strtoupper($full));
                }
                echo implode(', ', $parts);
                ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($legends['subjects'])): ?>
            <div class="ct-legend">
                <strong>Courses:</strong>
                <?php echo htmlspecialchars(implode(', ', array_keys($legends['subjects']))); ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if (!empty($unplaced)): ?>
    <div class="ct-unplaced alert alert-warning">
        <strong><?php echo count($unplaced); ?></strong> slot(s) do not match standard periods (7 AM–7:30 PM hourly).
        Edit them to use a standard period so they appear in the grid.
    </div>
<?php endif; ?>
