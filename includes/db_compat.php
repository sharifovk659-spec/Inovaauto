<?php

declare(strict_types=1);

require_once __DIR__ . '/db_input.php';

/** LIMIT/OFFSET: танҳо int-и санҷишшуда — барои SQL-и бо named placeholders. */
function ia_db_sql_limit(int $limit, int $min = 1, int $max = 500): int
{
    return max($min, min($max, $limit));
}

function ia_db_sql_offset(int $offset, int $max = 100000): int
{
    return max(0, min($max, $offset));
}

function ia_db_sql_ident_ok(string $name): bool
{
    return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name);
}

/**
 * Placeholders барои IN (...) — танҳо ID-ҳои мусбат.
 *
 * @param list<int> $ids
 * @return array{place: string, ids: list<int>}
 */
function ia_db_int_in_clause(array $ids): array
{
    $ids = array_values(array_unique(array_filter(
        array_map(static fn (mixed $v): int => ia_input_int($v, 0, 1),
        $ids),
        static fn (int $v): bool => $v > 0
    )));

    if ($ids === []) {
        return ['place' => '0', 'ids' => []];
    }

    return [
        'place' => implode(',', array_fill(0, count($ids), '?')),
        'ids' => $ids,
    ];
}

/**
 * SELECT бо LIMIT (placeholder ?) — барои query-ҳои positional.
 *
 * @param list<mixed> $params
 * @return list<array<string, mixed>>
 */
function ia_db_fetch_all_limit(
    IaPgConnection|IaPdoConnection $pdo,
    string $sql,
    array $params,
    int $limit,
    ?int $offset = null
): array {
    $limit = ia_db_sql_limit($limit);
    $bind = array_values($params);
    if ($offset !== null) {
        $offset = ia_db_sql_offset($offset);
        $sql .= ' LIMIT ? OFFSET ?';
        $bind[] = $limit;
        $bind[] = $offset;
    } else {
        $sql .= ' LIMIT ?';
        $bind[] = $limit;
    }
    $st = $pdo->prepare($sql);
    $st->execute($bind);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function ia_db_driver_name(IaPgConnection|IaPdoConnection|IaSupabaseRestConnection $pdo): string
{
    return strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
}

function ia_db_is_pgsql(IaPgConnection|IaPdoConnection|IaSupabaseRestConnection $pdo): bool
{
    return ia_db_driver_name($pdo) === 'pgsql';
}

function ia_db_table_exists(IaPgConnection|IaPdoConnection|IaSupabaseRestConnection $pdo, string $table): bool
{
    if (ia_db_is_pgsql($pdo)) {
        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = ?"
        );
    } else {
        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
        );
    }
    $st->execute([$table]);

    return (int) $st->fetchColumn() > 0;
}

function ia_db_last_insert_id(IaPgConnection|IaPdoConnection|IaSupabaseRestConnection $pdo, ?string $pgsqlSequenceName = null): int
{
    if ($pgsqlSequenceName !== null && $pgsqlSequenceName !== '') {
        $id = (int) $pdo->lastInsertId($pgsqlSequenceName);
        if ($id > 0) {
            return $id;
        }
    }

    return (int) $pdo->lastInsertId();
}

/**
 * OR-group of LIKE clauses with unique named placeholders (required for MySQL native prepares).
 *
 * @param list<string> $columns e.g. ['l.brand', 'l.model']
 * @param array<string, string|int|float> $params
 */
function ia_db_like_or(array $columns, string $prefix, string $pattern, array &$params): string
{
    $parts = [];
    foreach ($columns as $i => $col) {
        $key = $prefix . $i;
        $parts[] = $col . ' LIKE :' . $key;
        $params[$key] = $pattern;
    }

    return '(' . implode(' OR ', $parts) . ')';
}

function ia_db_column_exists(IaPgConnection|IaPdoConnection|IaSupabaseRestConnection $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . "\0" . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $st = $pdo->prepare(
        ia_db_is_pgsql($pdo)
            ? "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?"
            : 'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $st->execute([$table, $column]);
    $cache[$key] = (int) $st->fetchColumn() > 0;

    return $cache[$key];
}
