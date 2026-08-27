<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Key/value settings held in the database so an admin can change them without
 * touching code: academic year, document step count, deadlines, share links.
 */
final class Settings
{
    private static ?array $cache = null;

    public const DEFAULTS = [
        'academic_year'       => null,   // falls back to the current BE year
        'survey_round'        => 'Yearly',
        'report_step_count'   => '5',    // old system built 5 steps and shipped 2
        'report_deadline'     => '',
        'site_name'           => 'DVE PPP',
        'site_tagline'        => 'ระบบฐานข้อมูลความต้องการกำลังคน เพื่อการจัดการอาชีวศึกษาระบบทวิภาคี ภายใต้ความร่วมมือระหว่างสถานประกอบการและสำนักงานคณะกรรมการการอาชีวศึกษา',
        'rows_per_page'       => '25',
        'allow_public_search' => '1',
    ];

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $values = self::DEFAULTS;
        try {
            foreach (Database::all('SELECT setting_key, setting_value FROM app_settings') as $row) {
                $values[$row['setting_key']] = $row['setting_value'];
            }
        } catch (\Throwable) {
            // Before migrations run there is no table yet; defaults are enough.
        }
        return self::$cache = $values;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();
        $value = $all[$key] ?? $default;
        return ($value === null || $value === '') ? $default : $value;
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key);
        return is_numeric($v) ? (int) $v : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        return $v === null ? $default : in_array((string) $v, ['1', 'true', 'yes', 'on'], true);
    }

    public static function put(string $key, ?string $value): void
    {
        Database::run(
            'INSERT INTO app_settings (setting_key, setting_value, updated_at)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()',
            [$key, $value]
        );
        self::$cache = null;
    }

    public static function putMany(array $pairs): void
    {
        foreach ($pairs as $key => $value) {
            self::put((string) $key, $value === null ? null : (string) $value);
        }
    }
}
