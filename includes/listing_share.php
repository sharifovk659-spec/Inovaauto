<?php

declare(strict_types=1);

/**
 * «Поделиться» и Open Graph для страницы объявления.
 * Подключается из helpers.php и car.php (на случай старого helpers на хостинге).
 */

if (!function_exists('ia_listing_share_disclaimer_parts')) {
    /**
     * @return array{heading: string, lines: list<string>}
     */
    function ia_listing_share_disclaimer_parts(string $siteName = 'InnovaAuto'): array
    {
        $site = trim($siteName) !== '' ? trim($siteName) : 'InnovaAuto';

        $warning = 'Для вашей безопасности общайтесь только через сайт ' . $site . '. '
            . 'При общении вне сайта (WhatsApp, Telegram и другие мессенджеры) '
            . $site . ' не несёт ответственности.';

        return [
            'heading' => 'Внимание',
            'lines' => [$warning],
            'native' => $warning,
        ];
    }
}

if (!function_exists('ia_listing_share_disclaimer')) {
    function ia_listing_share_disclaimer(string $siteName = 'InnovaAuto'): string
    {
        $parts = ia_listing_share_disclaimer_parts($siteName);

        return (string) ($parts['native'] ?? implode(' ', $parts['lines']));
    }
}

if (!function_exists('ia_absolute_url')) {
    function ia_absolute_url(string $pathOrUrl): string
    {
        $pathOrUrl = trim($pathOrUrl);
        if ($pathOrUrl === '') {
            return '';
        }
        if (preg_match('#\Ahttps?://#i', $pathOrUrl)) {
            return $pathOrUrl;
        }
        if (str_starts_with($pathOrUrl, '//')) {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443');
            return ($https ? 'https' : 'http') . ':' . $pathOrUrl;
        }

        $base = rtrim(ia_site_base_url(), '/');
        if (str_starts_with($pathOrUrl, '/')) {
            $parts = parse_url($base);
            if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
                return $base . $pathOrUrl;
            }
            $origin = $parts['scheme'] . '://' . $parts['host'];
            if (!empty($parts['port'])) {
                $origin .= ':' . $parts['port'];
            }

            return $origin . $pathOrUrl;
        }

        return $base . '/' . ltrim(str_replace('\\', '/', $pathOrUrl), '/');
    }
}

if (!function_exists('ia_listing_share_meta')) {
    /**
     * @param array<string, mixed> $listing
     * @return array{
     *   url: string,
     *   title: string,
     *   page_title: string,
     *   description: string,
     *   image: string,
     *   share_line: string,
     *   share_text: string
     * }
     */
    function ia_listing_share_meta(array $listing, int $listingId, string $siteName = 'InnovaAuto'): array
    {
        $brand = trim((string) ($listing['brand'] ?? ''));
        $model = trim((string) ($listing['model'] ?? ''));
        $carTitle = trim($brand . ' ' . $model);
        if ($carTitle === '') {
            $carTitle = 'Автомобиль';
        }

        $year = (int) ($listing['model_year'] ?? 0);
        $cur = (string) ($listing['currency'] ?? 'TJS');
        $priceLabel = ia_listing_format_price((float) ($listing['price'] ?? 0), $cur);

        $shareTitle = $carTitle;
        if ($year >= 1950) {
            $shareTitle .= ', ' . $year;
        }
        $shareTitle .= ' — ' . $priceLabel;

        $url = ia_public_url('car.php?id=' . max(1, $listingId));

        $descBits = [];
        if ($year >= 1950) {
            $descBits[] = (string) $year;
        }
        if (isset($listing['mileage_km']) && $listing['mileage_km'] !== null && $listing['mileage_km'] !== '') {
            $descBits[] = ia_listing_mileage_label_ru($listing['mileage_km']);
        }
        $city = trim((string) ($listing['city'] ?? ''));
        if ($city !== '') {
            $descBits[] = $city;
        }
        $description = $shareTitle;
        if ($descBits !== []) {
            $description .= ' · ' . implode(' · ', $descBits);
        }

        $image = '';
        $photo = trim((string) ($listing['photo_url'] ?? ''));
        if ($photo !== '') {
            $src = ia_listing_photo_src($photo);
            if ($src !== '' && !str_starts_with($src, 'data:image')) {
                $image = ia_absolute_url($src);
            }
        }

        return [
            'url' => $url,
            'title' => $shareTitle,
            'page_title' => $carTitle,
            'description' => $description,
            'image' => $image,
            'share_line' => $shareTitle,
            'share_text' => $shareTitle . "\n" . $url,
        ];
    }
}

if (!function_exists('ia_open_graph_meta_html')) {
    /**
     * @param array{title?: string, description?: string, url?: string, image?: string, site_name?: string, type?: string} $meta
     */
    function ia_open_graph_meta_html(array $meta): string
    {
        $title = trim((string) ($meta['title'] ?? ''));
        $description = trim((string) ($meta['description'] ?? ''));
        $url = trim((string) ($meta['url'] ?? ''));
        $image = trim((string) ($meta['image'] ?? ''));
        $siteName = trim((string) ($meta['site_name'] ?? ''));
        $type = trim((string) ($meta['type'] ?? 'website'));
        if ($type === '') {
            $type = 'website';
        }

        $lines = [];
        if ($title !== '') {
            $lines[] = '<meta property="og:title" content="' . ia_h($title) . '">';
            $lines[] = '<meta name="twitter:title" content="' . ia_h($title) . '">';
        }
        if ($description !== '') {
            $lines[] = '<meta property="og:description" content="' . ia_h($description) . '">';
            $lines[] = '<meta name="description" content="' . ia_h($description) . '">';
            $lines[] = '<meta name="twitter:description" content="' . ia_h($description) . '">';
        }
        if ($url !== '') {
            $lines[] = '<meta property="og:url" content="' . ia_h($url) . '">';
        }
        if ($image !== '') {
            $lines[] = '<meta property="og:image" content="' . ia_h($image) . '">';
            $lines[] = '<meta name="twitter:image" content="' . ia_h($image) . '">';
        }
        if ($siteName !== '') {
            $lines[] = '<meta property="og:site_name" content="' . ia_h($siteName) . '">';
        }
        $lines[] = '<meta property="og:type" content="' . ia_h($type) . '">';
        $lines[] = '<meta name="twitter:card" content="' . ($image !== '' ? 'summary_large_image' : 'summary') . '">';

        return implode("\n", $lines);
    }
}
