<?php
/** รายละเอียดสถานประกอบการ + คะแนนความสมบูรณ์ + ประวัติแบบสำรวจ */
$score = (int) $row['score'];
?>
<div class="page-head">
  <div>
    <h1><?= e($row['enterprise_name']) ?></h1>
    <div class="sub"><?= e($row['estate_name'] ?? 'ไม่ระบุนิคมฯ') ?> · <?= e($row['province_name']) ?></div>
  </div>
  <span class="spacer"></span>
  <a class="btn btn-primary no-print" href="<?= e(url('pveo/survey/' . $row['id'])) ?>">กรอกแบบสำรวจ PPP-002</a>
</div>

<div class="grid-2">
  <div class="card">
    <h2>ข้อมูลทั่วไป</h2>
    <table class="table">
      <tbody>
        <tr><th style="width:170px">ประเภทกิจการ</th><td><?= e($row['business_type'] ?? '—') ?></td></tr>
        <tr><th>ที่อยู่</th><td><?= e($row['address'] ?? '—') ?></td></tr>
        <tr><th>โทรศัพท์</th><td><?= e($row['phone'] ?? '—') ?></td></tr>
        <tr><th>อีเมล</th><td><?= e($row['email'] ?? '—') ?></td></tr>
        <tr><th>ผู้ติดต่อ</th><td><?= e($row['contact_name'] ?? '—') ?> <span class="hint"><?= e($row['contact_position'] ?? '') ?></span></td></tr>
        <tr><th>แก้ไขล่าสุด</th><td><?= e(thai_date($row['updated_at'], true)) ?></td></tr>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h2>คะแนนความสมบูรณ์ของข้อมูล</h2>
    <div class="kpi-value"><?= $score ?><span style="font-size:18px;color:var(--ink-3)">/100</span></div>
    <div class="progress <?= $score < 50 ? 'is-low' : ($score < 80 ? 'is-mid' : '') ?>" style="height:12px">
      <span style="width:<?= $score ?>%"></span>
    </div>
    <?php if (!empty($row['missing_sections'])): ?>
      <p class="hint" style="margin-top:var(--s-3)">ส่วนที่ยังขาด:
        <?php foreach (explode(',', (string) $row['missing_sections']) as $missing): ?>
          <span class="chip"><?= e($missing) ?></span>
        <?php endforeach; ?>
      </p>
    <?php else: ?>
      <p class="hint" style="margin-top:var(--s-3)">ข้อมูลครบถ้วน</p>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <h2>ประวัติแบบสำรวจ</h2>
  <?php if ($surveys === []): ?>
    <div class="empty-state">
      <span class="big">📝</span>ยังไม่มีแบบสำรวจ
      <div><a href="<?= e(url('pveo/survey/' . $row['id'])) ?>">เริ่มกรอกแบบสำรวจ PPP-002</a></div>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>ปีการศึกษา</th><th>รอบ</th><th>วันที่ลงพื้นที่</th><th>สถานะ</th><th class="no-print"></th></tr></thead>
        <tbody>
        <?php foreach ($surveys as $s): ?>
          <tr>
            <td><?= e($s['survey_year']) ?></td>
            <td><?= e(App\Core\Context::ROUNDS[$s['survey_round']] ?? $s['survey_round']) ?></td>
            <td><?= e(thai_date($s['survey_date'])) ?></td>
            <td>
              <?php if ($s['status'] === 'submitted'): ?>
                <span class="badge badge-ok">✔ ส่งแล้ว</span>
              <?php else: ?>
                <span class="badge badge-warn">◐ ร่าง (ขั้นที่ <?= (int) $s['current_step'] ?>/10)</span>
              <?php endif; ?>
              <?php if ((int) $s['no_student_required'] === 1): ?>
                <span class="badge badge-mute">⊘ ไม่ประสงค์รับ</span>
              <?php endif; ?>
            </td>
            <td class="no-print"><a class="btn btn-sm" href="<?= e(url('pveo/survey/' . $row['id'])) ?>">เปิด</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
