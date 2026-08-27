<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Pre-flight checks run by install.php (and re-runnable from the admin area).
 * Every check returns: key, label, required, ok, actual, hint.
 */
final class Requirements
{
    public const PHP_MIN     = '8.0.0';
    public const MARIADB_MIN = '10.0.0';

    /** Paths that must be writable, relative to the project root. */
    public const WRITABLE = [
        'config'          => 'เก็บไฟล์ตั้งค่า config.php',
        'storage'         => 'เก็บ log และ cache',
        'storage/logs'    => 'บันทึกข้อผิดพลาด',
        'storage/cache'   => 'แคชระบบ',
        'uploads'         => 'ไฟล์ที่ผู้ใช้อัปโหลด',
        'uploads/reports' => 'เอกสารรายงานความคืบหน้า',
    ];

    public static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /** @return array<int,array<string,mixed>> */
    public static function system(): array
    {
        $checks = [];

        $checks[] = self::check(
            'php',
            'PHP ' . self::PHP_MIN . ' ขึ้นไป',
            version_compare(PHP_VERSION, self::PHP_MIN, '>='),
            PHP_VERSION,
            true,
            'ปรับรุ่น PHP ที่ใช้กับ virtual host นี้'
        );

        foreach (['pdo' => 'PDO', 'pdo_mysql' => 'PDO MySQL', 'mbstring' => 'mbstring', 'json' => 'JSON'] as $ext => $label) {
            $checks[] = self::check(
                'ext_' . $ext,
                'ส่วนขยาย ' . $label,
                extension_loaded($ext),
                extension_loaded($ext) ? 'เปิดใช้งาน' : 'ไม่พบ',
                true,
                'เปิด extension=' . $ext . ' ใน php.ini'
            );
        }

        foreach (['fileinfo' => 'fileinfo (ตรวจชนิดไฟล์อัปโหลด)', 'intl' => 'intl (จัดรูปแบบตัวเลข/วันที่)', 'zip' => 'zip (ส่งออกไฟล์)'] as $ext => $label) {
            $checks[] = self::check(
                'ext_' . $ext,
                'ส่วนขยาย ' . $label,
                extension_loaded($ext),
                extension_loaded($ext) ? 'เปิดใช้งาน' : 'ไม่พบ',
                false,
                'ไม่บังคับ — ระบบมีทางสำรองให้ แต่แนะนำให้เปิด'
            );
        }

        $upload = self::bytes((string) ini_get('upload_max_filesize'));
        $checks[] = self::check(
            'upload_max_filesize',
            'upload_max_filesize อย่างน้อย 8M',
            $upload >= 8 * 1024 * 1024,
            (string) ini_get('upload_max_filesize'),
            false,
            'ผู้ใช้อัปโหลด PDF รายงานหลายไฟล์ต่อครั้ง'
        );

        $post = self::bytes((string) ini_get('post_max_size'));
        $checks[] = self::check(
            'post_max_size',
            'post_max_size ไม่น้อยกว่า upload_max_filesize',
            $post >= $upload,
            (string) ini_get('post_max_size'),
            false,
            'ตั้ง post_max_size ให้มากกว่า upload_max_filesize'
        );

        $checks[] = self::check(
            'session',
            'เปิดใช้งาน session ได้',
            function_exists('session_start'),
            session_status() === PHP_SESSION_DISABLED ? 'ถูกปิดใช้งาน' : 'พร้อมใช้งาน',
            true,
            'session.save_path ต้องเขียนได้'
        );

        return $checks;
    }

    /** @return array<int,array<string,mixed>> */
    public static function writable(): array
    {
        $root = self::root();
        $checks = [];
        foreach (self::WRITABLE as $rel => $why) {
            $path = $root . '/' . $rel;
            if (!is_dir($path)) {
                @mkdir($path, 0775, true);
            }
            $ok = is_dir($path) && is_writable($path);
            $checks[] = self::check(
                'dir_' . str_replace('/', '_', $rel),
                $rel . '/',
                $ok,
                !is_dir($path) ? 'ไม่มีโฟลเดอร์' : ($ok ? 'เขียนได้' : 'เขียนไม่ได้'),
                true,
                $why
            );
        }
        return $checks;
    }

    /** True when every *required* check passes. */
    public static function passes(array ...$groups): bool
    {
        foreach ($groups as $group) {
            foreach ($group as $c) {
                if ($c['required'] && !$c['ok']) {
                    return false;
                }
            }
        }
        return true;
    }

