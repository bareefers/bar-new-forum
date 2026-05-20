<?php

declare(strict_types=1);

$src = '/var/www/bareefers.org/forum/data/assets/logo/bar-logo.png';
$dst = '/var/www/bareefers.org/forum/data/assets/logo/bar-logo.webp';

if (!extension_loaded('gd'))
{
    fwrite(STDERR, "GD extension is not loaded.\n");
    exit(1);
}

$image = imagecreatefrompng($src);
if (!$image)
{
    fwrite(STDERR, "Failed to load source PNG.\n");
    exit(1);
}

imagepalettetotruecolor($image);
imagealphablending($image, true);
imagesavealpha($image, true);

if (!imagewebp($image, $dst, 90))
{
    imagedestroy($image);
    fwrite(STDERR, "Failed to write WebP.\n");
    exit(1);
}

imagedestroy($image);
echo $dst . PHP_EOL;
