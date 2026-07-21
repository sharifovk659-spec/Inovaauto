<?php

declare(strict_types=1);

/**
 * Боркунии .env аз решаи лоиҳа (агар мавҷуд бошад).
 * Муҳим: танҳо агар getenv(KEY) аллакай муқаррар нашуда бошад.
 */
function ia_project_root(): string
{
    if (defined('IA_ROOT')) {
        return (string) IA_ROOT;
    }

    return dirname(__DIR__);
}

/**
 * Муҳим: .env ҳамеша аз файл мехонад (на танҳо агар getenv холӣ бошад).
 */
function ia_load_dotenv(?string $path = null): void
{
    if ($path === null) {
        $path = ia_project_root() . DIRECTORY_SEPARATOR . '.env';
    }
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        if ($key === '') {
            continue;
        }
        $value = trim($value);
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

function ia_env(string $key, ?string $default = null): ?string
{
    if (array_key_exists($key, $_ENV) && $_ENV[$key] !== '') {
        return (string) $_ENV[$key];
    }
    $v = getenv($key);
    if ($v === false || $v === '') {
        return $default;
    }

    return (string) $v;
}

/**
 * @return array{host: string, port: string, name: string, user: string, pass: string, sslmode: string}|null
 */
function ia_parse_postgres_url(string $databaseUrl): ?array
{
    $databaseUrl = trim($databaseUrl);
    if ($databaseUrl === '') {
        return null;
    }
    if (!preg_match('#^postgres(ql)?://#i', $databaseUrl)) {
        return null;
    }

    $parts = parse_url($databaseUrl);
    if ($parts === false || empty($parts['host'])) {
        return null;
    }

    $user = isset($parts['user']) ? rawurldecode((string) $parts['user']) : 'postgres';
    $pass = isset($parts['pass']) ? rawurldecode((string) $parts['pass']) : '';
    $name = isset($parts['path']) ? ltrim((string) $parts['path'], '/') : 'postgres';
    if ($name === '') {
        $name = 'postgres';
    }
    $port = isset($parts['port']) ? (string) $parts['port'] : '5432';
    $sslmode = 'require';
    if (!empty($parts['query'])) {
        parse_str((string) $parts['query'], $query);
        if (!empty($query['sslmode'])) {
            $sslmode = (string) $query['sslmode'];
        }
    }

    return [
        'host' => (string) $parts['host'],
        'port' => $port,
        'name' => $name,
        'user' => $user,
        'pass' => $pass,
        'sslmode' => $sslmode,
    ];
}
