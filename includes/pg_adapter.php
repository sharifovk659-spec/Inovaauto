<?php

declare(strict_types=1);

/**
 * Адаптер PostgreSQL: pg_query_params ($1, $2) + совместимость с ? и :name.
 */
final class IaPgException extends PDOException
{
}

/**
 * PDO-style SQL → PostgreSQL positional params.
 *
 * @param array<int|string, mixed> $params
 * @return array{0: string, 1: list<mixed>}
 */
function ia_pg_prepare_query(string $sql, array $params): array
{
    if ($params === []) {
        return [$sql, []];
    }

    $usesNamed = (bool) preg_match('/(?<![:]):[a-zA-Z_][a-zA-Z0-9_]*/', $sql);

    if (!$usesNamed) {
        $index = 0;
        $pgSql = (string) preg_replace_callback('/\?/', static function () use (&$index): string {
            $index++;

            return '$' . $index;
        }, $sql);

        return [$pgSql, array_values($params)];
    }

    $map = [];
    $bind = [];
    $pgSql = (string) preg_replace_callback(
        '/(?<![:]):([a-zA-Z_][a-zA-Z0-9_]*)/',
        static function (array $m) use (&$map, &$bind, $params): string {
            $name = $m[1];
            if (!array_key_exists($name, $params)) {
                throw new IaPgException('Missing bind parameter: ' . $name);
            }
            if (!isset($map[$name])) {
                $map[$name] = count($bind) + 1;
                $bind[] = $params[$name];
            }

            return '$' . $map[$name];
        },
        $sql
    );

    return [$pgSql, $bind];
}

final class IaPgStatement
{
    private ?\PgSql\Result $result = null;

    public function __construct(
        private readonly \PgSql\Connection $conn,
        private readonly string $sql,
    ) {
    }

    public function setResult(\PgSql\Result $result): void
    {
        $this->result = $result;
    }

    /**
     * @param array<int|string, mixed>|null $params
     */
    public function execute(?array $params = null): bool
    {
        [$pgSql, $bind] = ia_pg_prepare_query($this->sql, $params ?? []);
        $result = @pg_query_params($this->conn, $pgSql, $bind);
        if ($result === false) {
            $err = (string) pg_last_error($this->conn);
            throw new IaPgException($err !== '' ? $err : 'pg_query_params failed');
        }
        $this->result = $result;

        return true;
    }

    /** @return array<string, mixed>|false */
    public function fetch(int $mode = PDO::FETCH_ASSOC): array|false
    {
        if ($this->result === null) {
            return false;
        }
        $row = pg_fetch_assoc($this->result);

        return $row ?: false;
    }

    /** @return list<mixed> */
    public function fetchAll(int $mode = PDO::FETCH_ASSOC): array
    {
        if ($this->result === null) {
            return [];
        }
        if ($mode === PDO::FETCH_COLUMN) {
            $rows = pg_fetch_all($this->result, PGSQL_NUM) ?: [];

            return array_map(static fn (array $r): mixed => $r[0] ?? null, $rows);
        }
        if ($mode === PDO::FETCH_KEY_PAIR) {
            $out = [];
            while ($row = pg_fetch_row($this->result)) {
                if (isset($row[0], $row[1])) {
                    $out[(string) $row[0]] = $row[1];
                }
            }

            return $out;
        }

        return pg_fetch_all($this->result, PGSQL_ASSOC) ?: [];
    }

    public function fetchColumn(int $column = 0): mixed
    {
        if ($this->result === null) {
            return false;
        }
        if (pg_num_rows($this->result) < 1) {
            return false;
        }
        $value = pg_fetch_result($this->result, 0, $column);

        return $value === false ? false : $value;
    }

    public function rowCount(): int
    {
        if ($this->result === null) {
            return 0;
        }
        $n = pg_affected_rows($this->result);

        return $n >= 0 ? $n : 0;
    }
}

final class IaPgConnection
{
    private bool $inTransaction = false;

    public function __construct(
        private readonly \PgSql\Connection $conn,
    ) {
    }

    public function getNative(): \PgSql\Connection
    {
        return $this->conn;
    }

    public function prepare(string $sql): IaPgStatement
    {
        return new IaPgStatement($this->conn, $sql);
    }

    public function query(string $sql): IaPgStatement
    {
        $result = pg_query($this->conn, $sql);
        if ($result === false) {
            throw new IaPgException((string) pg_last_error($this->conn));
        }
        $st = new IaPgStatement($this->conn, $sql);
        $st->setResult($result);

        return $st;
    }

