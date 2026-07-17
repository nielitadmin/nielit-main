<?php
$variant = $public_skeleton_variant ?? 'generic';
?>
<div id="public-skeleton-loader" class="public-skeleton-loader" aria-hidden="true" aria-busy="true" data-variant="<?php echo htmlspecialchars($variant, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="public-skeleton-topbar">
        <div class="container">
            <div class="public-skeleton-row">
                <div class="public-skel public-skel-logo"></div>
                <div class="public-skel public-skel-topbar-text"></div>
                <div class="public-skel public-skel-emblem"></div>
            </div>
        </div>
    </div>

    <div class="public-skeleton-nav">
        <div class="container">
            <div class="public-skeleton-row">
                <div class="public-skel public-skel-nav-brand"></div>
                <div class="public-skel public-skel-nav-links"></div>
            </div>
        </div>
    </div>

    <?php if ($variant === 'home'): ?>
    <div class="container public-skeleton-body">
        <div class="public-skel public-skel-hero"></div>
        <div class="public-skeleton-stats">
            <div class="public-skel public-skel-stat"></div>
            <div class="public-skel public-skel-stat"></div>
            <div class="public-skel public-skel-stat"></div>
        </div>
        <div class="public-skeleton-grid public-skeleton-grid-3">
            <div class="public-skel public-skel-card"></div>
            <div class="public-skel public-skel-card"></div>
            <div class="public-skel public-skel-card"></div>
        </div>
        <div class="public-skeleton-grid public-skeleton-grid-4">
            <div class="public-skel public-skel-card public-skel-card-sm"></div>
            <div class="public-skel public-skel-card public-skel-card-sm"></div>
            <div class="public-skel public-skel-card public-skel-card-sm"></div>
            <div class="public-skel public-skel-card public-skel-card-sm"></div>
        </div>
    </div>
    <?php elseif ($variant === 'courses'): ?>
    <div class="container public-skeleton-body">
        <div class="public-skel public-skel-page-title"></div>
        <div class="public-skel public-skel-filter"></div>
        <div class="public-skeleton-stack">
            <div class="public-skel public-skel-accordion"></div>
            <div class="public-skel public-skel-accordion"></div>
            <div class="public-skel public-skel-accordion"></div>
            <div class="public-skel public-skel-accordion"></div>
            <div class="public-skel public-skel-accordion"></div>
        </div>
    </div>
    <?php else: ?>
    <div class="container public-skeleton-body">
        <div class="public-skel public-skel-page-hero"></div>
        <div class="public-skeleton-grid public-skeleton-grid-2">
            <div class="public-skel public-skel-block"></div>
            <div class="public-skel public-skel-block"></div>
        </div>
        <div class="public-skeleton-grid public-skeleton-grid-3">
            <div class="public-skel public-skel-card public-skel-card-sm"></div>
            <div class="public-skel public-skel-card public-skel-card-sm"></div>
            <div class="public-skel public-skel-card public-skel-card-sm"></div>
        </div>
    </div>
    <?php endif; ?>
</div>
