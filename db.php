<?php

declare(strict_types=1);

/**
 * Supabase PostgreSQL — pg_connect() + PDO fallback (XAMPP/Windows SSL).
 */
if (!defined('IA_ROOT')) {
    define('IA_ROOT', __DIR__);
}

require_once IA_ROOT . '/includes/env_loader.php';
ia_load_dotenv(IA_ROOT . DIRECTORY_SEPARATOR . '.env');

/** @var \PgSql\Connection|null $conn */
$conn = null;

if (!function_exists('ia_pg_libpq_quote')) {
    function ia_pg_libpq_quote(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.-]+$/', $value)) {
            return $value;
        }

        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
    }
}

if (!function_exists('ia_pg_db_password')) {
    function ia_pg_db_password(): string
    {
        $password = ia_env('IA_SUPABASE_DB_PASSWORD') ?: ia_env('IA_DB_PASS') ?: '';
        if ($password !== '') {
            return $password;
        }
        $parsed = ia_parse_postgres_url((string) (ia_env('IA_DATABASE_URL') ?: ia_env('DATABASE_URL') ?: ''));

        return $parsed['pass'] ?? '';
    }
}

if (!function_exists('ia_pg_pool_config')) {
    /**
     * @return array{host: string, user: string, dbname: string, password: string, ports: list<int>}
     */
    function ia_pg_pool_config(): array
    {
        $ref = ia_env('IA_SUPABASE_PROJECT_REF') ?: 'xenelqfppvjyuxnoamme';

        $poolerPort = (int) (ia_env('IA_DB_POOLER_PORT') ?: ia_env('IA_DB_PORT') ?: 0);

        return [
            'host' => ia_env('IA_DB_POOLER_HOST') ?: ia_env('IA_DB_HOST') ?: 'aws-1-ap-southeast-1.pooler.supabase.com',
            'user' => ia_env('IA_DB_POOLER_USER') ?: ia_env('IA_DB_USER') ?: ('postgres.' . $ref),
            'dbname' => ia_env('IA_DB_NAME') ?: 'postgres',
            'password' => ia_pg_db_password(),
            'ports' => $poolerPort > 0 ? [$poolerPort] : [6543, 5432],
        ];
    }
}

if (!function_exists('ia_pg_connect_uri')) {
    function ia_pg_connect_uri(string $host, string $user, string $dbname, string $password, int $port, string $sslmode = 'prefer'): string
    {
        $timeout = max(3, min(15, (int) (ia_env('IA_DB_CONNECT_TIMEOUT') ?: 8)));

        return sprintf(
            'postgresql://%s:%s@%s:%d/%s?sslmode=%s&connect_timeout=%d',
            rawurlencode($user),
            rawurlencode($password),
            $host,
            $port,
            rawurlencode($dbname),
            rawurlencode($sslmode),
            $timeout
        );
    }
}

if (!function_exists('ia_pg_connect_attempts')) {
    /**
     * @return list<string>
     */
    function ia_pg_connect_attempts(): array
    {
        $cfg = ia_pg_pool_config();
        $attempts = [];
        $timeout = max(3, min(15, (int) (ia_env('IA_DB_CONNECT_TIMEOUT') ?: 8)));
        $sslmode = ia_env('IA_DB_SSLMODE') ?: 'prefer';

        foreach ([ia_env('DATABASE_URL'), ia_env('IA_DATABASE_URL')] as $url) {
            if (is_string($url) && $url !== '') {
                $attempts[] = $url;
            }
        }

        if ($attempts !== []) {
            $port = (int) ($cfg['ports'][0] ?? 6543);
            $attempts[] = sprintf(
                'host=%s port=%d dbname=%s user=%s password=%s sslmode=%s connect_timeout=%d',
                ia_pg_libpq_quote($cfg['host']),
                $port,
                ia_pg_libpq_quote($cfg['dbname']),
                ia_pg_libpq_quote($cfg['user']),
                ia_pg_libpq_quote($cfg['password']),
                ia_pg_libpq_quote($sslmode),
                $timeout
            );

            return array_values(array_unique($attempts));
        }

        foreach ($cfg['ports'] as $port) {
            foreach (array_values(array_unique([$sslmode, 'require'])) as $mode) {
                $attempts[] = ia_pg_connect_uri($cfg['host'], $cfg['user'], $cfg['dbname'], $cfg['password'], $port, $mode);
                $attempts[] = sprintf(
                    'host=%s port=%d dbname=%s user=%s password=%s sslmode=%s connect_timeout=%d',
                    ia_pg_libpq_quote($cfg['host']),
                    $port,
                    ia_pg_libpq_quote($cfg['dbname']),
                    ia_pg_libpq_quote($cfg['user']),
                    ia_pg_libpq_quote($cfg['password']),
                    ia_pg_libpq_quote($mode),
                    $timeout
                );
            }
        }

        return array_values(array_unique($attempts));
    }
}

if (!function_exists('ia_mysql_connection_fail')) {
    function ia_mysql_connection_fail(string $message): never
    {
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            http_response_code(503);
            header('Content-Type: text/html; charset=UTF-8');
            $detail = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            echo '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>База данных</title></head>';
            echo '<body style="font-family:system-ui,sans-serif;padding:2rem;max-width:44rem;line-height:1.55">';
            echo '<h1>Не удалось подключиться к MySQL</h1>';
            echo '<p style="color:#64748b;font-size:.875rem;white-space:pre-wrap">' . $detail . '</p>';
            echo '<p><strong>Hostinger:</strong> hPanel → Databases → MySQL → создайте БД и пользователя.</p>';
            echo '<p>В <code>.env</code>: <code>IA_DB_DRIVER=mysql</code>, <code>IA_DB_HOST=localhost</code>, имя БД, user, pass.</p>';
            echo '</body></html>';
            exit(1);
        }

        throw new RuntimeException($message);
    }
}

