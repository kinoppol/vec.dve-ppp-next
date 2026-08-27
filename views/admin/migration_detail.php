<?php
/** ดู SQL ของ migration รุ่นหนึ่ง ก่อนตัดสินใจรันหรือย้อนกลับ */
use App\Core\Csrf;
?>
<div class="page-head">
  <div>
    <h1>Migration <?= e($version) ?></h1>
    <div class="sub"><?= e($row['name'] ?? '') ?> · <code><?= e($row['file'] ?? '') ?></code></div>
  </div>
  <span class="spacer"></span>
  <a class="btn btn-ghost" href="<?= e(url('admin/migrations')) ?>">← กลับรายการ</a>
</div>

<div class="card">
  <div class="card-head">
    <h2 style="margin:0">คำสั่งปรับโครงสร้าง (UP)</h2>
    <span class="spacer"></span>
    <?php if (($row['state'] ?? '') === 'pending'): ?>
      <form method="post" action="<?= e(url('admin/migrations/run')) ?>"
            onsubmit="return confirm('ยืนยันรัน migration รุ่น <?= e($version) ?>?')">
        <?= Csrf::field() ?>
        <input type="hidden" name="version" value="<?= e($version) ?>">
        <button class="btn btn-primary" type="submit">รันรุ่นนี้</button>
      </form>
    <?php endif; ?>
  </div>
  <pre class="log-box mono" style="white-space:pre-wrap;overflow-x:auto"><?= e($up) ?></pre>
</div>

<div class="card">
  <h2>คำสั่งย้อนกลับ (DOWN)</h2>
  <?php if (trim($down) === ''): ?>
    <p class="muted">รุ่นนี้ไม่มีส่วน <code>-- @DOWN</code> จึงย้อนกลับผ่านหน้าจอไม่ได้</p>
  <?php else: ?>
    <pre class="log-box mono" style="white-space:pre-wrap;overflow-x:auto"><?= e($down) ?></pre>

    <?php if (($row['state'] ?? '') === 'applied' || ($row['state'] ?? '') === 'drifted'): ?>
      <div class="alert alert-err">
        <span aria-hidden="true">⚠</span>
        <div><strong>การย้อนกลับจะลบข้อมูลในตารางที่ถูก DROP อย่างถาวร</strong>
          สำรองฐานข้อมูลก่อนดำเนินการเสมอ</div>
      </div>
      <form method="post" action="<?= e(url('admin/migrations/rollback')) ?>" class="form-grid">
        <?= Csrf::field() ?>
        <input type="hidden" name="version" value="<?= e($version) ?>">
        <label class="field" style="max-width:280px">
          <span>พิมพ์ <code><?= e($version) ?></code> เพื่อยืนยัน</span>
          <input class="input" type="text" name="confirm" required autocomplete="off">
        </label>
        <div class="form-actions">
          <button class="btn btn-danger" type="submit">ย้อนกลับรุ่นนี้</button>
        </div>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</div>
