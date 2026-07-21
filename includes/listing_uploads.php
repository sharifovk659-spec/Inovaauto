<?php

declare(strict_types=1);

const IA_LISTING_VIDEO_MAX_BYTES = 104857600; /* 100 МБ */

/**
 * Сохранение фото объявления в uploads/listings/.
 * Возвращает только имя файла для записи в ad_listings.photo_url или null.
 */
function ia_listing_uploads_dir(): string
{
    $d = IA_ROOT . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'listings';
    if (!is_dir($d)) {
        @mkdir($d, 0775, true);
    }

    return $d;
}

function ia_listing_fix_orientation_jpeg($img, string $tmpPath)
{
    if (!function_exists('exif_read_data')) {
        return $img;
    }
    $exif = @exif_read_data($tmpPath);
    if (!is_array($exif)) {
        return $img;
    }
    $orientation = (int) ($exif['Orientation'] ?? 1);
    if ($orientation === 3) {
        $rot = imagerotate($img, 180, 0);
    } elseif ($orientation === 6) {
        $rot = imagerotate($img, -90, 0);
    } elseif ($orientation === 8) {
        $rot = imagerotate($img, 90, 0);
    } else {
        return $img;
    }
    if (is_object($rot) || is_resource($rot)) {
        imagedestroy($img);

        return $rot;
    }

    return $img;
}

function ia_listing_resize_down($img, int $maxDimension = 2560)
{
    $w = imagesx($img);
    $h = imagesy($img);
    if ($w <= 0 || $h <= 0) {
        return $img;
    }
    $longest = max($w, $h);
    if ($longest <= $maxDimension) {
        return $img;
    }
    $ratio = $maxDimension / $longest;
    $nw = max(1, (int) round($w * $ratio));
    $nh = max(1, (int) round($h * $ratio));
    $dst = imagecreatetruecolor($nw, $nh);
    if (!(is_object($dst) || is_resource($dst))) {
        return $img;
    }
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($img);

    return $dst;
}

function ia_listing_store_image_processed(string $tmpPath, string $mime, string $dest): bool
{
    if (!function_exists('imagecreatefromjpeg')) {
        return @move_uploaded_file($tmpPath, $dest);
    }
    $img = null;
    if ($mime === 'image/jpeg') {
        $img = @imagecreatefromjpeg($tmpPath);
    } elseif ($mime === 'image/png') {
        $img = @imagecreatefrompng($tmpPath);
    } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
        $img = @imagecreatefromwebp($tmpPath);
    } elseif ($mime === 'image/gif') {
        $img = @imagecreatefromgif($tmpPath);
    }
    if (!(is_object($img) || is_resource($img))) {
        return @move_uploaded_file($tmpPath, $dest);
    }
    if ($mime === 'image/jpeg') {
        $img = ia_listing_fix_orientation_jpeg($img, $tmpPath);
    }
    $img = ia_listing_resize_down($img, 2560);
    if (function_exists('imageinterlace')) {
        @imageinterlace($img, true);
    }
    $ok = false;
    if ($mime === 'image/jpeg') {
        $ok = imagejpeg($img, $dest, 92);
    } elseif ($mime === 'image/png') {
        imagesavealpha($img, true);
        $ok = imagepng($img, $dest, 5);
    } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
        $ok = imagewebp($img, $dest, 92);
    } elseif ($mime === 'image/gif') {
        $ok = imagegif($img, $dest);
    }
    imagedestroy($img);

    return $ok;
}

function ia_listing_upload_save(string $fieldName): ?string
{
    if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
        return null;
    }
    if ((int) ($_FILES[$fieldName]['error'] ?? 0) !== UPLOAD_ERR_OK) {
        return null;
    }
    $tmp = (string) $_FILES[$fieldName]['tmp_name'];
    $info = @getimagesize($tmp);
    if ($info === false) {
        return null;
    }
    $mime = (string) ($info['mime'] ?? '');
    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($map[$mime])) {
        return null;
    }
    if ((int) ($_FILES[$fieldName]['size'] ?? 0) > 5 * 1024 * 1024) {
        return null;
    }

    $dir = ia_listing_uploads_dir();
    $basename = bin2hex(random_bytes(16)) . '.' . $map[$mime];
    $dest = $dir . DIRECTORY_SEPARATOR . $basename;
    if (!ia_listing_store_image_processed($tmp, $mime, $dest)) {
        return null;
    }
    require_once IA_ROOT . '/includes/supabase_storage.php';
    $cloudPath = ia_listing_storage_publish_file($dest, $basename, $mime, 'image');
    if ($cloudPath !== null) {
        @unlink($dest);

        return $cloudPath;
    }
    @unlink($dest);

    return null;
}

/**
 * Привести элемент $_FILES к списку записей (одиночный или множественный input).
 *
 * @return list<array{name:string,type:string,tmp_name:string,error:int,size:int}>
 */
