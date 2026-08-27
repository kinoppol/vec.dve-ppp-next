<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Migrator;
use App\Core\Session;
use App\Core\Url;

/**
 * เมนู Migration สำหรับผู้ดูแลระบบ — ดูสถานะ, รันรุ่นที่ค้าง, ย้อนกลับ, ดู SQL
 *
 * ทุกการกระทำเป็น POST + CSRF และจำกัดเฉพาะ Admin เท่านั้น
 * ระหว่างสวมสิทธิ์ (impersonate) จะแก้ไขโครงสร้างฐานข้อมูลไม่ได้
 */
final class MigrationController extends Controller
{
    private function migrator(): Migrator
    {
        return new Migrator(Database::pdo());
    }

    private function guard(): void
    {
        Auth::requireAdmin();
        if (Auth::isImpersonating()) {
            Session::flash('err', 'ออกจากโหมดสวมสิทธิ์ก่อนจึงจะจัดการ migration ได้');
            Url::redirect('admin/migrations');
        }
    }

    public function index(): void
    {
        Auth::requireAdmin();

        $rows    = $this->migrator()->status();
        $summary = ['applied' => 0, 'pending' => 0, 'drifted' => 0, 'missing' => 0];
        foreach ($rows as $r) {
            $summary[$r['state']] = ($summary[$r['state']] ?? 0) + 1;
        }

        $this->view('admin/migrations', [
            'title'    => 'Migration ฐานข้อมูล',
            'nav'      => 'migrations',
            'rows'     => $rows,
            'summary'  => $summary,
            'dbName'   => (string) Database::value('SELECT DATABASE()', [], ''),
            'dbServer' => (string) Database::value('SELECT VERSION()', [], ''),
            'locked'   => Auth::isImpersonating(),
        ]);
    }

    /** ดู SQL ของรุ่นหนึ่ง ทั้งส่วน UP และ DOWN ก่อนตัดสินใจรัน */
    public function show(string $version): void
    {
        Auth::requireAdmin();

        $migrator = $this->migrator();
        $up       = $migrator->sqlFor($version, 'up');
        if ($up === null) {
            Session::flash('err', 'ไม่พบ migration รุ่น ' . $version);
            Url::redirect('admin/migrations');
        }

        $row = null;
        foreach ($migrator->status() as $candidate) {
            if ($candidate['version'] === $version) {
                $row = $candidate;
                break;
            }
        }

        $this->view('admin/migration_detail', [
            'title'   => 'Migration ' . $version,
            'nav'     => 'migrations',
            'version' => $version,
            'row'     => $row,
            'up'      => $up,
            'down'    => (string) $migrator->sqlFor($version, 'down'),
        ]);
    }

    /** รันรุ่นที่ค้างทั้งหมด หรือระบุรุ่นเดียวด้วยช่อง version */
    public function run(): void
    {
        $this->guard();

        $only = trim((string) ($_POST['version'] ?? ''));
        $log  = $this->migrator()->migrate((string) Auth::name(), $only !== '' ? $only : null);

        if ($log === []) {
            Session::flash('info', 'ไม่มี migration ที่ต้องดำเนินการ');
            Url::redirect('admin/migrations');
        }

        foreach ($log as $line) {
            Session::flash(
                $line['ok'] ? 'ok' : 'err',
                sprintf(
                    '%s %s · %s — %s',
                    $line['ok'] ? '✔' : '✕',
                    $line['version'],
                    $line['name'],
                    $line['message']
                )
            );
        }
        Url::redirect('admin/migrations');
    }

    public function rollback(): void
    {
        $this->guard();

        $version = trim((string) ($_POST['version'] ?? ''));
        if (($_POST['confirm'] ?? '') !== $version) {
            Session::flash('err', 'ต้องพิมพ์เลขรุ่นให้ตรงเพื่อยืนยันการย้อนกลับ');
            Url::redirect('admin/migrations/' . $version);
        }

        $result = $this->migrator()->rollback($version, (string) Auth::name());
        Session::flash($result['ok'] ? 'ok' : 'err', $result['message']);
        Url::redirect('admin/migrations');
    }

    /** ไฟล์ถูกแก้หลังรันไปแล้ว — ยืนยันว่าตั้งใจ แล้วอัปเดต checksum */
    public function resync(): void
    {
        $this->guard();

        $result = $this->migrator()->resync(trim((string) ($_POST['version'] ?? '')));
        Session::flash($result['ok'] ? 'ok' : 'err', $result['message']);
        Url::redirect('admin/migrations');
    }
}
