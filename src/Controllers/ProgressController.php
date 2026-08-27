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
            'SELECT rp.*, rp.stage AS step_no,
                    (SELECT COUNT(*) FROM report_files f
                      WHERE f.progress_id = rp.id AND f.deleted_at IS NULL) AS file_count
               FROM report_progress rp
              WHERE rp.pveo_id = ? AND rp.academic_year = ? AND rp.industrial_estate_id <=> ?',
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
                'SELECT f.*, f.file_type AS mime_type, f.created_at AS uploaded_at, rp.stage AS step_no
                   FROM report_files f
                   JOIN report_progress rp ON rp.id = f.progress_id
                  WHERE rp.pveo_id = ? AND rp.academic_year = ? AND rp.industrial_estate_id <=> ?
                    AND f.deleted_at IS NULL
               ORDER BY f.created_at DESC',
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

        // report_progress.industrial_estate_id เป็น NOT NULL ในฐานข้อมูลจริง
        // ถ้ายังไม่ได้เลือกนิคมฯ ที่ทำงาน จะบันทึกไม่ได้ — บอกผู้ใช้ตรง ๆ
        if ($estateId === null) {
            Session::flash('err', 'กรุณาเลือกนิคมฯ ที่ทำงานบนแถบด้านบนก่อนอัปโหลดเอกสาร');
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
            'INSERT INTO report_progress (pveo_id, industrial_estate_id, academic_year, stage, status)
             VALUES (?,?,?,?,"draft")
             ON DUPLICATE KEY UPDATE updated_at = NOW()',
            [$pveoId, $estateId, $year, $stepNo]
        );

        $progress = Database::first(
            'SELECT * FROM report_progress
              WHERE pveo_id = ? AND academic_year = ? AND stage = ? AND industrial_estate_id <=> ?',
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
            'INSERT INTO report_files (progress_id, file_type, original_name, stored_name, file_size,
                                       uploaded_by_name, uploaded_by_code)
             VALUES (?,?,?,?,?,?,?)',
            [$progressId, $mime, $file['name'], $stored, (int) $file['size'],
             Auth::name(), (string) (Auth::user()['username'] ?? '')]
        );

        $count = Database::int('SELECT COUNT(*) FROM report_files WHERE progress_id = ? AND deleted_at IS NULL', [$progressId]);
        // production ใช้ enum('draft','submitted','done') ไม่ใช่ partial/complete
        $status = $count >= (int) $steps[$stepNo]['min_files'] ? 'done' : 'draft';
        Database::run(
            'UPDATE report_progress SET status = ?, submitted_at = IF(? = "done", NOW(), submitted_at),
                    updated_at = NOW() WHERE id = ?',
            [$status, $status, $progressId]
        );

        Database::run(
            'INSERT INTO report_activity_log
                (pveo_id, industrial_estate_id, academic_year, stage, action, description, actor_name, actor_code)
             VALUES (?,?,?,?,?,?,?,?)',
            [$pveoId, (int) $estateId, $year, $stepNo, 'upload', $file['name'],
             Auth::name(), (string) (Auth::user()['username'] ?? '')]
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
