<?php

// Isolated decoder: no Laravel bootstrap, DB, filesystem writes or network.
// GD may raise uncatchable fatal errors on malformed WebP. The caller checks exit status.
if (! function_exists('imagecreatefromstring') || count($argv) !== 3) {
    exit(2);
}
$width = (int) $argv[1];
$height = (int) $argv[2];
if ($width < 200 || $height < 200 || $width > 8000 || $height > 8000 || $width * $height > 16000000) {
    exit(2);
}
$bytes = stream_get_contents(STDIN, 20 * 1024 * 1024 + 1);
if (! is_string($bytes) || strlen($bytes) > 20 * 1024 * 1024) {
    exit(2);
}
$warning = false;
set_error_handler(function () use (&$warning) { $warning = true; return true; });
$bitmap = imagecreatefromstring($bytes);
$valid = $bitmap !== false && ! $warning && imagesx($bitmap) === $width && imagesy($bitmap) === $height;
if ($bitmap !== false) {
    imagedestroy($bitmap);
}
exit($valid ? 0 : 1);
