(function () {
    'use strict';

    function hidePublicSkeletonLoader() {
        document.documentElement.classList.remove('public-page-loading');
        document.body.classList.remove('public-page-loading');
        document.body.classList.add('public-page-loaded');
    }

    function showPublicSkeletonLoader() {
        document.documentElement.classList.add('public-page-loading');
        document.body.classList.add('public-page-loading');
        document.body.classList.remove('public-page-loaded');
    }

    function isInternalNavLink(link, event) {
        if (!link || link.target === '_blank' || event.metaKey || event.ctrlKey || event.shiftKey) {
            return false;
        }

        const href = link.getAttribute('href');
        if (!href || href === '#' || href.charAt(0) === '#') {
            return false;
        }

        if (/^(mailto:|tel:|javascript:)/i.test(href)) {
            return false;
        }

        if (/^https?:\/\//i.test(href)) {
            try {
                const targetUrl = new URL(href, window.location.href);
                return targetUrl.origin === window.location.origin;
            } catch (error) {
                return false;
            }
        }

        return true;
    }

    function initPublicSkeletonLoader() {
        const minDisplayMs = 400;
        const startedAt = performance.now();

        function finish() {
            const elapsed = performance.now() - startedAt;
            const wait = Math.max(0, minDisplayMs - elapsed);
            window.setTimeout(hidePublicSkeletonLoader, wait);
        }

        if (document.readyState === 'complete') {
            finish();
        } else {
            window.addEventListener('load', finish, { once: true });
        }

        const navSelectors = [
            '.navbar a.nav-link[href]',
            '.navbar a.navbar-brand[href]',
            '.navbar .dropdown-item[href]',
            'footer a[href]',
            '.footer-links a[href]',
            '.quick-grid a[href]',
            '.feat-card a[href]',
            '.news-card a[href]'
        ];

        document.querySelectorAll(navSelectors.join(', ')).forEach(function (link) {
            link.addEventListener('click', function (event) {
                if (!isInternalNavLink(link, event)) {
                    return;
                }
                showPublicSkeletonLoader();
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPublicSkeletonLoader);
    } else {
        initPublicSkeletonLoader();
    }

    window.showPublicSkeletonLoader = showPublicSkeletonLoader;
})();
