<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Thin PDO wrapper. One connection per process, built from Config or from an
 * explicit array (the installer connects before any config file exists).
 */
final class Database
{
    private static ?PDO $pdo = null;

    /**
     * @param array|null $cfg      Explicit settings; null reads config/config.php.
     * @param bool       $asDefault Make this the connection Database::pdo() returns.
     *                              The installer needs it: it connects with settings
     *                              that are not in the config file yet.
     */
    public static function connect(?array $cfg = null, bool $asDefault = false): PDO
    {
        $explicit = $cfg !== null;
        if (!$explicit && self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $cfg ??= (array) Config::get('db', []);
        $host    = $cfg['host'] ?? '127.0.0.1';
        $port    = (int) ($cfg['port'] ?? 3306);
        $name    = $cfg['database'] ?? '';
        $user    = $cfg['username'] ?? 'root';
        $pass    = $cfg['password'] ?? '';
        $charset = $cfg['charset'] ?? 'utf8mb4';

        $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $host, $port, $charset);
        if ($name !== '') {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);
        }

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);

        // Collation is decided at install time (MariaDB 10.4 has no utf8mb4_thai_520_w2).
        $collation = $cfg['collation'] ?? null;
        if (is_string($collation) && $collation !== '' && preg_match('/^[A-Za-z0-9_]+$/', $collation)) {
            try {
                $pdo->exec("SET NAMES {$charset} COLLATE {$collation}");
            } catch (PDOException) {
                $pdo->exec("SET NAMES {$charset}");
            }
        }
        // Predictable date handling + strict-ish mode without breaking legacy rows.
        $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
        $pdo->exec("SET SESSION time_zone = '+07:00'");

        if (!$explicit || $asDefault) {
            self::$pdo = $pdo;
        }
        return $pdo;
    }

    public static function pdo(): PDO
    {
        return self::$pdo instanceof PDO ? self::$pdo : self::connect();
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function first(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = [], mixed $default = null): mixed
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? $default : $v;
    }

    public static function int(string $sql, array $params = [], int $default = 0): int
    {
        return (int) self::value($sql, $params, $default);
    }

    public static function tableExists(string $table): bool
    {
        $sql = 'SELECT COUNT(*) FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?';
        return self::int($sql, [$table]) > 0;
    }

    public static function columnExists(string $table, string $column): bool
    {
        $sql = 'SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?';
        return self::int($sql, [$table, $column]) > 0;
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }
}