function ia_listing_normalize_files_array(?array $files): array
{
    if ($files === null || !isset($files['error'])) {
        return [];
    }
    if (!is_array($files['error'])) {
        return [[
            'name' => (string) ($files['name'] ?? ''),
            'type' => (string) ($files['type'] ?? ''),
            'tmp_name' => (string) ($files['tmp_name'] ?? ''),
            'error' => (int) ($files['error'] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($files['size'] ?? 0),
        ]];
    }
    $out = [];
    foreach ($files['error'] as $i => $err) {
        $out[] = [
            'name' => (string) ($files['name'][$i] ?? ''),
            'type' => (string) ($files['type'][$i] ?? ''),
            'tmp_name' => (string) ($files['tmp_name'][$i] ?? ''),
            'error' => (int) $err,
            'size' => (int) ($files['size'][$i] ?? 0),
        ];
    }

    return $out;
}

/**
 * MIME для загрузки: finfo, тип браузера, расширение, сигнатура (iPhone HEIC → octet-stream).
 */
function ia_listing_detect_upload_mime(string $tmp, string $finfoMime, string $filename = '', string $clientMime = ''): string
{
    $mime = strtolower(trim($finfoMime));
    $client = strtolower(trim($clientMime));
    if ($client !== '' && strpos($client, 'image/') === 0) {
        $mime = $client;
    }
    $imageMap = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/heic', 'image/heif'];
    if (in_array($mime, $imageMap, true)) {
        return $mime;
    }
    $fn = strtolower($filename);
    if (preg_match('/\.heic$/', $fn) || preg_match('/\.heif$/', $fn)) {
        return 'image/heic';
    }
    if (preg_match('/\.jpe?g$/', $fn)) {
        return 'image/jpeg';
    }
    if (preg_match('/\.png$/', $fn)) {
        return 'image/png';
    }
    if (preg_match('/\.webp$/', $fn)) {
        return 'image/webp';
    }
    if ($mime === 'application/octet-stream' && is_readable($tmp)) {
        $head = @file_get_contents($tmp, false, null, 0, 16);
        if (is_string($head) && strlen($head) >= 12 && substr($head, 4, 4) === 'ftyp') {
            $brand = substr($head, 8, 4);
            if (in_array($brand, ['heic', 'heix', 'hevc', 'mif1', 'msf1'], true)) {
                return 'image/heic';
            }
        }
        if (is_string($head) && strncmp($head, "\xFF\xD8\xFF", 3) === 0) {
            return 'image/jpeg';
        }
        if (is_string($head) && strncmp($head, "\x89PNG\r\n\x1a\n", 8) === 0) {
            return 'image/png';
        }
    }

    return $mime;
}

/**
 * Сохранить один загруженный файл (картинка или видео). Возвращает kind + basename или null.
 *
 * @return array{kind:string,path:string}|null
 */
function ia_listing_try_save_upload_entry(array $file, ?int $photoSlotIndex = null, ?string &$rejectMessage = null): ?array
{
    if ((int) ($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
        $err = (int) ($file['error'] ?? 0);
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            $rejectMessage = 'Файл слишком большой (лимит upload_max_filesize / post_max_size на сервере).';
        }

        return null;
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return null;
    }
    $size = (int) ($file['size'] ?? 0);
    $clientMime = (string) ($file['type'] ?? '');
    $origName = (string) ($file['name'] ?? '');
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = ia_listing_detect_upload_mime($tmp, (string) finfo_file($finfo, $tmp), $origName, $clientMime);
            finfo_close($finfo);
        } else {
            $mime = ia_listing_detect_upload_mime($tmp, $clientMime, $origName, $clientMime);
        }
    } else {
        $mime = ia_listing_detect_upload_mime($tmp, $clientMime, $origName, $clientMime);
    }

    $imageMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
    ];
    if (isset($imageMap[$mime])) {
        if ($size > 5 * 1024 * 1024) {
            return null;
        }
        $ext = $imageMap[$mime];
        $isHeic = in_array($mime, ['image/heic', 'image/heif'], true);
        if (!$isHeic) {
            $info = @getimagesize($tmp);
            if ($info === false || (string) ($info['mime'] ?? '') !== $mime) {
                return null;
            }
        }
        if ($photoSlotIndex !== null && !$isHeic) {
            require_once IA_ROOT . '/includes/listing_photo_validation.php';
            if (ia_listing_photo_qa_is_enabled()) {
                $qaErr = ia_listing_photo_qa_validate_tmp($tmp, $photoSlotIndex);
                if ($qaErr !== null) {
                    $rejectMessage = $qaErr;

                    return null;
                }
            }
        }
        $dir = ia_listing_uploads_dir();
        $basename = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $dir . DIRECTORY_SEPARATOR . $basename;
        if ($isHeic) {
            $ok = @move_uploaded_file($tmp, $dest);
        } else {
            $ok = ia_listing_store_image_processed($tmp, $mime, $dest);
        }
        if (!$ok) {
            return null;
        }
        require_once IA_ROOT . '/includes/supabase_storage.php';
        $cloudPath = ia_listing_storage_publish_file($dest, $basename, $mime, 'image');
        if ($cloudPath !== null) {
            @unlink($dest);

            return ['kind' => 'image', 'path' => $cloudPath];
        }
        @unlink($dest);

        return null;
    }

    $videoMap = [
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
    ];
    if (isset($videoMap[$mime])) {
        if ($size > IA_LISTING_VIDEO_MAX_BYTES || $size < 1) {
            return null;
        }
        $dir = ia_listing_uploads_dir();
        $basename = bin2hex(random_bytes(16)) . '.' . $videoMap[$mime];
        $dest = $dir . DIRECTORY_SEPARATOR . $basename;
        if (!@move_uploaded_file($tmp, $dest)) {
            return null;
        }
        require_once IA_ROOT . '/includes/supabase_storage.php';
        $cloudPath = ia_listing_storage_publish_file($dest, $basename, $mime, 'video');
        if ($cloudPath !== null) {
            @unlink($dest);

            return ['kind' => 'video', 'path' => $cloudPath];
        }
        @unlink($dest);

        return null;
    }

    return null;
}

