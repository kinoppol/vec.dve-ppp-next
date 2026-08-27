<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Context;
use App\Core\Database;
use App\Core\Session;
use App\Core\Url;

final class EnterpriseController extends Controller
{
    /** แดชบอร์ดของ สอจ. — ความคืบหน้าเทียบโควตา */
    public function dashboard(): void
    {
        Auth::requirePveo();
        $year   = Context::year();
        $pveoId = (int) Auth::id();

        $assignments = Database::all(
            'SELECT a.*, e.estate_name, COALESCE(p.province_name, "ไม่ระบุจังหวัด") AS province_name
               FROM pveo_estate_assignments a
               JOIN industrial_estates e ON e.id = a.estate_id
          LEFT JOIN provinces p ON p.id = e.province_id
              WHERE a.pveo_id = ? AND a.survey_year = ?
           ORDER BY e.estate_name',
            [$pveoId, $year]
        );

        $targetTotal = 0;
        $doneTotal   = 0;
        foreach ($assignments as &$a) {
            $a['percent'] = pct((int) $a['surveyed_count'], (int) $a['target_count']);
            $a['is_over'] = $a['percent'] !== null && $a['percent'] > 100;
            $targetTotal += (int) $a['target_count'];
            $doneTotal   += (int) $a['surveyed_count'];
        }
        unset($a);

        $this->view('pveo/dashboard', [
            'title'       => 'แดชบอร์ดของฉัน',
            'nav'         => 'pveo',
            'assignments' => $assignments,
            'targetTotal' => $targetTotal,
            'doneTotal'   => $doneTotal,
            'percent'     => pct($doneTotal, $targetTotal),
            'draftCount'  => Database::int(
                'SELECT COUNT(*) FROM surveys WHERE pveo_id = ? AND survey_year = ? AND status = "draft"',
                [$pveoId, $year]
            ),
        ]);
    }

    /** 5.5 รายการสถานประกอบการ — ค้นหา + กรอง + เรียง + แบ่งหน้า */
    public function index(): void
    {
        Auth::requirePveo();

        $year     = Context::year();
        $estateId = Context::activeEstateId();
        if ($estateId === null) {
            Session::flash('warn', 'ยังไม่ได้รับมอบหมายนิคมอุตสาหกรรม กรุณาติดต่อผู้ดูแลระบบ');
        }

        $q      = $this->input('q');
        $status = $this->input('status');
        $sort   = in_array($this->input('sort'), ['name', 'score', 'updated'], true) ? $this->input('sort') : 'name';
        $perPage = $this->perPage();
        $page    = $this->page();

        $where  = ['e.estate_id <=> ?'];
        $params = [$estateId];

        if ($q !== '') {
            $where[]  = '(e.enterprise_name LIKE ? OR e.business_type LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        if ($status === 'surveyed') {
            $where[]  = 'EXISTS (SELECT 1 FROM surveys s WHERE s.enterprise_id = e.id AND s.survey_year = ?)';
            $params[] = $year;
        } elseif ($status === 'pending') {
            $where[]  = 'NOT EXISTS (SELECT 1 FROM surveys s WHERE s.enterprise_id = e.id AND s.survey_year = ?)';
            $params[] = $year;
        } elseif ($status === 'nostudent') {
            $where[]  = 'EXISTS (SELECT 1 FROM surveys s WHERE s.enterprise_id = e.id AND s.survey_year = ? AND s.no_student_required = 1)';
            $params[] = $year;
        }

        $whereSql = implode(' AND ', $where);
        $orderSql = match ($sort) {
            'score'   => 'COALESCE(c.score, 0) DESC',
            'updated' => 'e.updated_at DESC',
            default   => 'e.enterprise_name',
        };

        $total = Database::int("SELECT COUNT(*) FROM enterprises e WHERE {$whereSql}", $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page  = min($page, $pages);

        $rows = Database::all(
            "SELECT e.*, COALESCE(c.score, 0) AS score,
                    (SELECT s.id FROM surveys s
                      WHERE s.enterprise_id = e.id AND s.survey_year = ?
                      ORDER BY s.id DESC LIMIT 1) AS survey_id,
                    (SELECT s.status FROM surveys s
                      WHERE s.enterprise_id = e.id AND s.survey_year = ?
                      ORDER BY s.id DESC LIMIT 1) AS survey_status,
                    (SELECT s.no_student_required FROM surveys s
                      WHERE s.enterprise_id = e.id AND s.survey_year = ?
                      ORDER BY s.id DESC LIMIT 1) AS no_student
               FROM enterprises e
          LEFT JOIN enterprise_completeness c ON c.enterprise_id = e.id
              WHERE {$whereSql}
           ORDER BY {$orderSql}
              LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage),
            array_merge([$year, $year, $year], $params)
        );

        $this->view('pveo/enterprises', [
            'title'    => 'สถานประกอบการ',
            'nav'      => 'enterprises',
            'rows'     => $rows,
            'total'    => $total,
            'page'     => $page,
            'pages'    => $pages,
            'perPage'  => $perPage,
            'q'        => $q,
            'status'   => $status,
            'sort'     => $sort,
        ]);
    }

