<?php
/** ตั้งค่าระบบ — ปีการศึกษา, ขั้นตอนเอกสาร, กำหนดส่ง, ลิงก์แชร์ */
use App\Core\Context;
use App\Core\Csrf;

$stepCount = (int) ($settings['report_step_count'] ?? 5);
$byNo = [];
foreach ($steps as $s) {
    $byNo[(int) $s['step_no']] = $s;
}
$defaults = [1 => 'หนังสือนำ', 2 => 'คำสั่งแต่งตั้งคณะทำงาน', 3 => 'แผนงาน', 4 => 'รายงานผลครั้งที่ 1', 5 => 'สรุปผลการดำเนินงาน'];
?>
<div class="page-head">
  <div>
    <h1>ตั้งค่าระบบ</h1>
    <div class="sub">ค่าที่เคย hardcode ไว้ในโค้ดเดิม ย้ายมาแก้ไขได้จากหน้านี้</div>
  </div>
</div>

<form method="post" action="<?= e(url('admin/settings')) ?>">
  <?= Csrf::field() ?>

  <div class="card">
    <h2>ทั่วไป</h2>
    <div class="form-grid form-grid-2">
      <label class="field">
        <span>ชื่อระบบ</span>
        <input class="input" type="text" name="site_name" value="<?= e($settings['site_name'] ?? 'DVE PPP') ?>">
      </label>
      <label class="field">
        <span>คำอธิบายใต้ชื่อ</span>
        <input class="input" type="text" name="site_tagline" value="<?= e($settings['site_tagline'] ?? '') ?>">
      </label>
      <label class="field">
        <span>ปีการศึกษาที่ใช้งาน (พ.ศ.)</span>
        <input class="input" type="text" name="academic_year" pattern="25[0-9]{2}"
               value="<?= e($settings['academic_year'] ?? Context::defaultYear()) ?>">
        <span class="hint">เว้นว่างไว้เพื่อให้ระบบคำนวณจากปีปัจจุบันอัตโนมัติ</span>
      </label>
      <label class="field">
        <span>รอบการสำรวจเริ่มต้น</span>
        <select class="input" name="survey_round">
          <?php foreach (Context::ROUNDS as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= ($settings['survey_round'] ?? 'Yearly') === $value ? 'selected' : '' ?>>
              <?= e($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field">
        <span>จำนวนแถวต่อหน้า</span>
        <input class="input" type="number" name="rows_per_page" min="10" max="100"
               value="<?= e($settings['rows_per_page'] ?? '25') ?>">
      </label>
      <label class="field">
        <span>การค้นหาสาธารณะ</span>
        <label style="display:flex;align-items:center;gap:8px;font-size:14px;color:var(--ink)">
          <input type="checkbox" name="allow_public_search" value="1"
                 <?= ($settings['allow_public_search'] ?? '1') === '1' ? 'checked' : '' ?>>
          เปิดให้ผู้เข้าชมทั่วไปค้นหาสถานประกอบการได้
        </label>
      </label>
    </div>
  </div>

  <div class="card">
    <h2>ขั้นตอนเอกสาร</h2>
    <p class="hint">ระบบเดิมออกแบบไว้ 5 ขั้นตอนแต่เปิดใช้จริง 2 ขั้นตอน — ที่นี่กำหนดจำนวนและชื่อได้เอง</p>
    <div class="form-grid form-grid-2">
      <label class="field">
        <span>จำนวนขั้นตอนที่เปิดใช้</span>
        <input class="input" type="number" name="report_step_count" min="1" max="12" value="<?= $stepCount ?>">
      </label>
      <label class="field">
        <span>กำหนดส่งรวม</span>
        <input class="input" type="date" name="report_deadline" value="<?= e($settings['report_deadline'] ?? '') ?>">
      </label>
    </div>

    <table class="table" style="margin-top:var(--s-4)">
      <thead><tr><th style="width:70px">ขั้นที่</th><th>ชื่อขั้นตอน</th><th style="width:190px">กำหนดส่ง</th></tr></thead>
      <tbody>
      <?php for ($i = 1; $i <= max($stepCount, 5); $i++): ?>
        <tr>
          <td class="num"><?= $i ?><?= $i > $stepCount ? ' <span class="badge badge-mute">🔒 ปิด</span>' : '' ?></td>
          <td><input class="input" type="text" name="step_name[<?= $i ?>]"
                     value="<?= e($byNo[$i]['step_name'] ?? ($defaults[$i] ?? '')) ?>"></td>
          <td><input class="input" type="date" name="step_due[<?= $i ?>]"
                     value="<?= e($byNo[$i]['due_date'] ?? '') ?>"></td>
        </tr>
      <?php endfor; ?>
      </tbody>
    </table>
  </div>

  <div class="form-actions">
    <button class="btn btn-primary" type="submit">บันทึกการตั้งค่า</button>
  </div>
</form>

<div class="card">
  <div class="card-head">
    <h2 style="margin:0">ลิงก์แชร์สาธารณะ</h2>
    <span class="spacer"></span>
    <form method="post" action="<?= e(url('admin/share')) ?>" style="display:flex;gap:8px">
      <?= Csrf::field() ?>
      <select class="input" name="target" style="width:auto">
        <option value="estates">ติดตามข้อมูลนิคมฯ</option>
        <option value="uploads">สถานะการอัปโหลดไฟล์</option>
      </select>
      <button class="btn" type="submit">สร้างลิงก์ใหม่</button>
    </form>
  </div>

  <?php if ($shares === []): ?>
    <div class="empty-state"><span class="big">🔗</span>ยังไม่มีลิงก์แชร์</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>ลิงก์</th><th>หน้า</th><th>ปี</th><th class="num">เปิดดู</th><th class="nw">หมดอายุ</th></tr></thead>
        <tbody>
        <?php foreach ($shares as $share): ?>
          <tr>
            <td class="mono"><a href="<?= e(url('share/' . $share['token'])) ?>"><?= e(mb_substr($share['token'], 0, 12)) ?>…</a></td>
            <td><?= $share['target'] === 'uploads' ? 'สถานะการอัปโหลดไฟล์' : 'ติดตามข้อมูลนิคมฯ' ?></td>
            <td><?= e($share['survey_year']) ?></td>
            <td class="num"><?= num((int) $share['hit_count']) ?></td>
            <td class="nw"><?= e(thai_date($share['expires_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
