<?php
// Usage:
//   php tools/make_panorama.php input.jpg output.jpg 1800 420

[$script, $in, $out, $w, $h] = array_pad($argv, 5, null);
$w = (int)($w ?: 1800);
$h = (int)($h ?: 420);

if (!$in || !$out) {
    fwrite(STDERR, "Usage: php tools/make_panorama.php input.jpg output.jpg [width] [height]\n");
    exit(2);
}

$img = @imagecreatefromjpeg($in);
if (!$img) {
    fwrite(STDERR, "Could not read JPEG: {$in}\n");
    exit(2);
}

$sw = imagesx($img);
$sh = imagesy($img);
if ($sw <= 0 || $sh <= 0) {
    fwrite(STDERR, "Invalid image size\n");
    exit(2);
}

$scale = max($w / $sw, $h / $sh);
$cw = (int)round($w / $scale);
$ch = (int)round($h / $scale);
$cx = (int)max(0, floor(($sw - $cw) / 2));
$cy = (int)max(0, floor(($sh - $ch) / 2));

$dst = imagecreatetruecolor($w, $h);
$bg = imagecolorallocate($dst, 8, 6, 20);
imagefilledrectangle($dst, 0, 0, $w, $h, $bg);

imagecopyresampled($dst, $img, 0, 0, $cx, $cy, $w, $h, $cw, $ch);

if (!@imagejpeg($dst, $out, 85)) {
    fwrite(STDERR, "Could not write output: {$out}\n");
    exit(2);
}

imagedestroy($dst);
imagedestroy($img);

echo "OK: {$out}\n";
