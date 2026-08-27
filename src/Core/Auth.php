<?php
declare(strict_types=1);

namespace App\Core;

/**
 * One permission model over the two legacy login tables.
 *
 * The old system had two entirely separate logins and no role column. Here both
 * `admins` and `provincial_vocational_offices` feed a single session identity
 * carrying an explicit role, so every screen can ask the same questions.
 *
 * Legacy password handling: PVEO rows store `college_password` in plaintext and
 * in production it equals `college_code`. Migration 0003 adds `password_hash`
 * alongside it without touching the legacy column. On login we accept the hash
 * when present, otherwise fall back to the plaintext column and force a reset.
 */
final class Auth
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_PVEO  = 'pveo';
    public const ROLE_GUEST = 'guest';

    public static function user(): ?array
    {
        return $_SESSION['auth'] ?? null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function role(): string
    {
        return self::user()['role'] ?? self::ROLE_GUEST;
    }

    public static function isAdmin(): bool
    {
        return self::role() === self::ROLE_ADMIN;
    }

    public static function isPveo(): bool
    {
        return self::role() === self::ROLE_PVEO;
    }

    public static function id(): ?int
    {
        $id = self::user()['id'] ?? null;
        return $id === null ? null : (int) $id;
    }

    public static function name(): string
    {
        return self::user()['name'] ?? 'ผู้เข้าชมทั่วไป';
    }

    public static function mustChangePassword(): bool
    {
        return (bool) (self::user()['must_change_password'] ?? false);
    }

    /** True while an admin is viewing the system as a PVEO office. */
    public static function isImpersonating(): bool
    {
        return isset($_SESSION['impersonator']);
    }

    public static function impersonator(): ?array
    {
        return $_SESSION['impersonator'] ?? null;
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public static function attemptAdmin(string $username, string $password): array
    {
        $row = Database::first(
            'SELECT * FROM admins WHERE username = ? LIMIT 1',
            [$username]
        );
        if ($row === null) {
            return ['ok' => false, 'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'];
        }

        $stored = (string) ($row['password'] ?? '');
        $valid  = self::verifyAgainst($password, $stored);
        if (!$valid) {
            return ['ok' => false, 'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'];
        }

        // Upgrade any legacy hash to the current algorithm on successful login.
        if (!self::isBcrypt($stored) || password_needs_rehash($stored, PASSWORD_DEFAULT)) {
            Database::run('UPDATE admins SET password = ? WHERE id = ?', [
                password_hash($password, PASSWORD_DEFAULT),
                $row['id'],
            ]);
        }

        self::login([
            'role'                 => self::ROLE_ADMIN,
            'id'                   => (int) $row['id'],
            'username'             => $row['username'],
            'name'                 => $row['full_name'] ?: 'ผู้ดูแลระบบ สอศ.',
            'scope'                => 'ทั้งประเทศ',
            'must_change_password' => false,
        ]);
        self::touchLastLogin('admins', (int) $row['id']);

        return ['ok' => true, 'message' => 'เข้าสู่ระบบสำเร็จ'];
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public static function attemptPveo(string $collegeCode, string $password): array
    {
        $row = Database::first(
            'SELECT * FROM provincial_vocational_offices WHERE college_code = ? LIMIT 1',
            [$collegeCode]
        );
        if ($row === null) {
            return ['ok' => false, 'message' => 'รหัสวิทยาลัยหรือรหัสผ่านไม่ถูกต้อง'];
        }

        $hash      = (string) ($row['password_hash'] ?? '');
        $legacy    = (string) ($row['college_password'] ?? '');
        $usedLegacy = false;

        if ($hash !== '') {
            $valid = password_verify($password, $hash);
        } else {
            // Legacy plaintext column - constant-time compare, then force a reset.
            $valid = $legacy !== '' && hash_equals($legacy, $password);
            $usedLegacy = $valid;
        }

        if (!$valid) {
            return ['ok' => false, 'message' => 'รหัสวิทยาลัยหรือรหัสผ่านไม่ถูกต้อง'];
        }

        // Production data has password == college_code for most offices.
        $weak = $usedLegacy || hash_equals((string) $row['college_code'], $password);

        self::login([
            'role'                 => self::ROLE_PVEO,
            'id'                   => (int) $row['id'],
            'username'             => $row['college_code'],
            'name'                 => $row['college_name'] ?: ('สอจ. ' . $row['college_code']),
            'scope'                => 'สอจ. · รหัส ' . $row['college_code'],
            'province_id'          => $row['province_id'] ?? null,
            'must_change_password' => $weak || (bool) ($row['must_change_password'] ?? false),
        ]);
        self::touchLastLogin('provincial_vocational_offices', (int) $row['id']);

        return ['ok' => true, 'message' => 'เข้าสู่ระบบสำเร็จ'];
    }

    /**
     * Verify the signed-in user's current password without touching the session.
     * (Re-running attemptAdmin/attemptPveo would regenerate the session id and
     * rewrite the identity as a side effect.)
     */
    public static function verifyCurrentPassword(string $password): bool
    {
        $user = self::user();
        if ($user === null || $password === '') {
            return false;
        }

        if ($user['role'] === self::ROLE_ADMIN) {
            $stored = (string) Database::value('SELECT password FROM admins WHERE id = ?', [$user['id']], '');
            return self::verifyAgainst($password, $stored);
        }

        $row = Database::first(
            'SELECT college_password, password_hash FROM provincial_vocational_offices WHERE id = ?',
            [$user['id']]
        );
        if ($row === null) {
            return false;
        }
        $hash = (string) ($row['password_hash'] ?? '');
        if ($hash !== '') {
            return password_verify($password, $hash);
        }
        $legacy = (string) ($row['college_password'] ?? '');
        return $legacy !== '' && hash_equals($legacy, $password);
    }

    public static function setPveoPassword(int $officeId, string $newPassword): void
    {
        Database::run(
            'UPDATE provincial_vocational_offices
                SET password_hash = ?, must_change_password = 0, password_changed_at = NOW()
              WHERE id = ?',
            [password_hash($newPassword, PASSWORD_DEFAULT), $officeId]
        );
        if (isset($_SESSION['auth'])) {
            $_SESSION['auth']['must_change_password'] = false;
        }
    }

    public static function setAdminPassword(int $adminId, string $newPassword): void
    {
        Database::run(
            'UPDATE admins SET password = ?, password_changed_at = NOW() WHERE id = ?',
            [password_hash($newPassword, PASSWORD_DEFAULT), $adminId]
        );
        if (isset($_SESSION['auth'])) {
            $_SESSION['auth']['must_change_password'] = false;
        }
    }

    /** Admin views the system as a given PVEO office. */
    public static function impersonate(int $officeId): array
    {
        if (!self::isAdmin()) {
            return ['ok' => false, 'message' => 'เฉพาะผู้ดูแลระบบเท่านั้น'];
        }
        $row = Database::first('SELECT * FROM provincial_vocational_offices WHERE id = ?', [$officeId]);
        if ($row === null) {
            return ['ok' => false, 'message' => 'ไม่พบ สอจ. ที่เลือก'];
        }

        $_SESSION['impersonator'] = self::user();
        self::regenerate();
        $_SESSION['auth'] = [
            'role'                 => self::ROLE_PVEO,
            'id'                   => (int) $row['id'],
            'username'             => $row['college_code'],
            'name'                 => $row['college_name'],
            'scope'                => 'สอจ. · รหัส ' . $row['college_code'],
            'province_id'          => $row['province_id'] ?? null,
            'must_change_password' => false,
        ];
        unset($_SESSION['active_estate_id']);

        return ['ok' => true, 'message' => 'เข้าสู่โหมดสวมสิทธิ์ในฐานะ ' . $row['college_name']];
    }

    public static function stopImpersonating(): void
    {
        if (!isset($_SESSION['impersonator'])) {
            return;
        }
        $_SESSION['auth'] = $_SESSION['impersonator'];
        unset($_SESSION['impersonator'], $_SESSION['active_estate_id']);
        self::regenerate();
    }

    public static function login(array $identity): void
    {
        self::regenerate();
        $_SESSION['auth'] = $identity;
        $_SESSION['last_activity'] = time();
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    /** Guard helpers used at the top of controller actions. */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            Session::flash('info', 'กรุณาเข้าสู่ระบบก่อนใช้งานหน้านี้');
            Url::redirect('login');
        }
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            http_response_code(403);
            exit('<h1>403 — ไม่มีสิทธิ์เข้าถึง</h1><p>หน้านี้สำหรับผู้ดูแลระบบเท่านั้น</p>');
        }
    }

    public static function requirePveo(): void
    {
        self::requireLogin();
        if (!self::isPveo()) {
            http_response_code(403);
            exit('<h1>403 — ไม่มีสิทธิ์เข้าถึง</h1><p>หน้านี้สำหรับ สอจ. เท่านั้น</p>');
        }
    }

    private static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    private static function isBcrypt(string $hash): bool
    {
        return (bool) preg_match('/^\$2[aby]\$\d{2}\$/', $hash);
    }

    private static function verifyAgainst(string $password, string $stored): bool
    {
        if ($stored === '') {
            return false;
        }
        if (self::isBcrypt($stored) || str_starts_with($stored, '$argon2')) {
            return password_verify($password, $stored);
        }
        // Legacy admin rows may hold md5/sha1 or plaintext.
        if (strlen($stored) === 32 && ctype_xdigit($stored)) {
            return hash_equals($stored, md5($password));
        }
        if (strlen($stored) === 40 && ctype_xdigit($stored)) {
            return hash_equals($stored, sha1($password));
        }
        return hash_equals($stored, $password);
    }

    private static function touchLastLogin(string $table, int $id): void
    {
        if (!Database::columnExists($table, 'last_login_at')) {
            return;
        }
        Database::run('UPDATE `' . $table . '` SET last_login_at = NOW() WHERE id = ?', [$id]);
    }
}
