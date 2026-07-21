<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/env_loader.php';
ia_load_dotenv(dirname(__DIR__) . '/.env');

$pass = ia_env('IA_SUPABASE_DB_PASSWORD') ?: ia_env('IA_DB_PASS') ?: '';
if ($pass === '') {
    fwrite(STDERR, "Set IA_SUPABASE_DB_PASSWORD in .env\n");
    exit(1);
}
$host = ia_env('IA_DB_HOST') ?: 'db.xenelqfppvjyuxnoamme.supabase.co';

echo "Host: $host\n";
echo "IPv4: " . gethostbyname($host) . "\n";

$records = @dns_get_record($host, DNS_A + DNS_AAAA);
if ($records) {
    foreach ($records as $r) {
        echo 'DNS: ' . ($r['type'] ?? '') . ' ' . ($r['ip'] ?? $r['ipv6'] ?? '') . "\n";
    }
}

$dsn = "pgsql:host=$host;port=5432;dbname=postgres;sslmode=prefer";
try {
    new PDO($dsn, 'postgres', $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "PDO: OK\n";
} catch (Throwable $e) {
    echo 'PDO: FAIL ' . $e->getMessage() . "\n";
}

$cs = "host=$host port=5432 dbname=postgres user=postgres password=$pass sslmode=prefer connect_timeout=15";
$c = @pg_connect($cs);
echo $c instanceof PgSql\Connection ? "pg_connect: OK\n" : "pg_connect: FAIL\n";

if ($records) {
    foreach ($records as $r) {
        if (($r['type'] ?? '') === 'AAAA' && !empty($r['ipv6'])) {
            $ip = $r['ipv6'];
            $cs6 = "host=$host hostaddr=$ip port=5432 dbname=postgres user=postgres password=$pass sslmode=prefer connect_timeout=15";
            $c6 = @pg_connect($cs6);
            echo ($c6 instanceof PgSql\Connection ? "pg_connect IPv6 OK ($ip)\n" : "pg_connect IPv6 FAIL ($ip)\n");
        }
        if (($r['type'] ?? '') === 'A' && !empty($r['ip'])) {
            $ip = $r['ip'];
            $cs4 = "host=$host hostaddr=$ip port=5432 dbname=postgres user=postgres password=$pass sslmode=prefer connect_timeout=15";
            $c4 = @pg_connect($cs4);
            echo ($c4 instanceof PgSql\Connection ? "pg_connect IPv4 OK ($ip)\n" : "pg_connect IPv4 FAIL ($ip)\n");
        }
    }
}

$ref = ia_env('IA_SUPABASE_PROJECT_REF') ?: 'xenelqfppvjyuxnoamme';
$poolers = [
    'aws-0-eu-central-1.pooler.supabase.com',
    'aws-0-us-east-1.pooler.supabase.com',
    'aws-0-ap-southeast-1.pooler.supabase.com',
];
foreach ($poolers as $pooler) {
    $url = "postgresql://postgres.$ref:$pass@$pooler:5432/postgres?sslmode=prefer";
    $cp = @pg_connect($url);
    echo ($cp instanceof PgSql\Connection ? "pooler OK: $pooler\n" : "pooler FAIL: $pooler\n");
}
