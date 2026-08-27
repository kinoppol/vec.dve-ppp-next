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
 * 5.6 รายงานความคืบหน้า / อัปโหลดเอกสารตามขั้นตอน
 *
 * ระบบเดิมออกแบบไว้ 5 ขั้นตอนแต่เปิดใช้จริง 2 ขั้นตอน ที่นี่จำนวนขั้นตอน
 * มาจาก app_settings.report_step_count และชื่อขั้นตอนมาจากตาราง report_steps
 * ขั้นตอนถัดไปจะ "ล็อก" จนกว่าขั้นก่อนหน้าจะครบ
 */
final class ProgressController extends Controller
{
    private const ALLOWED_MIME = [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ];
    private const MAX_BYTES = 8388608; // 8 MB

    public function index(): void
    {
        Auth::requirePveo();

        $year     = Context::year();
        $pveoId   = (int) Auth::id();
        $estateId = Context::activeEstateId();
        $steps    = $this->stepsFor($year);

        $existing = Database::all(
            'SELECT rp.*, (SELECT COUNT(*) FROM report_files f WHERE f.progress_id = rp.id) AS file_count
               FROM report_progress rp
              WHERE rp.pveo_id = ? AND rp.survey_year = ? AND rp.estate_id <=> ?',
            [$pveoId, $year, $estateId]
        );

        $byStep = [];
        foreach ($existing as $row) {
            $byStep[(int) $row['step_no']] = $row;
        }

        $previousComplete = true;
        foreach ($steps as $no => &$step) {
            $row = $byStep[$no] ?? null;
            $step['progress_id'] = $row['id'] ?? null;
            $step['files']       = (int) ($row['file_count'] ?? 0);
            $step['submitted_at'] = $row['submitted_at'] ?? null;

            if (!$previousComplete) {
                $step['status'] = 'locked';   // รอขั้นตอนก่อนหน้าเสร็จ
            } elseif ($step['files'] >= (int) $step['min_files']) {
                $step['status'] = 'complete';
            } elseif ($step['files'] > 0) {
                $step['status'] = 'partial';
            } else {
                $step['status'] = 'pending';
            }
            $previousComplete = $step['status'] === 'complete';

            $step['overdue'] = $step['due_date'] !== null
                && $step['status'] !== 'complete'
                && $step['due_date'] < date('Y-m-d');
        }
        unset($step);

        $this->view('pveo/progress', [
            'title' => 'รายงานความคืบหน้า',
            'nav'   => 'progress',
            'steps' => $steps,
            'files' => Database::all(
                'SELECT f.*, rp.step_no
                   FROM report_files f
                   JOIN report_progress rp ON rp.id = f.progress_id
                  WHERE rp.pveo_id = ? AND rp.survey_year = ? AND rp.estate_id <=> ?
               ORDER BY f.uploaded_at DESC',
                [$pveoId, $year, $estateId]
            ),
        ]);
    }

