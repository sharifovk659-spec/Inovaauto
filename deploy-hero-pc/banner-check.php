<?php

declare(strict_types=1);

/**
 * Санҷиши файлҳои баннер дар hosting — баъд аз санҷиш нест кунед.
 * Upload: корень сайт → https://ДОМЕН/banner-check.php
 */
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');

define('IA_ROOT', __DIR__);

$files = [
    'index.php',
    'includes/partials/home-hero-desktop.php',
    'includes/home_responsive_images.php',
    'assets/site.css',
    'assets/site.min.css',
    'IMG/bANER.png',
];

echo "InnovaAuto — banner deploy check\n";
echo "PHP: " . PHP_VERSION . "\n";
echo str_repeat('-', 50) . "\n";

$missing = [];
foreach ($files as $rel) {
    $abs = IA_ROOT . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $ok = is_file($abs);
    echo ($ok ? '[OK]  ' : '[MISS]') . ' ' . $rel;
    if ($ok) {
        echo ' (' . round(filesize($abs) / 1024, 1) . ' KB)';
    }
    echo "\n";
    if (!$ok) {
        $missing[] = $rel;
    }
}

echo str_repeat('-', 50) . "\n";

if ($missing === []) {
    echo "STATUS: ALL OK — баннер бояд кор кунад.\n";
    $bannerAbs = IA_ROOT . '/IMG/bANER.png';
    if (is_file($bannerAbs)) {
        echo "Banner image: found (" . round(filesize($bannerAbs) / 1024 / 1024, 2) . " MB)\n";
    }
} else {
    echo "STATUS: FAIL — ин файлҳоро upload кунед:\n";
    foreach ($missing as $rel) {
        echo "  - $rel\n";
    }
}
