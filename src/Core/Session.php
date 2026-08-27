<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Session bootstrap plus the flash-message bag.
 *
 * The old system timed admins out after 30 minutes and never timed out PVEO
 * users at all; here both roles share one idle timeout from config.
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => Url::basePath() ?: '/',
            'httponly' => true,
            'secure'   => $secure,
            'samesite' => 'Lax',
        ]);
        session_name((string) Config::get('app.session_name', 'dveppp_session'));
        session_start();

        self::enforceIdleTimeout();
    }

    private static function enforceIdleTimeout(): void
    {
        $minutes = (int) Config::get('app.session_timeout_minutes', 30);
        if ($minutes <= 0 || !isset($_SESSION['auth'])) {
            $_SESSION['last_activity'] = time();
            return;
        }

        $last = (int) ($_SESSION['last_activity'] ?? time());
        if (time() - $last > $minutes * 60) {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['flash'][] = ['type' => 'info', 'message' => 'หมดเวลาการใช้งาน กรุณาเข้าสู่ระบบอีกครั้ง'];
        }
        $_SESSION['last_activity'] = time();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return array<int,array{type:string,message:string}> */
    public static function takeFlash(): array
    {
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flash;
    }
}
