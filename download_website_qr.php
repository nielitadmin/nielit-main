<?php
$file = __DIR__ . '/assets/images/nielitbhubaneswar-website-qr.png';
if (!is_file($file)) {
    http_response_code(404);
    echo 'QR code file was not found.';
    exit;
}

header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="NIELIT-Bhubaneswar-website-QR.png"');
header('Content-Length: ' . filesize($file));
header('Cache-Control: no-cache');
readfile($file);
