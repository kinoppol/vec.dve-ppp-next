<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Core\Url;

/**
 * กระดานถามตอบ — ใช้ตาราง `topics` / `replies` ของระบบเดิมตรง ๆ
 *
 * ข้อจำกัดของสองตารางนี้ที่ต้องเผื่อไว้ (ดู CLAUDE.md "Database constraints")
 * - `topics.id` เป็น varchar(16) ไม่ใช่ AUTO_INCREMENT แอปต้องสร้างรหัสเอง
 * - `created_at` เป็น varchar ไม่ใช่ datetime จึงเขียนเป็น 'Y-m-d H:i:s'
 *   ซึ่งเรียงตามตัวอักษรได้ตรงกับเรียงตามเวลา และตรงกับ index created_at DESC
 * - `image` เป็น base64 ใน mediumtext ไม่ใช่ไฟล์บนดิสก์
 *
 * อ่านได้ทุกคน แต่ตั้งกระทู้/ตอบต้องเข้าสู่ระบบ (กัน spam โดยไม่ต้องมี captcha
 * ซึ่งจะต้องใช้ JavaScript) ผู้ดูแลระบบลบได้ทุกกระทู้และทุกคำตอบ
 */
final class BoardController extends Controller
{
    /** หมวดของกระดาน — คอลัมน์ category ตั้ง DEFAULT 'ทั่วไป' ไว้แล้ว */
    private const CATEGORIES = [
        'ทั่วไป',
        'การใช้งานระบบ',
        'ข้อมูลสถานประกอบการ',
        'การรายงานผล',
        'ปัญหาการใช้งาน',
    ];

    /** รูปแนบต่อโพสต์ — เก็บเป็น base64 จึงบวมกว่าไฟล์จริงราวหนึ่งในสาม */
    private const MAX_IMAGE_BYTES = 1048576;   // 1 MB
    private const IMAGE_TYPES     = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /** รายการกระทู้ + ค้นหา + กรองหมวด */
    public function index(): void
    {
        $q        = $this->input('q');
        $category = $this->input('category');
        $perPage  = $this->perPage();
        $page     = $this->page();

        $where  = ['1 = 1'];
        $params = [];

        if ($q !== '') {
            $where[]  = '(t.title LIKE ? OR t.content LIKE ? OR t.author LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        if ($category !== '' && in_array($category, self::CATEGORIES, true)) {
            $where[]  = 't.category = ?';
            $params[] = $category;
        }

        $whereSql = implode(' AND ', $where);
        $total    = Database::int("SELECT COUNT(*) FROM topics t WHERE {$whereSql}", $params);
        $pages    = max(1, (int) ceil($total / $perPage));
        $page     = min($page, $pages);

        $rows = Database::all(
            "SELECT t.id, t.title, t.category, t.author, t.college_code, t.created_at, t.views,
                    t.content, t.image IS NOT NULL AND t.image <> '' AS has_image,
                    (SELECT COUNT(*) FROM replies r WHERE r.topic_id = t.id) AS reply_count,
                    (SELECT MAX(r.created_at) FROM replies r WHERE r.topic_id = t.id) AS last_reply_at
               FROM topics t
              WHERE {$whereSql}
           ORDER BY t.created_at DESC
              LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage),
            $params
        );

