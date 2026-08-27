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

        $demand    = $this->demandBySystem($year);   // ['dve' => [vc, hvc], 'internship' => [...]]
        $dve       = $demand['dve'];
        $intern    = $demand['internship'];
        $estates   = Database::int('SELECT COUNT(*) FROM industrial_estates');
        $companies = Database::int('SELECT COUNT(*) FROM enterprises');
        $pveoTotal = Database::int('SELECT COUNT(*) FROM provincial_vocational_offices');
        $disabled  = $this->disabilityStats($year);

        // ตัวหารร่วมของการ์ดที่เล่าเป็นสัดส่วนได้ — นับเฉพาะที่สำรวจแล้วในปีนี้
        $surveyed = Database::int(
            'SELECT COUNT(DISTINCT s.enterprise_id) FROM surveys s WHERE s.survey_year = ?',
            [$year]
        );
        $pveoDone    = $this->pveoSurveyed($year);
        $teacher     = $this->teacherTrainingPlaces($year);
        $welfare     = $this->welfarePlaces($year);
        $ofSurveyed  = 'จาก ' . num($surveyed) . ' แห่งที่สำรวจแล้ว';

        $this->view('pub/home', [
            'title' => 'ภาพรวมความร่วมมือทั้งประเทศ',
            'kpis'  => [
                [
                    'icon'  => '🏭',
                    'label' => 'นิคมอุตสาหกรรมและสถานประกอบการ',
                    'value' => num($estates),
                    'unit'  => 'นิคม',
                    'extra' => num($companies),
                    'extraUnit' => 'สถานประกอบการ',
                ],
                [
                    'icon'  => '👷',
                    'label' => 'ความต้องการรับนักเรียน นักศึกษา รวม',
                    'value' => num($dve['vc'] + $dve['hvc'] + $intern['vc'] + $intern['hvc']),
                    'unit'  => 'คน',
                    'hint'  => 'ทวิภาคีและฝึกงานรวมกัน',
                ],
                [
                    'icon'  => '🤝',
                    'label' => 'ทวิภาคี (ปวช. / ปวส.)',
                    'value' => num($dve['vc']),
                    'unit'  => 'ปวช.',
                    'extra' => num($dve['hvc']),
                    'extraUnit' => 'ปวส.',
                    'bar'   => pct($dve['vc'], $dve['vc'] + $dve['hvc'], 0),
                    'barClass' => 'c1',
                    'hint'  => 'แถบคือสัดส่วน ปวช.',
                ],
                [
                    'icon'  => '🧑‍🏭',
                    'label' => 'ฝึกงาน (ปวช. / ปวส.)',
                    'value' => num($intern['vc']),
                    'unit'  => 'ปวช.',
                    'extra' => num($intern['hvc']),
                    'extraUnit' => 'ปวส.',
                    'bar'   => pct($intern['vc'], $intern['vc'] + $intern['hvc'], 0),
                    'barClass' => 'c3',
                    'hint'  => 'แถบคือสัดส่วน ปวช.',
                ],
                [
                    'icon'  => '♿',
                    'label' => 'ข้อมูลการรับผู้พิการ',
                    'value' => num($disabled['places']),
                    'unit'  => 'แห่ง',
                    'extra' => num($disabled['people']),
                    'extraUnit' => 'คน',
                    'bar'   => pct($disabled['places'], $surveyed, 0),
                    'barClass' => 'c5',
                    'hint'  => $ofSurveyed,
                ],
                [
                    'icon'  => '🧑‍🏫',
                    'label' => 'รับครูของสถานศึกษาเข้าฝึกประสบการณ์อาชีพ',
                    'value' => num($teacher),
                    'unit'  => 'แห่ง',
                    'bar'   => pct($teacher, $surveyed, 0),
                    'barClass' => 'c6',
                    'hint'  => $ofSurveyed,
                ],
                [
                    'icon'  => '🗺',
                    'label' => 'สอจ. ที่ออกสำรวจแล้ว',
                    'value' => num($pveoDone),
                    'unit'  => 'สอจ.',
                    'extra' => num($pveoTotal),
                    'extraUnit' => 'ทั้งหมด',
                    'bar'   => pct($pveoDone, $pveoTotal, 0),
                    'barClass' => 'c2',
                ],
                [
                    'icon'  => '🏫',
                    'label' => 'สถานศึกษาที่ออกสำรวจแล้ว',
                    'value' => num($this->collegesSurveyed($year)),
                    'unit'  => 'แห่ง',
                ],
                [
                    'icon'  => '🎁',
                    'label' => 'สถานประกอบการที่มีสวัสดิการ',
                    'value' => num($welfare),
                    'unit'  => 'แห่ง',
                    'bar'   => pct($welfare, $surveyed, 0),
                    'barClass' => 'c4',
                    'hint'  => $ofSurveyed,
                ],
            ],
            'demandSplit'   => $this->demandSplit($dve, $intern),
            'businessTypes' => $this->businessTypeShare(),
            'topDve'        => $this->topCourses($year, 'dve'),
            'topIntern'     => $this->topCourses($year, 'internship'),
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

    /**
     * ความต้องการกำลังคนแยกตามระบบ (ทวิภาคี / ฝึกงาน) และระดับชั้น
     * คิวรีเดียวแล้วแยกในฝั่ง PHP — หน้าแรกสาธารณะเปิดบ่อย จึงไม่ยิงซ้ำสี่รอบ
     *
     * @return array{dve: array{vc: int, hvc: int}, internship: array{vc: int, hvc: int}}
     */
    private function demandBySystem(string $year): array
    {
        $out = ['dve' => ['vc' => 0, 'hvc' => 0], 'internship' => ['vc' => 0, 'hvc' => 0]];

        $rows = Database::all(
            'SELECT d.system_type,
                    COALESCE(SUM(d.vc_male  + d.vc_female), 0)  AS vc,
                    COALESCE(SUM(d.hvc_male + d.hvc_female), 0) AS hvc
               FROM survey_demands d
               JOIN surveys s ON s.id = d.survey_id
              WHERE s.survey_year = ?
           GROUP BY d.system_type',
            [$year]
        );

        foreach ($rows as $row) {
            $key = (string) $row['system_type'];
            if (isset($out[$key])) {
                $out[$key] = ['vc' => (int) $row['vc'], 'hvc' => (int) $row['hvc']];
            }
        }

        return $out;
    }

    /**
     * ข้อมูลของแผนภูมิแท่งเรียงต่อ — ความต้องการทั้งหมดแบ่งเป็นสี่ก้อน
     * ให้เห็นทันทีว่าสัดส่วนทวิภาคี/ฝึกงาน และ ปวช./ปวส. เอียงไปทางไหน
     *
     * @param array{vc: int, hvc: int} $dve
     * @param array{vc: int, hvc: int} $intern
     * @return array{total: int, parts: list<array{label: string, value: int, share: float, class: string}>}
     */
    private function demandSplit(array $dve, array $intern): array
    {
        $parts = [
            ['label' => 'ทวิภาคี ปวช.', 'value' => $dve['vc'],    'class' => 'c1'],
            ['label' => 'ทวิภาคี ปวส.', 'value' => $dve['hvc'],   'class' => 'c2'],
            ['label' => 'ฝึกงาน ปวช.',  'value' => $intern['vc'],  'class' => 'c3'],
            ['label' => 'ฝึกงาน ปวส.',  'value' => $intern['hvc'], 'class' => 'c4'],
        ];

        $total = array_sum(array_column($parts, 'value'));
        foreach ($parts as $i => $part) {
            $parts[$i]['share'] = (float) (pct($part['value'], $total, 1) ?? 0);
        }

        return ['total' => $total, 'parts' => $parts];
    }

    /**
     * การรับผู้พิการ — นับทั้งจำนวนแห่งและจำนวนคนที่ประกาศรับ
     * disability_flag เป็น enum('yes','no') ไม่ใช่ 0/1 ต้องเทียบด้วยสตริง
     *
     * @return array{places: int, people: int}
     */
    private function disabilityStats(string $year): array
    {
        $row = Database::first(
            "SELECT COUNT(DISTINCT s.enterprise_id) AS places,
                    COALESCE(SUM(d.vc_male + d.vc_female + d.hvc_male + d.hvc_female), 0) AS people
               FROM survey_demands d
               JOIN surveys s ON s.id = d.survey_id
              WHERE d.disability_flag = 'yes' AND s.survey_year = ?",
            [$year]
        );

        return [
            'places' => (int) ($row['places'] ?? 0),
            'people' => (int) ($row['people'] ?? 0),
        ];
    }

    private function teacherTrainingPlaces(string $year): int
    {
        return Database::int(
            "SELECT COUNT(DISTINCT s.enterprise_id)
               FROM surveys s
              WHERE s.teacher_training_status = 'yes' AND s.survey_year = ?",
            [$year]
        );
    }

    private function pveoSurveyed(string $year): int
    {
        return Database::int(
            'SELECT COUNT(DISTINCT s.pveo_id) FROM surveys s WHERE s.survey_year = ?',
            [$year]
        );
    }

    private function collegesSurveyed(string $year): int
    {
        return Database::int(
            "SELECT COUNT(DISTINCT s.target_college_code)
               FROM surveys s
              WHERE s.survey_year = ? AND s.target_college_code <> ''",
            [$year]
        );
    }

    /** มีสวัสดิการอย่างน้อยหนึ่งอย่าง — คอลัมน์ welfare_* ทั้งหมดเป็น tinyint(1) */
    private function welfarePlaces(string $year): int
    {
        return Database::int(
            'SELECT COUNT(DISTINCT s.enterprise_id)
               FROM surveys s
              WHERE s.survey_year = ?
                AND (s.welfare_scholarship = 1 OR s.welfare_allowance = 1
                  OR s.welfare_accident = 1 OR s.welfare_uniform = 1
                  OR s.welfare_accommodation = 1 OR s.welfare_other_flag = 1)',
            [$year]
        );
    }

    /**
     * สัดส่วนลักษณะกิจการ — หกอันดับแรก ที่เหลือยุบเป็น "อื่น ๆ"
     *
     * @return list<array{label: string, count: int, share: float}>
     */
    private function businessTypeShare(): array
    {
        $total = Database::int("SELECT COUNT(*) FROM enterprises WHERE business_type <> ''");
        if ($total === 0) {
            return [];
        }

        $rows = Database::all(
            "SELECT e.business_type AS label, COUNT(*) AS n
               FROM enterprises e
              WHERE e.business_type <> ''
           GROUP BY e.business_type
           ORDER BY n DESC
              LIMIT 6"
        );

        $out    = [];
        $listed = 0;
        foreach ($rows as $row) {
            $n = (int) $row['n'];
            $listed += $n;
            $out[] = [
                'label' => (string) $row['label'],
                'count' => $n,
                'share' => (float) (pct($n, $total, 0) ?? 0),
                'class' => 'c' . (count($out) % 8 + 1),
            ];
        }

        if ($listed < $total) {
            $rest  = $total - $listed;
            $out[] = [
                'label' => 'อื่น ๆ',
                'count' => $rest,
                'share' => (float) (pct($rest, $total, 0) ?? 0),
                'class' => 'c8',   // "อื่น ๆ" ใช้สีเทากลาง ๆ เสมอ ไม่ปนกับหมวดจริง
            ];
        }

        return $out;
    }

    /**
     * 10 อันดับสาขาวิชาที่ต้องการมากที่สุดของระบบหนึ่ง ๆ
     * ปวช. กับ ปวส. อยู่คนละคอลัมน์ จึง UNION ให้เป็นคนละแถวเหมือนระบบเดิม
     *
     * @return list<array{label: string, total: int, share: float}>
     */
    private function topCourses(string $year, string $systemType): array
    {
        $rows = Database::all(
            "SELECT t.course_name, t.level, SUM(t.total) AS total FROM (
                SELECT COALESCE(c.course_name, d.course_code) AS course_name,
                       'ปวช.' AS level,
                       SUM(d.vc_male + d.vc_female) AS total
                  FROM survey_demands d
                  JOIN surveys s ON s.id = d.survey_id
             LEFT JOIN vocational_curriculum c ON c.course_code = d.course_code
                 WHERE s.survey_year = ? AND d.system_type = ?
              GROUP BY course_name
                 UNION ALL
                SELECT COALESCE(c.course_name, d.course_code),
                       'ปวส.',
                       SUM(d.hvc_male + d.hvc_female)
                  FROM survey_demands d
                  JOIN surveys s ON s.id = d.survey_id
             LEFT JOIN vocational_curriculum c ON c.course_code = d.course_code
                 WHERE s.survey_year = ? AND d.system_type = ?
              GROUP BY course_name
             ) t
             GROUP BY t.course_name, t.level
             HAVING total > 0
             ORDER BY total DESC
                LIMIT 10",
            [$year, $systemType, $year, $systemType]
        );

        if ($rows === []) {
            return [];
        }

        $max = max(array_map(static fn (array $r): int => (int) $r['total'], $rows));

        $out = [];
        foreach ($rows as $i => $r) {
            $out[] = [
                'label' => $r['course_name'] . ' - ' . $r['level'],
                'total' => (int) $r['total'],
                // เทียบกับอันดับหนึ่ง เพื่อให้แท่งยาวเต็มกรอบเหมือนกราฟแท่งของระบบเดิม
                'share' => $max > 0 ? round((int) $r['total'] * 100 / $max) : 0,
                'class' => 'c' . ($i % 8 + 1),
            ];
        }

        return $out;
    }
}
