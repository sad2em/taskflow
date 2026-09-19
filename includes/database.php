<?php
/**
 * TaskFlow — Database layer (PDO)
 * Supports MySQL / MariaDB (production + XAMPP) and SQLite (quick local tests).
 */

final class Db
{
    private static ?PDO $pdo = null;
    private static string $driver = 'mysql';

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $driver = strtolower((string)(defined('DB_DRIVER') ? DB_DRIVER : 'mysql'));
        self::$driver = $driver;

        try {
            if ($driver === 'sqlite') {
                $file = defined('DB_NAME') ? DB_NAME : 'taskflow.sqlite';
                if (!is_file($file)) {
                    $dir = dirname($file);
                    if ($dir !== '' && !is_dir($dir)) {
                        @mkdir($dir, 0775, true);
                    }
                    touch($file);
                }
                self::$pdo = new PDO('sqlite:' . $file, null, null, self::options());
                self::$pdo->exec('PRAGMA foreign_keys = ON');
            } else {
                $host    = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
                $port    = defined('DB_PORT') ? DB_PORT : '3306';
                $name    = defined('DB_NAME') ? DB_NAME : 'taskflow';
                $user    = defined('DB_USER') ? DB_USER : 'root';
                $pass    = defined('DB_PASS') ? DB_PASS : '';
                $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
                $socket  = defined('DB_SOCKET') ? DB_SOCKET : '';

                if ($socket !== '') {
                    $dsn = "mysql:unix_socket={$socket};dbname={$name};charset={$charset}";
                } else {
                    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
                }

                self::$pdo = new PDO($dsn, $user, $pass, self::options());
            }
        } catch (PDOException $e) {
            http_response_code(500);
            $hint = defined('DB_NAME') ? DB_NAME : 'taskflow';
            echo '<div style="font-family:system-ui,sans-serif;max-width:720px;margin:60px auto;padding:28px;'
               . 'border:1px solid #fecaca;background:#fff7f7;border-radius:14px;color:#7f1d1d">'
               . '<h2 style="margin-top:0">Database connection failed</h2>'
               . '<p><strong>Reason:</strong> ' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>'
               . '<p>Make sure MySQL/MariaDB is running and that the database <code>' . htmlspecialchars($hint) . '</code> exists.</p>'
               . '<p>Import <code>database/setup.sql</code></code>, '
               . 'or edit <code>config/env.php</code>.</p></div>';
            exit;
        }

        return self::$pdo;
    }

    private static function options(): array
    {
        return [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ];
    }

    public static function driver(): string
    {
        self::pdo();
        return self::$driver;
    }

    public static function isSqlite(): bool
    {
        return self::driver() === 'sqlite';
    }

    /** Run a prepared statement and return it. */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : (str_starts_with((string)$key, ':') ? $key : ':' . $key);
            $type = PDO::PARAM_STR;
            if (is_int($value))  { $type = PDO::PARAM_INT; }
            if (is_bool($value)) { $value = $value ? 1 : 0; $type = PDO::PARAM_INT; }
            if ($value === null) { $type = PDO::PARAM_NULL; }
            $stmt->bindValue($name, $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    /** Fetch all rows. */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** Fetch a single row or null. */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** Fetch the first column of the first row. */
    public static function value(string $sql, array $params = [])
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function count(string $sql, array $params = []): int
    {
        return (int)self::value($sql, $params);
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql  = 'INSERT INTO ' . self::table($table) . ' (`' . implode('`, `', $cols)
              . '`) VALUES (:' . implode(', :', $cols) . ')';
        self::run($sql, $data);
        return (int)self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = [];
        foreach (array_keys($data) as $col) {
            $sets[] = '`' . $col . '` = :set_' . $col;
        }
        $params = [];
        foreach ($data as $k => $v) {
            $params['set_' . $k] = $v;
        }
        $sql = 'UPDATE ' . self::table($table) . ' SET ' . implode(', ', $sets) . ' WHERE ' . $where;
        return self::run($sql, array_merge($params, $whereParams))->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::run('DELETE FROM ' . self::table($table) . ' WHERE ' . $where, $params)->rowCount();
    }

    /**
     * INSERT that silently skips duplicate keys.
     * $columns: list of column names, $placeholders: e.g. "?, ?, ?"
     */
    public static function insertIgnore(string $table, array $columns, string $placeholders, array $params): int
    {
        $verb = self::isSqlite() ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
        $sql  = $verb . ' INTO ' . self::table($table) . ' (`' . implode('`, `', $columns) . '`) VALUES (' . $placeholders . ')';
        return self::run($sql, $params)->rowCount();
    }

    public static function table(string $name): string
    {
        return preg_match('/^[A-Za-z0-9_]+$/', $name) ? $name : 'x';
    }

    public static function transaction(callable $fn)
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Split an .sql file into individual statements (handles ; inside strings
     * and -- / # comments).
     */
    public static function splitSql(string $sql): array
    {
        $statements = [];
        $buffer     = '';
        $inSingle   = false;
        $inDouble   = false;
        $inBacktick = false;
        $length     = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if (!$inSingle && !$inDouble && !$inBacktick) {
                // line comment
                if (($char === '-' && $next === '-') || $char === '#') {
                    $end = strpos($sql, "\n", $i);
                    if ($end === false) { break; }
                    $i = $end;
                    $buffer .= "\n";
                    continue;
                }
                // block comment
                if ($char === '/' && $next === '*') {
                    $end = strpos($sql, '*/', $i + 2);
                    if ($end === false) { break; }
                    $i = $end + 1;
                    continue;
                }
            }

            if ($char === "'" && !$inDouble && !$inBacktick) {
                if ($inSingle && $next === "'") { $buffer .= "''"; $i++; continue; }
                $inSingle = !$inSingle;
            } elseif ($char === '"' && !$inSingle && !$inBacktick) {
                $inDouble = !$inDouble;
            } elseif ($char === '`' && !$inSingle && !$inDouble) {
                $inBacktick = !$inBacktick;
            }

            if ($char === ';' && !$inSingle && !$inDouble && !$inBacktick) {
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $trimmed = trim($buffer);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }

        return $statements;
    }

    /**
     * Rewrite MySQL-specific DDL so the same schema file also runs on SQLite
     * (used only for quick local testing / demos).
     */
    public static function toSqliteDialect(string $statement): string
    {
        $statement = preg_replace('/ENGINE\s*=\s*\w+[^;]*/i', '', $statement) ?? $statement;
        $statement = preg_replace('/UNIQUE\s+KEY\s+\w+\s*\(/i', 'UNIQUE (', $statement) ?? $statement;
        $statement = preg_replace('/\bKEY\s+\w+\s*\([^)]*\)\s*,?/i', '', $statement) ?? $statement;
        $statement = preg_replace('/\bINDEX\s+\w+\s*\([^)]*\)\s*,?/i', '', $statement) ?? $statement;
        $statement = str_replace('JSON', 'TEXT', $statement);
        $statement = preg_replace('/,\s*\)/', ')', $statement) ?? $statement;
        return $statement;
    }

    /** Execute every statement of an .sql file. Returns the number of statements run. */
    public static function importFile(string $path): int
    {
        if (!is_file($path)) {
            throw new RuntimeException('SQL file not found: ' . $path);
        }
        $sql        = (string)file_get_contents($path);
        $statements = self::splitSql($sql);
        $sqlite     = self::isSqlite();
        $count      = 0;
        foreach ($statements as $statement) {
            if ($sqlite) {
                $statement = self::toSqliteDialect($statement);
            }
            self::pdo()->exec($statement);
            $count++;
        }
        return $count;
    }
}
