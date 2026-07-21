<?php

declare(strict_types=1);

/**
 * Supabase Storage — bucket photos (REST, PHP cURL / streams).
 */

/**
 * @return array{url: string, bucket: string, secret_key: string, enabled: bool}
 */
function ia_supabase_storage_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }

    $appCfg = function_exists('ia_config') ? ia_config() : [];
    $supabase = is_array($appCfg['supabase'] ?? null) ? $appCfg['supabase'] : [];

    $url = rtrim((string) ($supabase['url'] ?? ia_env('IA_SUPABASE_URL') ?: ''), '/');
    $secret = trim((string) (
        $supabase['secret_key']
        ?? ia_env('IA_SUPABASE_SECRET_KEY')
        ?? ia_env('IA_SUPABASE_SERVICE_KEY')
        ?? ''
    ));
    $bucket = strtolower(trim((string) (
        $supabase['storage_bucket']
        ?? ia_env('IA_SUPABASE_STORAGE_BUCKET')
        ?? 'photos'
    )));
    if ($bucket === '') {
        $bucket = 'photos';
    }

    $enabledEnv = ia_env('IA_SUPABASE_STORAGE_ENABLED');
    $enabled = $secret !== '' && $url !== '';
    if ($enabledEnv !== null && $enabledEnv !== '') {
        $enabled = filter_var($enabledEnv, FILTER_VALIDATE_BOOLEAN) && $secret !== '' && $url !== '';
    }

    $cfg = [
        'url' => $url,
        'bucket' => $bucket,
        'secret_key' => $secret,
        'enabled' => $enabled,
    ];

    return $cfg;
}

function ia_supabase_storage_enabled(): bool
{
    return ia_supabase_storage_config()['enabled'];
}

function ia_supabase_storage_parse_stored(string $stored): string
{
    $s = trim($stored);
    if ($s === '') {
        return '';
    }
    if (preg_match('#/storage/v1/object/public/[^/]+/(.+)$#i', $s, $m)) {
        $s = rawurldecode($m[1]);
    }
    $s = ltrim(str_replace('\\', '/', $s), '/');
    if (str_starts_with($s, 'uploads/listings/')) {
        $s = substr($s, strlen('uploads/listings/'));
    }

    return $s;
}

/** Имя файла для bucket (корень photos/). */
function ia_supabase_storage_file_key(string $stored): string
{
    $s = ia_supabase_storage_parse_stored($stored);

    return $s === '' ? '' : basename($s);
}

/**
 * Путь объекта в bucket: сначала как в БД (listings/...), иначе корень.
 */
function ia_supabase_storage_object_path(string $storedPath): string
{
    $s = ia_supabase_storage_parse_stored($storedPath);
    if ($s === '') {
        return '';
    }
    if (str_contains($s, '/')) {
        return $s;
    }

    return $s;
}

function ia_supabase_storage_public_url(string $objectPath): string
{
    $cfg = ia_supabase_storage_config();
    $path = ltrim(str_replace('\\', '/', $objectPath), '/');
    if ($path === '' || $cfg['url'] === '') {
        return '';
    }
    $segments = explode('/', $path);
    $encoded = array_map(static fn (string $part): string => rawurlencode(rawurldecode($part)), $segments);

    return $cfg['url'] . '/storage/v1/object/public/' . $cfg['bucket'] . '/' . implode('/', $encoded);
}

function ia_supabase_storage_api_url(string $objectPath): string
{
    $cfg = ia_supabase_storage_config();
    $path = ltrim(str_replace('\\', '/', $objectPath), '/');
    $segments = explode('/', $path);
    $encoded = array_map(static fn (string $part): string => rawurlencode(rawurldecode($part)), $segments);

    return $cfg['url'] . '/storage/v1/object/' . $cfg['bucket'] . '/' . implode('/', $encoded);
}

/**
 * @return array{ok: bool, status: int, body: string, error: string}
 */
function ia_supabase_storage_request(string $method, string $objectPath, ?string $body = null, ?string $contentType = null): array
{
    $cfg = ia_supabase_storage_config();
    if (!$cfg['enabled']) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Supabase Storage disabled'];
    }

    $url = ia_supabase_storage_api_url($objectPath);
    $headers = [
        'Authorization: Bearer ' . $cfg['secret_key'],
        'apikey: ' . $cfg['secret_key'],
    ];
    if ($method === 'POST') {
        $headers[] = 'x-upsert: true';
        if ($contentType !== null && $contentType !== '') {
            $headers[] = 'Content-Type: ' . $contentType;
        }
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'curl_init failed'];
        }
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body ?? '');
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        } else {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);
        $responseBody = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        if ($curlErr !== '') {
            return ['ok' => false, 'status' => $status, 'body' => $responseBody, 'error' => $curlErr];
        }
    } else {
        $headerLines = $headers;
        if ($method === 'POST') {
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => implode("\r\n", $headerLines),
                    'content' => $body ?? '',
                    'timeout' => 120,
                    'ignore_errors' => true,
                ],
            ]);
        } elseif ($method === 'DELETE') {
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'DELETE',
                    'header' => implode("\r\n", $headerLines),
                    'timeout' => 60,
                    'ignore_errors' => true,
                ],
            ]);
        } else {
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => implode("\r\n", $headerLines),
                    'timeout' => 60,
                    'ignore_errors' => true,
                ],
            ]);
        }
        $responseBody = (string) @file_get_contents($url, false, $ctx);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', (string) $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }
    }

    $ok = $status >= 200 && $status < 300;

    return [
        'ok' => $ok,
        'status' => $status,
        'body' => $responseBody,
        'error' => $ok ? '' : ('HTTP ' . $status . ': ' . $responseBody),
    ];
}

