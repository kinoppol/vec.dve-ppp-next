<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Context;
use App\Core\Database;
use App\Core\Session;
use App\Core\Settings;
use App\Core\Url;

final class AdminController extends Controller
{
    /** 5.1 แดชบอร์ดภาพรวมประเทศ */
    public function dashboard(): void
    {
        Auth::requireAdmin();
        $year = Context::year();

        $this->view('admin/dashboard', [
            'title'          => 'แดชบอร์ดภาพรวมประเทศ',
            'nav'            => 'dash',
            'kpis'           => $this->nationalKpis($year),
            'regionProgress' => $this->regionProgress(),
            'demandSplit'    => $this->demandSplit($year),
            'topCourses'     => $this->topCourses($year),
            'laggards'       => $this->laggards($year),
        ]);
    }

    /** 5.2 ติดตามข้อมูลนิคมอุตสาหกรรม — KPI คลิกเพื่อกรองตาราง */
    public function estates(): void
    {
        Auth::requireAdmin();
        $this->renderEstates('admin/estates', 'app');
    }

    /** 5.3 ตรวจสอบสถานะการอัปโหลดไฟล์ */
    public function uploads(): void
    {
        Auth::requireAdmin();
        $this->renderUploads('admin/uploads', 'app');
    }

    /**
     * ใช้ร่วมกับหน้าที่แชร์ลิงก์สาธารณะ (PublicController::shared) จึงแยกเป็น
     * เมธอดเดียวกัน เพื่อไม่ให้เกิดหน้าจอซ้ำซ้อนแบบระบบเดิม
     */
    public function renderEstates(string $template, string $layout, bool $readOnly = false): void
    {
        $year   = Context::year();
        $filter = $this->input('kpi');
        $q      = $this->input('q');

        $rows = Database::all(
            'SELECT v.*,
                    (SELECT GROUP_CONCAT(o.college_name SEPARATOR "|")
                       FROM industrial_estate_responsibility r
                       JOIN provincial_vocational_offices o ON o.id = r.pveo_id
                      WHERE r.estate_id = v.estate_id AND r.is_active = 1) AS pveo_names,
                    (SELECT COALESCE(SUM(a.target_count), 0)
                       FROM pveo_estate_assignments a
                      WHERE a.estate_id = v.estate_id AND a.survey_year = ?) AS target_count
               FROM v_estate_progress v
           ORDER BY v.estate_name',
            [$year]
        );

        foreach ($rows as &$row) {
            $target = (int) $row['target_count'] ?: (int) $row['enterprise_total'];
            $row['target_effective'] = $target;
            $row['percent']  = pct((int) $row['surveyed_count'], $target);
            // ข้อมูลจริงมีกรณี 230.92% — ต้องแสดงให้เห็นว่าผิดปกติ ไม่ใช่ตัดทิ้ง
            $row['is_over']  = $row['percent'] !== null && $row['percent'] > 100;
            $row['pveo_list'] = array_filter(explode('|', (string) $row['pveo_names']));
        }
        unset($row);

        $rows = array_values(array_filter($rows, static function (array $r) use ($filter, $q): bool {
            if ($q !== '' && mb_stripos($r['estate_name'] . ' ' . $r['province_name'], $q) === false) {
                return false;
            }
            return match ($filter) {
                'recorded'   => (int) $r['surveyed_count'] > 0,
                'nostudent'  => (int) $r['no_student_count'] > 0,
                'incomplete' => $r['percent'] !== null && $r['percent'] < 100,
                'over'       => $r['is_over'],
                default      => true,
            };
        }));

        $this->view($template, [
            'title'    => 'ติดตามข้อมูลนิคมอุตสาหกรรม',
            'nav'      => 'estates',
            'rows'     => $rows,
            'kpis'     => $this->nationalKpis($year),
            'filter'   => $filter,
            'q'        => $q,
            'readOnly' => $readOnly,
        ], $layout);
    }