        $this->view('board/index', [
            'title'      => 'กระดานถามตอบ',
            'nav'        => 'board',
            'rows'       => $rows,
            'total'      => $total,
            'page'       => $page,
            'pages'      => $pages,
            'q'          => $q,
            'category'   => $category,
            'categories' => self::CATEGORIES,
        ], 'public');
    }

    /** ฟอร์มตั้งกระทู้ใหม่ */
    public function create(): void
    {
        Auth::requireLogin();

        $this->view('board/form', [
            'title'      => 'ตั้งกระทู้ใหม่',
            'nav'        => 'board',
            'categories' => self::CATEGORIES,
            'maxImage'   => self::MAX_IMAGE_BYTES,
        ], 'public');
    }

    public function store(): void
    {
        Auth::requireLogin();

        $title    = $this->input('title');
        $content  = trim((string) ($_POST['content'] ?? ''));
        $category = $this->input('category');

        if ($title === '' || $content === '') {
            Session::flash('err', 'กรุณากรอกหัวข้อและรายละเอียดให้ครบ');
            Url::redirect('board/new');
        }
        if (!in_array($category, self::CATEGORIES, true)) {
            $category = self::CATEGORIES[0];
        }

        $image = $this->uploadedImage('image');
        $id    = $this->newTopicId();

        Database::run(
            'INSERT INTO topics (id, title, category, content, image, author, college_code, created_at, views)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)',
            [
                $id,
                mb_substr($title, 0, 200),
                $category,
                $content,
                $image,
                mb_substr(Auth::name(), 0, 200),
                mb_substr((string) (Auth::user()['username'] ?? ''), 0, 20),
                date('Y-m-d H:i:s'),
            ]
        );

        Session::flash('ok', 'ตั้งกระทู้เรียบร้อยแล้ว');
        Url::redirect('board/' . $id);
    }

    /** อ่านกระทู้ + คำตอบ */
    public function show(string $id): void
    {
        $topic = Database::first('SELECT * FROM topics WHERE id = ?', [$id]);

        if ($topic === null) {
            http_response_code(404);
            $this->view('errors/404', ['title' => 'ไม่พบกระทู้ที่ต้องการ'], 'public');
            return;
        }

        // นับการเข้าอ่านแบบง่าย ๆ ครั้งละหนึ่งต่อการเปิดหน้า เหมือนระบบเดิม
        Database::run('UPDATE topics SET views = views + 1 WHERE id = ?', [$id]);

        $this->view('board/show', [
            'title'   => $topic['title'],
            'nav'     => 'board',
            'topic'   => $topic,
            'replies' => Database::all(
                'SELECT * FROM replies WHERE topic_id = ? ORDER BY created_at ASC, id ASC',
                [$id]
            ),
            'maxImage' => self::MAX_IMAGE_BYTES,
        ], 'public');
    }

    public function reply(string $id): void
    {
        Auth::requireLogin();

        $content = trim((string) ($_POST['content'] ?? ''));
        $exists  = Database::int('SELECT COUNT(*) FROM topics WHERE id = ?', [$id]) > 0;

        if (!$exists) {
            Session::flash('err', 'ไม่พบกระทู้ที่ต้องการตอบ');
            Url::redirect('board');
        }
        if ($content === '') {
            Session::flash('err', 'กรุณาพิมพ์ข้อความก่อนส่งคำตอบ');
            Url::redirect('board/' . $id);
        }

        Database::run(
            'INSERT INTO replies (topic_id, author, college_code, content, image, created_at)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $id,
                mb_substr(Auth::name(), 0, 200),
                mb_substr((string) (Auth::user()['username'] ?? ''), 0, 20),
                $content,
                $this->uploadedImage('image'),
                date('Y-m-d H:i:s'),
            ]
        );

        Session::flash('ok', 'ส่งคำตอบเรียบร้อยแล้ว');
        Url::redirect('board/' . $id);
    }

    /** ลบกระทู้ทั้งกระทู้ — ไม่มี FK ระหว่างสองตาราง ต้องลบคำตอบเองด้วย */
    public function destroy(string $id): void
    {
        Auth::requireAdmin();

        Database::run('DELETE FROM replies WHERE topic_id = ?', [$id]);
        Database::run('DELETE FROM topics WHERE id = ?', [$id]);

        Session::flash('ok', 'ลบกระทู้เรียบร้อยแล้ว');
        Url::redirect('board');
    }

    public function destroyReply(string $id): void
    {
        Auth::requireAdmin();

        $topicId = (string) Database::value('SELECT topic_id FROM replies WHERE id = ?', [(int) $id]);
        Database::run('DELETE FROM replies WHERE id = ?', [(int) $id]);

        Session::flash('ok', 'ลบคำตอบเรียบร้อยแล้ว');
        Url::redirect($topicId === '' ? 'board' : 'board/' . $topicId);
    }

    /**
     * รหัสกระทู้ยาวไม่เกิน 16 ตัวอักษรตามชนิดคอลัมน์
     * เวลา 12 หลักทำให้เรียงตามรหัสได้ใกล้เคียงเรียงตามเวลา ส่วนสามหลักท้าย
     * กันชนกันเมื่อมีคนกดพร้อมกันในวินาทีเดียว
     */
    private function newTopicId(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $id = date('ymdHis') . substr(str_shuffle('abcdefghijkmnpqrstuvwxyz23456789'), 0, 3);
            if (Database::int('SELECT COUNT(*) FROM topics WHERE id = ?', [$id]) === 0) {
                return $id;
            }
        }

        return substr(bin2hex(random_bytes(8)), 0, 16);
    }

    /**
     * แปลงไฟล์รูปที่แนบมาเป็น data URI สำหรับคอลัมน์ image
     * คืน null เมื่อไม่ได้แนบ และเด้งกลับพร้อมข้อความเมื่อไฟล์ใหญ่หรือผิดชนิด
     */
    private function uploadedImage(string $field): ?string
    {
        $file = $_FILES[$field] ?? null;
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            Session::flash('err', 'อัปโหลดรูปไม่สำเร็จ กรุณาลองใหม่');
            Url::back();
        }
        if ((int) $file['size'] > self::MAX_IMAGE_BYTES) {
            Session::flash('err', 'ไฟล์รูปต้องมีขนาดไม่เกิน ' . file_size_human(self::MAX_IMAGE_BYTES));
            Url::back();
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!in_array($mime, self::IMAGE_TYPES, true)) {
            Session::flash('err', 'รองรับเฉพาะไฟล์รูป JPG, PNG, GIF และ WebP');
            Url::back();
        }

        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($file['tmp_name']));
    }
}
