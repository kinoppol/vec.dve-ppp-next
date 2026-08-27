<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Context;
use App\Core\Settings;
use App\Core\Url;
use App\Core\View;

abstract class Controller
{
    /**
     * Render a page with the shell (top bar + sidebar) already populated.
     * $nav marks the active sidebar item.
     */
    protected function view(string $template, array $data = [], string $layout = 'app'): void
    {
        $data += [
            'title'         => 'DVE PPP',
            'nav'           => '',
            'year'          => Context::year(),
            'years'         => Context::years(),
            'user'          => Auth::user(),
            'role'          => Auth::role(),
            'impersonating' => Auth::isImpersonating(),
            'activeEstate'  => Auth::isPveo() ? Context::activeEstate() : null,
            'myEstates'     => Auth::isPveo() ? Context::estatesForCurrentUser() : [],
            'sidebar'       => $this->sidebar(),
        ];

        // A user still on their initial password gets one destination only —
        // unless they chose "ข้ามไปก่อน", which holds only for this session.
        if (Auth::check() && Auth::mustChangePassword() && !Auth::passwordChangePostponed()
            && Url::current() !== 'password/change') {
            Url::redirect('password/change');
        }

        View::display($template, $data, $layout);
    }

    /** Sidebar items for the signed-in role, in the order the brief specifies. */
    protected function sidebar(): array
    {
        if (Auth::isAdmin()) {
            return [
                'title' => 'ผู้ดูแลระบบ',
                'items' => [
                    ['id' => 'dash',     'icon' => '◧', 'label' => 'แดชบอร์ดภาพรวม',        'href' => url('admin')],
                    ['id' => 'estates',  'icon' => '🏭', 'label' => 'ติดตามข้อมูลนิคมฯ',     'href' => url('admin/estates')],
                    ['id' => 'uploads',  'icon' => '🗂', 'label' => 'สถานะการอัปโหลดไฟล์',  'href' => url('admin/uploads')],
                    ['id' => 'assign',   'icon' => '👥', 'label' => 'จัดการ สอจ. และโควตา', 'href' => url('admin/assign')],
                    ['id' => 'settings', 'icon' => '⚙', 'label' => 'ตั้งค่าระบบ',            'href' => url('admin/settings')],
                    ['id' => 'migrations','icon' => '🗄', 'label' => 'Migration ฐานข้อมูล', 'href' => url('admin/migrations')],
                ],
            ];
        }

        if (Auth::isPveo()) {
            return [
                'title' => 'สอจ.',
                'items' => [
                    ['id' => 'pveo',        'icon' => '◧', 'label' => 'แดชบอร์ดของฉัน',      'href' => url('pveo')],
                    ['id' => 'enterprises', 'icon' => '🏢', 'label' => 'สถานประกอบการ',       'href' => url('pveo/enterprises')],
                    ['id' => 'progress',    'icon' => '🗂', 'label' => 'รายงานความคืบหน้า',  'href' => url('pveo/progress')],
                ],
            ];
        }

        return ['title' => '', 'items' => []];
    }

    protected function perPage(): int
    {
        $configured = Settings::int('rows_per_page', 25);
        $requested  = (int) ($_GET['per'] ?? $configured);
        return max(10, min(100, $requested ?: $configured));
    }

    protected function page(): int
    {
        return max(1, (int) ($_GET['page'] ?? 1));
    }

    protected function input(string $key, string $default = ''): string
    {
        $value = $_GET[$key] ?? $_POST[$key] ?? $default;
        return is_string($value) ? trim($value) : $default;
    }
}
