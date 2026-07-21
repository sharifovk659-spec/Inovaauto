<?php

declare(strict_types=1);

/**
 * Run pending SQL migrations once (production-safe).
 *
 * Usage: php tools/migrate.php [--dry-run]
 *
 * Blocks: DROP DATABASE/TABLE, TRUNCATE, DELETE/UPDATE without WHERE.
 * Requires backup before running on production (deploy.sh does this).
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

define('IA_ROOT', dirname(__DIR__));
require_once IA_ROOT . '/includes/public_bootstrap.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);

/** @var list<string> */
function ia_migration_blocked_patterns(): array
{
    return [
        '/\bDROP\s+DATABASE\b/i',
        '/\bDROP\s+TABLE\b/i',
        '/\bTRUNCATE\b/i',
        '/\bDELETE\s+FROM\s+[`\w.]+\s*(;|$)/i',
        '/\bUPDATE\s+[`\w.]+\s+SET\s+/i', // allowed only with WHERE — checked separately
    ];
}

function ia_migration_sql_is_safe(string $sql, string $filename): ?string
{
    $normalized = preg_replace('/--[^\n]*\n/', "\n", $sql) ?? $sql;
    $normalized = preg_replace('/\/\*.*?\*\//s', '', $normalized) ?? $normalized;

    foreach (ia_migration_blocked_patterns() as $pattern) {
        if (preg_match($pattern, $normalized)) {
            if (stripos($pattern, 'UPDATE') !== false) {
                if (!preg_match('/\bWHERE\b/i', $normalized)) {
                    return "UPDATE without WHERE in {$filename}";
                }
                continue;
            }
            if (stripos($pattern, 'DELETE') !== false) {
                return "DELETE without WHERE in {$filename}";
            }

            return "Blocked statement in {$filename} (pattern: {$pattern})";
        }
    }

    return null;
}

function ia_migration_ensure_tracking_table(IaPdoConnection|IaPgConnection $db): void
{
    $driver = strtolower((string) (ia_config()['db']['driver'] ?? 'mysql'));
    if ($driver !== 'mysql') {
        throw new RuntimeException('schema_migrations currently supports MySQL production only.');
    }

    $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS schema_migrations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration VARCHAR(255) NOT NULL,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_schema_migrations_migration (migration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
    $db->exec($sql);
}

/** @return array<string, true> */
function ia_migration_applied(IaPdoConnection|IaPgConnection $db): array
{
    ia_migration_ensure_tracking_table($db);
    $rows = $db->query('SELECT migration FROM schema_migrations')->fetchAll();
    $map = [];
    foreach ($rows as $row) {
        $name = (string) ($row['migration'] ?? '');
        if ($name !== '') {
            $map[$name] = true;
        }
    }

    return $map;
}

function ia_migration_record(IaPdoConnection|IaPgConnection $db, string $name): void
{
    $stmt = $db->prepare('INSERT INTO schema_migrations (migration) VALUES (?)');
    $stmt->execute([$name]);
}

$migrationsDir = IA_ROOT . '/database/migrations';
if (!is_dir($migrationsDir)) {
    fwrite(STDERR, "No migrations directory: {$migrationsDir}\n");
    exit(1);
}

$files = glob($migrationsDir . '/*.sql') ?: [];
sort($files, SORT_STRING);

if ($files === []) {
    echo "No migration files.\n";
    exit(0);
}

$pdo = ia_db();
$applied = ia_migration_applied($pdo);
$pending = 0;

foreach ($files as $path) {
    $name = basename($path);
    if (isset($applied[$name])) {
        echo "skip: {$name} (already applied)\n";
        continue;
    }

    $sql = file_get_contents($path);
    if ($sql === false || trim($sql) === '') {
        fwrite(STDERR, "Empty migration: {$name}\n");
        exit(1);
    }

    $unsafe = ia_migration_sql_is_safe($sql, $name);
    if ($unsafe !== null) {
        fwrite(STDERR, "Unsafe migration blocked: {$unsafe}\n");
        exit(1);
    }

    echo ($dryRun ? 'would apply' : 'apply') . ": {$name}\n";
    $pending++;

    if ($dryRun) {
        continue;
    }

    try {
        $pdo->beginTransaction();
        $pdo->exec($sql);
        ia_migration_record($pdo, $name);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fwrite(STDERR, "Migration failed ({$name}): " . $e->getMessage() . "\n");
        exit(1);
    }
}

echo $pending === 0 ? "Nothing to migrate.\n" : "Done. Applied {$pending} migration(s).\n";
