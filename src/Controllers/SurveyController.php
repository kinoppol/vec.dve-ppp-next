<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Context;
use App\Core\Database;
use App\Core\Session;
use App\Core\Settings;
use App\Core\Url;

/**
 * แบบสำรวจ PPP-002 — หน้าเดิมยาวกว่า 1,200 บรรทัดใน 9 ส่วน ไม่มีตัวบอกความคืบหน้า
 * และไม่มีการบันทึกร่าง ที่นี่แบ่งเป็น 10 ขั้นตอน บันทึกร่างทุกครั้งที่กด "ถัดไป"
 * ผู้ใช้จึงออกกลางคันแล้วกลับมาทำต่อได้
 */
final class SurveyController extends Controller
{
    public const STEPS = [
        1  => 'ข้อมูลสถานประกอบการ',
        2  => 'การลงพื้นที่',
        3  => 'ประวัติการรับนักเรียน/นักศึกษา',
        4  => 'ความต้องการกำลังคน',
        5  => 'ครูฝึกประสบการณ์',
        6  => 'สวัสดิการ',
        7  => 'ข้อสรุปการประชุม',
        8  => 'ข้อเสนอแนะ',
        9  => 'ปัญหาและอุปสรรค',
        10 => 'ตรวจสอบและบันทึก',
    ];

    /** เปิดแบบสำรวจของสถานประกอบการ (สร้างร่างให้อัตโนมัติถ้ายังไม่มี) */
    public function wizard(string $enterpriseId): void
    {
        Auth::requirePveo();

        $enterpriseId = (int) $enterpriseId;
        $survey       = $this->findOrCreateDraft($enterpriseId);
        $step         = max(1, min(count(self::STEPS), (int) ($_GET['step'] ?? $survey['current_step'])));

        $enterprise = Database::first('SELECT * FROM enterprises WHERE id = ?', [$enterpriseId]);
        if ($enterprise === null) {
            http_response_code(404);
            $this->view('errors/404', ['title' => 'ไม่พบสถานประกอบการ']);
            return;
        }

        $this->view('pveo/survey', [
            'title'      => 'แบบสำรวจ PPP-002',
            'nav'        => 'enterprises',
            'steps'      => self::STEPS,
            'step'       => $step,
            'survey'     => $survey,
            'enterprise' => $enterprise,
            'demands'    => Database::all(
                'SELECT * FROM survey_demands WHERE survey_id = ? ORDER BY id',
                [$survey['id']]
            ),
            'notes'      => Database::all(
                'SELECT * FROM survey_meeting_notes WHERE survey_id = ? ORDER BY note_order, id',
                [$survey['id']]
            ),
            'trainings'  => Database::all(
                'SELECT * FROM survey_past_trainings WHERE survey_id = ? ORDER BY id',
                [$survey['id']]
            ),
            'courses'    => Database::all(
                'SELECT course_code, course_name, course_type, level
                   FROM vocational_curriculum WHERE is_active = 1
               ORDER BY course_type, course_name'
            ),
        ]);
    }

    /** บันทึกร่างของขั้นตอนปัจจุบัน แล้วไปขั้นถัดไป (หรือส่งแบบสำรวจ) */
    public function save(string $enterpriseId): void
    {
        Auth::requirePveo();

        $enterpriseId = (int) $enterpriseId;
        $survey       = $this->findOrCreateDraft($enterpriseId);
        $surveyId     = (int) $survey['id'];
        $step         = max(1, min(count(self::STEPS), (int) ($_POST['step'] ?? 1)));
        $action       = (string) ($_POST['action'] ?? 'next');

        match ($step) {
            1, 2    => $this->saveVisit($surveyId),
            3       => $this->savePastTrainings($surveyId),
            4       => $this->saveDemands($surveyId),
            5       => $this->saveTeacherTraining($surveyId),
            6       => $this->saveWelfare($surveyId),
            7       => $this->saveMeetingNotes($surveyId),
            8, 9    => $this->saveNarrative($surveyId),
            default => null,
        };

        $next = match ($action) {
            'prev'   => max(1, $step - 1),
            'submit' => $step,
            default  => min(count(self::STEPS), $step + 1),
        };

        Database::run(
            'UPDATE surveys SET current_step = ?, updated_at = NOW() WHERE id = ?',
            [max((int) $survey['current_step'], $next), $surveyId]
        );

        if ($action === 'submit') {
            $problems = $this->validateForSubmit($surveyId);
            if ($problems !== []) {
                foreach ($problems as $p) {
                    Session::flash('err', $p);
                }
                Url::redirect('pveo/survey/' . $enterpriseId, ['step' => $step]);
            }

            Database::run(
                'UPDATE surveys SET status = "submitted", certifier_name = ?, certifier_position = ?,
                        certifier_date = ?, updated_at = NOW()
                  WHERE id = ?',
                [
                    $this->input('certifier_name') ?: null,
                    $this->input('certifier_position') ?: null,
                    $this->input('certifier_date') ?: null,
                    $surveyId,
                ]
            );
            $this->refreshCounters($enterpriseId, (string) $survey['survey_year']);

            Session::flash('ok', 'ส่งแบบสำรวจเรียบร้อยแล้ว');
            Url::redirect('pveo/enterprises/' . $enterpriseId);
        }

        $this->refreshCounters($enterpriseId, (string) $survey['survey_year']);
        Session::flash('ok', 'บันทึกร่างขั้นตอนที่ ' . $step . ' แล้ว');
        Url::redirect('pveo/survey/' . $enterpriseId, ['step' => $next]);
    }

