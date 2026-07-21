<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/env_loader.php';
ia_load_dotenv(dirname(__DIR__) . '/.env');

$host = 'aws-1-ap-southeast-1.pooler.supabase.com';
$user = 'postgres.xenelqfppvjyuxnoamme';
$pass = ia_env('IA_SUPABASE_DB_PASSWORD') ?: ia_env('IA_DB_PASS') ?: '';
if ($pass === '') {
    fwrite(STDERR, "Set IA_SUPABASE_DB_PASSWORD in .env\n");
    exit(1);
}

foreach ([6543, 5432] as $port) {
    echo "=== Port $port ===\n";
    $uri = "postgresql://$user:$pass@$host:$port/postgres?sslmode=require";
    $c = @pg_connect($uri);
    echo 'pg URI: ' . ($c ? 'OK' : 'FAIL') . "\n";

    $kv = "host=$host port=$port dbname=postgres user=$user password=$pass sslmode=require connect_timeout=15";
    $c2 = @pg_connect($kv);
    echo 'pg kv: ' . ($c2 ? 'OK' : 'FAIL') . "\n";

    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=postgres;sslmode=require";
        new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo "PDO: OK\n";
    } catch (Throwable $e) {
        echo 'PDO: ' . $e->getMessage() . "\n";
    }

    foreach (['prefer', 'require', 'allow', 'disable'] as $ssl) {
        $dsn = "pgsql:host=$host;port=$port;dbname=postgres;sslmode=$ssl";
        try {
            new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            echo "PDO sslmode=$ssl: OK\n";
            break;
        } catch (Throwable $e) {
            echo "PDO sslmode=$ssl: " . $e->getMessage() . "\n";
        }
    }
    echo "\n";
}

// wrong password test
echo "=== Wrong password test ===\n";
try {
    new PDO(
        "pgsql:host=$host;port=6543;dbname=postgres;sslmode=require",
        $user,
        'wrongpassword',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Throwable $e) {
    echo $e->getMessage() . "\n";
}
