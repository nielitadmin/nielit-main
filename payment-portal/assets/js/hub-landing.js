(function () {
    'use strict';

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        document.querySelectorAll('.hub-animate').forEach(function (el) {
            el.classList.add('hub-animate-visible');
        });
        return;
    }

    var reveals = document.querySelectorAll('.hub-animate');
    if (!reveals.length) {
        return;
    }

    var observer = new IntersectionObserver(
        function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('hub-animate-visible');
                    observer.unobserve(entry.target);
                }
            });
        },
        { rootMargin: '0px 0px -8% 0px', threshold: 0.12 }
    );

    reveals.forEach(function (el, index) {
        el.style.setProperty('--hub-delay', index % 6 * 80 + 'ms');
        observer.observe(el);
    });

    document.querySelectorAll('[data-hub-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var text = btn.getAttribute('data-hub-copy') || '';
            if (!text || !navigator.clipboard) {
                return;
            }
            navigator.clipboard.writeText(text).then(function () {
                var label = btn.querySelector('.hub-copy-label');
                if (!label) {
                    return;
                }
                var original = label.textContent;
                label.textContent = 'Copied';
                setTimeout(function () {
                    label.textContent = original;
                }, 1800);
            });
        });
    });
})();
