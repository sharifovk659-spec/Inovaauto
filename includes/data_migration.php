<?php

declare(strict_types=1);

require_once IA_ROOT . '/includes/supabase_rest.php';
require_once IA_ROOT . '/includes/db_compat.php';

/**
 * @return list<string>
 */
function ia_migrate_table_order(): array
{
    return [
        'admin_users',
        'admin_login_attempts',
        'admin_remember_tokens',
        'admin_password_resets',
        'site_settings',
        'platform_users',
        'car_brands',
        'car_models',
        'vehicle_categories',
        'billing_tariffs',
        'ad_listings',
        'ad_listing_media',
        'site_payments',
        'billing_transactions',
        'chat_threads',
        'chat_messages',
        'chat_complaints',
        'site_banners',
        'user_favorites',
        'user_compare',
        'notification_campaigns',
        'notification_deliveries',
        'platform_notifications',
        'platform_password_resets',
        'contact_requests',
        'listing_complaints',
    ];
}

function ia_migrate_normalize_value(mixed $value): mixed
{
    if ($value === true) {
        return 1;
    }
    if ($value === false) {
        return 0;
    }
    if (is_array($value) || is_object($value)) {
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    return $value;
}

/**
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function ia_migrate_normalize_row(array $row): array
{
    $out = [];
    foreach ($row as $k => $v) {
        $out[(string) $k] = ia_migrate_normalize_value($v);
    }

    return $out;
}

function ia_mysql_insert_row(IaPdoConnection $mysql, string $table, array $row): void
{
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';
    if ($table === '' || $row === []) {
        return;
    }
    $row = ia_migrate_normalize_row($row);
    $cols = array_keys($row);
    $placeholders = implode(',', array_fill(0, count($cols), '?'));
    $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . $placeholders . ')';
    $mysql->prepare($sql)->execute(array_values($row));
}

function ia_mysql_truncate_all(IaPdoConnection $mysql): void
{
    $mysql->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach (array_reverse(ia_migrate_table_order()) as $table) {
        if (!ia_db_table_exists($mysql, $table)) {
            continue;
        }
        try {
            $mysql->exec('TRUNCATE TABLE `' . preg_replace('/[^a-zA-Z0-9_]/', '', $table) . '`');
        } catch (Throwable) {
            try {
                $mysql->exec('DELETE FROM `' . preg_replace('/[^a-zA-Z0-9_]/', '', $table) . '`');
            } catch (Throwable) {
            }
        }
    }
    $mysql->exec('SET FOREIGN_KEY_CHECKS=1');
}

function ia_mysql_fix_auto_increment(IaPdoConnection $mysql, string $table, string $idCol = 'id'): void
{
    if (!ia_db_table_exists($mysql, $table)) {
        return;
    }
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';
    $idCol = preg_replace('/[^a-zA-Z0-9_]/', '', $idCol) ?? 'id';
    if ($table === '' || $idCol === '' || !ia_db_sql_ident_ok($table) || !ia_db_sql_ident_ok($idCol)) {
        return;
    }
    try {
        $st = $mysql->prepare('SELECT COALESCE(MAX(`' . $idCol . '`), 0) FROM `' . $table . '`');
        $st->execute();
        $max = (int) $st->fetchColumn();
        if ($max > 0) {
            $next = ia_db_sql_limit($max + 1, 1, PHP_INT_MAX);
            $mysql->exec('ALTER TABLE `' . $table . '` AUTO_INCREMENT = ' . $next);
        }
    } catch (Throwable) {
    }
}

/**
 * @return array{ok: bool, tables: list<array{table:string,source:int,inserted:int,error:string}>, total:int, error:string}
 */
function ia_migrate_supabase_to_mysql(IaPdoConnection $mysql, bool $truncateFirst = true): array
{
    if (!ia_supabase_rest_configured()) {
        return ['ok' => false, 'tables' => [], 'total' => 0, 'error' => 'Supabase REST not configured (IA_SUPABASE_URL + IA_SUPABASE_SECRET_KEY)'];
    }

    $report = ['ok' => true, 'tables' => [], 'total' => 0, 'error' => ''];

    try {
        if ($truncateFirst) {
            ia_mysql_truncate_all($mysql);
        }

        foreach (ia_migrate_table_order() as $table) {
            $entry = ['table' => $table, 'source' => 0, 'inserted' => 0, 'error' => ''];
            if (!ia_db_table_exists($mysql, $table)) {
                $entry['error'] = 'skip (table missing in MySQL)';
                $report['tables'][] = $entry;
                continue;
            }

            try {
                $rows = ia_supabase_rest_fetch_table($table);
                $entry['source'] = count($rows);
                foreach ($rows as $row) {
                    ia_mysql_insert_row($mysql, $table, $row);
                    $entry['inserted']++;
                }
                if ($entry['inserted'] > 0 && ia_db_column_exists($mysql, $table, 'id')) {
                    ia_mysql_fix_auto_increment($mysql, $table, 'id');
                }
            } catch (Throwable $e) {
                $entry['error'] = $e->getMessage();
                $report['ok'] = false;
            }

            $report['tables'][] = $entry;
            $report['total'] += $entry['inserted'];
        }
    } catch (Throwable $e) {
        $report['ok'] = false;
        $report['error'] = $e->getMessage();
    }

    if ($report['ok']) {
        ia_migrate_post_process_mysql($mysql);
    }

    return $report;
}

/**
 * After Supabase → MySQL: statuses, engagement dates, cache.
 *
 * @return array{listings_approved:int,cache_files_removed:int}
 */
function ia_migrate_post_process_mysql(IaPdoConnection $mysql): array
{
    $approved = 0;
    if (ia_db_table_exists($mysql, 'ad_listings')) {
        $approved += (int) $mysql->exec("UPDATE ad_listings SET status = 'approved' WHERE status = 'active'");
        $approved += (int) $mysql->exec("UPDATE ad_listings SET status = 'approved' WHERE status = 'pending'");
        $approved += (int) $mysql->exec(
            "UPDATE ad_listings SET status = 'pending'
             WHERE status NOT IN ('pending','approved','rejected','archived')"
        );
        if (ia_db_column_exists($mysql, 'ad_listings', 'last_engagement_at')) {
            $mysql->exec(
                "UPDATE ad_listings SET last_engagement_at = COALESCE(updated_at, created_at)
                 WHERE last_engagement_at IS NULL AND status = 'approved'"
            );
        }
    }

    $cacheRemoved = ia_migrate_clear_public_cache();

    return ['listings_approved' => $approved, 'cache_files_removed' => $cacheRemoved];
}

function ia_migrate_clear_public_cache(): int
{
    require_once IA_ROOT . '/includes/ia_cache.php';
    $dir = ia_cache_dir();
    $removed = 0;
    if (!is_dir($dir)) {
        return 0;
    }
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
        if (is_file($file) && @unlink($file)) {
            $removed++;
        }
    }

    return $removed;
}

