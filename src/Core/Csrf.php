<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public const FIELD = '_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::FIELD])) {
            $_SESSION[self::FIELD] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::FIELD];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function check(?string $token): bool
    {
        $expected = $_SESSION[self::FIELD] ?? '';
        return is_string($token) && $expected !== '' && hash_equals($expected, $token);
    }

    /** Abort the request when a POST arrives without a valid token. */
    public static function verify(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }
        if (!self::check($_POST[self::FIELD] ?? null)) {
            http_response_code(419);
            exit('<h1>419 — เซสชันหมดอายุ</h1><p>กรุณาย้อนกลับและส่งฟอร์มใหม่อีกครั้ง</p>');
        }
    }
}
