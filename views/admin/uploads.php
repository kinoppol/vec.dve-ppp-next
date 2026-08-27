<?php
/** 5.3 ตรวจสอบสถานะการอัปโหลดไฟล์ — ตารางกากบาท สอจ. × ขั้นตอน */
use App\Core\Csrf;

$mark = static function (string $status): string {
    return match ($status) {
        'complete' => '<span class="badge badge-ok" title="ครบแล้ว">✔</span>',
        'partial'  => '<span class="badge badge-warn" title="อัปโหลดบางส่วน">◐</span>',
        'locked'   => '<span class="badge badge-mute" title="ยังไม่เปิดขั้นตอนนี้">🔒</span>',
        default    => '<span class="badge badge-err" title="ยังไม่อัปโหลด">✕</span>',
    };
};
?>
<div class="page-head">
  <div>
    <h1>ตรวจสอบสถานะการอัปโหลดไฟล์</h1>
    <div class="sub">
      ปีการศึกษา <?= e($year) ?> · <?= num(count($offices)) ?> สอจ. · <?= (int) $stepCount ?> ขั้นตอน
      <?php if ($readOnly): ?><span class="badge badge-info">โหมดดูสาธารณะ · อ่านอย่างเดียว</span><?php endif; ?>
    </div>
  </div>
  <span class="spacer"></span>
  <?php if (!$readOnly): ?>
    <form method="post" action="<?= e(url('admin/share')) ?>" class="no-print">
      <?= Csrf::field() ?>
      <input type="hidden" name="target" value="uploads">
      <button class="btn" type="submit">แชร์ลิงก์สาธารณะ</button>
    </form>
    <a class="btn no-print" href="<?= e(url('admin/settings')) ?>">ตั้งค่าขั้นตอนเอกสาร</a>
  <?php endif; ?>
  <button class="btn no-print" type="button" onclick="window.print()">พิมพ์</button>
</div>

<div class="alert alert-info no-print">
  <span aria-hidden="true">i</span>
  <div>จำนวนขั้นตอนกำหนดค่าได้ที่หน้า “ตั้งค่าระบบ” — ขั้นตอนถัดไปจะล็อกไว้จนกว่าขั้นก่อนหน้าจะครบ</div>
</div>

<div class="table-wrap">
  <table class="table">
    <thead>
      <tr>
        <th>สอจ.</th>
        <th>จังหวัด</th>
        <?php for ($i = 1; $i <= $stepCount; $i++): ?>
          <th class="nw">ขั้นที่ <?= $i ?></th>
        <?php endfor; ?>
        <th class="num">ครบแล้ว</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($offices as $office): ?>
      <tr>
        <td><?= e($office['college_name']) ?><div class="hint mono"><?= e($office['college_code']) ?></div></td>
        <td><?= e($office['province_name']) ?></td>
        <?php for ($i = 1; $i <= $stepCount; $i++): $s = $office['steps'][$i]; ?>
          <td>
            <?= $mark($s['status']) ?>
            <?php if ($s['files'] > 0): ?><div class="hint"><?= (int) $s['files'] ?> ไฟล์</div><?php endif; ?>
          </td>
        <?php endfor; ?>
        <td class="num"><?= (int) $office['done'] ?>/<?= (int) $stepCount ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($offices === []): ?>
      <tr><td colspan="<?= $stepCount + 3 ?>" class="empty-state">
        <span class="big">🗂</span>ยังไม่มีข้อมูล สอจ. ในระบบ
      </td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="card" style="margin-top:var(--s-4)">
  <h3>ความหมายของสัญลักษณ์</h3>
  <p>
    <?= $mark('complete') ?> ครบแล้ว ·
    <?= $mark('partial') ?> อัปโหลดบางส่วน ·
    <?= $mark('pending') ?> ยังไม่อัปโหลด ·
    <?= $mark('locked') ?> ยังไม่เปิดขั้นตอน
  </p>
  <p class="hint">ทุกสถานะมีไอคอนกำกับเสมอ ไม่ใช้สีเพียงอย่างเดียว เพื่อให้ผู้ใช้ตาบอดสีแยกแยะได้</p>
</div>
