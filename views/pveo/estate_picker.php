<?php
use App\Core\Context;
use App\Core\Csrf;
?>
<div class="page-head">
  <div>
    <h1>เลือกนิคมอุตสาหกรรมที่จะทำงาน</h1>
    <div class="sub">ระบบจะกรองข้อมูลทุกหน้าตามนิคมฯ ที่เลือก</div>
  </div>
</div>

<?php if ($estates === []): ?>
  <div class="empty-state card">
    <span class="big">🏭</span>
    ยังไม่ได้รับมอบหมายนิคมอุตสาหกรรม
    <div class="hint">ติดต่อผู้ดูแลระบบเพื่อขอรับมอบหมาย</div>
  </div>
<?php else: ?>
  <div class="grid-2">
    <?php foreach ($estates as $estate): $on = (int) $estate['id'] === Context::activeEstateId(); ?>
      <form method="post" action="<?= e(url('context/estate')) ?>" class="card" style="<?= $on ? 'border-color:var(--brand)' : '' ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="estate_id" value="<?= (int) $estate['id'] ?>">
        <h3 style="margin-bottom:4px"><?= e($estate['estate_name']) ?></h3>
        <div class="hint"><?= e($estate['province_name'] ?? 'ไม่ระบุจังหวัด') ?></div>
        <div class="form-actions">
          <?php if ($on): ?>
            <span class="badge badge-brand">✔ กำลังทำงานอยู่</span>
          <?php else: ?>
            <button class="btn btn-primary" type="submit">เลือกนิคมฯ นี้</button>
          <?php endif; ?>
        </div>
      </form>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