    public function create(): void
    {
        Auth::requirePveo();
        $this->view('pveo/enterprise_form', [
            'title'     => 'เพิ่มสถานประกอบการ',
            'nav'       => 'enterprises',
            'provinces' => Database::all('SELECT id, province_name FROM provinces ORDER BY province_name'),
            'row'       => null,
        ]);
    }

    public function store(): void
    {
        Auth::requirePveo();

        $name = $this->input('enterprise_name');
        if ($name === '') {
            Session::flash('err', 'กรุณากรอกชื่อสถานประกอบการ');
            Url::redirect('pveo/enterprises/new');
        }

        Database::run(
            'INSERT INTO enterprises
                (enterprise_name, business_type, estate_id, province_id, address, phone, email,
                 contact_name, contact_position, contact_phone, created_by_pveo, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())',
            [
                $name,
                $this->input('business_type') ?: null,
                Context::activeEstateId(),
                (int) $this->input('province_id') ?: null,
                $this->input('address') ?: null,
                $this->input('phone') ?: null,
                $this->input('email') ?: null,
                $this->input('contact_name') ?: null,
                $this->input('contact_position') ?: null,
                $this->input('contact_phone') ?: null,
                Auth::id(),
            ]
        );

        $id = (int) Database::pdo()->lastInsertId();
        Database::run('CALL RecalcEnterpriseCompleteness(?)', [$id]);

        Session::flash('ok', 'บันทึกสถานประกอบการเรียบร้อยแล้ว');
        Url::redirect('pveo/enterprises/' . $id);
    }

    public function show(string $id): void
    {
        Auth::requirePveo();
        $id = (int) $id;

        $row = Database::first(
            'SELECT e.*, COALESCE(c.score, 0) AS score, c.missing_sections,
                    est.estate_name, COALESCE(p.province_name, "ไม่ระบุจังหวัด") AS province_name
               FROM enterprises e
          LEFT JOIN enterprise_completeness c ON c.enterprise_id = e.id
          LEFT JOIN industrial_estates est ON est.id = e.estate_id
          LEFT JOIN provinces p ON p.id = e.province_id
              WHERE e.id = ?',
            [$id]
        );

        if ($row === null) {
            http_response_code(404);
            $this->view('errors/404', ['title' => 'ไม่พบสถานประกอบการ']);
            return;
        }

        $this->view('pveo/enterprise_detail', [
            'title'   => $row['enterprise_name'],
            'nav'     => 'enterprises',
            'row'     => $row,
            'surveys' => Database::all(
                'SELECT * FROM surveys WHERE enterprise_id = ? ORDER BY survey_year DESC, id DESC',
                [$id]
            ),
        ]);
    }
}
