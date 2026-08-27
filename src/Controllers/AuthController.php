<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Context;
use App\Core\Session;
use App\Core\Url;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            Url::redirect(Auth::isAdmin() ? 'admin' : 'pveo');
        }
        $this->view('auth/login', [
            'title' => 'เข้าสู่ระบบ',
            'mode'  => $this->input('mode', 'pveo'),
        ], 'blank');
    }

    public function login(): void
    {
        $mode     = $this->input('mode', 'pveo');
        $username = $this->input('username');
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            Session::flash('err', 'กรุณากรอกข้อมูลให้ครบทุกช่อง');
            Url::redirect('login', ['mode' => $mode]);
        }

        $result = $mode === 'admin'
            ? Auth::attemptAdmin($username, $password)
            : Auth::attemptPveo($username, $password);

        if (!$result['ok']) {
            Session::flash('err', $result['message']);
            Url::redirect('login', ['mode' => $mode]);
        }

        if (Auth::mustChangePassword()) {
            Session::flash('warn', 'รหัสผ่านของคุณยังเป็นค่าเริ่มต้น กรุณาตั้งรหัสผ่านใหม่ก่อนใช้งาน');
            Url::redirect('password/change');
        }

        Session::flash('ok', $result['message']);
        Url::redirect(Auth::isAdmin() ? 'admin' : 'pveo');
    }

    public function logout(): void
    {
        Auth::logout();
        Url::redirect('');
    }

    public function showChangePassword(): void
    {
        Auth::requireLogin();
        $this->view('auth/change_password', [
            'title' => 'ตั้งรหัสผ่านใหม่',
            'first' => Auth::mustChangePassword(),
        ], 'blank');
    }

    public function changePassword(): void
    {
        Auth::requireLogin();

        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');
        $errors  = [];

        if (mb_strlen($new) < 8) {
            $errors[] = 'รหัสผ่านใหม่ต้องยาวอย่างน้อย 8 ตัวอักษร';
        }
        if ($new !== $confirm) {
            $errors[] = 'รหัสผ่านใหม่ทั้งสองช่องไม่ตรงกัน';
        }
        if ($new === Auth::user()['username']) {
            $errors[] = 'รหัสผ่านต้องไม่เหมือนกับชื่อผู้ใช้';  // ปัญหาเดิม: pass = college_code
        }

        if (!Auth::verifyCurrentPassword($current)) {
            $errors[] = 'รหัสผ่านเดิมไม่ถูกต้อง';
        }

        if ($errors !== []) {
            foreach ($errors as $msg) {
                Session::flash('err', $msg);
            }
            Url::redirect('password/change');
        }

        if (Auth::isAdmin()) {
            Auth::setAdminPassword((int) Auth::id(), $new);
        } else {
            Auth::setPveoPassword((int) Auth::id(), $new);
        }

        Session::flash('ok', 'ตั้งรหัสผ่านใหม่เรียบร้อยแล้ว');
        Url::redirect(Auth::isAdmin() ? 'admin' : 'pveo');
    }

    /** ตัวเลือกปีการศึกษาบน top bar — แทนการ hardcode '2568' ในระบบเดิม */
    public function switchYear(): void
    {
        Context::setYear($this->input('year'));
        Url::back();
    }

    /** ตัวสลับนิคมฯ ที่กำลังทำงาน */
    public function switchEstate(): void
    {
        Auth::requirePveo();
        $id = (int) ($_POST['estate_id'] ?? 0);
        if (Context::setActiveEstate($id)) {
            Session::flash('ok', 'เปลี่ยนนิคมฯ ที่กำลังทำงานแล้ว');
        } else {
            Session::flash('err', 'คุณไม่ได้รับมอบหมายให้ดูแลนิคมฯ ที่เลือก');
        }
        Url::back('pveo');
    }

    public function switchTheme(): void
    {
        $next = Context::theme() === 'dark' ? 'light' : 'dark';
        setcookie('dveppp_theme', $next, [
            'expires'  => time() + 60 * 60 * 24 * 365,
            'path'     => Url::basePath() ?: '/',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        Url::back();
    }

    public function estatePicker(): void
    {
        Auth::requirePveo();
        $this->view('pveo/estate_picker', [
            'title'   => 'เลือกนิคมอุตสาหกรรมที่จะทำงาน',
            'estates' => Context::estatesForCurrentUser(),
        ]);
    }
}
