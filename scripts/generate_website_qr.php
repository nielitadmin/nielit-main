<?php
require_once __DIR__ . '/../phpqrcode/qrlib.php';

$url = 'https://nielitbhubaneswar.in/';
$logoPath = __DIR__ . '/../assets/images/bhubaneswar_logo.png';
$outPath = __DIR__ . '/../assets/images/nielitbhubaneswar-website-qr.png';
$tmpPath = __DIR__ . '/../assets/images/_qr_tmp.png';

QRcode::png($url, $tmpPath, QR_ECLEVEL_H, 12, 2);

$qr = imagecreatefrompng($tmpPath);
$logo = imagecreatefrompng($logoPath);
if (!$qr || !$logo) {
    fwrite(STDERR, "Could not open images\n");
    exit(1);
}

$qw = imagesx($qr);
$qh = imagesy($qr);
$lw = imagesx($logo);
$lh = imagesy($logo);

$badgeW = (int) round($qw * 0.48);
$badgeH = (int) round($badgeW * ($lh / $lw)) + 16;
$badgeW = max(120, $badgeW);
$badgeH = max(48, $badgeH);
$bx = (int) (($qw - $badgeW) / 2);
$by = (int) (($qh - $badgeH) / 2);

$white = imagecolorallocate($qr, 255, 255, 255);
$navy = imagecolorallocate($qr, 15, 61, 122);
imagefilledrectangle($qr, $bx - 2, $by - 2, $bx + $badgeW + 1, $by + $badgeH + 1, $navy);
imagefilledrectangle($qr, $bx, $by, $bx + $badgeW - 1, $by + $badgeH - 1, $white);

$pad = 8;
$maxW = $badgeW - ($pad * 2);
$maxH = $badgeH - ($pad * 2);
$scale = min($maxW / $lw, $maxH / $lh);
$dw = max(1, (int) round($lw * $scale));
$dh = max(1, (int) round($lh * $scale));

// Flatten onto white first. Resampling the transparent logo turns its clear
// background into an opaque black rectangle.
$flat = imagecreatetruecolor($lw, $lh);
$flatWhite = imagecolorallocate($flat, 255, 255, 255);
imagefilledrectangle($flat, 0, 0, $lw, $lh, $flatWhite);
imagealphablending($flat, true);
imagecopy($flat, $logo, 0, 0, 0, 0, $lw, $lh);

$scaled = imagecreatetruecolor($dw, $dh);
imagealphablending($scaled, true);
imagecopyresampled($scaled, $flat, 0, 0, 0, 0, $dw, $dh, $lw, $lh);

$dx = (int) (($qw - $dw) / 2);
$dy = (int) (($qh - $dh) / 2);
imagealphablending($qr, true);
imagecopy($qr, $scaled, $dx, $dy, 0, 0, $dw, $dh);

imagepng($qr, $outPath);
imagedestroy($qr);
imagedestroy($logo);
imagedestroy($flat);
imagedestroy($scaled);
@unlink($tmpPath);

echo $outPath . PHP_EOL;
echo $qw . 'x' . $qh . PHP_EOL;
