<?php

declare(strict_types=1);

/**
 * Responsive WebP sets for homepage / banner static assets (768w mobile, 1920w desktop).
 * Бехатар барои hosting: function_exists + fallback URL-ҳо.
 */

if (!function_exists('ia_responsive_webp_set')) {
    /**
     * @return array{
     *   has_webp: bool,
     *   fallback: string,
     *   width: int,
     *   height: int,
     *   sources: list<array{media: string, srcset: string, type: string}>,
     *   srcset: string,
     *   preload: string
     * }
     */
    function ia_responsive_webp_set(string $sourceRelative, int $fallbackWidth = 640, int $fallbackHeight = 480): array
    {
        $sourceRelative = ltrim(str_replace('\\', '/', $sourceRelative), '/');
        $abs = IA_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sourceRelative);
        $dir = dirname($sourceRelative);
        $stem = pathinfo($sourceRelative, PATHINFO_FILENAME);
        $webpDir = ($dir === '.' ? '' : $dir . '/') . 'webp';
        if (strncmp($sourceRelative, 'IMG/', 4) === 0 && $dir !== 'IMG/hero') {
            $webpDir = 'IMG/webp';
        }
        $mobileRel = $webpDir . '/' . $stem . '-768.webp';
        $desktopRel = $webpDir . '/' . $stem . '-1920.webp';
        $mobileAbs = IA_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $mobileRel);
        $desktopAbs = IA_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $desktopRel);

        $hasMobile = is_file($mobileAbs);
        $hasDesktop = is_file($desktopAbs);
        $hasWebp = $hasMobile || $hasDesktop;

        $assetUrl = static function (string $rel) use ($abs, $sourceRelative): string {
            if (function_exists('ia_public_asset_version')) {
                return ia_public_asset_version($rel);
            }
            if (function_exists('ia_public_asset')) {
                return ia_public_asset($rel);
            }

            return is_file($abs) ? $sourceRelative : '';
        };

        $fallbackUrl = $hasDesktop
            ? $assetUrl($desktopRel)
            : ($hasMobile
                ? $assetUrl($mobileRel)
                : (is_file($abs) ? $assetUrl($sourceRelative) : ''));

        $width = $fallbackWidth;
        $height = $fallbackHeight;
        $dimFile = $hasDesktop ? $desktopAbs : ($hasMobile ? $mobileAbs : $abs);
        if (is_file($dimFile)) {
            $info = @getimagesize($dimFile);
            if (is_array($info) && isset($info[0], $info[1])) {
                $width = (int) $info[0];
                $height = (int) $info[1];
            }
        }

        $sources = [];
        $srcsetParts = [];
        if ($hasMobile) {
            $mobileUrl = $assetUrl($mobileRel);
            $sources[] = [
                'media' => '(max-width: 991.98px)',
                'srcset' => $mobileUrl . ' 768w',
                'type' => 'image/webp',
            ];
            $srcsetParts[] = $mobileUrl . ' 768w';
        }
        if ($hasDesktop) {
            $desktopUrl = $assetUrl($desktopRel);
            $sources[] = [
                'media' => '(min-width: 992px)',
                'srcset' => $desktopUrl . ' 1920w',
                'type' => 'image/webp',
            ];
            $srcsetParts[] = $desktopUrl . ' 1920w';
        }

        $preload = $hasMobile
            ? $assetUrl($mobileRel)
            : ($hasDesktop ? $assetUrl($desktopRel) : $fallbackUrl);

        return [
            'has_webp' => $hasWebp,
            'fallback' => $fallbackUrl,
            'width' => $width,
            'height' => $height,
            'sources' => $sources,
            'srcset' => implode(', ', $srcsetParts),
            'preload' => $preload,
        ];
    }
}

