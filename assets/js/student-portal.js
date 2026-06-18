// Student Portal JavaScript

function hideStudentSkeletonLoader() {
    document.documentElement.classList.remove('student-portal-loading');
    document.body.classList.remove('student-portal-loading');
    document.body.classList.add('student-portal-loaded');
}

function showStudentSkeletonLoader() {
    document.documentElement.classList.add('student-portal-loading');
    document.body.classList.add('student-portal-loading');
    document.body.classList.remove('student-portal-loaded');
}

function initStudentSkeletonLoader() {
    const minDisplayMs = 350;
    const startedAt = performance.now();

    function finish() {
        const elapsed = performance.now() - startedAt;
        const wait = Math.max(0, minDisplayMs - elapsed);
        window.setTimeout(hideStudentSkeletonLoader, wait);
    }

    if (document.readyState === 'complete') {
        finish();
    } else {
        window.addEventListener('load', finish, { once: true });
    }

    document.querySelectorAll('#studentNav a.nav-link[href], .footer-links a[href], .student-navbar a.navbar-brand[href]').forEach((link) => {
        link.addEventListener('click', function (event) {
            const href = this.getAttribute('href');
            if (!href || href === '#' || href.startsWith('#') || this.target === '_blank' || event.metaKey || event.ctrlKey || event.shiftKey) {
                return;
            }
            if (/^(https?:|mailto:|tel:)/i.test(href)) {
                return;
            }
            showStudentSkeletonLoader();
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initStudentSkeletonLoader);
} else {
    initStudentSkeletonLoader();
}

// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Auto-hide temporary flash alerts after 5 seconds
setTimeout(() => {
    document.querySelectorAll('.alert:not(.alert-permanent)').forEach(alert => {
        if (alert.closest('.announcement-ticker')) {
            return;
        }
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    });
}, 5000);

function initAnnouncementTickers() {
    document.querySelectorAll('.announcement-ticker').forEach((ticker) => {
        const viewport = ticker.querySelector('.announcement-ticker-viewport');
        const track = ticker.querySelector('.announcement-ticker-track');
        if (!viewport || !track || track.dataset.tickerReady === '1') {
            return;
        }

        track.dataset.tickerReady = '1';

        if (!track.dataset.loopReady) {
            Array.from(track.children).forEach((item) => {
                track.appendChild(item.cloneNode(true));
            });
            track.dataset.loopReady = '1';
        }

        track.style.animation = 'none';
        track.style.willChange = 'transform';

        let offset = 0;
        let paused = false;
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const speed = reduceMotion ? 0.2 : 0.45;

        viewport.addEventListener('mouseenter', () => {
            paused = true;
        });
        viewport.addEventListener('mouseleave', () => {
            paused = false;
        });

        function step() {
            const loopHeight = track.scrollHeight / 2;
            if (loopHeight > 0 && !paused) {
                offset += speed;
                if (offset >= loopHeight) {
                    offset = 0;
                }
                track.style.transform = 'translate3d(0,' + (-offset) + 'px,0)';
            }
            requestAnimationFrame(step);
        }

        requestAnimationFrame(step);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAnnouncementTickers);
} else {
    initAnnouncementTickers();
}

// Confirm before logout
document.querySelectorAll('a[href*="logout"]').forEach(link => {
    link.addEventListener('click', function(e) {
        if (!confirm('Are you sure you want to logout?')) {
            e.preventDefault();
        }
    });
});

// Add loading state to buttons
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        }
    });
});

// Image preview for file uploads
document.querySelectorAll('input[type="file"]').forEach(input => {
    input.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById(input.id + '_preview');
                if (preview) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
            };
            reader.readAsDataURL(file);
        }
    });
});

// Print functionality
function printPage() {
    window.print();
}

// Download as PDF (if needed)
function downloadAsPDF() {
    window.print();
}

// Copy to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Copied to clipboard!', 'success');
    }).catch(err => {
        console.error('Failed to copy:', err);
    });
}

// Format currency
function formatCurrency(amount) {
    return '₹' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Validate form fields
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    return isValid;
}

// Mobile menu toggle
const navbarToggler = document.querySelector('.navbar-toggler');
if (navbarToggler) {
    navbarToggler.addEventListener('click', function() {
        this.classList.toggle('active');
    });
}

// Back to top button
const backToTopBtn = document.createElement('button');
backToTopBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
backToTopBtn.className = 'back-to-top';
backToTopBtn.style.cssText = `
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--primary-color);
    color: white;
    border: none;
    cursor: pointer;
    display: none;
    z-index: 1000;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    transition: all 0.3s;
`;

document.body.appendChild(backToTopBtn);

window.addEventListener('scroll', () => {
    if (window.pageYOffset > 300) {
        backToTopBtn.style.display = 'block';
    } else {
        backToTopBtn.style.display = 'none';
    }
});

backToTopBtn.addEventListener('click', () => {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
});

// Initialize tooltips if Bootstrap is available
if (typeof $ !== 'undefined' && $.fn.tooltip) {
    $('[data-toggle="tooltip"]').tooltip();
}

// Console welcome message
console.log('%cWelcome to NIELIT Bhubaneswar Student Portal!', 'color: #356c9f; font-size: 20px; font-weight: bold;');
console.log('%cFor support, contact: dir-bbsr@nielit.gov.in', 'color: #666; font-size: 14px;');
