<?php

declare(strict_types=1);

require_once IA_ROOT . '/includes/listing_media.php';

function ia_listing_photo_qa_messages_ru(): array
{
    return [
        'car' => 'Автомобиль не найден',
        'blur' => 'Фото размыто',
        'dark' => 'Недостаточно света',
        'crop' => 'Автомобиль должен быть полностью в кадре',
        'angle' => 'Неверный ракурс',
    ];
}

function ia_listing_photo_qa_with_retake(string $code): string
{
    $messages = ia_listing_photo_qa_messages_ru();

    return ($messages[$code] ?? 'Фото не подходит') . ' Переснимите фото.';
}

/**
 * @return resource|\GdImage|null
 */
function ia_listing_photo_qa_load_image(string $tmpPath, string $mime)
{
    if (!function_exists('imagecreatefromjpeg')) {
        return null;
    }
    if ($mime === 'image/jpeg') {
        return @imagecreatefromjpeg($tmpPath) ?: null;
    }
    if ($mime === 'image/png') {
        return @imagecreatefrompng($tmpPath) ?: null;
    }
    if ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
        return @imagecreatefromwebp($tmpPath) ?: null;
    }

    return null;
}

/**
 * @param resource|\GdImage $img
 */
function ia_listing_photo_qa_resize($img, int $maxSide = 240)
{
    $w = imagesx($img);
    $h = imagesy($img);
    if ($w <= 0 || $h <= 0) {
        return null;
    }
    $longest = max($w, $h);
    $nw = $w;
    $nh = $h;
    if ($longest > $maxSide) {
        $ratio = $maxSide / $longest;
        $nw = max(1, (int) round($w * $ratio));
        $nh = max(1, (int) round($h * $ratio));
    }
    $dst = imagecreatetruecolor($nw, $nh);
    if (!($dst instanceof \GdImage) && !is_resource($dst)) {
        return null;
    }
    imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

    return $dst;
}

/**
 * @param resource|\GdImage $img
 * @return list<float>
 */
function ia_listing_photo_qa_gray_grid($img, int $w, int $h): array
{
    $gray = [];
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $rgb = imagecolorat($img, $x, $y);
            $r = ($rgb >> 16) & 0xff;
            $g = ($rgb >> 8) & 0xff;
            $b = $rgb & 0xff;
            $gray[] = 0.299 * $r + 0.587 * $g + 0.114 * $b;
        }
    }

    return $gray;
}

/**
 * @param list<float> $gray
 */
function ia_listing_photo_qa_laplacian_variance(array $gray, int $w, int $h): float
{
    if ($w < 3 || $h < 3) {
        return 0.0;
    }
    $sum = 0.0;
    $sumSq = 0.0;
    $n = 0;
    for ($y = 1; $y < $h - 1; $y++) {
        for ($x = 1; $x < $w - 1; $x++) {
            $i = $y * $w + $x;
            $lap = -4 * $gray[$i]
                + $gray[$i - 1]
                + $gray[$i + 1]
                + $gray[$i - $w]
                + $gray[$i + $w];
            $sum += $lap;
            $sumSq += $lap * $lap;
            $n++;
        }
    }
    if ($n === 0) {
        return 0.0;
    }
    $mean = $sum / $n;

    return ($sumSq / $n) - ($mean * $mean);
}

/**
 * @param list<float> $gray
 */
function ia_listing_photo_qa_mean_brightness(array $gray): float
{
    if ($gray === []) {
        return 0.0;
    }

    return array_sum($gray) / count($gray);
}

/**
 * @param list<float> $gray
 */
function ia_listing_photo_qa_edge_metrics(array $gray, int $w, int $h): array
{
    $edges = 0;
    $total = 0;
    $minX = $w;
    $minY = $h;
    $maxX = 0;
    $maxY = 0;
    $left = 0.0;
    $right = 0.0;
    $top = 0.0;
    $bottom = 0.0;
    $centerEdges = 0;
    $centerTotal = 0;
    $cx0 = (int) floor($w * 0.15);
    $cx1 = (int) ceil($w * 0.85);
    $cy0 = (int) floor($h * 0.15);
    $cy1 = (int) ceil($h * 0.85);

    for ($y = 1; $y < $h - 1; $y++) {
        for ($x = 1; $x < $w - 1; $x++) {
            $i = $y * $w + $x;
            $gx = $gray[$i + 1] - $gray[$i - 1];
            $gy = $gray[$i + $w] - $gray[$i - $w];
            $mag = sqrt($gx * $gx + $gy * $gy);
            $total++;
            if ($mag < 18) {
                continue;
            }
            $edges++;
            $minX = min($minX, $x);
            $minY = min($minY, $y);
            $maxX = max($maxX, $x);
            $maxY = max($maxY, $y);
            if ($x < (int) floor($w * 0.5)) {
                $left += $mag;
            } else {
                $right += $mag;
            }
            if ($y < (int) floor($h * 0.5)) {
                $top += $mag;
            } else {
                $bottom += $mag;
            }
            if ($x >= $cx0 && $x <= $cx1 && $y >= $cy0 && $y <= $cy1) {
                $centerEdges++;
            }
        }
    }

    for ($y = $cy0; $y <= $cy1; $y++) {
        for ($x = $cx0; $x <= $cx1; $x++) {
            $centerTotal++;
        }
    }

    $bboxW = $edges > 0 ? max(1, $maxX - $minX + 1) : 0;
    $bboxH = $edges > 0 ? max(1, $maxY - $minY + 1) : 0;

    return [
        'edge_ratio' => $total > 0 ? $edges / $total : 0.0,
        'center_ratio' => $centerTotal > 0 ? $centerEdges / $centerTotal : 0.0,
        'bbox_w_ratio' => $w > 0 ? $bboxW / $w : 0.0,
        'bbox_h_ratio' => $h > 0 ? $bboxH / $h : 0.0,
        'left' => $left,
        'right' => $right,
        'top' => $top,
        'bottom' => $bottom,
    ];
}

