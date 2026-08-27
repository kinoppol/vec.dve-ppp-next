<?php
declare(strict_types=1);

namespace App\Core;

/**
 * URL helpers. The app is designed to run from a sub-folder of the document
 * root (XAMPP: /vec.dve-ppp-next/), so every link is built from the detected
 * base path rather than assuming "/".
 */
final class Url
{
    private static ?string $base = null;

    public static function basePath(): string
    {
        if (self::$base !== null) {
            return self::$base;
        }
        $configured = Config::get('app.base_path');
        if (is_string($configured) && $configured !== '') {
            return self::$base = rtrim($configured, '/');
        }
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $dir    = str_replace('\\', '/', dirname($script));
        return self::$base = ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
    }

    /** Build an app URL: to('admin/estates', ['year' => 2569]). */
    public static function to(string $path = '', array $query = []): string
    {
        $path = ltrim($path, '/');
        $url  = self::basePath() . '/' . $path;
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }
        return $url;
    }

    /**
     * ลิงก์ไฟล์ใน assets/ พร้อมตัวกันแคช
     *
     * ต่อ ?v=<เวลาแก้ไขไฟล์> ท้าย URL — เบราว์เซอร์และแคชฝั่งเซิร์ฟเวอร์จะถือว่า
     * เป็นคนละไฟล์ทันทีที่ deploy สไตล์ใหม่ ไม่ต้องให้ผู้ใช้กด hard refresh เอง
     */
    public static function asset(string $path): string
    {
        $clean = ltrim($path, '/');
        $url   = self::basePath() . '/assets/' . $clean;
        $file  = dirname(__DIR__, 2) . '/assets/' . $clean;
        $stamp = is_file($file) ? filemtime($file) : false;

        return $stamp === false ? $url : $url . '?v=' . $stamp;
    }

    /** Current path relative to the app root, e.g. "admin/estates". */
    public static function current(): string
    {
        $uri  = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $base = self::basePath();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        return trim($path, '/');
    }

    /** Preserve the current query string while changing selected parameters. */
    public static function withQuery(array $changes): string
    {
        $query = $_GET;
        unset($query['r']);
        foreach ($changes as $k => $v) {
            if ($v === null || $v === '') {
                unset($query[$k]);
            } else {
                $query[$k] = $v;
            }
        }
        return self::to(self::current(), $query);
    }

    public static function redirect(string $path, array $query = []): never
    {
        header('Location: ' . self::to($path, $query));
        exit;
    }

    public static function back(string $fallback = ''): never
    {
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        // Only follow a referrer that points back at this host.
        if ($ref !== '' && $host !== '' && str_contains($ref, $host)) {
            header('Location: ' . $ref);
            exit;
        }
        self::redirect($fallback);
    }
}