    public function renderUploads(string $template, string $layout, bool $readOnly = false): void
    {
        $year      = Context::year();
        $stepCount = Settings::int('report_step_count', 5);

        $offices = Database::all(
            'SELECT o.id, o.college_code, o.college_name,
                    COALESCE(p.province_name, "ไม่ระบุจังหวัด") AS province_name
               FROM provincial_vocational_offices o
          LEFT JOIN provinces p ON p.id = o.province_id
              WHERE o.is_active = 1
           ORDER BY o.college_name'
        );

        $progress = Database::all(
            'SELECT pveo_id, step_no, status, submitted_at,
                    (SELECT COUNT(*) FROM report_files f WHERE f.progress_id = rp.id) AS file_count
               FROM report_progress rp
              WHERE survey_year = ?',
            [$year]
        );

        $byOffice = [];
        foreach ($progress as $p) {
            $byOffice[(int) $p['pveo_id']][(int) $p['step_no']] = $p;
        }

        foreach ($offices as &$office) {
            $steps = [];
            $done  = 0;
            for ($i = 1; $i <= $stepCount; $i++) {
                $p = $byOffice[(int) $office['id']][$i] ?? null;
                $status = $p['status'] ?? 'pending';
                if ($status === 'complete') {
                    $done++;
                }
                $steps[$i] = ['status' => $status, 'files' => (int) ($p['file_count'] ?? 0)];
            }
            $office['steps']     = $steps;
            $office['done']      = $done;
            $office['stepCount'] = $stepCount;
        }
        unset($office);

        $this->view($template, [
            'title'     => 'ตรวจสอบสถานะการอัปโหลดไฟล์',
            'nav'       => 'uploads',
            'offices'   => $offices,
            'stepCount' => $stepCount,
            'readOnly'  => $readOnly,
        ], $layout);
    }

    /** จัดการ สอจ. — บัญชี / มอบหมายนิคมฯ / โควตา */
    public function assign(): void
    {
        Auth::requireAdmin();
        $year = Context::year();

        $rows = Database::all(
            'SELECT o.id AS pveo_id, o.college_code, o.college_name,
                    COALESCE(p.province_name, "ไม่ระบุจังหวัด") AS province_name,
                    o.password_hash IS NOT NULL AS has_password,
                    o.last_login_at,
                    COUNT(DISTINCT r.estate_id) AS estate_count,
                    COALESCE(SUM(a.target_count), 0)   AS target_total,
                    COALESCE(SUM(a.surveyed_count), 0) AS surveyed_total
               FROM provincial_vocational_offices o
          LEFT JOIN provinces p ON p.id = o.province_id
          LEFT JOIN industrial_estate_responsibility r ON r.pveo_id = o.id AND r.is_active = 1
          LEFT JOIN pveo_estate_assignments a ON a.pveo_id = o.id AND a.survey_year = ?
              WHERE o.is_active = 1
           GROUP BY o.id, o.college_code, o.college_name, p.province_name, o.password_hash, o.last_login_at
           ORDER BY o.college_name',
            [$year]
        );

        foreach ($rows as &$row) {
            $row['percent'] = pct((int) $row['surveyed_total'], (int) $row['target_total']);
            $row['is_over'] = $row['percent'] !== null && $row['percent'] > 100;
        }
        unset($row);

        $this->view('admin/assign', [
            'title' => 'จัดการ สอจ. และโควตา',
            'nav'   => 'assign',
            'rows'  => $rows,
        ]);
    }

    public function saveQuota(): void
    {
        Auth::requireAdmin();

        $pveoId   = (int) ($_POST['pveo_id'] ?? 0);
        $estateId = (int) ($_POST['estate_id'] ?? 0);
        $target   = max(0, (int) ($_POST['target_count'] ?? 0));

        if ($pveoId <= 0 || $estateId <= 0) {
            Session::flash('err', 'ข้อมูลไม่ครบถ้วน');
            Url::back('admin/assign');
        }

        // is_manual = 1 กัน SyncPveoEstateAssignments เขียนทับโควตาที่ตั้งเอง
        Database::run(
            'INSERT INTO pveo_estate_assignments (pveo_id, estate_id, survey_year, target_count, is_manual, updated_at)
             VALUES (?,?,?,?,1,NOW())
             ON DUPLICATE KEY UPDATE target_count = VALUES(target_count), is_manual = 1, updated_at = NOW()',
            [$pveoId, $estateId, Context::year(), $target]
        );

        Session::flash('ok', 'บันทึกโควตาเรียบร้อยแล้ว');
        Url::back('admin/assign');
    }

    public function syncAssignments(): void
    {
        Auth::requireAdmin();
        Database::run('CALL SyncPveoEstateAssignments(?)', [Context::year()]);
        Session::flash('ok', 'ปรับยอดสำรวจของปี ' . Context::year() . ' เรียบร้อยแล้ว (โควตาที่ตั้งเองไม่ถูกเขียนทับ)');
        Url::back('admin/assign');
    }