/**
 * @param array<string, float> $metrics
 */
function ia_listing_photo_qa_angle_ok(int $slotIdx, array $metrics): bool
{
    $left = $metrics['left'];
    $right = $metrics['right'];
    $top = $metrics['top'];
    $bottom = $metrics['bottom'];

    if ($slotIdx === 0 || $slotIdx === 1) {
        $ratio = min($left, $right) / max(max($left, $right), 1.0);

        return $ratio >= 0.55;
    }
    if ($slotIdx === 2 || $slotIdx === 3) {
        return $metrics['bbox_w_ratio'] >= 0.5 && $metrics['bbox_w_ratio'] >= $metrics['bbox_h_ratio'] * 1.1;
    }
    if ($slotIdx === 4) {
        return $left >= $right * 0.85;
    }
    if ($slotIdx === 5) {
        return $right >= $left * 0.85;
    }
    if ($slotIdx >= 6 && $slotIdx <= 8) {
        return $bottom >= $top * 0.75;
    }

    return $metrics['center_ratio'] >= 0.08;
}

/**
 * @param resource|\GdImage $img
 */
function ia_listing_photo_qa_analyze_image($img, int $slotIdx): ?string
{
    $w = imagesx($img);
    $h = imagesy($img);
    if ($w < 3 || $h < 3) {
        return ia_listing_photo_qa_with_retake('car');
    }

    $gray = ia_listing_photo_qa_gray_grid($img, $w, $h);
    $brightness = ia_listing_photo_qa_mean_brightness($gray);
    if ($brightness < 48) {
        return ia_listing_photo_qa_with_retake('dark');
    }

    $lap = ia_listing_photo_qa_laplacian_variance($gray, $w, $h);
    if ($lap < 85) {
        return ia_listing_photo_qa_with_retake('blur');
    }

    $metrics = ia_listing_photo_qa_edge_metrics($gray, $w, $h);
    if ($metrics['edge_ratio'] < 0.03 || $metrics['center_ratio'] < 0.05) {
        return ia_listing_photo_qa_with_retake('car');
    }

    if ($metrics['bbox_w_ratio'] < 0.42 || $metrics['bbox_h_ratio'] < 0.34) {
        return ia_listing_photo_qa_with_retake('crop');
    }

    if (!ia_listing_photo_qa_angle_ok($slotIdx, $metrics)) {
        return ia_listing_photo_qa_with_retake('angle');
    }

    return null;
}

function ia_listing_photo_qa_validate_tmp(string $tmpPath, int $slotIdx): ?string
{
    if ($slotIdx < 0 || $slotIdx >= IA_LISTING_PHOTO_SLOT_COUNT) {
        return null;
    }
    $info = @getimagesize($tmpPath);
    if ($info === false) {
        return ia_listing_photo_qa_with_retake('car');
    }
    $w = (int) ($info[0] ?? 0);
    $h = (int) ($info[1] ?? 0);
    $short = min($w, $h);
    $long = max($w, $h);
    $aspect = $h > 0 ? $w / $h : 0.0;
    if ($short < 480 || $long > 4096 || $aspect < (9 / 16) || $aspect > (16 / 9)) {
        return ia_listing_photo_qa_with_retake('angle');
    }

    $mime = (string) ($info['mime'] ?? '');
    $img = ia_listing_photo_qa_load_image($tmpPath, $mime);
    if ($img === null) {
        return null;
    }
    $scaled = ia_listing_photo_qa_resize($img, 240);
    imagedestroy($img);
    if ($scaled === null) {
        return null;
    }
    $err = ia_listing_photo_qa_analyze_image($scaled, $slotIdx);
    imagedestroy($scaled);

    return $err;
}

/**
 * Включена ли эвристическая проверка фото (ракурс, резкость и т.д.) — задаётся в админке «Настройки сайта».
 * По умолчанию выключена: принимаются только базовые проверки файла при загрузке.
 */
function ia_listing_photo_qa_is_enabled(IaPgConnection|IaPdoConnection|null $pdo = null): bool
{
    require_once IA_ROOT . '/includes/site_settings.php';
    $pdo = $pdo ?? ia_db();
    $v = strtolower(trim(ia_site_setting_get($pdo, 'listing_photo_qa_enabled', '0')));

    return in_array($v, ['1', 'true', 'yes', 'on'], true);
}
