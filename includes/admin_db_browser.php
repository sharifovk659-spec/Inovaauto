<?php

declare(strict_types=1);

/**
 * Read-only database browser for admin UI (PostgreSQL + MySQL).
 * Table identifiers are validated against an allow-list from information_schema.
 */

function ia_admin_db_ident_ok(string $name): bool
{
    return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name);
}

/**
 * @return list<array{name: string}>
 */
function ia_admin_db_list_tables(IaPgConnection|IaPdoConnection $pdo): array
{
    if (ia_db_is_pgsql($pdo)) {
        $sql = <<<'SQL'
SELECT table_name AS name
FROM information_schema.tables
WHERE table_schema = current_schema()
  AND table_type = 'BASE TABLE'
ORDER BY table_name
SQL;
    } else {
        $sql = <<<'SQL'
SELECT table_name AS name
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_type = 'BASE TABLE'
ORDER BY table_name
SQL;
    }
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach ($rows as $r) {
        $n = (string) ($r['name'] ?? '');
        if ($n !== '' && ia_admin_db_ident_ok($n)) {
            $out[] = ['name' => $n];
        }
    }

    return $out;
}

/**
 * @return list<string>
 */
function ia_admin_db_list_column_names(IaPgConnection|IaPdoConnection $pdo, string $table): array
{
    if (!ia_admin_db_ident_ok($table)) {
        return [];
    }
    if (ia_db_is_pgsql($pdo)) {
        $st = $pdo->prepare(
            'SELECT column_name FROM information_schema.columns
             WHERE table_schema = current_schema() AND table_name = ?
             ORDER BY ordinal_position'
        );
    } else {
        $st = $pdo->prepare(
            'SELECT column_name FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ?
             ORDER BY ordinal_position'
        );
    }
    $st->execute([$table]);
    $names = [];
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $c = (string) ($row['column_name'] ?? '');
        if ($c !== '' && ia_admin_db_ident_ok($c)) {
            $names[] = $c;
        }
    }

    return $names;
}

function ia_admin_db_quote_table(IaPgConnection|IaPdoConnection $pdo, string $table): string
{
    if (ia_db_is_pgsql($pdo)) {
        return '"' . str_replace('"', '""', $table) . '"';
    }

    return '`' . str_replace('`', '``', $table) . '`';
}

/**
 * @return array{ok: bool, rows: list<array<string, mixed>>, columns: list<string>, has_more: bool, error: ?string}
 */
function ia_admin_db_fetch_table_page(IaPgConnection|IaPdoConnection $pdo, string $table, int $limit, int $offset): array
{
    $limit = max(1, min(100, $limit));
    $offset = max(0, min(10000, $offset));

    if (!ia_admin_db_ident_ok($table)) {
        return ['ok' => false, 'rows' => [], 'columns' => [], 'has_more' => false, 'error' => 'Некорректное имя таблицы.'];
    }

    $tables = ia_admin_db_list_tables($pdo);
    $allowed = false;
    foreach ($tables as $t) {
        if (($t['name'] ?? '') === $table) {
            $allowed = true;
            break;
        }
    }
    if (!$allowed) {
        return ['ok' => false, 'rows' => [], 'columns' => [], 'has_more' => false, 'error' => 'Таблица не найдена в текущей схеме.'];
    }

    $columns = ia_admin_db_list_column_names($pdo, $table);
    if ($columns === []) {
        return ['ok' => false, 'rows' => [], 'columns' => [], 'has_more' => false, 'error' => 'Не удалось прочитать столбцы таблицы.'];
    }

    $q = ia_admin_db_quote_table($pdo, $table);
    $fetchLimit = ia_db_sql_limit($limit + 1, 2, 101);
    $offset = ia_db_sql_offset($offset);
    $sql = "SELECT * FROM {$q} LIMIT ? OFFSET ?";
    try {
        $st = $pdo->prepare($sql);
        $st->execute([$fetchLimit, $offset]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'rows' => [],
            'columns' => $columns,
            'has_more' => false,
            'error' => 'Ошибка чтения: ' . $e->getMessage(),
        ];
    }

    $rows = is_array($rows) ? $rows : [];
    $hasMore = count($rows) > $limit;
    if ($hasMore) {
        $rows = array_slice($rows, 0, $limit);
    }

    return [
        'ok' => true,
        'rows' => $rows,
        'columns' => $columns,
        'has_more' => $hasMore,
        'error' => null,
    ];
}

function ia_admin_db_driver_label(IaPgConnection|IaPdoConnection $pdo): string
{
    return ia_db_is_pgsql($pdo) ? 'PostgreSQL' : 'MySQL / MariaDB';
}