    public static function databaseServer(\PDO $pdo): array
    {
        $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        $isMaria = stripos($version, 'mariadb') !== false;
        // "10.4.32-MariaDB" -> "10.4.32"
        $numeric = preg_match('/(\d+\.\d+\.\d+)/', $version, $m) ? $m[1] : '0.0.0';
        $ok = $isMaria
            ? version_compare($numeric, self::MARIADB_MIN, '>=')
            : version_compare($numeric, '8.0.0', '>='); // MySQL 8 is an acceptable stand-in

        return self::check(
            'db_version',
            'MariaDB ' . self::MARIADB_MIN . ' ขึ้นไป (หรือ MySQL 8)',
            $ok,
            $version,
            true,
            'ระบบใช้ CTE และ JSON ที่ต้องการรุ่นนี้ขึ้นไป'
        );
    }

    /** ตารางที่แปลว่า "ฐานข้อมูลนี้เป็นของระบบเดิม" ถ้ามีข้อมูลอยู่แล้ว */
    public const LEGACY_TABLES = [
        'provincial_vocational_offices',
        'industrial_estates',
        'enterprises',
        'surveys',
        'admins',
    ];

    /**
     * ตรวจว่าฐานข้อมูลปลายทางมีข้อมูลของระบบเดิมอยู่ก่อนหรือไม่.
     *
     * ระบบเดิมกับระบบใหม่ใช้ชื่อตารางชุดเดียวกัน ถ้าผู้ติดตั้งกรอกชื่อฐานข้อมูล
     * ของระบบเดิมเข้ามา migration จะไปทำงานทับข้อมูลจริง — โดยเฉพาะ 0004 ที่
     * DROP แล้วสร้าง trigger/procedure ชื่อเดิมทับของระบบเดิม
     *
     * "เคยติดตั้งด้วยแอปนี้แล้ว" (มีตาราง schema_migrations) ไม่นับว่าเป็นของเดิม
     *
     * @return array{shared:bool, tables:array<string,int>, migrated:bool}
     */
    public static function legacyData(\PDO $pdo): array
    {
        $migrated = false;
        try {
            $migrated = (int) $pdo->query(
                "SELECT COUNT(*) FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . Migrator::TABLE . "'"
            )->fetchColumn() > 0;
        } catch (\Throwable) {
            $migrated = false;
        }

        $found = [];
        foreach (self::LEGACY_TABLES as $table) {
            try {
                $exists = (int) $pdo->query(
                    "SELECT COUNT(*) FROM information_schema.TABLES
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $pdo->quote($table)
                )->fetchColumn() > 0;
                if (!$exists) {
                    continue;
                }
                $rows = (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
                if ($rows > 0) {
                    $found[$table] = $rows;
                }
            } catch (\Throwable) {
                // อ่านไม่ได้ก็ถือว่าไม่พบ — ไม่ให้การตรวจสอบทำให้ติดตั้งไม่ได้
            }
        }

        return [
            'shared'   => $found !== [] && !$migrated,
            'tables'   => $found,
            'migrated' => $migrated,
        ];
    }

    /**
     * Thai collation preference. utf8mb4_thai_520_w2 exists on MySQL 8 but not
     * on MariaDB 10.4, so pick the best available rather than failing install.
     */
    public static function pickCollation(\PDO $pdo): array
    {
        $preferred = ['utf8mb4_thai_520_w2', 'utf8mb4_unicode_520_ci', 'utf8mb4_unicode_ci', 'utf8mb4_general_ci'];
        $available = $pdo->query(
            "SELECT COLLATION_NAME FROM information_schema.COLLATIONS WHERE CHARACTER_SET_NAME = 'utf8mb4'"
        )->fetchAll(\PDO::FETCH_COLUMN);
        $available = array_map('strtolower', $available);

        foreach ($preferred as $c) {
            if (in_array(strtolower($c), $available, true)) {
                return [
                    'collation' => $c,
                    'exact'     => $c === 'utf8mb4_thai_520_w2',
                    'available' => $available,
                ];
            }
        }
        return ['collation' => 'utf8mb4_general_ci', 'exact' => false, 'available' => $available];
    }

    private static function check(string $key, string $label, bool $ok, string $actual, bool $required, string $hint): array
    {
        return compact('key', 'label', 'ok', 'actual', 'required', 'hint');
    }

    public static function bytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $unit = strtolower($value[strlen($value) - 1]);
        $num  = (int) $value;
        return match ($unit) {
            'g' => $num * 1024 * 1024 * 1024,
            'm' => $num * 1024 * 1024,
            'k' => $num * 1024,
            default => $num,
        };
    }
}
