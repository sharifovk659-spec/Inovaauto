<?php

declare(strict_types=1);

/** Принимаем крупные загрузки — на сервере сжимаем до компактного JPEG (если доступен GD). */
const IA_USER_AVATAR_MAX_BYTES = 52428800; // 50 МБ (лимит PHP upload_max_filesize может быть меньше)
const IA_USER_AVATAR_SIZE = 256;
const IA_USER_AVATAR_MIN_SIDE = 20;
const IA_USER_AVATAR_JPEG_QUALITY = 72;

function ia_user_avatar_dir(): string
{
    $d = IA_ROOT . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'avatars';
    if (!is_dir($d)) {
        @mkdir($d, 0775, true);
    }

    return $d;
}

function ia_user_avatar_gd_available(): bool
{
    return extension_loaded('gd')
        && function_exists('imagecreatetruecolor')
        && function_exists('imagejpeg')
        && function_exists('imagecreatefromjpeg');
}

function ia_user_avatar_upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Файл слишком большой для настроек сервера. Попробуйте другое фото или обратитесь к администратору.',
        UPLOAD_ERR_PARTIAL => 'Загрузка прервалась. Проверьте интернет и попробуйте снова.',
        UPLOAD_ERR_NO_FILE => 'Файл не выбран.',
        UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'Ошибка сервера при загрузке. Попробуйте позже.',
        default => 'Не удалось загрузить фото.',
    };
}

/**
 * @return array{0:int,1:int,2:string}|null [width, height, mime]
 */
function ia_user_avatar_probe(string $tmp): ?array
{
    $info = @getimagesize($tmp);
    if (!is_array($info)) {
        return null;
    }
    $mime = (string) ($info['mime'] ?? '');
    $w = (int) ($info[0] ?? 0);
    $h = (int) ($info[1] ?? 0);
    if ($w < IA_USER_AVATAR_MIN_SIDE || $h < IA_USER_AVATAR_MIN_SIDE) {
        return null;
    }
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mime, $allowed, true)) {
        return null;
    }

    return [$w, $h, $mime];
}

function ia_user_avatar_new_basename(int $userId, string $ext): string
{
    try {
        $rand = bin2hex(random_bytes(6));
    } catch (\Throwable) {
        $rand = (string) mt_rand(100000, 999999);
    }

    return sprintf('u%d_%d_%s.%s', $userId, time(), $rand, $ext);
}

/**
 * Fallback when GD is disabled (typical XAMPP): store validated image as-is.
 */
function ia_user_avatar_save_direct(array $file, int $userId): ?string
{
    $probe = ia_user_avatar_probe((string) $file['tmp_name']);
    if ($probe === null) {
        return null;
    }
    [, , $mime] = $probe;
    $ext = match ($mime) {
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        default => 'jpg',
    };
    $name = ia_user_avatar_new_basename($userId, $ext);
    $dest = ia_user_avatar_dir() . DIRECTORY_SEPARATOR . $name;
    if (!@move_uploaded_file((string) $file['tmp_name'], $dest) || !is_file($dest)) {
        return null;
    }

    return $name;
}

/**
 * Saves an uploaded avatar (square crop, compressed JPEG) when GD is available.
 */
function ia_user_avatar_save_with_gd(array $file, int $userId): ?string
{
    $tmp = (string) $file['tmp_name'];
    $probe = ia_user_avatar_probe($tmp);
    if ($probe === null) {
        return null;
    }
    [$w, $h, $mime] = $probe;

    $src = null;
    if ($mime === 'image/jpeg') {
        $src = @imagecreatefromjpeg($tmp);
    } elseif ($mime === 'image/png' && function_exists('imagecreatefrompng')) {
        $src = @imagecreatefrompng($tmp);
    } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
        $src = @imagecreatefromwebp($tmp);
    } elseif ($mime === 'image/gif' && function_exists('imagecreatefromgif')) {
        $src = @imagecreatefromgif($tmp);
    }
    if (!$src) {
        return null;
    }

    $cropSize = min($w, $h);
    $cropX = (int) (($w - $cropSize) / 2);
    $cropY = (int) (($h - $cropSize) / 2);

    $target = imagecreatetruecolor(IA_USER_AVATAR_SIZE, IA_USER_AVATAR_SIZE);
    if (!$target) {
        imagedestroy($src);

        return null;
    }
    imagealphablending($target, true);
    $white = imagecolorallocate($target, 255, 255, 255);
    imagefilledrectangle($target, 0, 0, IA_USER_AVATAR_SIZE, IA_USER_AVATAR_SIZE, $white);
    imagecopyresampled(
        $target,
        $src,
        0,
        0,
        $cropX,
        $cropY,
        IA_USER_AVATAR_SIZE,
        IA_USER_AVATAR_SIZE,
        $cropSize,
        $cropSize
    );
    imagedestroy($src);

    $name = ia_user_avatar_new_basename($userId, 'jpg');
    $dest = ia_user_avatar_dir() . DIRECTORY_SEPARATOR . $name;
    $ok = @imagejpeg($target, $dest, IA_USER_AVATAR_JPEG_QUALITY);
    imagedestroy($target);

    if (!$ok || !is_file($dest)) {
        return null;
    }

    return $name;
}

/**
 * Saves an uploaded avatar. Returns basename for `platform_users.avatar_path` or null.
 */
function ia_user_avatar_save(array $file, int $userId): ?string
{
    if (!isset($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
        return null;
    }
    $uploadErr = (int) ($file['error'] ?? UPLOAD_ERR_OK);
    if ($uploadErr !== UPLOAD_ERR_OK) {
        return null;
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > IA_USER_AVATAR_MAX_BYTES) {
        return null;
    }

    @ini_set('memory_limit', '256M');

    if (ia_user_avatar_gd_available()) {
        return ia_user_avatar_save_with_gd($file, $userId);
    }

    return ia_user_avatar_save_direct($file, $userId);
}

function ia_user_avatar_save_error(array $file): string
{
    $uploadErr = (int) ($file['error'] ?? UPLOAD_ERR_OK);
    if ($uploadErr !== UPLOAD_ERR_OK) {
        return ia_user_avatar_upload_error_message($uploadErr);
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        return 'Файл пустой или не выбран.';
    }
    if ($size > IA_USER_AVATAR_MAX_BYTES) {
        return 'Фото слишком большое (более 50 МБ).';
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return 'Не удалось загрузить фото.';
    }
    if (ia_user_avatar_probe($tmp) === null) {
        return 'Поддерживаются только фото: JPG, PNG, WEBP или GIF.';
    }

    return 'Не удалось сохранить фото. Проверьте права на папку uploads/avatars.';
}

function ia_user_avatar_delete(?string $stored): void
{
    $s = trim((string) $stored);
    if ($s === '') {
        return;
    }
    $base = basename(str_replace('\\', '/', $s));
    if ($base === '' || !preg_match('/^[A-Za-z0-9_\-.]+$/', $base)) {
        return;
    }
    $path = ia_user_avatar_dir() . DIRECTORY_SEPARATOR . $base;
    if (is_file($path)) {
        @unlink($path);
    }
}
