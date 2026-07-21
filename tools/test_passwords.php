<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/env_loader.php';
ia_load_dotenv(dirname(__DIR__) . '/.env');

$host = 'aws-1-ap-southeast-1.pooler.supabase.com';
$user = 'postgres.xenelqfppvjyuxnoamme';
$pass = ia_env('IA_SUPABASE_DB_PASSWORD') ?: ia_env('IA_DB_PASS') ?: '';
if ($pass === '') {
    fwrite(STDERR, "Set IA_SUPABASE_DB_PASSWORD or IA_DB_PASS in .env\n");
    exit(1);
}

foreach ([''] as $_once) {
    echo "=== Testing password from .env ===\n";
    foreach ([6543, 5432] as $port) {
        foreach (['prefer', 'require'] as $ssl) {
            $kv = "host=$host port=$port dbname=postgres user=$user password=" . ia_pg_libpq_quote($pass) . " sslmode=$ssl connect_timeout=15";
            $c = @pg_connect($kv);
            echo "pg $port $ssl: " . ($c ? 'OK' : 'FAIL') . "\n";
            if ($c) {
                $r = pg_query($c, 'SELECT 1');
                echo 'query: ' . ($r ? 'OK' : pg_last_error($c)) . "\n";
                exit(0);
            }
            try {
                $dsn = "pgsql:host=$host;port=$port;dbname=postgres;sslmode=$ssl";
                $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                echo "PDO $port $ssl: OK\n";
                exit(0);
            } catch (Throwable $e) {
                echo "PDO $port $ssl: " . $e->getMessage() . "\n";
            }
        }
    }
    echo "\n";
}

function ia_pg_libpq_quote(string $value): string {
    if ($value === '' || preg_match('/^[A-Za-z0-9_.-]+$/', $value)) return $value;
    return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $value) . "'";
}