/**
 * Migrate using local PostgreSQL source (XAMPP with pgsql ext).
 *
 * @return array{ok: bool, tables: list<array{table:string,source:int,inserted:int,error:string}>, total:int, error:string}
 */
function ia_migrate_pgsql_to_mysql(IaPgConnection|IaPdoConnection $pg, IaPdoConnection $mysql, bool $truncateFirst = true): array
{
    $report = ['ok' => true, 'tables' => [], 'total' => 0, 'error' => ''];

    try {
        if ($truncateFirst) {
            ia_mysql_truncate_all($mysql);
        }

        foreach (ia_migrate_table_order() as $table) {
            $entry = ['table' => $table, 'source' => 0, 'inserted' => 0, 'error' => ''];
            if (!ia_db_table_exists($mysql, $table)) {
                $entry['error'] = 'skip (table missing in MySQL)';
                $report['tables'][] = $entry;
                continue;
            }

            try {
                if (!ia_db_table_exists($pg, $table)) {
                    $entry['error'] = 'skip (empty in PostgreSQL)';
                    $report['tables'][] = $entry;
                    continue;
                }
                $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';
                $rows = $pg->query('SELECT * FROM ' . $safeTable)->fetchAll(PDO::FETCH_ASSOC);
                $entry['source'] = count($rows);
                foreach ($rows as $row) {
                    if (is_array($row)) {
                        ia_mysql_insert_row($mysql, $table, $row);
                        $entry['inserted']++;
                    }
                }
                if ($entry['inserted'] > 0 && ia_db_column_exists($mysql, $table, 'id')) {
                    ia_mysql_fix_auto_increment($mysql, $table, 'id');
                }
            } catch (Throwable $e) {
                $entry['error'] = $e->getMessage();
                $report['ok'] = false;
            }

            $report['tables'][] = $entry;
            $report['total'] += $entry['inserted'];
        }
    } catch (Throwable $e) {
        $report['ok'] = false;
        $report['error'] = $e->getMessage();
    }

    if ($report['ok']) {
        ia_migrate_post_process_mysql($mysql);
    }

    return $report;
}