    /**
     * ปรับยอดสำรวจและคะแนนความสมบูรณ์หลังบันทึกแบบสำรวจ
     *
     * บนฐานข้อมูลร่วม แอปนี้ไม่ได้สร้าง trigger ของตัวเอง (จะยิงซ้อนกับของ
     * ระบบเดิมแล้วนับซ้ำ) จึงเรียกคำนวณเองตรงนี้แทน ทั้งสอง procedure เป็นแบบ
     * "คำนวณใหม่" ไม่ใช่ "บวกเพิ่ม" เรียกซ้ำกี่ครั้งผลก็เท่าเดิม และถ้าระบบเดิม
     * มี trigger คอยบวกให้อยู่แล้ว การคำนวณใหม่ก็ยังได้ค่าที่ถูกต้อง
     */
    private function refreshCounters(int $enterpriseId, string $year): void
    {
        Database::run('CALL PppRecountSurveyed(?, ?)', [$enterpriseId, $year]);
        Database::run('CALL PppRecalcEnterpriseCompleteness(?)', [$enterpriseId]);
    }

    // ------------------------------------------------------- ราย ขั้นตอน ----

    private function saveVisit(int $surveyId): void
    {
        Database::run(
            'UPDATE surveys SET survey_date = ?, no_student_required = ?, updated_at = NOW() WHERE id = ?',
            [
                $this->input('survey_date') ?: null,
                isset($_POST['no_student_required']) ? 1 : 0,
                $surveyId,
            ]
        );
    }

    private function savePastTrainings(int $surveyId): void
    {
        Database::run('DELETE FROM survey_past_trainings WHERE survey_id = ?', [$surveyId]);

        foreach ((array) ($_POST['training'] ?? []) as $t) {
            $college = trim((string) ($t['college_name'] ?? ''));
            if ($college === '') {
                continue;
            }
            Database::run(
                'INSERT INTO survey_past_trainings (survey_id, academic_year, college_name, course_name, student_count, system_type)
                 VALUES (?,?,?,?,?,?)',
                [
                    $surveyId,
                    ($t['academic_year'] ?? '') ?: null,
                    $college,
                    ($t['course_name'] ?? '') ?: null,
                    max(0, (int) ($t['student_count'] ?? 0)),
                    in_array($t['system_type'] ?? '', ['internship', 'dve'], true) ? $t['system_type'] : null,
                ]
            );
        }
    }

