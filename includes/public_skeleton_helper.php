<?php

declare(strict_types=1);

if (!function_exists('public_skeleton_asset_version')) {
    function public_skeleton_asset_version(string $relativePath): int
    {
        $path = __DIR__ . '/../' . ltrim($relativePath, '/');
        return is_file($path) ? (int) filemtime($path) : time();
    }
}

if (!function_exists('public_skeleton_asset_url')) {
    function public_skeleton_asset_url(string $relativePath): string
    {
        if (!function_exists('asset_url')) {
            require_once __DIR__ . '/url_helper.php';
        }

        return asset_url($relativePath);
    }
}

if (!function_exists('public_skeleton_render_head')) {
    function public_skeleton_render_head(): void
    {
        $cssUrl = public_skeleton_asset_url('assets/css/public-skeleton.css');
        $cssVer = public_skeleton_asset_version('assets/css/public-skeleton.css');

        echo '<link rel="stylesheet" href="' . htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8') . '?v=' . $cssVer . '">' . "\n";
        echo '<script>document.documentElement.classList.add("public-page-loading");</script>' . "\n";
    }
}

if (!function_exists('public_skeleton_render_loader')) {
    function public_skeleton_render_loader(string $variant = 'generic'): void
    {
        $allowed = ['home', 'courses', 'generic'];
        $public_skeleton_variant = in_array($variant, $allowed, true) ? $variant : 'generic';
        include __DIR__ . '/public_skeleton_loader.php';
    }
}

if (!function_exists('public_skeleton_render_script')) {
    function public_skeleton_render_script(): void
    {
        $jsUrl = public_skeleton_asset_url('assets/js/public-skeleton.js');
        $jsVer = public_skeleton_asset_version('assets/js/public-skeleton.js');

        echo '<script src="' . htmlspecialchars($jsUrl, ENT_QUOTES, 'UTF-8') . '?v=' . $jsVer . '"></script>' . "\n";
    }
}
