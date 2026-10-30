<?php

require_once __DIR__ . '/../includes/bootstrap.php';

// Redirect if already logged in
if (hub_admin_logged_in()) {
    header('Location: ' . hub_url('admin/dashboard.php'));
    exit;
}

// Clean up expired OTPs periodically
hub_admin_otp_cleanup($hub_conn);

$step = 'email'; // 'email' or 'otp'
$error = '';
$success = '';
$email = '';
$adminId = 0;

// Handle OTP verification (Step 2)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
    $adminId = (int) ($_POST['admin_id'] ?? 0);
    $otpCode = trim((string) ($_POST['otp_code'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));

    if ($adminId > 0 && $otpCode !== '') {
        if (hub_admin_otp_verify($hub_conn, $adminId, $otpCode)) {
            header('Location: ' . hub_url('admin/dashboard.php'));
            exit;
        }
        $error = 'Invalid or expired OTP. Please try again.';
    } else {
        $error = 'Please enter the OTP code.';
    }
    $step = 'otp';
}

// Handle email submission (Step 1)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_otp') {
    $email = trim(strtolower((string) ($_POST['email'] ?? '')));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $admin = hub_admin_find_by_email($hub_conn, $email);
        if ($admin) {
            $adminId = (int) $admin['id'];
            $otp = hub_admin_otp_generate($hub_conn, $adminId);
            if ($otp) {
                $sent = hub_send_otp_email($hub_conn, $email, $otp, (string) $admin['username']);
                if ($sent) {
                    $success = 'A 6-digit code has been sent to your email.';
                    $step = 'otp';
                } else {
                    $error = 'Could not send the OTP email. Please check SMTP settings or try again.';
                }
            } else {
                $error = 'Could not generate OTP. Please try again.';
            }
        } else {
            // Don't reveal whether the email exists — show the same success message
            $success = 'If an account exists with this email, a code has been sent.';
            $step = 'otp';
        }
    }
}

hub_auth_layout_start('Sign in');
?>
<div class="hub-auth-grid">
    <div class="hub-auth-hero">
        <div class="mb-3"><?php echo hub_brand_mark_html('hub-brand-logo'); ?></div>
        <p class="text-uppercase small fw-semibold opacity-75 mb-2">NIELIT Bhubaneswar</p>
        <h1><?php echo htmlspecialchars(HUB_BRAND_SHORT); ?></h1>
        <p class="opacity-90 mt-3"><?php echo htmlspecialchars(HUB_TAGLINE_AUTH); ?></p>
        <ul class="list-unstyled mt-4 small opacity-85">
            <li class="mb-2"><i class="bi bi-shield-lock me-2"></i>OTP-based secure login</li>
            <li class="mb-2"><i class="bi bi-envelope-check me-2"></i>Code sent to your email</li>
            <li><i class="bi bi-clock-history me-2"></i>Expires in 10 minutes</li>
        </ul>
    </div>
    <div class="hub-auth-form-wrap">
        <?php if ($step === 'email'): ?>
            <h2 class="h4 fw-bold mb-1">Staff sign in</h2>
            <p class="text-secondary small mb-4">Enter your registered email to receive a one-time passcode</p>
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="post" class="vstack gap-3">
                <input type="hidden" name="action" value="send_otp">
                <div>
                    <label class="form-label">Email address</label>
                    <input class="form-control form-control-lg" type="email" name="email" required autocomplete="email"
                           value="<?php echo htmlspecialchars($email); ?>"
                           placeholder="you@nielitbhubaneswar.in">
                </div>
                <button class="btn btn-primary btn-lg w-100" type="submit">
                    <i class="bi bi-envelope me-2"></i>Send OTP code
                </button>
            </form>
        <?php else: ?>
            <h2 class="h4 fw-bold mb-1">Enter verification code</h2>
            <p class="text-secondary small mb-2">We sent a 6-digit code to <strong><?php echo htmlspecialchars($email); ?></strong></p>
            <?php if ($success !== ''): ?>
                <div class="alert alert-success py-2 small"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="post" class="vstack gap-3">
                <input type="hidden" name="action" value="verify_otp">
                <input type="hidden" name="admin_id" value="<?php echo $adminId; ?>">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <div>
                    <label class="form-label">One-time passcode</label>
                    <input class="form-control form-control-lg text-center fw-bold" type="text" name="otp_code"
                           required maxlength="6" pattern="[0-9]{6}" inputmode="numeric"
                           placeholder="000000" autocomplete="one-time-code"
                           style="letter-spacing: 0.5em; font-size: 1.5rem; font-family: monospace;">
                </div>
                <button class="btn btn-primary btn-lg w-100" type="submit">
                    <i class="bi bi-shield-check me-2"></i>Verify &amp; sign in
                </button>
            </form>
            <hr class="my-3">
            <form method="post">
                <input type="hidden" name="action" value="send_otp">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <button class="btn btn-outline-secondary btn-sm w-100" type="submit">
                    <i class="bi bi-arrow-repeat me-2"></i>Resend code
                </button>
            </form>
            <p class="text-center mt-3 mb-0">
                <a href="<?php echo hub_asset('admin/login.php'); ?>" class="small text-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Use a different email
                </a>
            </p>
        <?php endif; ?>
        <p class="text-center mt-4 mb-0 small"><a href="<?php echo hub_asset('/'); ?>">← Back to portal home</a></p>
    </div>
</div>
<?php
hub_auth_layout_end();