    public function impersonate(): void
    {
        Auth::requireAdmin();
        $result = Auth::impersonate((int) ($_POST['pveo_id'] ?? 0));
        Session::flash($result['ok'] ? 'warn' : 'err', $result['message']);
        Url::redirect($result['ok'] ? 'pveo' : 'admin/assign');
    }

    public function stopImpersonating(): void
    {
        Auth::stopImpersonating();
        Session::flash('ok', 'ออกจากโหมดสวมสิทธิ์แล้ว');
        Url::redirect('admin/assign');
    }

    public function settings(): void
    {
        Auth::requireAdmin();
        $year = Context::year();

        $this->view('admin/settings', [
            'title'    => 'ตั้งค่าระบบ',
            'nav'      => 'settings',
            'settings' => \App\Core\Settings::all(),
            'steps'    => Database::all(
                'SELECT * FROM report_steps WHERE survey_year = ? ORDER BY step_no',
                [$year]
            ),
            'shares'   => Database::all(
                'SELECT * FROM share_links WHERE revoked_at IS NULL ORDER BY created_at DESC LIMIT 20'
            ),
        ]);
    }

    public function saveSettings(): void
    {
        Auth::requireAdmin();

        Settings::putMany([
            'academic_year'       => $this->input('academic_year'),
            'survey_round'        => $this->input('survey_round', 'Yearly'),
            'report_step_count'   => (string) max(1, min(12, (int) $this->input('report_step_count', '5'))),
            'report_deadline'     => $this->input('report_deadline'),
            'site_name'           => $this->input('site_name', 'DVE PPP'),
            'site_tagline'        => $this->input('site_tagline'),
            'rows_per_page'       => (string) max(10, min(100, (int) $this->input('rows_per_page', '25'))),
            'allow_public_search' => isset($_POST['allow_public_search']) ? '1' : '0',
        ]);

        // ชื่อขั้นตอนเอกสารของปีนี้ — จำนวนขั้นตอนกำหนดค่าได้
        $names = (array) ($_POST['step_name'] ?? []);
        $dues  = (array) ($_POST['step_due'] ?? []);
        $year  = $this->input('academic_year') ?: Context::year();

        foreach ($names as $stepNo => $name) {
            $stepNo = (int) $stepNo;
            $name   = trim((string) $name);
            if ($stepNo < 1 || $name === '') {
                continue;
            }
            Database::run(
                'INSERT INTO report_steps (survey_year, step_no, step_name, due_date, is_enabled)
                 VALUES (?,?,?,?,1)
                 ON DUPLICATE KEY UPDATE step_name = VALUES(step_name), due_date = VALUES(due_date)',
                [$year, $stepNo, $name, ($dues[$stepNo] ?? '') ?: null]
            );
        }

        Session::flash('ok', 'บันทึกการตั้งค่าเรียบร้อยแล้ว');
        Url::redirect('admin/settings');
    }

    /** ปุ่มแชร์ลิงก์สาธารณะของหน้าติดตามผล (อ่านอย่างเดียว) */
    public function createShareLink(): void
    {
        Auth::requireAdmin();

        $target = $this->input('target', 'estates');
        if (!in_array($target, ['estates', 'uploads'], true)) {
            Session::flash('err', 'เป้าหมายของลิงก์ไม่ถูกต้อง');
            Url::back('admin/settings');
        }

        $token = bin2hex(random_bytes(20));
        Database::run(
            'INSERT INTO share_links (token, target, survey_year, created_by, expires_at)
             VALUES (?,?,?,?, DATE_ADD(NOW(), INTERVAL 90 DAY))',
            [$token, $target, Context::year(), Auth::id()]
        );

        Session::flash('ok', 'สร้างลิงก์แชร์แล้ว: ' . Url::to('share/' . $token));
        Url::back('admin/settings');
    }

    // ------------------------------------------------------------ ข้อมูล ----

