<?php

declare(strict_types=1);

require_once IA_ROOT . '/includes/ia_cache.php';

/**
 * @return array<string, string>
 */
function ia_site_settings_map(IaPgConnection|IaPdoConnection $pdo): array
{
    try {
        $rows = $pdo->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll();
    } catch (\PDOException) {
        return [];
    }
    $out = [];
    foreach ($rows as $row) {
        $out[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
    }

    return $out;
}

/**
 * @return array<string, string>
 */
function ia_site_settings_cached(IaPgConnection|IaPdoConnection $pdo, bool $reload = false): array
{
    static $byConn = [];
    $id = spl_object_id($pdo);
    if ($reload) {
        ia_cache_forget('site_settings_map');
        unset($byConn[$id]);
    }
    if (isset($byConn[$id])) {
        return $byConn[$id];
    }

    $ttl = ia_cache_ttl('site_settings', 300);
    $byConn[$id] = ia_cache_remember('site_settings_map', $ttl, static fn (): array => ia_site_settings_map($pdo));

    return $byConn[$id];
}

function ia_site_setting_get(IaPgConnection|IaPdoConnection $pdo, string $key, string $default = ''): string
{
    $map = ia_site_settings_cached($pdo);

    return array_key_exists($key, $map) ? $map[$key] : $default;
}

function ia_site_setting_set(IaPgConnection|IaPdoConnection $pdo, string $key, string $value): void
{
    if (ia_db_is_pgsql($pdo)) {
        $st = $pdo->prepare(
            'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
             ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value'
        );
    } else {
        $st = $pdo->prepare(
            'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
    }
    $st->execute([$key, $value]);
    ia_site_settings_cached($pdo, true);
    ia_cache_forget('pub_brands_ordered');
    ia_cache_forget('pub_models_grouped');
    ia_cache_forget_prefix('pub_popular_brands');
    ia_cache_forget('pub_banners_home');
}

function ia_site_logo_public_url(?string $relativePath): string
{
    if ($relativePath === null || $relativePath === '') {
        return '';
    }

    return ia_site_base_url() . '/uploads/site/' . ltrim(str_replace('\\', '/', $relativePath), '/');
}
