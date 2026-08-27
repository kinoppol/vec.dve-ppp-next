<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

/**
 * File-based schema migrations.
 *
 * Each file in migrations/ is named NNNN_snake_case_name.sql and may contain a
 * "-- @DOWN" marker; everything above it is the UP migration, everything below
 * is the rollback. Statements are split on the delimiter at end of line, with
 * DELIMITER blocks (routines, triggers) kept intact.
 *
 * Applied versions live in schema_migrations, with a checksum so an edited file
 * that has already run is reported as drifted rather than silently ignored.
 */
final class Migrator
{
    public const TABLE = 'schema_migrations';

    private string $dir;

    public function __construct(private PDO $pdo, ?string $dir = null)
    {
        $this->dir = $dir ?? dirname(__DIR__, 2) . '/migrations';
    }

    public function ensureTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS `' . self::TABLE . '` (
                `version`      VARCHAR(20)  NOT NULL,
                `name`         VARCHAR(191) NOT NULL,
                `checksum`     CHAR(64)     NOT NULL,
                `applied_at`   DATETIME     NOT NULL,
                `duration_ms`  INT UNSIGNED NOT NULL DEFAULT 0,
                `applied_by`   VARCHAR(100) NULL,
                PRIMARY KEY (`version`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    /** @return array<string,array<string,string>> keyed by version */
    public function files(): array
    {
        $out = [];
        foreach (glob($this->dir . '/*.sql') ?: [] as $path) {
            $base = basename($path, '.sql');
            if (!preg_match('/^(\d{4})_(.+)$/', $base, $m)) {
                continue;
            }
            $sql = (string) file_get_contents($path);
            [$up, $down] = $this->split($sql);
            $out[$m[1]] = [
                'version'  => $m[1],
                'name'     => str_replace('_', ' ', $m[2]),
                'path'     => $path,
                'file'     => basename($path),
                'checksum' => hash('sha256', $this->normalise($sql)),
                'up'       => $up,
                'down'     => $down,
            ];
        }
        ksort($out);
        return $out;
    }

    /** @return array<string,array<string,mixed>> keyed by version */
    public function applied(): array
    {
        $this->ensureTable();
        $rows = $this->pdo->query('SELECT * FROM `' . self::TABLE . '` ORDER BY `version`')->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[$r['version']] = $r;
        }
        return $out;
    }

    /**
     * Full status list for the admin screen: every known migration with its
     * state (applied | pending | drifted | missing).
     */
    public function status(): array
    {
        $files   = $this->files();
        $applied = $this->applied();
        $rows    = [];

        foreach ($files as $version => $f) {
            $a = $applied[$version] ?? null;
            $state = $a === null
                ? 'pending'
                : ($a['checksum'] === $f['checksum'] ? 'applied' : 'drifted');
            $rows[] = [
                'version'     => $version,
                'name'        => $f['name'],
                'file'        => $f['file'],
                'state'       => $state,
                'applied_at'  => $a['applied_at'] ?? null,
                'duration_ms' => $a['duration_ms'] ?? null,
                'applied_by'  => $a['applied_by'] ?? null,
                'reversible'  => $this->isReversible($f['down']),
                'checksum'    => $f['checksum'],
            ];
        }

        // Rows in the table with no matching file - someone deleted a migration.
        foreach ($applied as $version => $a) {
            if (!isset($files[$version])) {
                $rows[] = [
                    'version'     => $version,
                    'name'        => $a['name'],
                    'file'        => '(ไม่พบไฟล์)',
                    'state'       => 'missing',
                    'applied_at'  => $a['applied_at'],
                    'duration_ms' => $a['duration_ms'],
                    'applied_by'  => $a['applied_by'],
                    'reversible'  => false,
                    'checksum'    => $a['checksum'],
                ];
            }
        }

        usort($rows, static fn(array $a, array $b) => strcmp($a['version'], $b['version']));
        return $rows;
    }

    /** @return array<int,array<string,string>> */
    public function pending(): array
    {
        $applied = $this->applied();
        return array_values(array_filter(
            $this->files(),
            static fn(array $f) => !isset($applied[$f['version']])
        ));
    }

    /**
     * Apply every pending migration in order, or just one when $only is given.
     * DDL is not transactional in MariaDB, so a failure stops the run and leaves
     * earlier migrations applied - which is why each file must be self-contained.
     *
     * @return array<int,array<string,mixed>> one log line per migration
     */
    public function migrate(?string $by = null, ?string $only = null): array
    {
        $this->ensureTable();
        $log = [];
        foreach ($this->pending() as $file) {
            if ($only !== null && $file['version'] !== $only) {
                continue;
            }
            $started = microtime(true);
            try {
                $this->execScript($file['up']);
            } catch (Throwable $e) {
                $log[] = [
                    'version' => $file['version'],
                    'name'    => $file['name'],
                    'ok'      => false,
                    'message' => $e->getMessage(),
                    'ms'      => 0,
                ];
                return $log;
            }
            $ms = (int) round((microtime(true) - $started) * 1000);
            $stmt = $this->pdo->prepare(
                'INSERT INTO `' . self::TABLE . '` (`version`,`name`,`checksum`,`applied_at`,`duration_ms`,`applied_by`)
                 VALUES (?,?,?,NOW(),?,?)'
            );
            $stmt->execute([$file['version'], $file['name'], $file['checksum'], $ms, $by]);
            $log[] = [
                'version' => $file['version'],
                'name'    => $file['name'],
                'ok'      => true,
                'message' => 'สำเร็จ',
                'ms'      => $ms,
            ];
        }
        return $log;
    }

    /** Roll back a single applied migration using its @DOWN section. */
    public function rollback(string $version, ?string $by = null): array
    {
        $files = $this->files();
        if (!isset($files[$version])) {
            return ['ok' => false, 'message' => 'ไม่พบไฟล์ migration รุ่น ' . $version];
        }
        if (!$this->isReversible($files[$version]['down'])) {
            return ['ok' => false, 'message' => 'migration รุ่นนี้ไม่มีคำสั่งย้อนกลับ จึงย้อนกลับไม่ได้'];
        }
        if (!isset($this->applied()[$version])) {
            return ['ok' => false, 'message' => 'migration รุ่นนี้ยังไม่ถูกใช้งาน'];
        }
        try {
            $this->execScript($files[$version]['down']);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
        $this->pdo->prepare('DELETE FROM `' . self::TABLE . '` WHERE `version` = ?')->execute([$version]);
        return ['ok' => true, 'message' => 'ย้อนกลับรุ่น ' . $version . ' สำเร็จ'];
    }

    /** Re-stamp a drifted row so the recorded checksum matches the file again. */
    public function resync(string $version): array
    {
        $files = $this->files();
        if (!isset($files[$version])) {
            return ['ok' => false, 'message' => 'ไม่พบไฟล์ migration รุ่น ' . $version];
        }
        $this->pdo->prepare('UPDATE `' . self::TABLE . '` SET `checksum` = ? WHERE `version` = ?')
                  ->execute([$files[$version]['checksum'], $version]);
        return ['ok' => true, 'message' => 'อัปเดต checksum ของรุ่น ' . $version . ' แล้ว'];
    }

    public function sqlFor(string $version, string $section = 'up'): ?string
    {
        $files = $this->files();
        if (!isset($files[$version])) {
            return null;
        }
        return $section === 'down' ? $files[$version]['down'] : $files[$version]['up'];
    }

    /**
     * A down section counts as reversible only when it holds at least one real
     * statement. Judging by raw text instead would treat a section that is only
     * explanatory comments - which is how a deliberately irreversible migration
     * documents itself - as reversible, and "rolling it back" would then run
     * nothing while still deleting the schema_migrations row, leaving the
     * migration marked pending on top of a schema it had already changed.
     */
    private function isReversible(string $down): bool
    {
        return $this->statements($down) !== [];
    }

    /** Split a file into [up, down] on the "-- @DOWN" marker. */
    private function split(string $sql): array
    {
        $parts = preg_split('/^[ \t]*--[ \t]*@DOWN[ \t]*$/mi', $sql, 2);
        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    private function normalise(string $sql): string
    {
        return str_replace(["\r\n", "\r"], "\n", $sql);
    }

    /** Execute a multi-statement script, honouring DELIMITER blocks. */
    private function execScript(string $script): void
    {
        foreach ($this->statements($script) as $statement) {
            $this->pdo->exec($statement);
        }
    }

    /** @return string[] */
    public function statements(string $script): array
    {
        $script    = $this->normalise($script);
        $out       = [];
        $buffer    = '';
        $delimiter = ';';

        foreach (explode("\n", $script) as $line) {
            $trimmed = trim($line);

            if ($buffer === '' && ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#'))) {
                continue;
            }
            if (preg_match('/^DELIMITER[ \t]+(\S+)$/i', $trimmed, $m)) {
                $delimiter = $m[1];
                continue;
            }

            $buffer .= $line . "\n";

            if (str_ends_with($trimmed, $delimiter)) {
                $statement = trim(substr(rtrim($buffer), 0, -strlen($delimiter)));
                if ($statement !== '') {
                    $out[] = $statement;
                }
                $buffer = '';
            }
        }

        if (trim($buffer) !== '') {
            $out[] = trim($buffer);
        }
        return $out;
    }
}
