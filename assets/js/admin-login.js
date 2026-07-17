(function () {
    'use strict';

    const config = window.ADMIN_LOGIN_CONFIG || {};

    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('togglePasswordIcon');
        if (!passwordInput || !toggleIcon) return;

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    window.togglePassword = togglePassword;

    function initBannerCarousel() {
        const slides = document.querySelectorAll('.banner-slide');
        const dots = document.querySelectorAll('.banner-dot');
        const progressBar = document.getElementById('bannerProgressBar');
        if (slides.length <= 1) return;

        let current = 0;
        let timer = null;
        let progressTimer = null;
        const intervalMs = 6000;
        let progressStart = 0;

        function updateProgress() {
            if (!progressBar) return;
            const elapsed = Date.now() - progressStart;
            const pct = Math.min(100, (elapsed / intervalMs) * 100);
            progressBar.style.width = pct + '%';
        }

        function goTo(index) {
            slides[current].classList.remove('active');
            if (dots[current]) dots[current].classList.remove('active');
            current = (index + slides.length) % slides.length;
            slides[current].classList.add('active');
            if (dots[current]) dots[current].classList.add('active');
            progressStart = Date.now();
            if (progressBar) progressBar.style.width = '0%';
        }

        function next() {
            goTo(current + 1);
        }

        function start() {
            stop();
            progressStart = Date.now();
            if (progressBar) progressBar.style.width = '0%';
            progressTimer = setInterval(updateProgress, 50);
            timer = setInterval(next, intervalMs);
        }

        function stop() {
            if (timer) clearInterval(timer);
            if (progressTimer) clearInterval(progressTimer);
        }

        dots.forEach((dot, index) => {
            dot.addEventListener('click', () => {
                goTo(index);
                start();
            });
        });

        start();
    }

    function initBannerParallax() {
        const page = document.querySelector('.login-page');
        const panel = document.getElementById('bannerPanel');
        if (!page || !panel) return;

        if (window.matchMedia('(max-width: 1024px)').matches) return;

        page.addEventListener('mousemove', function (e) {
            const rect = page.getBoundingClientRect();
            const x = (e.clientX - rect.left) / rect.width - 0.5;
            const y = (e.clientY - rect.top) / rect.height - 0.5;

            panel.querySelectorAll('.banner-slide.active .banner-parallax-layer').forEach(function (layer) {
                layer.style.transform = 'translate(' + (x * 22) + 'px, ' + (y * 14) + 'px)';
            });
        });

        page.addEventListener('mouseleave', function () {
            panel.querySelectorAll('.banner-parallax-layer').forEach(function (layer) {
                layer.style.transform = '';
            });
        });
    }

    function initParticles() {
        const container = document.getElementById('panelParticles');
        if (!container) return;

        const isMobile = window.matchMedia('(max-width: 768px)').matches;
        const count = isMobile ? 18 : 35;

        for (let i = 0; i < count; i++) {
            const p = document.createElement('span');
            const roll = Math.random();
            if (roll > 0.82) {
                p.className = 'particle particle-ring';
                const size = Math.random() * 12 + 8;
                p.style.width = size + 'px';
                p.style.height = size + 'px';
            } else if (roll > 0.65) {
                p.className = 'particle particle-navy';
                const size = Math.random() * 3 + 2;
                p.style.width = size + 'px';
                p.style.height = size + 'px';
            } else {
                p.className = 'particle particle-gold';
                const size = Math.random() * 5 + 2;
                p.style.width = size + 'px';
                p.style.height = size + 'px';
            }

            p.style.left = Math.random() * 100 + '%';
            p.style.animationDuration = (Math.random() * 14 + 12) + 's';
            p.style.animationDelay = (Math.random() * 12) + 's';
            container.appendChild(p);
        }
    }

    function initCardTilt() {
        const page = document.querySelector('.login-page');
        const inner = document.getElementById('loginPanelInner');
        if (!page || !inner) return;

        if (window.matchMedia('(max-width: 1024px)').matches) return;

        page.addEventListener('mousemove', function (e) {
            const rect = page.getBoundingClientRect();
            const x = (e.clientX - rect.left) / rect.width - 0.5;
            const y = (e.clientY - rect.top) / rect.height - 0.5;

            inner.style.transform =
                'perspective(900px) rotateY(' + (x * 8) + 'deg) rotateX(' + (-y * 6) + 'deg) translateZ(12px)';
        });

        page.addEventListener('mouseleave', function () {
            inner.style.transform = '';
        });
    }

    function initBannerShapesParallax() {
        const page = document.querySelector('.login-page');
        const shapes = document.querySelector('.banner-shapes');
        if (!page || !shapes) return;

        if (window.matchMedia('(max-width: 1024px)').matches) return;

        page.addEventListener('mousemove', function (e) {
            const rect = page.getBoundingClientRect();
            const x = (e.clientX - rect.left) / rect.width - 0.5;
            const y = (e.clientY - rect.top) / rect.height - 0.5;

            shapes.style.transform =
                'translate(' + (x * 28) + 'px, ' + (y * 18) + 'px)';
        });

        page.addEventListener('mouseleave', function () {
            shapes.style.transform = '';
        });
    }

    function initPanelParallax() {
        const page = document.querySelector('.login-page');
        const scene = document.querySelector('.panel-3d-scene');
        if (!page || !scene) return;

        if (window.matchMedia('(max-width: 1024px)').matches) return;

        page.addEventListener('mousemove', function (e) {
            const rect = page.getBoundingClientRect();
            const x = (e.clientX - rect.left) / rect.width - 0.5;
            const y = (e.clientY - rect.top) / rect.height - 0.5;

            scene.style.transform =
                'translate(' + (x * -18) + 'px, ' + (y * -12) + 'px)';
        });

        page.addEventListener('mouseleave', function () {
            scene.style.transform = '';
        });
    }

    function submitGoogleCredential(credential) {
        const form = document.getElementById('googleLoginForm');
        const input = document.getElementById('googleCredentialInput');
        if (!form || !input) return;

        input.value = credential;
        form.submit();
    }

    window.handleGoogleSignIn = function (response) {
        if (response && response.credential) {
            submitGoogleCredential(response.credential);
        }
    };

    function initGoogleSignIn() {
        if (!config.googleEnabled || !config.googleClientId) return;

        const stack = document.getElementById('googleSignInStack');
        const container = document.getElementById('googleSignInContainer');
        if (!stack || !container || !window.google || !google.accounts || !google.accounts.id) return;

        google.accounts.id.initialize({
            client_id: config.googleClientId,
            callback: window.handleGoogleSignIn,
            auto_select: false,
            cancel_on_tap_outside: true,
        });

        const buttonWidth = Math.min(400, Math.max(Math.floor(stack.getBoundingClientRect().width), 280));

        google.accounts.id.renderButton(container, {
            type: 'standard',
            theme: 'outline',
            size: 'large',
            text: 'continue_with',
            shape: 'rectangular',
            width: buttonWidth,
            logo_alignment: 'left',
        });
    }

    function initMascot() {
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');
        const mascotEyes = document.getElementById('mascotEyes');
        const mascotHands = document.getElementById('mascotHands');
        const mascotMouth = document.getElementById('mascotMouth');

        if (!mascotEyes) return;

        if (mascotMouth) mascotMouth.classList.add('smile');

        if (usernameInput) {
            usernameInput.addEventListener('focus', () => {
                mascotEyes.classList.remove('closed', 'looking-right', 'looking-up');
                mascotEyes.classList.add('looking-left', 'happy');
                mascotHands.classList.remove('covering');
                if (mascotMouth) mascotMouth.classList.add('smile');
            });

            usernameInput.addEventListener('blur', () => {
                mascotEyes.classList.remove('looking-left', 'happy');
            });
        }

        if (passwordInput) {
            passwordInput.addEventListener('focus', () => {
                mascotEyes.classList.remove('looking-left', 'looking-right', 'happy');
                mascotEyes.classList.add('closed');
                mascotHands.classList.add('covering');
                if (mascotMouth) mascotMouth.classList.remove('smile');
            });

            passwordInput.addEventListener('blur', () => {
                mascotEyes.classList.remove('closed');
                mascotHands.classList.remove('covering');
                if (mascotMouth) mascotMouth.classList.add('smile');
            });
        }

        const otpInputs = document.querySelectorAll('.otp-input');
        if (otpInputs.length > 0) {
            otpInputs.forEach((input, index) => {
                input.addEventListener('focus', () => {
                    mascotEyes.classList.add('closed');
                    mascotHands.classList.add('covering');
                    if (mascotMouth) mascotMouth.classList.remove('smile');
                });

                input.addEventListener('blur', () => {
                    setTimeout(() => {
                        if (!document.querySelector('.otp-input:focus')) {
                            mascotEyes.classList.remove('closed');
                            mascotHands.classList.remove('covering');
                            if (mascotMouth) mascotMouth.classList.add('smile');
                        }
                    }, 100);
                });

                input.addEventListener('input', function () {
                    if (!/^\d*$/.test(this.value)) {
                        this.value = '';
                        return;
                    }
                    if (this.value.length === 1 && index < otpInputs.length - 1) {
                        otpInputs[index + 1].focus();
                    }
                    updateOTPHidden();
                });

                input.addEventListener('keydown', function (e) {
                    if (e.key === 'Backspace' && !this.value && index > 0) {
                        otpInputs[index - 1].focus();
                        otpInputs[index - 1].value = '';
                        updateOTPHidden();
                    }
                    if (e.key === 'ArrowLeft' && index > 0) otpInputs[index - 1].focus();
                    if (e.key === 'ArrowRight' && index < otpInputs.length - 1) otpInputs[index + 1].focus();
                });

                input.addEventListener('paste', function (e) {
                    e.preventDefault();
                    const pastedData = e.clipboardData.getData('text').trim();
                    if (/^\d{6}$/.test(pastedData)) {
                        pastedData.split('').forEach((digit, i) => {
                            if (otpInputs[i]) otpInputs[i].value = digit;
                        });
                        updateOTPHidden();
                        otpInputs[5].focus();
                    }
                });
            });
        }

        function updateOTPHidden() {
            const otpHidden = document.getElementById('otp-hidden');
            if (!otpHidden) return;
            otpHidden.value = Array.from(otpInputs).map((el) => el.value).join('');
        }

        document.addEventListener('mousemove', function (e) {
            const active = document.activeElement;
            const isOtp = active && active.classList.contains('otp-input');
            if (active === usernameInput || active === passwordInput || isOtp) return;

            const mascotFace = document.querySelector('.mascot-face');
            if (!mascotFace) return;

            const rect = mascotFace.getBoundingClientRect();
            const deltaX = e.clientX - (rect.left + rect.width / 2);
            const deltaY = e.clientY - (rect.top + rect.height / 2);

            document.querySelectorAll('.pupil').forEach((pupil) => {
                const maxMove = 6;
                const moveX = Math.max(-maxMove, Math.min(maxMove, deltaX / 20));
                const moveY = Math.max(-maxMove, Math.min(maxMove, deltaY / 20));
                pupil.style.transform = `translate(calc(-50% + ${moveX}px), calc(-50% + ${moveY}px))`;
            });
        });
    }

    function initFormLoading() {
        ['loginBtn', 'verifyBtn'].forEach((id) => {
            const btn = document.getElementById(id);
            if (!btn) return;
            btn.closest('form').addEventListener('submit', () => btn.classList.add('loading'));
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initBannerCarousel();
        initBannerParallax();
        initBannerShapesParallax();
        initParticles();
        initCardTilt();
        initPanelParallax();
        initMascot();
        initFormLoading();

        if (config.googleEnabled) {
            const startGoogle = function () {
                window.requestAnimationFrame(initGoogleSignIn);
            };

            if (window.google && google.accounts) {
                startGoogle();
            } else {
                window.addEventListener('load', startGoogle);
            }
        }
    });
})();
