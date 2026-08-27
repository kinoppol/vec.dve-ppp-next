<?php
/** 5.6 รายงานความคืบหน้า — อัปโหลดเอกสารตามขั้นตอน (จำนวนขั้นตอนกำหนดค่าได้) */
use App\Core\Csrf;

$badge = static function (array $s): string {
    return match ($s['status']) {
        'complete' => '<span class="badge badge-ok">✔ ครบแล้ว</span>',
        'partial'  => '<span class="badge badge-warn">◐ บางส่วน</span>',
        'locked'   => '<span class="badge badge-mute">🔒 ล็อก</span>',
        default    => '<span class="badge badge-err">✕ ยังไม่อัปโหลด</span>',
    };
};
?>
<div class="page-head">
  <div>
    <h1>รายงานความคืบหน้า</h1>
    <div class="sub">
      ปีการศึกษา <?= e($year) ?>
      <?php if ($activeEstate): ?> · <?= e($activeEstate['estate_name']) ?><?php endif; ?>
    </div>
  </div>
  <span class="spacer"></span>
  <button class="btn no-print" type="button" onclick="window.print()">พิมพ์</button>
</div>

<?php foreach ($steps as $no => $s): ?>
  <div class="card">
    <div class="card-head">
      <h2 style="margin:0">ขั้นที่ <?= (int) $no ?> · <?= e($s['step_name']) ?></h2>
      <?= $badge($s) ?>
      <?php if (!empty($s['overdue'])): ?>
        <span class="badge badge-err">⚠ เกินกำหนด</span>
      <?php endif; ?>
      <span class="spacer"></span>
      <?php if ($s['due_date']): ?>
        <span class="hint">ครบกำหนด <?= e(thai_date($s['due_date'])) ?></span>
      <?php endif; ?>
    </div>

    <?php if ($s['status'] === 'locked'): ?>
      <p class="muted">ล็อก — รอให้ขั้นตอนก่อนหน้าอัปโหลดครบก่อน</p>
    <?php else: ?>
      <p class="hint">อัปโหลดแล้ว <?= (int) $s['files'] ?> ไฟล์ · ต้องมีอย่างน้อย <?= (int) $s['min_files'] ?> ไฟล์
        · รองรับ PDF, Word, JPG, PNG ขนาดไม่เกิน 8 MB</p>
      <form method="post" action="<?= e(url('pveo/progress/upload')) ?>" enctype="multipart/form-data" class="form-actions no-print">
        <?= Csrf::field() ?>
        <input type="hidden" name="step_no" value="<?= (int) $no ?>">
        <input class="input" type="file" name="document" required style="max-width:340px"
               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
        <button class="btn btn-primary" type="submit">อัปโหลด</button>
      </form>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<div class="card">
  <h2>ไฟล์ที่อัปโหลดแล้ว</h2>
  <?php if ($files === []): ?>
    <div class="empty-state"><span class="big">🗂</span>ยังไม่มีไฟล์ในปีการศึกษานี้</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>ชื่อไฟล์</th><th class="num">ขั้นที่</th><th class="num">ขนาด</th><th class="nw">อัปโหลดเมื่อ</th></tr></thead>
        <tbody>
        <?php foreach ($files as $f): ?>
          <tr>
            <td><?= e($f['original_name']) ?><div class="hint mono"><?= e($f['mime_type']) ?></div></td>
            <td class="num"><?= (int) $f['step_no'] ?></td>
            <td class="num"><?= e(file_size_human((int) $f['file_size'])) ?></td>
            <td class="nw"><?= e(thai_date($f['uploaded_at'], true)) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
