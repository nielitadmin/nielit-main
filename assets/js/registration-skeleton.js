/**
 * Skeleton loaders for AI preload and document verification.
 */
(function (global) {
    'use strict';

    function getOrCreateFieldSkeleton(input) {
        if (!input || !input.parentElement) {
            return null;
        }
        var parent = input.parentElement;
        var el = parent.querySelector('.reg-field-skeleton');
        if (!el) {
            el = document.createElement('div');
            el.className = 'reg-field-skeleton';
            el.setAttribute('aria-hidden', 'true');
            el.innerHTML =
                '<div class="reg-skeleton-thumb reg-skeleton-shimmer"></div>' +
                '<div class="reg-skeleton-lines">' +
                '<div class="reg-skeleton-line reg-skeleton-shimmer"></div>' +
                '<div class="reg-skeleton-line reg-skeleton-line--short reg-skeleton-shimmer"></div>' +
                '<span class="reg-field-skeleton-label">Verifying document…</span>' +
                '</div>';
            var statusEl = parent.querySelector('.face-check-status, .doc-check-status');
            if (statusEl && statusEl.nextSibling) {
                parent.insertBefore(el, statusEl.nextSibling);
            } else {
                parent.appendChild(el);
            }
        }
        return el;
    }

    function showPanel(id) {
        var el = typeof id === 'string' ? document.getElementById(id) : id;
        if (!el) {
            return;
        }
        el.classList.add('is-visible');
        el.setAttribute('aria-hidden', 'false');
    }

    function hidePanel(id) {
        var el = typeof id === 'string' ? document.getElementById(id) : id;
        if (!el) {
            return;
        }
        el.classList.remove('is-visible');
        el.setAttribute('aria-hidden', 'true');
    }

    function bindAiLoaderEvents(panelId) {
        if (global.__regSkeletonAiBound) {
            return;
        }
        global.__regSkeletonAiBound = true;

        document.addEventListener('registration-ai-load-start', function () {
            if (panelId) {
                showPanel(panelId);
            }
        });

        document.addEventListener('registration-ai-load-done', function () {
            if (panelId) {
                hidePanel(panelId);
            }
        });
    }

    global.RegistrationSkeleton = {
        bindAiLoaderEvents: bindAiLoaderEvents,

        showAiPreload: function (panelId) {
            showPanel(panelId);
        },

        hideAiPreload: function (panelId) {
            hidePanel(panelId);
        },

        showFieldCheck: function (input, label) {
            var el = getOrCreateFieldSkeleton(input);
            if (!el) {
                return;
            }
            if (label) {
                var labelEl = el.querySelector('.reg-field-skeleton-label');
                if (labelEl) {
                    labelEl.textContent = label;
                }
            }
            el.classList.add('is-visible');
            el.setAttribute('aria-hidden', 'false');
        },

        hideFieldCheck: function (input) {
            if (!input || !input.parentElement) {
                return;
            }
            var el = input.parentElement.querySelector('.reg-field-skeleton');
            if (el) {
                el.classList.remove('is-visible');
                el.setAttribute('aria-hidden', 'true');
            }
        },

        trackPreload: function (panelId) {
            bindAiLoaderEvents(panelId);
            if (typeof global.RegistrationAiLoader === 'undefined') {
                return Promise.resolve();
            }
            if (global.RegistrationAiLoader.isReady()) {
                hidePanel(panelId);
                return Promise.resolve();
            }
            showPanel(panelId);
            return global.RegistrationAiLoader.preload().finally(function () {
                hidePanel(panelId);
            });
        }
    };
})(window);
