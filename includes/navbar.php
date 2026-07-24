<?php
if (!function_exists('getPublicSiteNavigationHtml')) {
    require_once __DIR__ . '/navigation_helper.php';
}
if (!function_exists('app_url')) {
    require_once __DIR__ . '/url_helper.php';
}
?>
<!-- Navigation Menu -->
<nav class="navbar navbar-expand-lg navbar-light" style="background-color: #356c9f;">
    <div class="container">
        <a class="navbar-brand" href="<?php echo app_url(); ?>">NIELIT Bhubaneswar</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
                <?php
                $connForNav = (isset($conn) && $conn instanceof mysqli) ? $conn : null;
                echo getPublicSiteNavigationHtml($connForNav, basename($_SERVER['PHP_SELF'] ?? ''));
                ?>
            </ul>
        </div>
    </div>
</nav>
