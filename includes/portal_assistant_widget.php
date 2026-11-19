<?php
/**
 * Floating homepage assistant widget (index.php).
 */

if (!function_exists('portal_assistant_render_widget')) {
    function portal_assistant_render_widget(?mysqli $conn = null): void
    {
        require_once __DIR__ . '/url_helper.php';
        $api = app_url('api/portal_assistant_chat.php');
        $css = app_url('assets/css/portal-assistant.css');
        $js = app_url('assets/js/portal-assistant.js');
        ?>
        <link rel="stylesheet" href="<?php echo htmlspecialchars($css, ENT_QUOTES, 'UTF-8'); ?>">
        <div id="portal-assistant-root" data-api-url="<?php echo htmlspecialchars($api, ENT_QUOTES, 'UTF-8'); ?>">
            <div id="portal-assistant-panel" class="portal-assistant-panel" role="dialog" aria-label="NIELIT assistant chat">
                <div class="portal-assistant-header">
                    <h3><i class="fas fa-robot" aria-hidden="true"></i> NIELIT Assistant</h3>
                    <p>Students: login, fees, courses. Staff (logged in): student updates via n8n.</p>
                </div>
                <div id="portal-assistant-messages" class="portal-assistant-messages" aria-live="polite">
                    <div class="portal-assistant-msg portal-assistant-msg--bot">Hello! Ask about login, registration, or fees. Staff: try “student update report” (uses n8n when enabled).</div>
                </div>
                <div class="portal-assistant-chips">
                    <button type="button" class="portal-assistant-chip" data-question="How do I login as a student?">Student login</button>
                    <button type="button" class="portal-assistant-chip" data-question="How to register new admission?">Registration</button>
                    <button type="button" class="portal-assistant-chip" data-question="Fees and payment portal">Fees</button>
                    <button type="button" class="portal-assistant-chip" data-question="Mock test link">Mock test</button>
                    <button type="button" class="portal-assistant-chip" data-question="student update report">Student updates</button>
                </div>
                <form id="portal-assistant-form" class="portal-assistant-form">
                    <input id="portal-assistant-input" type="text" maxlength="2000" autocomplete="off"
                           placeholder="Type your question…" aria-label="Your message">
                    <button type="submit" aria-label="Send"><i class="fas fa-paper-plane"></i></button>
                </form>
            </div>
            <div id="portal-assistant-dock" class="portal-assistant-dock">
                <button id="portal-assistant-fab" class="portal-assistant-fab" type="button"
                        aria-expanded="false" aria-controls="portal-assistant-panel"
                        title="Chat with NIELIT Assistant">
                    <i class="fas fa-comments" aria-hidden="true"></i>
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