    private function nationalKpis(string $year): array
    {
        $estateCount  = Database::int('SELECT COUNT(*) FROM industrial_estates WHERE is_active = 1');
        $targetTotal  = Database::int('SELECT COALESCE(SUM(enterprise_total), 0) FROM industrial_estates WHERE is_active = 1');
        $recorded     = Database::int('SELECT COUNT(*) FROM enterprises');
        $noStudent    = Database::int(
            'SELECT COUNT(DISTINCT enterprise_id) FROM surveys WHERE no_student_required = 1 AND survey_year = ?',
            [$year]
        );
        $percent = pct($recorded, $targetTotal);

        return [
            ['key' => '',           'icon' => '🏭', 'label' => 'นิคมอุตสาหกรรม',            'value' => num($estateCount), 'note' => 'ที่เปิดใช้งาน'],
            ['key' => '',           'icon' => '🏢', 'label' => 'สถานประกอบการทั้งหมด',     'value' => num($targetTotal), 'note' => 'เป้าหมายรวมทั้งประเทศ'],
            ['key' => 'recorded',   'icon' => '✔', 'label' => 'บันทึกเข้าระบบแล้ว',        'value' => num($recorded),    'note' => 'คลิกเพื่อกรองตาราง'],
            ['key' => 'nostudent',  'icon' => '⊘', 'label' => 'ไม่ประสงค์รับนักศึกษา',     'value' => num($noStudent),   'note' => 'ปีการศึกษา ' . $year],
            ['key' => 'incomplete', 'icon' => '％', 'label' => 'ร้อยละความคืบหน้า',        'value' => $percent === null ? '—' : num($percent, 2) . '%', 'note' => 'บันทึกแล้ว ÷ เป้าหมาย'],
        ];
    }

    private function regionProgress(): array
    {
        return Database::all(
            'SELECT COALESCE(g.name, "ไม่ระบุภาค") AS region,
                    COALESCE(SUM(e.enterprise_total), 0) AS target,
                    COUNT(DISTINCT en.id) AS recorded
               FROM industrial_estates e
          LEFT JOIN provinces p   ON p.id = e.province_id
          LEFT JOIN geographies g ON g.id = p.geography_id
          LEFT JOIN enterprises en ON en.estate_id = e.id
              WHERE e.is_active = 1
           GROUP BY g.name
           ORDER BY recorded DESC'
        );
    }

    /** ฝึกงาน vs ทวิภาคี */
    private function demandSplit(string $year): array
    {
        $rows = Database::all(
            'SELECT d.system_type,
                    COALESCE(SUM(d.vc_male + d.vc_female + d.hvc_male + d.hvc_female), 0) AS total
               FROM survey_demands d
               JOIN surveys s ON s.id = d.survey_id
              WHERE s.survey_year = ?
           GROUP BY d.system_type',
            [$year]
        );

        $out = ['internship' => 0, 'dve' => 0];
        foreach ($rows as $r) {
            $out[$r['system_type']] = (int) $r['total'];
        }
        return $out;
    }

    private function topCourses(string $year, int $limit = 10): array
    {
        return Database::all(
            'SELECT COALESCE(NULLIF(d.course_name, ""), d.course_code, "ไม่ระบุสาขา") AS course,
                    SUM(d.vc_male + d.vc_female + d.hvc_male + d.hvc_female) AS total
               FROM survey_demands d
               JOIN surveys s ON s.id = d.survey_id
              WHERE s.survey_year = ?
           GROUP BY course
             HAVING total > 0
           ORDER BY total DESC
              LIMIT ' . $limit,
            [$year]
        );
    }

    /** สอจ. ที่คืบหน้าน้อยที่สุด 10 อันดับ */
    private function laggards(string $year, int $limit = 10): array
    {
        $rows = Database::all(
            'SELECT o.id, o.college_name, o.college_code,
                    COALESCE(SUM(a.target_count), 0)   AS target_total,
                    COALESCE(SUM(a.surveyed_count), 0) AS surveyed_total
               FROM provincial_vocational_offices o
               JOIN pveo_estate_assignments a ON a.pveo_id = o.id AND a.survey_year = ?
              WHERE o.is_active = 1
           GROUP BY o.id, o.college_name, o.college_code
             HAVING target_total > 0
           ORDER BY (SUM(a.surveyed_count) / SUM(a.target_count)) ASC
              LIMIT ' . $limit,
            [$year]
        );

        foreach ($rows as &$row) {
            $row['percent'] = pct((int) $row['surveyed_total'], (int) $row['target_total']);
            $row['is_over'] = $row['percent'] !== null && $row['percent'] > 100;
        }
        return $rows;
    }
}