    public function upload(): void
    {
        Auth::requirePveo();

        $year     = Context::year();
        $pveoId   = (int) Auth::id();
        $estateId = Context::activeEstateId();
        $stepNo   = (int) ($_POST['step_no'] ?? 0);
        $steps    = $this->stepsFor($year);

        if (!isset($steps[$stepNo])) {
            Session::flash('err', 'ขั้นตอนที่เลือกไม่ถูกต้อง');
            Url::redirect('pveo/progress');
        }
        if (!isset($_FILES['document']) || ($_FILES['document']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            Session::flash('err', 'กรุณาเลือกไฟล์ที่จะอัปโหลด');
            Url::redirect('pveo/progress');
        }

        $file = $_FILES['document'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            Session::flash('err', 'อัปโหลดไม่สำเร็จ (รหัสข้อผิดพลาด ' . (int) $file['error'] . ')');
            Url::redirect('pveo/progress');
        }
        if ((int) $file['size'] > self::MAX_BYTES) {
            Session::flash('err', 'ไฟล์ใหญ่เกิน ' . file_size_human(self::MAX_BYTES));
            Url::redirect('pveo/progress');
        }

        $mime = $this->detectMime($file['tmp_name']);
        if (!isset(self::ALLOWED_MIME[$mime])) {
            Session::flash('err', 'รองรับเฉพาะไฟล์ PDF, Word, JPG และ PNG เท่านั้น (ตรวจพบ ' . $mime . ')');
            Url::redirect('pveo/progress');
        }

        // หาหรือสร้างแถวขั้นตอนของปี/นิคมฯ นี้
        Database::run(
            'INSERT INTO report_progress (pveo_id, estate_id, survey_year, step_no, step_name, status, due_date)
             VALUES (?,?,?,?,?, "partial", ?)
             ON DUPLICATE KEY UPDATE step_name = VALUES(step_name)',
            [$pveoId, $estateId, $year, $stepNo, $steps[$stepNo]['step_name'], $steps[$stepNo]['due_date']]
        );

        $progress = Database::first(
            'SELECT * FROM report_progress
              WHERE pveo_id = ? AND survey_year = ? AND step_no = ? AND estate_id <=> ?',
            [$pveoId, $year, $stepNo, $estateId]
        );
        $progressId = (int) $progress['id'];

        $stored = sprintf('%s_%d_%d_%s.%s', $year, $pveoId, $stepNo, bin2hex(random_bytes(8)), self::ALLOWED_MIME[$mime]);
        $target = APP_ROOT . '/uploads/reports/' . $stored;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            Session::flash('err', 'บันทึกไฟล์ลงเซิร์ฟเวอร์ไม่สำเร็จ — ตรวจสอบสิทธิ์การเขียนโฟลเดอร์ uploads/reports');
            Url::redirect('pveo/progress');
        }

        Database::run(
            'INSERT INTO report_files (progress_id, original_name, stored_name, mime_type, file_size, uploaded_by)
             VALUES (?,?,?,?,?,?)',
            [$progressId, $file['name'], $stored, $mime, (int) $file['size'], $pveoId]
        );

        $count = Database::int('SELECT COUNT(*) FROM report_files WHERE progress_id = ?', [$progressId]);
        $status = $count >= (int) $steps[$stepNo]['min_files'] ? 'complete' : 'partial';
        Database::run(
            'UPDATE report_progress SET status = ?, submitted_at = IF(? = "complete", NOW(), submitted_at) WHERE id = ?',
            [$status, $status, $progressId]
        );

        Database::run(
            'INSERT INTO report_activity_log (progress_id, actor_role, actor_id, action, detail)
             VALUES (?,?,?,?,?)',
            [$progressId, Auth::role(), $pveoId, 'upload', $file['name']]
        );

        Session::flash('ok', 'อัปโหลด "' . $file['name'] . '" เรียบร้อยแล้ว');
        Url::redirect('pveo/progress');
    }

    /**
     * ขั้นตอนของปีที่ระบุ — ใช้ชื่อจาก report_steps ถ้ามี ไม่มีก็ใช้ชื่อเริ่มต้น
     * ตามจำนวนที่ตั้งไว้ใน app_settings.report_step_count
     */
    private function stepsFor(string $year): array
    {
        $count    = Settings::int('report_step_count', 5);
        $defaults = [
            1 => 'หนังสือนำ',
            2 => 'คำสั่งแต่งตั้งคณะทำงาน',
            3 => 'แผนงาน',
            4 => 'รายงานผลครั้งที่ 1',
            5 => 'สรุปผลการดำเนินงาน',
        ];

        $configured = [];
        foreach (Database::all('SELECT * FROM report_steps WHERE survey_year = ? AND is_enabled = 1', [$year]) as $row) {
            $configured[(int) $row['step_no']] = $row;
        }

        $steps = [];
        for ($i = 1; $i <= $count; $i++) {
            $steps[$i] = [
                'step_no'   => $i,
                'step_name' => $configured[$i]['step_name'] ?? ($defaults[$i] ?? 'ขั้นตอนที่ ' . $i),
                'due_date'  => $configured[$i]['due_date'] ?? null,
                'min_files' => (int) ($configured[$i]['min_files'] ?? 1),
            ];
        }
        return $steps;
    }

    private function detectMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = (string) finfo_file($finfo, $path);
                finfo_close($finfo);
                return $mime;
            }
        }
        return (string) mime_content_type($path);
    }
}
