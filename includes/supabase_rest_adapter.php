<?php

declare(strict_types=1);

require_once IA_ROOT . '/includes/supabase_rest.php';
require_once IA_ROOT . '/includes/pg_adapter.php';

final class IaSupabaseRestException extends PDOException
{
}

final class IaSupabaseRestStatement
{
    /** @var list<array<string,mixed>> */
    private array $rows = [];
    private int $rowIndex = 0;
    private int $affected = 0;
    private bool $executed = false;

    public function __construct(
        private readonly string $sql,
    ) {
    }

    /** @param array<int|string, mixed>|null $params */
    public function execute(?array $params = null): bool
    {
        [$pgSql, $bind] = ia_pg_prepare_query($this->sql, $params ?? []);
        $result = ia_supabase_rest_execute_sql($pgSql, $bind);
        if (!$result['ok']) {
            throw new IaSupabaseRestException($result['error'] !== '' ? $result['error'] : 'Supabase REST execute failed');
        }
        $this->rows = $result['rows'];
        $this->affected = $result['affected'];
        $this->rowIndex = 0;
        $this->executed = true;

        return true;
    }

    /** @return array<string, mixed>|false */
    public function fetch(int $mode = PDO::FETCH_ASSOC): array|false
    {
        if (!$this->executed || $this->rowIndex >= count($this->rows)) {
            return false;
        }
        $row = $this->rows[$this->rowIndex];
        $this->rowIndex++;

        return $mode === PDO::FETCH_ASSOC ? $row : $row;
    }

    /** @return list<mixed> */
    public function fetchAll(int $mode = PDO::FETCH_ASSOC): array
    {
        if (!$this->executed) {
            return [];
        }
        if ($mode === PDO::FETCH_COLUMN) {
            return array_map(static function (array $row): mixed {
                return reset($row);
            }, $this->rows);
        }
        if ($mode === PDO::FETCH_KEY_PAIR) {
            $out = [];
            foreach ($this->rows as $row) {
                $vals = array_values($row);
                if (isset($vals[0], $vals[1])) {
                    $out[(string) $vals[0]] = $vals[1];
                }
            }

            return $out;
        }

        return $this->rows;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        $row = $this->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return false;
        }
        if ($column === 0) {
            return reset($row);
        }
        $vals = array_values($row);

        return $vals[$column] ?? false;
    }

    public function rowCount(): int
    {
        return $this->affected > 0 ? $this->affected : count($this->rows);
    }
}

final class IaSupabaseRestConnection
{
    private ?string $lastInsertId = null;

    public function prepare(string $sql): IaSupabaseRestStatement
    {
        return new IaSupabaseRestStatement($sql);
    }

    public function query(string $sql): IaSupabaseRestStatement
    {
        $st = new IaSupabaseRestStatement($sql);
        $st->execute();

        return $st;
    }

    public function exec(string $sql): int|false
    {
        $st = new IaSupabaseRestStatement($sql);
        $st->execute();

        return $st->rowCount();
    }

    public function lastInsertId(?string $name = null): string
    {
        if ($this->lastInsertId !== null && $this->lastInsertId !== '') {
            return $this->lastInsertId;
        }

        $st = new IaSupabaseRestStatement('SELECT lastval() AS id');
        $st->execute();
        $id = $st->fetchColumn(0);

        return $id === false ? '0' : (string) $id;
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
        return true;
    }

    public function commit(): bool
    {
        return true;
    }

    public function rollBack(): bool
    {
        return true;
    }

    public function inTransaction(): bool
    {
        return false;
    }

    public function quote(string $string, int $type = PDO::PARAM_STR): string|false
    {
        if ($type !== PDO::PARAM_STR && $type !== PDO::PARAM_STR_NATL) {
            return false;
        }

        return "'" . str_replace("'", "''", $string) . "'";
    }

    public function getNative(): \PgSql\Connection
    {
        throw new RuntimeException('Supabase REST mode — native pg connection unavailable');
    }
}

function ia_supabase_rest_connect(): IaSupabaseRestConnection
{
    static $verified = false;

    if (!ia_supabase_rest_configured()) {
        ia_supabase_rest_connection_fail(
            "IA_SUPABASE_URL and IA_SUPABASE_SECRET_KEY are required for IA_DB_DRIVER=supabase."
        );
    }

    if (!$verified) {
        $probe = ia_supabase_rest_execute_sql('SELECT 1 AS ok', []);
        if (!$probe['ok']) {
            ia_supabase_rest_connection_fail($probe['error']);
        }
        $verified = true;
    }

    return new IaSupabaseRestConnection();
}