    /** ความต้องการกำลังคน — แถวละสาขา แยก ปวช./ปวส. และ ชาย/หญิง */
    private function saveDemands(int $surveyId): void
    {
        Database::run('DELETE FROM survey_demands WHERE survey_id = ?', [$surveyId]);

        foreach ((array) ($_POST['demand'] ?? []) as $d) {
            $course = trim((string) ($d['course_name'] ?? ''));
            $counts = [
                max(0, (int) ($d['vc_male'] ?? 0)),
                max(0, (int) ($d['vc_female'] ?? 0)),
                max(0, (int) ($d['hvc_male'] ?? 0)),
                max(0, (int) ($d['hvc_female'] ?? 0)),
            ];
            if ($course === '' && array_sum($counts) === 0) {
                continue;
            }

            Database::run(
                'INSERT INTO survey_demands
                    (survey_id, system_type, course_code, course_name, vc_male, vc_female,
                     hvc_male, hvc_female, disability_flag, job_description, required_skills)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $surveyId,
                    in_array($d['system_type'] ?? '', ['internship', 'dve'], true) ? $d['system_type'] : 'internship',
                    ($d['course_code'] ?? '') ?: null,
                    $course ?: null,
                    $counts[0], $counts[1], $counts[2], $counts[3],
                    empty($d['disability_flag']) ? 0 : 1,
                    ($d['job_description'] ?? '') ?: null,
                    ($d['required_skills'] ?? '') ?: null,
                ]
            );

            $demandId = (int) Database::pdo()->lastInsertId();
            foreach ((array) ($d['disability_type'] ?? []) as $type => $qty) {
                $qty = (int) $qty;
                if ($qty > 0) {
                    Database::run(
                        'INSERT INTO survey_demand_disabilities (demand_id, disability_type, quantity) VALUES (?,?,?)',
                        [$demandId, (string) $type, $qty]
                    );
                }
            }
        }
    }

    private function saveTeacherTraining(int $surveyId): void
    {
        Database::run(
            'UPDATE surveys SET teacher_training_status = ?, updated_at = NOW() WHERE id = ?',
            [$this->input('teacher_training_status') ?: null, $surveyId]
        );
    }

    private function saveWelfare(int $surveyId): void
    {
        Database::run(
            'UPDATE surveys SET welfare_accommodation = ?, welfare_meal = ?, welfare_transport = ?,
                    welfare_allowance = ?, welfare_insurance = ?, welfare_other = ?, updated_at = NOW()
              WHERE id = ?',
            [
                isset($_POST['welfare_accommodation']) ? 1 : 0,
                isset($_POST['welfare_meal']) ? 1 : 0,
                isset($_POST['welfare_transport']) ? 1 : 0,
                isset($_POST['welfare_allowance']) ? 1 : 0,
                isset($_POST['welfare_insurance']) ? 1 : 0,
                $this->input('welfare_other') ?: null,
                $surveyId,
            ]
        );
    }

    private function saveMeetingNotes(int $surveyId): void
    {
        Database::run('DELETE FROM survey_meeting_notes WHERE survey_id = ?', [$surveyId]);

        $order = 0;
        foreach ((array) ($_POST['note'] ?? []) as $n) {
            $conclusion = trim((string) ($n['conclusion'] ?? ''));
            if ($conclusion === '') {
                continue;
            }
            Database::run(
                'INSERT INTO survey_meeting_notes (survey_id, topic, conclusion, note_order) VALUES (?,?,?,?)',
                [$surveyId, ($n['topic'] ?? '') ?: null, $conclusion, ++$order]
            );
        }
    }

    private function saveNarrative(int $surveyId): void
    {
        $field = isset($_POST['suggestion_text']) ? 'suggestion_text' : 'problem_obstacle';
        Database::run(
            "UPDATE surveys SET {$field} = ?, updated_at = NOW() WHERE id = ?",
            [$this->input($field) ?: null, $surveyId]
        );
    }

    /** @return string[] */
    private function validateForSubmit(int $surveyId): array
    {
        $problems = [];
        $survey   = Database::first('SELECT * FROM surveys WHERE id = ?', [$surveyId]);

        if ($survey === null) {
            return ['ไม่พบแบบสำรวจ'];
        }
        if (($survey['survey_date'] ?? null) === null) {
            $problems[] = 'ขั้นตอนที่ 2: กรุณาระบุวันที่ลงพื้นที่';
        }
        if ($this->input('certifier_name') === '') {
            $problems[] = 'ขั้นตอนที่ 10: กรุณาระบุชื่อผู้รับรองข้อมูล';
        }

        $hasDemand = Database::int('SELECT COUNT(*) FROM survey_demands WHERE survey_id = ?', [$surveyId]) > 0;
        if (!$hasDemand && (int) $survey['no_student_required'] === 0) {
            $problems[] = 'ขั้นตอนที่ 4: ต้องระบุความต้องการกำลังคนอย่างน้อย 1 รายการ '
                        . 'หรือเลือก "ไม่ประสงค์รับนักเรียน/นักศึกษา" ในขั้นตอนที่ 2';
        }

        return $problems;
    }

    private function findOrCreateDraft(int $enterpriseId): array
    {
        $year  = Context::year();
        $round = (string) Settings::get('survey_round', 'Yearly');

        $survey = Database::first(
            'SELECT * FROM surveys WHERE enterprise_id = ? AND survey_year = ? AND survey_round = ?',
            [$enterpriseId, $year, $round]
        );
        if ($survey !== null) {
            return $survey;
        }

        Database::run(
            'INSERT INTO surveys (enterprise_id, pveo_id, survey_year, survey_round, status, current_step)
             VALUES (?,?,?,?,"draft",1)',
            [$enterpriseId, Auth::id(), $year, $round]
        );

        return (array) Database::first(
            'SELECT * FROM surveys WHERE id = ?',
            [(int) Database::pdo()->lastInsertId()]
        );
    }
}