    public function exec(string $sql): int|false
    {
        $result = pg_query($this->conn, $sql);
        if ($result === false) {
            throw new IaPgException((string) pg_last_error($this->conn));
        }
        $n = pg_affected_rows($result);
        pg_free_result($result);

        return $n;
    }

    public function lastInsertId(?string $name = null): string
    {
        if ($name !== null && $name !== '') {
            $result = pg_query_params($this->conn, 'SELECT currval($1::regclass) AS id', [$name]);
        } else {
            $result = pg_query($this->conn, 'SELECT lastval() AS id');
        }
        if ($result === false) {
            throw new IaPgException((string) pg_last_error($this->conn));
        }
        if (pg_num_rows($result) < 1) {
            pg_free_result($result);
            throw new IaPgException('lastInsertId: empty result');
        }
        $id = pg_fetch_result($result, 0, 0);
        pg_free_result($result);

        return (string) $id;
    }

    public function getAttribute(int $attribute): mixed
    {
        return match ($attribute) {
            PDO::ATTR_DRIVER_NAME => 'pgsql',
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            default => null,
        };
    }

    public function beginTransaction(): bool
    {
        if ($this->inTransaction) {
            throw new IaPgException('There is already an active transaction');
        }
        $this->pgExecCommand('BEGIN');
        $this->inTransaction = true;

        return true;
    }

    public function commit(): bool
    {
        if (!$this->inTransaction) {
            throw new IaPgException('No active transaction');
        }
        $this->pgExecCommand('COMMIT');
        $this->inTransaction = false;

        return true;
    }

    public function rollBack(): bool
    {
        if (!$this->inTransaction) {
            return false;
        }
        $this->pgExecCommand('ROLLBACK');
        $this->inTransaction = false;

        return true;
    }

    public function inTransaction(): bool
    {
        return $this->inTransaction;
    }

    public function quote(string $string, int $type = PDO::PARAM_STR): string|false
    {
        if ($type !== PDO::PARAM_STR && $type !== PDO::PARAM_STR_NATL) {
            return false;
        }
        $quoted = pg_escape_literal($this->conn, $string);

        return $quoted !== false ? $quoted : false;
    }

    private function pgExecCommand(string $sql): void
    {
        $result = pg_query($this->conn, $sql);
        if ($result === false) {
            throw new IaPgException((string) pg_last_error($this->conn));
        }
        pg_free_result($result);
    }
}

final class IaPdoStatement
{
    private ?PDOStatement $stmt = null;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $sql,
    ) {
    }

    /** @param array<int|string, mixed>|null $params */
    public function execute(?array $params = null): bool
    {
        $this->stmt = $this->pdo->prepare($this->sql);
        if ($this->stmt === false) {
            throw new IaPgException('PDO prepare failed');
        }

        return $this->stmt->execute($params ?? []);
    }

    /** @return array<string, mixed>|false */
    public function fetch(int $mode = PDO::FETCH_ASSOC): array|false
    {
        if ($this->stmt === null) {
            return false;
        }
        $row = $this->stmt->fetch($mode);

        return $row ?: false;
    }

    /** @return list<mixed> */
    public function fetchAll(int $mode = PDO::FETCH_ASSOC): array
    {
        if ($this->stmt === null) {
            return [];
        }

        return $this->stmt->fetchAll($mode) ?: [];
    }

    public function fetchColumn(int $column = 0): mixed
    {
        if ($this->stmt === null) {
            return false;
        }

        return $this->stmt->fetchColumn($column);
    }

    public function rowCount(): int
    {
        if ($this->stmt === null) {
            return 0;
        }

        return $this->stmt->rowCount();
    }
}

final class IaPdoConnection
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function getNative(): \PgSql\Connection
    {
        throw new RuntimeException('PDO mode — native pg connection unavailable');
    }

    public function prepare(string $sql): IaPdoStatement
    {
        return new IaPdoStatement($this->pdo, $sql);
    }

    public function query(string $sql): IaPdoStatement
    {
        $st = new IaPdoStatement($this->pdo, $sql);
        $st->execute();

        return $st;
    }

    public function exec(string $sql): int|false
    {
        $n = $this->pdo->exec($sql);

        return $n === false ? false : $n;
    }

    public function lastInsertId(?string $name = null): string
    {
        if ($name !== null && $name !== '') {
            $id = $this->pdo->lastInsertId($name);
            if ($id !== '0' && $id !== '') {
                return (string) $id;
            }
        }

        return (string) $this->pdo->lastInsertId();
    }

    public function getAttribute(int $attribute): mixed
    {
        return $this->pdo->getAttribute($attribute);
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    public function quote(string $string, int $type = PDO::PARAM_STR): string|false
    {
        return $this->pdo->quote($string, $type);
    }
}
