<?php

declare(strict_types=1);

use InnovaAuto\Security\Csrf;

function ia_admin_site_upload_dir(): string
{
    return IA_ROOT . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'site';
}

/**
 * @param mixed $img GD image
 */
function ia_admin_site_logo_pixel_rgb($img, int $x, int $y): array
{
    $c = imagecolorat($img, $x, $y);

    return [(int) (($c >> 16) & 0xFF), (int) (($c >> 8) & 0xFF), (int) ($c & 0xFF)];
}

/**
 * Soft chroma key: removes a solid background colour and produces a
 * smoothly-feathered alpha channel so that anti-aliased edges of the
 * logo (text, glow effects) stay clean — no halo, no jaggies.
 *
 * @param mixed $jpegImg
 * @return mixed GD с альфой или false
 */
function ia_admin_site_logo_jpeg_chroma_to_alpha($jpegImg, int $innerTolerance = 36, int $outerTolerance = 130): mixed
{
    if (!(is_object($jpegImg) || is_resource($jpegImg))) {
        return false;
    }
    $w = imagesx($jpegImg);
    $h = imagesy($jpegImg);
    if ($w <= 1 || $h <= 1) {
        return false;
    }
    $sampleX = max(1, (int) floor($w * 0.02));
    $sampleY = max(1, (int) floor($h * 0.02));
    $coords = [
        [0, 0], [$w - 1, 0], [0, $h - 1], [$w - 1, $h - 1],
        [$sampleX, $sampleY],
        [$w - 1 - $sampleX, $sampleY],
        [$sampleX, $h - 1 - $sampleY],
        [$w - 1 - $sampleX, $h - 1 - $sampleY],
    ];
    $sr = 0;
    $sg = 0;
    $sb = 0;
    $n = 0;
    foreach ($coords as [$cx, $cy]) {
        if ($cx < 0 || $cy < 0 || $cx >= $w || $cy >= $h) {
            continue;
        }
        [$r, $g, $b] = ia_admin_site_logo_pixel_rgb($jpegImg, $cx, $cy);
        $sr += $r;
        $sg += $g;
        $sb += $b;
        $n++;
    }
    if ($n === 0) {
        return false;
    }
    $br = (int) round($sr / $n);
    $bg = (int) round($sg / $n);
    $bb = (int) round($sb / $n);

    if ($outerTolerance <= $innerTolerance) {
        $outerTolerance = $innerTolerance + 1;
    }

    $out = imagecreatetruecolor($w, $h);
    if (!(is_object($out) || is_resource($out))) {
        return false;
    }
    imagealphablending($out, false);
    imagesavealpha($out, true);
    $trans = imagecolorallocatealpha($out, 0, 0, 0, 127);
    if ($trans !== false) {
        imagefill($out, 0, 0, $trans);
    }

    $innerSum = $innerTolerance * 3;
    $outerSum = $outerTolerance * 3;
    $range = max(1, $outerSum - $innerSum);

    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            [$r, $g, $b] = ia_admin_site_logo_pixel_rgb($jpegImg, $x, $y);
            $diff = abs($r - $br) + abs($g - $bg) + abs($b - $bb);
            if ($diff <= $innerSum) {
                continue;
            }
            if ($diff >= $outerSum) {
                $alpha = 0;
            } else {
                $alpha = (int) round(127 - (($diff - $innerSum) / $range) * 127);
                if ($alpha < 0) {
                    $alpha = 0;
                } elseif ($alpha > 127) {
                    $alpha = 127;
                }
            }
            $col = imagecolorallocatealpha($out, $r, $g, $b, $alpha);
            if ($col !== false) {
                imagesetpixel($out, $x, $y, $col);
            }
        }
    }

    return $out;
}

/**
 * Обрезка полей с alpha и нормализация размеров логотипа.
 * JPEG с белым/однотонным фоном → PNG с прозрачностью (имя файла меняется на .png).
 * Возвращает basename для site_settings.logo_path.
 */
/**
 * Проверяет, плотный ли фон по углам (без альфы) — значит, можно сделать chroma key.
 * @param mixed $img
 */
