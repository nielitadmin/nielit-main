<?php

require_once __DIR__ . '/../includes/bootstrap.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = (string) ($_POST['username'] ?? '');
    $pass = (string) ($_POST['password'] ?? '');
    if (hub_admin_login($hub_conn, $user, $pass)) {
        header('Location: ' . hub_url('admin/dashboard.php'));
        exit;
    }
    $error = 'Invalid username or password.';
}

hub_auth_layout_start('Sign in');
?>
<div class="hub-auth-grid">
    <div class="hub-auth-hero">
        <p class="text-uppercase small fw-semibold opacity-75 mb-2">NIELIT Bhubaneswar</p>
        <h1>Central payment hub</h1>
        <p class="opacity-90 mt-3">One secure place for MIS, mock test, and future sites to collect fees. Razorpay keys and invoices live here only.</p>
        <ul class="list-unstyled mt-4 small opacity-85">
            <li class="mb-2"><i class="bi bi-shield-check me-2"></i>Signed checkout API</li>
            <li class="mb-2"><i class="bi bi-receipt me-2"></i>Unified invoice series</li>
            <li><i class="bi bi-arrow-repeat me-2"></i>Refunds & credit notes</li>
        </ul>
    </div>
    <div class="hub-auth-form-wrap">
        <h2 class="h4 fw-bold mb-1">Staff sign in</h2>
        <p class="text-secondary small mb-4">Accounts and authorised payment administrators</p>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post" class="vstack gap-3">
            <div>
                <label class="form-label">Username</label>
                <input class="form-control form-control-lg" name="username" required autocomplete="username">
            </div>
            <div>
                <label class="form-label">Password</label>
                <input class="form-control form-control-lg" type="password" name="password" required autocomplete="current-password">
            </div>
            <button class="btn btn-primary btn-lg w-100" type="submit">Sign in to dashboard</button>
        </form>
        <p class="text-center mt-4 mb-0 small"><a href="<?php echo hub_asset('/'); ?>">← Back to portal home</a></p>
    </div>
</div>
<?php
hub_auth_layout_end();