if (!function_exists('ia_home_hero_mobile_set')) {
    /**
     * @return array<string, mixed>|null
     */
    function ia_home_hero_mobile_set(): ?array
    {
        $candidates = [
            'IMG/baneri telefon.jpeg',
            'IMG/baneri telefon.jpg',
            'IMG/baneri-telefon.jpeg',
            'IMG/baneri-telefon.jpg',
            'IMG/bANER.png',
            'IMG/BANER.png',
        ];
        foreach ($candidates as $rel) {
            $abs = IA_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (!is_file($abs)) {
                continue;
            }
            $set = ia_responsive_webp_set($rel, 1080, 608);
            if (!$set['has_webp']) {
                $set['fallback'] = function_exists('ia_public_asset_version')
                    ? ia_public_asset_version($rel)
                    : $rel;
                $set['preload'] = $set['fallback'];
                $info = @getimagesize($abs);
                if (is_array($info) && isset($info[0], $info[1])) {
                    $set['width'] = (int) $info[0];
                    $set['height'] = (int) $info[1];
                }
            } elseif ($set['preload'] === '' || $set['preload'] === $set['fallback']) {
                $stem = pathinfo($rel, PATHINFO_FILENAME);
                $mobileRel = 'IMG/webp/' . $stem . '-768.webp';
                $ma = IA_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $mobileRel);
                if (is_file($ma) && function_exists('ia_public_asset_version')) {
                    $set['preload'] = ia_public_asset_version($mobileRel);
                    $info = @getimagesize($ma);
                    if (is_array($info) && isset($info[0], $info[1])) {
                        $set['width'] = (int) $info[0];
                        $set['height'] = (int) $info[1];
                    }
                }
            }

            return $set;
        }

        return null;
    }
}

if (!function_exists('ia_home_hero_desktop_bg_url')) {
    function ia_home_hero_desktop_bg_url(): string
    {
        $candidates = [
            'IMG/bANER.png',
            'IMG/BANER.png',
            'IMG/Baner PC.png',
            'IMG/Baner-PC.png',
            'IMG/baner-pc.png',
            'IMG/hero/desktop-1920.webp',
        ];
        foreach ($candidates as $rel) {
            $abs = IA_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (!is_file($abs)) {
                continue;
            }
            if (function_exists('ia_public_asset_version')) {
                return ia_public_asset_version($rel);
            }
            if (function_exists('ia_public_asset')) {
                return ia_public_asset($rel);
            }

            return $rel;
        }

        return '';
    }
}

if (!function_exists('ia_banner_responsive_set')) {
    /**
     * @return array<string, mixed>
     */
    function ia_banner_responsive_set(string $imagePath): array
    {
        $imagePath = ltrim(str_replace('\\', '/', $imagePath), '/');
        $abs = IA_ROOT . '/uploads/banners/' . $imagePath;
        $stem = str_replace('/', '__', pathinfo($imagePath, PATHINFO_FILENAME));
        $mobileRel = 'uploads/banners/webp/' . $stem . '-768.webp';
        $desktopRel = 'uploads/banners/webp/' . $stem . '-1920.webp';
        $mobileAbs = IA_ROOT . '/' . str_replace('/', DIRECTORY_SEPARATOR, $mobileRel);
        $desktopAbs = IA_ROOT . '/' . str_replace('/', DIRECTORY_SEPARATOR, $desktopRel);

        $originalUrl = function_exists('ia_uploads_banners_public_url')
            ? ia_uploads_banners_public_url($imagePath)
            : ('uploads/banners/' . $imagePath);
        $hasMobile = is_file($mobileAbs);
        $hasDesktop = is_file($desktopAbs);

        $width = 1920;
        $height = 823;
        $dimFile = $hasDesktop ? $desktopAbs : ($hasMobile ? $mobileAbs : (is_file($abs) ? $abs : null));
        if ($dimFile !== null && is_file($dimFile)) {
            $info = @getimagesize($dimFile);
            if (is_array($info) && isset($info[0], $info[1])) {
                $width = (int) $info[0];
                $height = (int) $info[1];
            }
        }

        $sources = [];
        $srcsetParts = [];
        $bannerAssetUrl = static function (string $rel, string $absFile): string {
            if (function_exists('ia_root_asset')) {
                $url = ia_root_asset($rel);
            } elseif (function_exists('ia_public_asset')) {
                $url = ia_public_asset($rel);
            } else {
                $url = $rel;
            }

            return is_file($absFile) ? $url . '?v=' . (string) filemtime($absFile) : $url;
        };

        if ($hasMobile) {
            $u = $bannerAssetUrl($mobileRel, $mobileAbs);
            $sources[] = ['media' => '(max-width: 991.98px)', 'srcset' => $u . ' 768w', 'type' => 'image/webp'];
            $srcsetParts[] = $u . ' 768w';
        }
        if ($hasDesktop) {
            $u = $bannerAssetUrl($desktopRel, $desktopAbs);
            $sources[] = ['media' => '(min-width: 992px)', 'srcset' => $u . ' 1920w', 'type' => 'image/webp'];
            $srcsetParts[] = $u . ' 1920w';
        }

        $fallback = $hasDesktop
            ? $bannerAssetUrl($desktopRel, $desktopAbs)
            : ($hasMobile
                ? $bannerAssetUrl($mobileRel, $mobileAbs)
                : $originalUrl);

        return [
            'has_webp' => $hasMobile || $hasDesktop,
            'fallback' => $fallback,
            'width' => $width,
            'height' => $height,
            'sources' => $sources,
            'srcset' => implode(', ', $srcsetParts),
            'preload' => $fallback,
        ];
    }
}

