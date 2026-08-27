<?php
/**
 * หน้าเข้าสู่ระบบ — สองขั้นตอน
 *
 * ขั้นที่ 1 เลือกประเภทผู้ใช้ (ยังไม่แสดงช่องกรอก)
 * ขั้นที่ 2 กรอกข้อมูลของประเภทที่เลือก
 *
 * เดิมหน้านี้เปิดมาเป็นโหมด สอจ. ทันที ช่องแรกจึงขึ้นว่า "รหัสวิทยาลัย"
 * ผู้ดูแลระบบที่ไม่ทันสังเกตแถบด้านบนจะกรอกชื่อผู้ใช้ลงไปแล้วเข้าไม่ได้
 */
use App\Core\Csrf;

$mode = $mode ?? '';
?>
<div class="card">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:var(--s-4)">
    <div class="brand-mark" style="width:42px;height:42px">PPP</div>
    <div>
      <h1 style="margin:0;font-size:19px"><?= e($appName ?? 'DVE PPP') ?></h1>
      <div class="hint"><?= e($appTagline ?? '') ?></div>
    </div>
  </div>

<?php if ($mode === ''): ?>

  <p class="hint" style="margin-bottom:var(--s-3)">เลือกประเภทผู้ใช้เพื่อเข้าสู่ระบบ</p>

  <div class="choice-list">
    <a class="choice" href="<?= e(url('login', ['mode' => 'pveo'])) ?>">
      <span class="choice-icon" aria-hidden="true">🏛</span>
      <span class="choice-body">
        <span class="choice-title">สอจ.</span>
        <span class="choice-sub">สำนักงานอาชีวศึกษาจังหวัด — เข้าด้วยรหัสวิทยาลัย</span>
      </span>
      <span class="choice-go" aria-hidden="true">→</span>
    </a>

    <a class="choice" href="<?= e(url('login', ['mode' => 'admin'])) ?>">
      <span class="choice-icon" aria-hidden="true">⚙</span>
      <span class="choice-body">
        <span class="choice-title">ผู้ดูแลระบบ</span>
        <span class="choice-sub">สอศ. ส่วนกลาง — เข้าด้วยชื่อผู้ใช้</span>
      </span>
      <span class="choice-go" aria-hidden="true">→</span>
    </a>
  </div>

<?php else: ?>

  <?php $isAdmin = $mode === 'admin'; ?>

  <div class="mode-tag">
    <span class="mode-tag-icon" aria-hidden="true"><?= $isAdmin ? '⚙' : '🏛' ?></span>
    <span>เข้าสู่ระบบในฐานะ <strong><?= $isAdmin ? 'ผู้ดูแลระบบ' : 'สอจ.' ?></strong></span>
    <span class="spacer"></span>
    <a href="<?= e(url('login')) ?>">เปลี่ยน</a>
  </div>

  <form method="post" action="<?= e(url('login')) ?>" class="form-grid">
    <?= Csrf::field() ?>
    <input type="hidden" name="mode" value="<?= e($mode) ?>">

    <label class="field">
      <span><?= $isAdmin ? 'ชื่อผู้ใช้' : 'รหัสวิทยาลัย' ?></span>
      <input class="input" type="text" name="username" required autofocus autocomplete="username"
             <?= $isAdmin ? '' : 'inputmode="numeric" placeholder="เช่น 1371016101"' ?>>
    </label>

    <label class="field">
      <span>รหัสผ่าน</span>
      <input class="input" type="password" name="password" required autocomplete="current-password">
    </label>

    <button class="btn btn-primary" type="submit" style="justify-content:center">เข้าสู่ระบบ</button>
  </form>

  <?php if (!$isAdmin): ?>
    <div class="alert alert-info" style="margin-top:var(--s-4)">
      <span aria-hidden="true">i</span>
      <div>หากเข้าสู่ระบบครั้งแรกด้วยรหัสผ่านเริ่มต้น (เท่ากับรหัสวิทยาลัย)
        ระบบจะให้ตั้งรหัสผ่านใหม่ก่อนใช้งาน</div>
    </div>
  <?php endif; ?>

<?php endif; ?>

  <p class="hint" style="margin-top:var(--s-4)">
    <a href="<?= e(url('')) ?>">← กลับหน้าสาธารณะ</a>
  </p>
</div>
