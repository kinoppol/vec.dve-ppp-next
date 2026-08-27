<?php
declare(strict_types=1);

namespace App\Core;

/**
 * The two pieces of ambient state every screen filters by.
 *
 * Academic year (พ.ศ.) was hardcoded as '2568' throughout the old codebase; it
 * now comes from app_settings with a per-session override chosen in the top bar.
 *
 * Active estate keeps the old "work one estate at a time" rule: a PVEO office
 * may be responsible for several industrial estates, and almost every query is
 * scoped to the one currently selected. The switcher lives in the top bar
 * instead of being buried in a sub-menu.
 */
final class Context
{
    public const ROUNDS = [
        '1'      => 'ภาคเรียนที่ 1',
        '2'      => 'ภาคเรียนที่ 2',
        '3'      => 'ภาคฤดูร้อน',
        'Yearly' => 'ตลอดปีการศึกษา',
    ];

    /** Current Buddhist-Era academic year as a string, e.g. "2569". */
    public static function year(): string
    {
        $override = $_SESSION['academic_year'] ?? null;
        if (is_string($override) && preg_match('/^25\d{2}$/', $override)) {
            return $override;
        }
        return self::defaultYear();
    }

    public static function defaultYear(): string
    {
        $setting = Settings::get('academic_year');
        if (is_string($setting) && preg_match('/^25\d{2}$/', $setting)) {
            return $setting;
        }
        return (string) ((int) date('Y') + 543);
    }

    public static function setYear(string $year): void
    {
        if (preg_match('/^25\d{2}$/', $year)) {
            $_SESSION['academic_year'] = $year;
        }
    }

    /** Years offered in the top-bar picker: whatever the data holds, plus the default. */
    public static function years(): array
    {
        $years = [];
        try {
            $years = Database::run(
                'SELECT DISTINCT survey_year FROM pveo_estate_assignments
                 UNION SELECT DISTINCT survey_year FROM surveys
                 ORDER BY 1 DESC'
            )->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Throwable) {
            $years = [];
        }
        $years = array_values(array_unique(array_filter(array_map('strval', $years))));
        foreach ([self::defaultYear(), self::year()] as $extra) {
            if (!in_array($extra, $years, true)) {
                $years[] = $extra;
            }
        }
        rsort($years);
        return $years;
    }

    /** Estates the signed-in PVEO office is responsible for this year. */
    public static function estatesForCurrentUser(): array
    {
        if (!Auth::isPveo()) {
            return [];
        }
        return Database::all(
            'SELECT e.industrial_estate_id AS id,
                    e.industrial_estate_name AS estate_name,
                    COALESCE(p.province_name_th, "ไม่ระบุจังหวัด") AS province_name
               FROM industrial_estate_responsibility r
               JOIN industrial_estates e ON e.industrial_estate_id = r.industrial_estate_id
          LEFT JOIN provinces p ON p.province_id = e.province_id
              WHERE r.pveo_id = ? AND r.is_active = 1
           ORDER BY e.industrial_estate_name',
            [Auth::id()]
        );
    }

    public static function activeEstateId(): ?int
    {
        if (!Auth::isPveo()) {
            return null;
        }
        $id = $_SESSION['active_estate_id'] ?? null;
        if ($id !== null) {
            return (int) $id;
        }
        // First estate becomes the working one until the user picks another.
        $estates = self::estatesForCurrentUser();
        if ($estates === []) {
            return null;
        }
        $_SESSION['active_estate_id'] = (int) $estates[0]['id'];
        return (int) $estates[0]['id'];
    }

    public static function activeEstate(): ?array
    {
        $id = self::activeEstateId();
        if ($id === null) {
            return null;
        }
        foreach (self::estatesForCurrentUser() as $estate) {
            if ((int) $estate['id'] === $id) {
                return $estate;
            }
        }
        return null;
    }

    /** Returns false when the office is not responsible for the requested estate. */
    public static function setActiveEstate(int $estateId): bool
    {
        foreach (self::estatesForCurrentUser() as $estate) {
            if ((int) $estate['id'] === $estateId) {
                $_SESSION['active_estate_id'] = $estateId;
                return true;
            }
        }
        return false;
    }

    public static function theme(): string
    {
        $cookie = $_COOKIE['dveppp_theme'] ?? null;
        return $cookie === 'dark' ? 'dark' : 'light';
    }
}
