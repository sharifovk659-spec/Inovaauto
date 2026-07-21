<?php

declare(strict_types=1);

function ia_cache_dir(): string
{
    $dir = IA_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    return $dir;
}

function ia_cache_file_path(string $key): string
{
    $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $key) ?? 'cache';

    return ia_cache_dir() . DIRECTORY_SEPARATOR . $safe . '.json';
}

/**
 * @template T
 * @param callable(): T $loader
 * @return T
 */
function ia_cache_remember(string $key, int $ttlSeconds, callable $loader): mixed
{
    if ($ttlSeconds <= 0) {
        return $loader();
    }

    $path = ia_cache_file_path($key);
    if (is_file($path) && (time() - (int) filemtime($path)) < $ttlSeconds) {
        $raw = file_get_contents($path);
        if ($raw !== false && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }
    }

    $data = $loader();
    @file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

    return $data;
}

function ia_cache_forget(string $key): void
{
    $path = ia_cache_file_path($key);
    if (is_file($path)) {
        @unlink($path);
    }
}

function ia_cache_forget_prefix(string $prefix): void
{
    $dir = ia_cache_dir();
    $glob = glob($dir . DIRECTORY_SEPARATOR . preg_replace('/[^a-zA-Z0-9._-]+/', '_', $prefix) . '*.json');
    if ($glob === false) {
        return;
    }
    foreach ($glob as $file) {
        @unlink($file);
    }
}

function ia_cache_clear_all(): int
{
    $dir = ia_cache_dir();
    $glob = glob($dir . DIRECTORY_SEPARATOR . '*.json');
    if ($glob === false) {
        return 0;
    }
    $n = 0;
    foreach ($glob as $file) {
        if (@unlink($file)) {
            $n++;
        }
    }

    return $n;
}

function ia_cache_ttl(string $name, int $default): int
{
    $raw = ia_env('IA_CACHE_TTL_' . strtoupper($name));
    if ($raw === null || $raw === '') {
        return $default;
    }

    return max(0, (int) $raw);
}
