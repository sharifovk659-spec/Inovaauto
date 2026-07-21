<?php

declare(strict_types=1);

require_once IA_ROOT . '/includes/pg_adapter.php';
require_once IA_ROOT . '/includes/db_compat.php';

function ia_db_is_supabase_rest_driver(string $driver): bool
{
    return in_array(strtolower($driver), ['supabase', 'supabase_rest', 'rest'], true);
}

/**
 * @return array<string, mixed>
 */
function ia_config(): array
{
    static $cfg;
    if ($cfg === null) {
        $cfg = require IA_ROOT . '/config/config.php';
    }

    return $cfg;
}

function ia_db_pg_extensions_available(): bool
{
    return extension_loaded('pgsql') || extension_loaded('pdo_pgsql');
}

function ia_db_is_pgsql_driver(string $driver): bool
{
    return in_array(strtolower($driver), ['pgsql', 'postgres', 'postgresql'], true);
}

function ia_db_bootstrap_mysql(IaPdoConnection $db): void
{
    $c = ia_config()['db'];
    if (!empty($c['auto_ensure_schema'])) {
        require_once IA_ROOT . '/includes/schema_ensure.php';
        ia_ensure_auth_schema($db);
    }
    if (!empty($c['auto_ensure_platform_schema'])) {
        require_once IA_ROOT . '/includes/schema_platform.php';
        ia_ensure_platform_schema($db);
    }
    if (!empty($c['auto_ensure_schema']) || !empty($c['auto_ensure_platform_schema'])) {
        require_once IA_ROOT . '/includes/schema_settings.php';
        ia_ensure_site_settings_schema($db);
        ia_seed_default_site_settings($db);
    }
    if (!empty($c['seed_default_admin_if_empty'])) {
        require_once IA_ROOT . '/includes/schema_ensure.php';
        ia_seed_default_admin_if_empty($db);
    }
    if (!empty($c['seed_platform_demo_if_empty'])) {
        require_once IA_ROOT . '/includes/schema_platform.php';
        ia_seed_platform_demo_if_empty($db);
    }
}

function ia_db_bootstrap_pgsql(IaPgConnection|IaPdoConnection $db): void
{
    $c = ia_config()['db'];
    if (!empty($c['auto_ensure_schema']) || !empty($c['auto_ensure_platform_schema'])) {
        require_once IA_ROOT . '/includes/schema_pgsql.php';
        ia_ensure_pgsql_schema($db);
        require_once IA_ROOT . '/includes/schema_settings.php';
        ia_seed_default_site_settings($db);
    }
    if (!empty($c['seed_default_admin_if_empty'])) {
        require_once IA_ROOT . '/includes/schema_ensure.php';
        ia_seed_default_admin_if_empty($db);
    }
    if (!empty($c['seed_platform_demo_if_empty'])) {
        require_once IA_ROOT . '/includes/schema_platform.php';
        ia_seed_platform_demo_if_empty($db);
    }
}

/**
 * @return IaPgConnection|IaPdoConnection|IaSupabaseRestConnection
 */
function ia_db(): IaPgConnection|IaPdoConnection|IaSupabaseRestConnection
{
    static $db = null;
    if ($db instanceof IaPgConnection || $db instanceof IaPdoConnection || $db instanceof IaSupabaseRestConnection) {
        return $db;
    }

    $c = ia_config()['db'];
    $driver = strtolower((string) ($c['driver'] ?? 'pgsql'));

    if (ia_db_is_supabase_rest_driver($driver)) {
        require_once IA_ROOT . '/includes/supabase_rest_adapter.php';
        $db = ia_supabase_rest_connect();

        return $db;
    }

    if ($driver === 'mysql') {
        require_once IA_ROOT . '/db.php';
        $db = new IaPdoConnection(ia_pdo_connect_mysql());
        ia_db_bootstrap_mysql($db);

        return $db;
    }

    if (!ia_db_is_pgsql_driver($driver)) {
        throw new RuntimeException('IA_DB_DRIVER must be pgsql, mysql, or supabase.');
    }

    if (!ia_db_pg_extensions_available()) {
        if ($driver === 'mysql' || (extension_loaded('pdo_mysql') && trim((string) ($c['name'] ?? '')) !== '' && trim((string) ($c['user'] ?? '')) !== '')) {
            require_once IA_ROOT . '/db.php';
            $db = new IaPdoConnection(ia_pdo_connect_mysql());
            ia_db_bootstrap_mysql($db);

            return $db;
        }
        if (ia_supabase_rest_configured()) {
            require_once IA_ROOT . '/includes/supabase_rest.php';
            require_once IA_ROOT . '/includes/supabase_rest_adapter.php';
            $db = ia_supabase_rest_connect();

            return $db;
        }
        require_once IA_ROOT . '/db.php';
        ia_pg_connection_fail(
            "PHP PostgreSQL extensions (pgsql / pdo_pgsql) are not available on this server.\n\n"
            . "Hostinger: set IA_DB_DRIVER=mysql and MySQL credentials from hPanel → Databases.\n"
            . "See .env.hostinger.example"
        );
    }

    require_once IA_ROOT . '/db.php';

    try {
        if (extension_loaded('pgsql')) {
            $native = ia_pg_connect();
            global $conn;
            $conn = $native;
            $db = new IaPgConnection($native);
        } else {
            throw new RuntimeException('pgsql not loaded');
        }
    } catch (Throwable) {
        $db = new IaPdoConnection(ia_pdo_connect_supabase());
    }

    ia_db_bootstrap_pgsql($db);

    return $db;
}

/**
 * @return \PgSql\Connection
 */
function ia_pg(): \PgSql\Connection
{
    $db = ia_db();
    if ($db instanceof IaPgConnection) {
        return $db->getNative();
    }

    throw new RuntimeException('PDO fallback — pg native connection недоступен');
}

/**
 * @return array{driver: string, host: string, port: string, name: string, user: string, is_supabase: bool, label: string}
 */
function ia_db_connection_info(): array
{
    $c = ia_config()['db'];
    $host = (string) ($c['host'] ?? '');
    $driver = strtolower((string) ($c['driver'] ?? 'pgsql'));
    $isSupabase = ia_db_is_supabase_rest_driver($driver)
        || (ia_db_is_pgsql_driver($driver)
            && (str_contains($host, 'supabase.co')
                || str_contains($host, 'pooler.supabase.com')
                || (string) (ia_config()['supabase']['project_ref'] ?? '') !== ''));

    return [
        'driver' => (string) ($c['driver'] ?? 'pgsql'),
        'host' => $host,
        'port' => (string) ($c['port'] ?? ''),
        'name' => (string) ($c['name'] ?? ''),
        'user' => (string) ($c['user'] ?? ''),
        'is_supabase' => $isSupabase,
        'label' => ia_db_is_supabase_rest_driver($driver)
            ? 'Supabase REST (HTTP)'
            : ($driver === 'mysql'
                ? 'MySQL / MariaDB'
                : ($isSupabase ? 'Supabase (PostgreSQL)' : 'PostgreSQL')),
    ];
}