/** Сколько файлов в multipart пришло без ошибки upload. */
function ia_listing_count_ok_upload_files(?array $files): int
{
    $n = 0;
    foreach (ia_listing_normalize_files_array($files) as $f) {
        if ((int) ($f['error'] ?? 0) === UPLOAD_ERR_OK) {
            $n++;
        }
    }

    return $n;
}

/**
 * Собрать успешно сохранённые файлы с поля listing_media[] и при необходимости listing_photo.
 *
 * @return list<array{kind:string,path:string}>
 */
function ia_listing_collect_saved_uploads(int $maxFiles, ?string &$rejectMessage = null): array
{
    require_once IA_ROOT . '/includes/listing_media.php';
    require_once IA_ROOT . '/includes/db_compat.php';
    $pdo = ia_db();
    ia_ensure_listing_media_table($pdo);
    $out = [];
    $placementImageIdx = 0;
    $seenUploads = [];
    foreach (ia_listing_normalize_files_array($_FILES['listing_media'] ?? null) as $uploadIdx => $f) {
        if (count($out) >= $maxFiles) {
            break;
        }
        $slotIdx = null;
        if ((int) ($f['error'] ?? 0) === UPLOAD_ERR_OK) {
            $tmp = (string) ($f['tmp_name'] ?? '');
            if ($tmp !== '' && is_uploaded_file($tmp)) {
                $md5 = @md5_file($tmp);
                $sig = (int) $uploadIdx . ':' . (int) ($f['size'] ?? 0) . ':' . ($md5 !== false ? $md5 : $tmp);
                if (isset($seenUploads[$sig])) {
                    continue;
                }
                $seenUploads[$sig] = true;
                if ($placementImageIdx < IA_LISTING_PHOTO_SLOT_COUNT) {
                    $slotIdx = $placementImageIdx;
                }
            }
        }
        $one = ia_listing_try_save_upload_entry($f, $slotIdx, $rejectMessage);
        if ($one !== null) {
            $out[] = $one;
            if (($one['kind'] ?? '') === 'image') {
                $placementImageIdx++;
            }
            continue;
        }
        if ($rejectMessage !== null && $rejectMessage !== '') {
            ia_listing_rollback_saved_uploads($out);

            return [];
        }
    }
    if (count($out) < $maxFiles) {
        foreach (ia_listing_normalize_files_array($_FILES['listing_photo'] ?? null) as $f) {
            if (count($out) >= $maxFiles) {
                break;
            }
            $one = ia_listing_try_save_upload_entry($f, null, $rejectMessage);
            if ($one !== null) {
                $out[] = $one;
                continue;
            }
            if ($rejectMessage !== null && $rejectMessage !== '') {
                ia_listing_rollback_saved_uploads($out);

                return [];
            }
        }
    }

    return $out;
}

function ia_listing_rollback_saved_uploads(array $saved): void
{
    foreach ($saved as $row) {
        if (isset($row['path'])) {
            ia_listing_delete_stored_file((string) $row['path']);
        }
    }
}

/** Удалить сохранённый файл объявления (локально и/или Supabase Storage). */
function ia_listing_delete_stored_file(?string $stored): void
{
    if ($stored === null || $stored === '') {
        return;
    }
    $s = trim((string) $stored);
    if (preg_match('#\Ahttps?://#i', $s)) {
        return;
    }
    require_once IA_ROOT . '/includes/supabase_storage.php';
    ia_listing_storage_delete($s);
    $name = basename(str_replace('\\', '/', $s));
    if ($name === '' || strpbrk($name, "/\0") !== false) {
        return;
    }
    $path = ia_listing_uploads_dir() . DIRECTORY_SEPARATOR . $name;
    if (is_file($path)) {
        @unlink($path);
    }
}