function ia_supabase_storage_upload_file(string $localPath, string $objectPath, string $mime): bool
{
    if (!is_file($localPath)) {
        return false;
    }
    $bytes = file_get_contents($localPath);
    if ($bytes === false) {
        return false;
    }

    $res = ia_supabase_storage_request('POST', $objectPath, $bytes, $mime);
    if (!$res['ok']) {
        error_log('supabase_storage upload: ' . $res['error']);

        return false;
    }

    return true;
}

function ia_supabase_storage_delete_object(string $objectPath): bool
{
    $path = ltrim(str_replace('\\', '/', $objectPath), '/');
    if ($path === '') {
        return false;
    }
    $res = ia_supabase_storage_request('DELETE', $path);
    if (!$res['ok'] && $res['status'] !== 404) {
        error_log('supabase_storage delete: ' . $res['error']);

        return false;
    }

    return true;
}

function ia_supabase_storage_object_exists(string $objectPath): bool
{
    $url = ia_supabase_storage_public_url($objectPath);
    if ($url === '') {
        return false;
    }
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $status === 200;
    }
    $headers = @get_headers($url);

    return is_array($headers) && isset($headers[0]) && str_contains((string) $headers[0], '200');
}

function ia_supabase_storage_resolve_photo_url(?string $stored): ?string
{
    if (!ia_supabase_storage_enabled()) {
        return null;
    }
    $s = trim((string) $stored);
    if ($s === '') {
        return null;
    }
    if (preg_match('#\Ahttps?://#i', $s)) {
        return $s;
    }

    $key = ia_supabase_storage_file_key($s);
    if ($key === '') {
        return null;
    }

    return ia_supabase_storage_public_url($key);
}

/**
 * Загрузить в bucket photos (корень) и вернуть имя файла для БД.
 */
function ia_listing_storage_publish_file(string $localPath, string $basename, string $mime, string $kind): ?string
{
    if (!ia_supabase_storage_enabled()) {
        return null;
    }

    $fileKey = basename(str_replace('\\', '/', $basename));
    if ($fileKey === '') {
        return null;
    }
    if (!ia_supabase_storage_upload_file($localPath, $fileKey, $mime)) {
        return null;
    }

    return $fileKey;
}

function ia_listing_storage_delete(?string $stored): void
{
    if ($stored === null || $stored === '') {
        return;
    }
    $s = trim((string) $stored);
    if (preg_match('#\Ahttps?://#i', $s)) {
        $s = ia_supabase_storage_parse_stored($s);
    }
    if ($s === '' || !ia_supabase_storage_enabled()) {
        return;
    }
    ia_supabase_storage_delete_object(ia_supabase_storage_object_path($s));
    $key = basename($s);
    if ($key !== '' && $key !== $s) {
        ia_supabase_storage_delete_object($key);
    }
}

/**
 * Синхронизировать все локальные фото объявлений в bucket photos.
 *
 * @return array{uploaded: int, updated: int, errors: int}
 */
function ia_supabase_storage_sync_all_listing_photos(IaPgConnection|IaPdoConnection $pdo): array
{
    require_once IA_ROOT . '/includes/listing_uploads.php';

    $mimeMap = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'webp' => 'image/webp', 'gif' => 'image/gif', 'mp4' => 'video/mp4', 'webm' => 'video/webm',
    ];

    $paths = [];
    foreach ($pdo->query("SELECT photo_url FROM ad_listings WHERE photo_url IS NOT NULL AND photo_url <> ''")->fetchAll() as $row) {
        $paths[] = (string) ($row['photo_url'] ?? '');
    }
    foreach ($pdo->query('SELECT stored_path FROM ad_listing_media')->fetchAll() as $row) {
        $paths[] = (string) ($row['stored_path'] ?? '');
    }

    $localDir = ia_listing_uploads_dir();
    foreach (glob($localDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        if (is_file($file)) {
            $paths[] = basename($file);
        }
    }

    $keys = [];
    foreach ($paths as $stored) {
        $key = ia_supabase_storage_file_key($stored);
        if ($key !== '') {
            $keys[$key] = true;
        }
    }

    $uploaded = 0;
    $updated = 0;
    $errors = 0;

    foreach (array_keys($keys) as $key) {
        $local = $localDir . DIRECTORY_SEPARATOR . $key;
        if (!is_file($local)) {
            continue;
        }
        $ext = strtolower(pathinfo($key, PATHINFO_EXTENSION));
        $mime = $mimeMap[$ext] ?? 'application/octet-stream';
        if (!ia_supabase_storage_upload_file($local, $key, $mime)) {
            $errors++;
            error_log('sync upload failed: ' . $key);
            continue;
        }
        $uploaded++;

        $st = $pdo->prepare(
            'UPDATE ad_listings SET photo_url = ? WHERE photo_url = ? OR photo_url LIKE ? OR photo_url LIKE ? OR photo_url = ?'
        );
        $st->execute([$key, $key, '%/' . $key, '%/' . $key, 'uploads/listings/' . $key]);
        $updated += $st->rowCount();

        $st2 = $pdo->prepare(
            'UPDATE ad_listing_media SET stored_path = ? WHERE stored_path = ? OR stored_path LIKE ? OR stored_path LIKE ? OR stored_path = ?'
        );
        $st2->execute([$key, $key, '%/' . $key, '%/' . $key, 'uploads/listings/' . $key]);
        $updated += $st2->rowCount();
    }

    return ['uploaded' => $uploaded, 'updated' => $updated, 'errors' => $errors];
}