if (!function_exists('ia_render_responsive_picture')) {
    /**
     * @param array<string, mixed> $set
     * @param array<string, mixed> $opts
     */
    function ia_render_responsive_picture(array $set, array $opts = []): void
    {
        $class = (string) ($opts['class'] ?? '');
        $alt = (string) ($opts['alt'] ?? '');
        $sizes = (string) ($opts['sizes'] ?? '100vw');
        $loading = (string) ($opts['loading'] ?? 'lazy');
        $fetchpriority = (string) ($opts['fetchpriority'] ?? '');
        $decoding = (string) ($opts['decoding'] ?? 'async');
        $width = max(1, (int) ($set['width'] ?? 640));
        $height = max(1, (int) ($set['height'] ?? 480));
        $fallback = (string) ($set['fallback'] ?? '');
        if ($fallback === '') {
            return;
        }

        $perfAttrs = function_exists('ia_img_perf_attrs')
            ? ia_img_perf_attrs([
                'loading' => $loading,
                'fetchpriority' => $fetchpriority,
                'width' => $width,
                'height' => $height,
                'decoding' => $decoding,
            ])
            : 'loading="' . htmlspecialchars($loading, ENT_QUOTES, 'UTF-8') . '" decoding="' . htmlspecialchars($decoding, ENT_QUOTES, 'UTF-8') . '" width="' . $width . '" height="' . $height . '"';

        echo '<picture>';
        foreach ($set['sources'] ?? [] as $source) {
            if (!is_array($source)) {
                continue;
            }
            $media = (string) ($source['media'] ?? '');
            $srcset = (string) ($source['srcset'] ?? '');
            $type = (string) ($source['type'] ?? 'image/webp');
            if ($srcset === '') {
                continue;
            }
            echo '<source type="' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '"';
            if ($media !== '') {
                echo ' media="' . htmlspecialchars($media, ENT_QUOTES, 'UTF-8') . '"';
            }
            echo ' srcset="' . htmlspecialchars($srcset, ENT_QUOTES, 'UTF-8') . '">';
        }
        echo '<img class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"';
        echo ' src="' . htmlspecialchars($fallback, ENT_QUOTES, 'UTF-8') . '"';
        echo ' alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '"';
        echo ' sizes="' . htmlspecialchars($sizes, ENT_QUOTES, 'UTF-8') . '"';
        echo ' ' . $perfAttrs . '>';
        echo '</picture>';
    }
}
