<?php

declare(strict_types=1);

const IA_CHAT_IMAGE_MAX_BYTES = 8388608;   // 8 МБ
const IA_CHAT_FILE_MAX_BYTES = 15728640;   // 15 МБ
const IA_CHAT_VOICE_MAX_BYTES = 10485760;  // 10 МБ

function ia_chat_upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Файл слишком большой для сервера.',
        UPLOAD_ERR_PARTIAL => 'Файл загружен не полностью. Попробуйте ещё раз.',
        UPLOAD_ERR_NO_FILE => 'Файл не выбран.',
        UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'Ошибка сервера при загрузке.',
        default => 'Не удалось загрузить файл.',
    };
}

function ia_chat_uploads_dir(int $threadId): string
{
    $d = IA_ROOT . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'chat'
        . DIRECTORY_SEPARATOR . max(0, $threadId);
    if (!is_dir($d)) {
        @mkdir($d, 0775, true);
    }

    return $d;
}

function ia_chat_attachment_public_url(string $stored): string
{
    $s = trim(str_replace('\\', '/', $stored));
    if ($s === '') {
        return '';
    }
    if (str_starts_with($s, 'http://') || str_starts_with($s, 'https://')) {
        return $s;
    }
    if (str_starts_with($s, 'uploads/chat/')) {
        return ia_site_base_url() . '/' . $s;
    }

    return ia_site_base_url() . '/uploads/chat/' . ltrim($s, '/');
}

/**
 * @return array{path:string,name:string,mime:string}|null
 */
function ia_chat_save_upload(array $file, int $threadId, int $userId, string $kind): ?array
{
    if (!isset($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
        return null;
    }
    $uploadErr = (int) ($file['error'] ?? UPLOAD_ERR_OK);
    if ($uploadErr !== UPLOAD_ERR_OK) {
        return null;
    }

    $size = (int) ($file['size'] ?? 0);
    $tmp = (string) $file['tmp_name'];
    $origName = basename((string) ($file['name'] ?? 'file'));
    $origName = preg_replace('/[^\p{L}\p{N}\s._\-()]/u', '_', $origName) ?: 'file';
    if (mb_strlen($origName) > 180) {
        $origName = mb_substr($origName, 0, 180);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmp);

    if ($kind === 'image') {
        if ($size <= 0 || $size > IA_CHAT_IMAGE_MAX_BYTES) {
            return null;
        }
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($allowed[$mime])) {
            return null;
        }
        $ext = $allowed[$mime];
    } elseif ($kind === 'voice') {
        if ($size <= 0 || $size > IA_CHAT_VOICE_MAX_BYTES) {
            return null;
        }
        $allowed = [
            'audio/webm' => 'webm',
            'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/mp4' => 'm4a',
            'audio/x-m4a' => 'm4a',
            'audio/aac' => 'aac',
            'video/webm' => 'webm',
            'application/octet-stream' => 'webm',
        ];
        if (!isset($allowed[$mime])) {
            $name = strtolower((string) ($file['name'] ?? ''));
            if (str_ends_with($name, '.m4a')) {
                $mime = 'audio/mp4';
                $allowed[$mime] = 'm4a';
            } elseif (str_ends_with($name, '.webm')) {
                $mime = 'audio/webm';
                $allowed[$mime] = 'webm';
            } else {
                return null;
            }
        }
        $ext = $allowed[$mime];
        $origName = 'voice.' . $ext;
    } else {
        if ($size <= 0 || $size > IA_CHAT_FILE_MAX_BYTES) {
            return null;
        }
        $ext = pathinfo($origName, PATHINFO_EXTENSION);
        $ext = is_string($ext) && $ext !== '' ? strtolower($ext) : 'bin';
        if (!preg_match('/^[a-z0-9]{1,8}$/', $ext)) {
            $ext = 'bin';
        }
    }

    try {
        $rand = bin2hex(random_bytes(6));
    } catch (\Throwable) {
        $rand = (string) mt_rand(100000, 999999);
    }
    $fname = sprintf('t%d_u%d_%d_%s.%s', $threadId, $userId, time(), $rand, $ext);
    $dest = ia_chat_uploads_dir($threadId) . DIRECTORY_SEPARATOR . $fname;
    if (!move_uploaded_file($tmp, $dest)) {
        return null;
    }

    $relative = $threadId . '/' . $fname;

    return [
        'path' => $relative,
        'name' => $origName,
        'mime' => $mime,
    ];
}

function ia_chat_delete_attachment(?string $stored): void
{
    $s = trim(str_replace('\\', '/', (string) $stored));
    if ($s === '' || str_contains($s, '..')) {
        return;
    }
    $base = basename($s);
    $threadPart = dirname($s);
    if ($threadPart === '.' || $threadPart === '') {
        return;
    }
    $threadId = (int) basename($threadPart);
    if ($threadId <= 0) {
        return;
    }
    $path = ia_chat_uploads_dir($threadId) . DIRECTORY_SEPARATOR . $base;
    if (is_file($path)) {
        @unlink($path);
    }
}
