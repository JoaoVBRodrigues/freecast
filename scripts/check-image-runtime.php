<?php

// No application bootstrap, environment, database or storage access.
if (! function_exists('imagecreatefromstring') || (imagetypes() & IMG_PNG) === 0 || (imagetypes() & IMG_WEBP) === 0) {
    if (! in_array('--quiet', $argv, true)) {
        fwrite(STDERR, "Freecast imports require PHP GD with PNG and WebP support.\n");
    }
    exit(42);
}
echo "PHP GD: PNG/WebP decode available.\n";
