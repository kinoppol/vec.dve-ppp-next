<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Context;
use App\Core\Database;
use App\Core\Settings;

final class PublicController extends Controller
{
    /** หน้าแรกสาธารณะ — สถิติภาพรวมประเทศ */
    public function home(): void
    {
        $year = Context::year();

        $this->view('pub/home', [
            'title' => 'ภาพรวมความร่วมมือทั้งประเทศ',
            'kpis'  => [
                ['icon' => '🏭', 'label' => 'นิคมอุตสาหกรรม',        'value' => num(Database::int('SELECT COUNT(*) FROM industrial_estates'))],
                ['icon' => '🏢', 'label' => 'สถานประกอบการในระบบ',  'value' => num(Database::int('SELECT COUNT(*) FROM enterprises'))],
                ['icon' => '👷', 'label' => 'ความต้องการกำลังคนรวม', 'value' => num($this->demandTotal($year))],
                ['icon' => '♿', 'label' => 'แห่งที่รับผู้พิการ',      'value' => num($this->disabilityFriendly($year))],
            ],
            'estates' => Database::all(
                'SELECT v.* FROM v_ppp_estate_progress v ORDER BY v.surveyed_count DESC LIMIT 10'
            ),
        ], 'public');
    }

    /** ค้นหาสถานประกอบการ (สาธารณะ อ่านอย่างเดียว) */
    public function search(): void
    {
        if (!Settings::bool('allow_public_search', true)) {
            $this->view('errors/404', ['title' => 'ปิดการค้นหาสาธารณะ'], 'public');
            return;
        }

        $q          = $this->input('q');
        $provinceId = (int) $this->input('province_id');
        $estateId   = (int) $this->input('estate_id');
        $perPage    = $this->perPage();
        $page       = $this->page();

        $where  = ['1 = 1'];
        $params = [];

        if ($q !== '') {
            $where[]  = '(e.name LIKE ? OR e.business_type LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        if ($provinceId > 0) {
            $where[]  = 'e.province_id = ?';
            $params[] = $provinceId;
        }
        if ($estateId > 0) {
            $where[]  = 'e.industrial_estate_id = ?';
            $params[] = $estateId;
        }

        $whereSql = implode(' AND ', $where);
        $total    = Database::int("SELECT COUNT(*) FROM enterprises e WHERE {$whereSql}", $params);
        $pages    = max(1, (int) ceil($total / $perPage));
        $page     = min($page, $pages);

        $rows = Database::all(
            "SELECT e.id, e.name AS enterprise_name, e.business_type,
                    est.industrial_estate_name AS estate_name,
                    COALESCE(p.province_name_th, 'ไม่ระบุจังหวัด') AS province_name,
                    COALESCE(c.score, 0) AS score
               FROM enterprises e
          LEFT JOIN industrial_estates est ON est.industrial_estate_id = e.industrial_estate_id
          LEFT JOIN provinces p ON p.province_id = e.province_id
          LEFT JOIN ppp_enterprise_completeness c ON c.enterprise_id = e.id
              WHERE {$whereSql}
           ORDER BY e.name
              LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage),
            $params
        );

        $this->view('pub/search', [
            'title'     => 'ค้นหาสถานประกอบการ',
            'rows'      => $rows,
            'total'     => $total,
            'page'      => $page,
            'pages'     => $pages,
            'q'         => $q,
            'provinceId' => $provinceId,
            'estateId'  => $estateId,
            'provinces' => Database::all('SELECT province_id AS id, province_name_th AS province_name FROM provinces ORDER BY province_name_th'),
            'estates'   => Database::all('SELECT industrial_estate_id AS id, industrial_estate_name AS estate_name FROM industrial_estates ORDER BY industrial_estate_name'),
        ], 'public');
    }

    public function downloads(): void
    {
        $this->view('pub/downloads', ['title' => 'ดาวน์โหลดแบบฟอร์มและคู่มือ'], 'public');
    }

    /**
     * หน้าติดตามผลที่ผู้ดูแลแชร์ลิงก์ให้ — ใช้ template เดียวกับหน้า Admin
     * แต่ซ่อนปุ่มที่แก้ไขข้อมูล (readOnly) เพื่อไม่ให้เกิดหน้าจอซ้ำซ้อน
     */
    public function shared(string $token): void
    {
        $link = Database::first(
            'SELECT * FROM share_links
              WHERE token = ? AND revoked_at IS NULL
                AND (expires_at IS NULL OR expires_at > NOW())',
            [$token]
        );

        if ($link === null) {
            http_response_code(404);
            $this->view('errors/404', ['title' => 'ลิงก์หมดอายุหรือถูกยกเลิก'], 'public');
            return;
        }

        Database::run('UPDATE share_links SET hit_count = hit_count + 1 WHERE id = ?', [$link['id']]);

        if (!empty($link['survey_year'])) {
            Context::setYear((string) $link['survey_year']);
        }

        $admin = new AdminController();
        if ($link['target'] === 'uploads') {
            $admin->renderUploads('admin/uploads', 'public', true);
            return;
        }
        $admin->renderEstates('admin/estates', 'public', true);
    }

    private function demandTotal(string $year): int
    {
        return Database::int(
            'SELECT COALESCE(SUM(d.vc_male + d.vc_female + d.hvc_male + d.hvc_female), 0)
               FROM survey_demands d
               JOIN surveys s ON s.id = d.survey_id
              WHERE s.survey_year = ?',
            [$year]
        );
    }

    private function disabilityFriendly(string $year): int
    {
        return Database::int(
            'SELECT COUNT(DISTINCT s.enterprise_id)
               FROM survey_demands d
               JOIN surveys s ON s.id = d.survey_id
              WHERE d.disability_flag = 1 AND s.survey_year = ?',
            [$year]
        );
    }
}
