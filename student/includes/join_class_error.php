<?php
/**
 * Simple error shell for join_class.php
 * Expects $errorTitle, $errorMsg
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($errorTitle ?? 'Error'); ?> — NIELIT Classroom</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background:#0f172a;color:#e2e8f0;min-height:100vh;display:flex;align-items:center;">
    <div class="container" style="max-width:560px;">
        <div class="p-4 rounded-3" style="background:#1e293b;border:1px solid #334155;">
            <h1 class="h4 mb-2"><?php echo htmlspecialchars($errorTitle ?? 'Error'); ?></h1>
            <p class="mb-3 text-secondary"><?php echo htmlspecialchars($errorMsg ?? 'Something went wrong.'); ?></p>
            <a class="btn btn-warning" href="online_classes.php">Back to Online Classes</a>
            <a class="btn btn-outline-light ms-2" href="../admin/manage_online_classes.php">Admin</a>
        </div>
    </div>
</body>
</html>