if (!function_exists('ia_pdo_connect_mysql')) {
    function ia_pdo_connect_mysql(): PDO
    {
        if (!extension_loaded('pdo_mysql')) {
            ia_mysql_connection_fail('pdo_mysql не включён на сервере.');
        }

        $c = ia_config()['db'];
        $host = (string) ($c['host'] ?? '127.0.0.1');
        $port = (string) ($c['port'] ?? '3306');
        $name = (string) ($c['name'] ?? '');
        $user = (string) ($c['user'] ?? '');
        $pass = (string) ($c['pass'] ?? '');
        $charset = (string) ($c['charset'] ?? 'utf8mb4');

        if ($name === '' || $user === '') {
            ia_mysql_connection_fail('IA_DB_NAME и IA_DB_USER в .env пусты.');
        }

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $name, $charset);

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->query('SELECT 1');

            return $pdo;
        } catch (Throwable $e) {
            ia_mysql_connection_fail($e->getMessage());
        }
    }
}

if (!function_exists('ia_pg_connection_fail')) {
    function ia_pg_connection_fail(string $message): never
    {
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            http_response_code(503);
            header('Content-Type: text/html; charset=UTF-8');
            $detail = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            echo '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>База данных</title></head>';
            echo '<body style="font-family:system-ui,sans-serif;padding:2rem;max-width:44rem;line-height:1.55">';
            echo '<h1>Не удалось подключиться к базе данных</h1>';
            echo '<p style="color:#64748b;font-size:.875rem;white-space:pre-wrap">' . $detail . '</p>';
            echo '<p><strong>Hostinger (shared):</strong> PostgreSQL недоступен. Используйте <code>IA_DB_DRIVER=mysql</code> — см. <code>.env.hostinger.example</code>.</p>';
            echo '</body></html>';
            exit(1);
        }

        throw new RuntimeException($message);
    }
}

if (!function_exists('ia_pdo_connect_supabase')) {
    function ia_pdo_connect_supabase(): PDO
    {
        if (!extension_loaded('pdo_pgsql')) {
            ia_pg_connection_fail('pdo_pgsql не включён. Добавьте extension=pdo_pgsql в php.ini и перезапустите Apache.');
        }

        $cfg = ia_pg_pool_config();
        if ($cfg['password'] === '') {
            ia_pg_connection_fail('Парол холӣ аст. IA_SUPABASE_DB_PASSWORD дар .env гузоред.');
        }

        $lastError = '';
        foreach ($cfg['ports'] as $port) {
            foreach (['prefer', 'require'] as $sslmode) {
                $dsn = sprintf(
                    'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
                    $cfg['host'],
                    $port,
                    $cfg['dbname'],
                    $sslmode
                );
                try {
                    $pdo = new PDO($dsn, $cfg['user'], $cfg['password'], [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_TIMEOUT => max(3, min(15, (int) (ia_env('IA_DB_CONNECT_TIMEOUT') ?: 8))),
                    ]);
                    $pdo->query('SELECT 1');

                    return $pdo;
                } catch (Throwable $e) {
                    $lastError = $e->getMessage();
                }
            }
        }

        if (str_contains(strtolower($lastError), 'password authentication failed')) {
            ia_pg_connection_fail(
                'Парол нодуруст. Supabase → Settings → Database → Reset database password. '
                . 'Сипас .env: IA_SUPABASE_DB_PASSWORD=... '
                . 'User: ' . $cfg['user']
            );
        }
        if (str_contains(strtolower($lastError), 'circuitbreaker')) {
            ia_pg_connection_fail(
                'Зиёд хато — Supabase муваққатан блок кард. 10–15 дақиқа интизор шавед, паролро reset кунед.'
            );
        }

        ia_pg_connection_fail('PDO: ' . $lastError);
    }
}

if (!function_exists('ia_pg_connect')) {
    /**
     * @return \PgSql\Connection
     */
    function ia_pg_connect(): \PgSql\Connection
    {
        global $conn;

        if ($conn instanceof \PgSql\Connection) {
            $status = @pg_connection_status($conn);
            if ($status === PGSQL_CONNECTION_OK) {
                return $conn;
            }
            $conn = null;
        }

        if (!extension_loaded('pgsql')) {
            throw new RuntimeException('extension pgsql not loaded');
        }

        @putenv('PGSSLMODE=prefer');

        foreach (ia_pg_connect_attempts() as $connectString) {
            $attempt = @pg_connect($connectString);
            if ($attempt instanceof \PgSql\Connection) {
                $conn = $attempt;
                @pg_set_client_encoding($conn, 'UTF8');
                $check = @pg_query($conn, 'SELECT 1');
                if ($check !== false) {
                    pg_free_result($check);

                    return $conn;
                }
                $conn = null;
            }
        }

        throw new RuntimeException('pg_connect() failed on all attempts');
    }
}

require_once IA_ROOT . '/includes/pg_helpers.php';