function ia_admin_site_logo_has_solid_corners($img, int $tolerance = 18): bool
{
    if (!(is_object($img) || is_resource($img))) {
        return false;
    }
    $w = imagesx($img);
    $h = imagesy($img);
    if ($w < 4 || $h < 4) {
        return false;
    }
    $coords = [[0, 0], [$w - 1, 0], [0, $h - 1], [$w - 1, $h - 1]];
    $colors = [];
    foreach ($coords as [$cx, $cy]) {
        $rgba = imagecolorat($img, $cx, $cy);
        $alpha = ($rgba >> 24) & 0x7F;
        if ($alpha > 10) {
            return false;
        }
        $colors[] = [($rgba >> 16) & 0xFF, ($rgba >> 8) & 0xFF, $rgba & 0xFF];
    }
    $r0 = $colors[0][0];
    $g0 = $colors[0][1];
    $b0 = $colors[0][2];
    foreach ($colors as [$r, $g, $b]) {
        if (abs($r - $r0) + abs($g - $g0) + abs($b - $b0) > $tolerance * 3) {
            return false;
        }
    }

    return true;
}

function ia_admin_site_logo_normalize(string $fullPath, string $ext): string
{
    $basename = basename($fullPath);
    if ($ext === 'svg' || !function_exists('imagecreatefromjpeg')) {
        return $basename;
    }

    $dir = dirname($fullPath);
    $src = null;
    if ($ext === 'png') {
        $src = @imagecreatefrompng($fullPath);
    } elseif ($ext === 'webp' && function_exists('imagecreatefromwebp')) {
        $src = @imagecreatefromwebp($fullPath);
    } elseif ($ext === 'gif') {
        $src = @imagecreatefromgif($fullPath);
    } elseif ($ext === 'jpg' || $ext === 'jpeg') {
        $src = @imagecreatefromjpeg($fullPath);
    }
    if (!(is_object($src) || is_resource($src))) {
        return $basename;
    }

    $isJpeg = in_array($ext, ['jpg', 'jpeg'], true);
    $needsChroma = $isJpeg;
    if (!$isJpeg && in_array($ext, ['png', 'webp', 'gif'], true)) {
        if (ia_admin_site_logo_has_solid_corners($src, 24)) {
            $needsChroma = true;
        }
    }
    if ($needsChroma) {
        $long = max(imagesx($src), imagesy($src));
        if ($long > 1000) {
            $ratio = 1000 / $long;
            $nw = max(1, (int) round(imagesx($src) * $ratio));
            $nh = max(1, (int) round(imagesy($src) * $ratio));
            $tmp = imagecreatetruecolor($nw, $nh);
            if (is_object($tmp) || is_resource($tmp)) {
                imagecopyresampled($tmp, $src, 0, 0, 0, 0, $nw, $nh, imagesx($src), imagesy($src));
                imagedestroy($src);
                $src = $tmp;
            }
        }
        $chroma = ia_admin_site_logo_jpeg_chroma_to_alpha($src, 36, 130);
        if (!(is_object($chroma) || is_resource($chroma))) {
            imagedestroy($src);

            return $basename;
        }
        imagedestroy($src);
        $src = $chroma;
        $ext = 'png';
        $baseNameOnly = pathinfo($basename, PATHINFO_FILENAME);
        $newBasename = $baseNameOnly . '.png';
        $newPath = $dir . DIRECTORY_SEPARATOR . $newBasename;
        if ($newPath !== $fullPath && is_file($fullPath)) {
            @unlink($fullPath);
        }
        $fullPath = $newPath;
        $basename = $newBasename;
    }

    $w = (int) imagesx($src);
    $h = (int) imagesy($src);
    if ($w <= 0 || $h <= 0) {
        imagedestroy($src);

        return $basename;
    }

    $hasAlpha = in_array($ext, ['png', 'webp', 'gif'], true);
    if ($hasAlpha) {
        imagealphablending($src, false);
        imagesavealpha($src, true);

        $minX = $w;
        $minY = $h;
        $maxX = -1;
        $maxY = -1;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgba = imagecolorat($src, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                if ($alpha < 110) {
                    if ($x < $minX) {
                        $minX = $x;
                    }
                    if ($y < $minY) {
                        $minY = $y;
                    }
                    if ($x > $maxX) {
                        $maxX = $x;
                    }
                    if ($y > $maxY) {
                        $maxY = $y;
                    }
                }
            }
        }
        if ($maxX >= $minX && $maxY >= $minY && ($maxX - $minX + 1) >= 2 && ($maxY - $minY + 1) >= 2) {
            $padX = (int) round(($maxX - $minX + 1) * 0.04);
            $padY = (int) round(($maxY - $minY + 1) * 0.04);
            $cx = max(0, $minX - $padX);
            $cy = max(0, $minY - $padY);
            $cw = min($w - $cx, $maxX - $cx + 1 + $padX);
            $ch = min($h - $cy, $maxY - $cy + 1 + $padY);

            $cropped = imagecreatetruecolor($cw, $ch);
            if (is_object($cropped) || is_resource($cropped)) {
                imagealphablending($cropped, false);
                imagesavealpha($cropped, true);
                $transparent = imagecolorallocatealpha($cropped, 0, 0, 0, 127);
                if ($transparent !== false) {
                    imagefill($cropped, 0, 0, $transparent);
                }
                imagecopy($cropped, $src, 0, 0, $cx, $cy, $cw, $ch);
                imagedestroy($src);
                $src = $cropped;
                $w = $cw;
                $h = $ch;
            }
        }
    }

    $maxH = 220;
    $maxW = 640;
    $scale = min($maxH / $h, $maxW / $w, 1.0);
    if ($scale < 1.0) {
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        if (is_object($dst) || is_resource($dst)) {
            if ($hasAlpha) {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
                if ($transparent !== false) {
                    imagefill($dst, 0, 0, $transparent);
                }
            }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);
            $src = $dst;
        }
    }

    $ok = false;
    if ($ext === 'png') {
        $ok = (bool) imagepng($src, $fullPath, 6);
    } elseif ($ext === 'webp' && function_exists('imagewebp')) {
        $ok = (bool) imagewebp($src, $fullPath, 92);
    } elseif ($ext === 'gif') {
        $ok = (bool) imagegif($src, $fullPath);
    }
    imagedestroy($src);

    return $basename;
}

