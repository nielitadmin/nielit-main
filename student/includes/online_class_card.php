<?php
/**
 * Partial: single online class card for student portal.
 * Expects $oc array with display_status, title, join_url, etc.
 */
$ds = $oc['display_status'] ?? 'upcoming';
$when = !empty($oc['scheduled_at'])
    ? date('D, d M Y · h:i A', strtotime($oc['scheduled_at']))
    : '—';
$duration = (int) ($oc['duration_minutes'] ?? 60);
$isLive = ($ds === 'live');
$meetingUrl = trim((string) ($oc['join_url'] ?? $oc['meeting_url'] ?? ''));
$recordingUrl = trim((string) ($oc['recording_url'] ?? ''));
?>
<div class="col-md-6 col-lg-4 mb-3">
    <div class="oc-card <?php echo $isLive ? 'is-live' : ''; ?>">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
            <div class="oc-card-title"><?php echo htmlspecialchars($oc['title'] ?? ''); ?></div>
            <?php if ($isLive): ?>
                <span class="oc-badge-live">Live</span>
            <?php endif; ?>
        </div>
        <div class="oc-card-meta">
            <div><i class="fas fa-calendar-alt me-1"></i> <?php echo htmlspecialchars($when); ?></div>
            <div>
                <i class="fas fa-hourglass-half me-1"></i> <?php echo $duration; ?> min
                <?php if (!empty($oc['platform'])): ?>
                    · <?php echo htmlspecialchars($oc['platform']); ?>
                <?php endif; ?>
            </div>
            <?php if (!empty($oc['batch_name'])): ?>
                <div><i class="fas fa-layer-group me-1"></i> <?php echo htmlspecialchars($oc['batch_name']); ?></div>
            <?php endif; ?>
        </div>
        <?php if (!empty($oc['description'])): ?>
            <div class="oc-card-notes"><?php echo nl2br(htmlspecialchars($oc['description'])); ?></div>
        <?php else: ?>
            <div class="oc-card-notes"></div>
        <?php endif; ?>
        <div class="oc-card-actions">
            <?php if ($meetingUrl !== '' && in_array($ds, ['live', 'upcoming'], true)): ?>
                <a href="<?php echo htmlspecialchars($meetingUrl); ?>"
                   class="btn btn-sm <?php echo $isLive ? 'btn-danger' : 'btn-primary'; ?>">
                    <i class="fas fa-video"></i> <?php echo $isLive ? 'Join Now' : 'Join Class'; ?>
                </a>
            <?php endif; ?>

            <?php if ($recordingUrl !== ''): ?>
                <a href="<?php echo htmlspecialchars($recordingUrl); ?>"
                   class="btn btn-sm btn-success"
                   target="_blank" rel="noopener noreferrer">
                    <i class="fas fa-play-circle"></i> Watch Recording
                </a>
            <?php elseif ($ds === 'completed'): ?>
                <span class="text-muted small align-self-center">Recording not uploaded yet</span>
            <?php endif; ?>
        </div>
    </div>
</div>
