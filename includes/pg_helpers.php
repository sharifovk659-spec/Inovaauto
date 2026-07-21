<?php

declare(strict_types=1);

/**
 * Безопасные запросы PostgreSQL через pg_query_params().
 */

/**
 * @param list<mixed> $params
 * @return \PgSql\Result
 */
function ia_pg_query_params(\PgSql\Connection $conn, string $sql, array $params = []): \PgSql\Result
{
    $result = pg_query_params($conn, $sql, $params);
    if ($result === false) {
        throw new IaPgException((string) pg_last_error($conn));
    }

    return $result;
}

/**
 * @param list<mixed> $params
 * @return list<array<string, mixed>>
 */
function ia_pg_fetch_all(\PgSql\Connection $conn, string $sql, array $params = []): array
{
    $result = ia_pg_query_params($conn, $sql, $params);
    $rows = pg_fetch_all($result, PGSQL_ASSOC);
    pg_free_result($result);

    return $rows ?: [];
}

/**
 * @param list<mixed> $params
 * @return array<string, mixed>|null
 */
function ia_pg_fetch_one(\PgSql\Connection $conn, string $sql, array $params = []): ?array
{
    $result = ia_pg_query_params($conn, $sql, $params);
    $row = pg_fetch_assoc($result);
    pg_free_result($result);

    return $row ?: null;
}

/**
 * Пример INSERT — platform_users (параметризованный).
 */
function ia_pg_example_insert(\PgSql\Connection $conn, string $name, string $email): int
{
    $sql = <<<'SQL'
INSERT INTO platform_users (name, phone, email, password_hash, account_type, status)
VALUES ($1, $2, $3, $4, $5, $6)
RETURNING id
SQL;
    $result = ia_pg_query_params($conn, $sql, [
        $name,
        '',
        $email,
        password_hash('example', PASSWORD_DEFAULT),
        'private',
        'active',
    ]);
    $id = (int) pg_fetch_result($result, 0, 0);
    pg_free_result($result);

    return $id;
}

/**
 * Пример SELECT — список пользователей.
 *
 * @return list<array<string, mixed>>
 */
function ia_pg_example_select_users(\PgSql\Connection $conn, int $limit = 10): array
{
    return ia_pg_fetch_all(
        $conn,
        'SELECT id, name, email, status, created_at FROM platform_users ORDER BY id DESC LIMIT $1',
        [$limit]
    );
}