function ia_admin_site_settings_handle_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        ia_flash('settings_error', 'Сессия устарела.');
        ia_redirect(ia_admin_url('settings.php'));
    }

    $pdo = ia_db();

    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'save_text') {
        $fields = [
            'site_name',
            'contact_phone',
            'contact_email',
            'contact_address',
            'footer_brand_title',
            'footer_company_text',
            'meta_title',
            'meta_description',
            'social_vk',
            'social_telegram',
            'social_instagram',
            'social_facebook',
            'social_youtube',
            'api_maps_key',
            'api_sms_gateway_key',
            'api_push_server_key',
        ];
        foreach ($fields as $k) {
            if (!array_key_exists($k, $_POST)) {
                continue;
            }
            ia_site_setting_set($pdo, $k, trim((string) $_POST[$k]));
        }
        ia_flash('settings_ok', 'Настройки сохранены.');
        ia_redirect(ia_admin_url('settings.php'));
    }

    if ($action === 'save_listing_publish_rules') {
        $onQa = isset($_POST['listing_photo_qa_enabled']) && (string) $_POST['listing_photo_qa_enabled'] === '1';
        ia_site_setting_set($pdo, 'listing_photo_qa_enabled', $onQa ? '1' : '0');
        ia_flash('settings_ok', 'Настройки публикации объявлений сохранены.');
        ia_redirect(ia_admin_url('settings.php'));
    }

    if ($action === 'save_promotion_monetization') {
        $wasOn = ia_site_setting_get($pdo, 'promotion_monetization_enabled', '1') === '1';
        $on = isset($_POST['promotion_monetization_enabled']) && (string) $_POST['promotion_monetization_enabled'] === '1';
        $restartTimer = isset($_POST['promotion_restart_timer']) && (string) $_POST['promotion_restart_timer'] === '1';
        ia_site_setting_set($pdo, 'promotion_monetization_enabled', $on ? '1' : '0');

        $freeMonths = (int) ($_POST['promotion_free_months'] ?? 6);
        $freeMonths = max(1, min(36, $freeMonths));
        ia_site_setting_set($pdo, 'promotion_free_months', (string) $freeMonths);

        if ($on && (!$wasOn || $restartTimer)) {
            ia_promotion_reset_launch_now($pdo);
        } else {
            $launchRaw = trim((string) ($_POST['promotion_launch_at'] ?? ''));
            if ($launchRaw !== '') {
                try {
                    $dt = new DateTimeImmutable(str_replace('T', ' ', $launchRaw));
                    ia_site_setting_set($pdo, 'promotion_launch_at', $dt->format('Y-m-d H:i:s'));
                } catch (\Throwable) {
                    ia_flash('settings_error', 'Некорректная дата старта сайта.');
                    ia_redirect(ia_admin_url('settings.php'));

                    return;
                }
            }
        }

        ia_promotion_ensure_settings($pdo);
        $msg = 'Настройки VIP/TOP сохранены.';
        if ($on && (!$wasOn || $restartTimer)) {
            $msg = 'Монетизация включена. Отсчёт 6 месяцев начат с текущего момента.';
        }
        ia_flash('settings_ok', $msg);
        ia_redirect(ia_admin_url('settings.php'));
    }

    if ($action === 'save_logo_view') {
        $h = (int) ($_POST['logo_height'] ?? 0);
        if ($h < 24) {
            $h = 24;
        }
        if ($h > 80) {
            $h = 80;
        }
        ia_site_setting_set($pdo, 'logo_height', (string) $h);

        $bgMode = (string) ($_POST['logo_bg_mode'] ?? 'transparent');
        if (!in_array($bgMode, ['transparent', 'white', 'dark', 'custom'], true)) {
            $bgMode = 'transparent';
        }
        ia_site_setting_set($pdo, 'logo_bg_mode', $bgMode);

        $bgColor = trim((string) ($_POST['logo_bg_color'] ?? ''));
        if ($bgColor !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $bgColor)) {
            $bgColor = '';
        }
        ia_site_setting_set($pdo, 'logo_bg_color', $bgColor);

        ia_flash('settings_ok', 'Настройки логотипа сохранены.');
        ia_redirect(ia_admin_url('settings.php'));
    }

    if ($action === 'upload_logo') {
        if (!isset($_FILES['logo']) || !is_array($_FILES['logo']) || (int) ($_FILES['logo']['error'] ?? 0) !== UPLOAD_ERR_OK) {
            ia_flash('settings_error', 'Выберите файл логотипа (PNG, JPEG, WebP, SVG).');
            ia_redirect(ia_admin_url('settings.php'));
        }
        $tmp = (string) $_FILES['logo']['tmp_name'];
        $info = @getimagesize($tmp);
        $mime = $info !== false ? (string) ($info['mime'] ?? '') : '';
        $isSvg = str_ends_with(strtolower((string) ($_FILES['logo']['name'] ?? '')), '.svg');
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        $ext = null;
        if ($isSvg && @file_get_contents($tmp, false, null, 0, 256) !== false) {
            $buf = (string) @file_get_contents($tmp, false, null, 0, 5000);
            if (str_contains($buf, '<svg')) {
                $ext = 'svg';
            }
        } elseif (isset($map[$mime])) {
            $ext = $map[$mime];
        }
        if ($ext === null) {
            ia_flash('settings_error', 'Допустимы PNG, JPEG, WebP, GIF, SVG.');
            ia_redirect(ia_admin_url('settings.php'));
        }
        if ((int) ($_FILES['logo']['size'] ?? 0) > 2 * 1024 * 1024) {
            ia_flash('settings_error', 'Размер файла не более 2 МБ.');
            ia_redirect(ia_admin_url('settings.php'));
        }
        $dir = ia_admin_site_upload_dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $old = ia_site_setting_get($pdo, 'logo_path', '');
        $basename = 'logo_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest = $dir . DIRECTORY_SEPARATOR . $basename;
        if (!@move_uploaded_file($tmp, $dest)) {
            ia_flash('settings_error', 'Не удалось сохранить файл.');
            ia_redirect(ia_admin_url('settings.php'));
        }
        $finalBasename = ia_admin_site_logo_normalize($dest, $ext);
        if ($old !== '' && !str_contains($old, '..')) {
            $full = ia_admin_site_upload_dir() . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $old);
            if (is_file($full)) {
                @unlink($full);
            }
        }
        ia_site_setting_set($pdo, 'logo_path', $finalBasename);
        ia_flash('settings_ok', 'Логотип обновлён.');
        ia_redirect(ia_admin_url('settings.php'));
    }

    if ($action === 'reprocess_logo') {
        $cur = ia_site_setting_get($pdo, 'logo_path', '');
        if ($cur !== '' && !str_contains($cur, '..')) {
            $full = ia_admin_site_upload_dir() . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $cur);
            if (is_file($full)) {
                $extCur = strtolower(pathinfo($cur, PATHINFO_EXTENSION));
                $newName = ia_admin_site_logo_normalize($full, $extCur);
                if ($newName !== $cur) {
                    ia_site_setting_set($pdo, 'logo_path', $newName);
                }
                ia_flash('settings_ok', 'Логотип обработан повторно: фон удалён.');
            } else {
                ia_flash('settings_error', 'Файл логотипа не найден.');
            }
        } else {
            ia_flash('settings_error', 'Логотип не загружен.');
        }
        ia_redirect(ia_admin_url('settings.php'));
    }

    if ($action === 'remove_logo') {
        $old = ia_site_setting_get($pdo, 'logo_path', '');
        if ($old !== '') {
            $full = ia_admin_site_upload_dir() . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $old);
            if (is_file($full)) {
                @unlink($full);
            }
        }
        ia_site_setting_set($pdo, 'logo_path', '');
        ia_flash('settings_ok', 'Логотип удалён.');
        ia_redirect(ia_admin_url('settings.php'));
    }
}
