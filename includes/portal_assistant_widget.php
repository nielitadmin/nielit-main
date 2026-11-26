<?php
/**
 * Floating Saarthi AI Campus & Portal Assistant widget (rendered across all public pages).
 */

if (!function_exists('portal_assistant_render_widget')) {
    function portal_assistant_render_widget(?mysqli $conn = null): void
    {
        static $rendered = false;
        if ($rendered) {
            return;
        }
        $rendered = true;

        if (!$conn instanceof mysqli && isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
            $conn = $GLOBALS['conn'];
        }

        require_once __DIR__ . '/url_helper.php';
        $api = app_url('api/portal_assistant_chat.php');
        $coursesUrl = app_url('public/courses');
        $loginUrl = app_url('student/login');

        $cssFile = dirname(__DIR__) . '/assets/css/portal-assistant.css';
        $jsFile = dirname(__DIR__) . '/assets/js/portal-assistant.js';
        $cssVer = is_file($cssFile) ? (int) filemtime($cssFile) : time();
        $jsVer = is_file($jsFile) ? (int) filemtime($jsFile) : time();
        $css = app_url('assets/css/portal-assistant.css') . '?v=' . $cssVer;
        $js = app_url('assets/js/portal-assistant.js') . '?v=' . $jsVer;

        $activeLang = 'en';
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['saarthi_lang'])) {
            $l = strtolower((string) $_SESSION['saarthi_lang']);
            if (in_array($l, ['en', 'hi', 'or'], true)) {
                $activeLang = $l;
            }
        }
        ?>
        <link rel="stylesheet" href="<?php echo htmlspecialchars($css, ENT_QUOTES, 'UTF-8'); ?>">
        <div id="portal-assistant-root"
             data-api-url="<?php echo htmlspecialchars($api, ENT_QUOTES, 'UTF-8'); ?>"
             data-courses-url="<?php echo htmlspecialchars($coursesUrl, ENT_QUOTES, 'UTF-8'); ?>"
             data-login-url="<?php echo htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>"
             data-initial-lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>">
            <div id="portal-assistant-panel" class="portal-assistant-panel" role="dialog" aria-label="Saarthi NIELIT AI Assistant" aria-hidden="true">
                <div class="portal-assistant-header">
                    <div class="saarthi-header-top">
                        <div class="saarthi-brand">
                            <div id="saarthi-header-avatar" class="saarthi-avatar saarthi-avatar--wave" aria-hidden="true">
                                <svg viewBox="0 0 64 64" width="38" height="38" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="32" cy="32" r="29" fill="#112240" stroke="#f59e0b" stroke-width="2.5"/>
                                    <line x1="32" y1="6" x2="32" y2="14" stroke="#fcd34d" stroke-width="2.5" stroke-linecap="round"/>
                                    <circle class="saarthi-antenna-dot" cx="32" cy="5" r="3.5" fill="#f59e0b"/>
                                    <rect x="16" y="16" width="32" height="24" rx="10" fill="#1e3a8a" stroke="#60a5fa" stroke-width="1.8"/>
                                    <circle class="saarthi-eye" cx="25" cy="27" r="3.2" fill="#fde68a"/>
                                    <circle class="saarthi-eye" cx="39" cy="27" r="3.2" fill="#fde68a"/>
                                    <path class="saarthi-mouth" d="M25 33 Q32 38 39 33" fill="none" stroke="#fcd34d" stroke-width="2.4" stroke-linecap="round"/>
                                    <path d="M20 44 C20 40 44 40 44 44 L46 53 C46 55 18 55 18 53 Z" fill="#1a56db"/>
                                    <g class="saarthi-hand">
                                        <circle cx="53" cy="36" r="4.5" fill="#f59e0b"/>
                                    </g>
                                </svg>
                            </div>
                            <div>
                                <h3>Saarthi <span class="saarthi-badge">AI Guide</span></h3>
                                <p id="saarthi-status-text">NIELIT Bhubaneswar · EN / हिन्दी / ଓଡ଼ିଆ</p>
                            </div>
                        </div>
                        <div class="saarthi-header-controls">
                            <button type="button" id="saarthi-reset-btn" class="saarthi-icon-btn" title="Start new chat" aria-label="Reset chat">
                                <i class="fas fa-rotate-right" aria-hidden="true"></i>
                            </button>
                            <button type="button" id="saarthi-close-btn" class="saarthi-icon-btn" title="Close Saarthi" aria-label="Close chat">
                                <i class="fas fa-times" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <div class="saarthi-lang-bar" role="group" aria-label="Choose language">
                        <button type="button" class="saarthi-lang-pill" data-lang="en">English</button>
                        <button type="button" class="saarthi-lang-pill" data-lang="hi">हिन्दी</button>
                        <button type="button" class="saarthi-lang-pill" data-lang="or">ଓଡ଼ିଆ</button>
                    </div>
                </div>

                <div id="portal-assistant-messages" class="portal-assistant-messages" aria-live="polite"></div>

                <div id="portal-assistant-chips" class="portal-assistant-chips"></div>

                <form id="portal-assistant-form" class="portal-assistant-form">
                    <input id="portal-assistant-input" type="text" maxlength="2000" autocomplete="off"
                           placeholder="Ask Saarthi in English, हिन्दी, or ଓଡ଼ିଆ…" aria-label="Your message">
                    <button type="submit" aria-label="Send message"><i class="fas fa-paper-plane" aria-hidden="true"></i></button>
                </form>
            </div>

            <div id="portal-assistant-dock" class="portal-assistant-dock">
                <button id="portal-assistant-fab" class="portal-assistant-fab" type="button"
                        aria-expanded="false" aria-controls="portal-assistant-panel"
                        title="Chat with Saarthi — AI Campus &amp; Portal Guide">
                    <span class="saarthi-fab-avatar saarthi-avatar saarthi-avatar--wave" id="saarthi-fab-avatar" aria-hidden="true">
                        <svg viewBox="0 0 64 64" width="32" height="32" xmlns="http://www.w3.org/2000/svg">
                            <rect x="14" y="14" width="36" height="26" rx="11" fill="#0a1628" stroke="#fcd34d" stroke-width="2.5"/>
                            <circle class="saarthi-eye" cx="25" cy="26" r="3.4" fill="#fcd34d"/>
                            <circle class="saarthi-eye" cx="39" cy="26" r="3.4" fill="#fcd34d"/>
                            <path d="M25 33 Q32 38 39 33" fill="none" stroke="#f59e0b" stroke-width="2.4" stroke-linecap="round"/>
                            <line x1="32" y1="6" x2="32" y2="14" stroke="#fcd34d" stroke-width="2.5" stroke-linecap="round"/>
                            <circle class="saarthi-antenna-dot" cx="32" cy="5" r="3.5" fill="#f59e0b"/>
                        </svg>
                    </span>
                    <span class="saarthi-fab-label">Saarthi</span>
                </button>
            </div>
        </div>
        <script src="<?php echo htmlspecialchars($js, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
        <script>
        (function () {
            function dockThemeToggle() {
                document.body.classList.add('portal-assistant-active');
                var dock = document.getElementById('portal-assistant-dock');
                var theme = document.getElementById('themeModeToggle') || document.querySelector('.theme-mode-toggle');
                var fab = document.getElementById('portal-assistant-fab');
                if (!dock || !fab) {
                    return;
                }
                if (theme && theme.parentNode !== dock) {
                    dock.insertBefore(theme, fab);
                }
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', dockThemeToggle);
            } else {
                dockThemeToggle();
            }
        })();
        </script>
        <?php
    }
}
