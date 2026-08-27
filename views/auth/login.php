<?php
/** หน้าเข้าสู่ระบบเดียว รองรับทั้ง Admin และ สอจ. */
use App\Core\Csrf;
$mode = $mode ?? 'pveo';
?>
<div class="card">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:var(--s-4)">
    <div class="brand-mark" style="width:42px;height:42px">PPP</div>
    <div>
      <h1 style="margin:0;font-size:19px"><?= e($appName ?? 'DVE PPP') ?></h1>
      <div class="hint"><?= e($appTagline ?? '') ?></div>
    </div>
  </div>

  <div class="seg" style="margin-bottom:var(--s-4)">
    <a href="<?= e(url('login', ['mode' => 'pveo'])) ?>"  class="<?= $mode !== 'admin' ? 'is-on' : '' ?>">สอจ.</a>
    <a href="<?= e(url('login', ['mode' => 'admin'])) ?>" class="<?= $mode === 'admin' ? 'is-on' : '' ?>">ผู้ดูแลระบบ</a>
  </div>

  <form method="post" action="<?= e(url('login')) ?>" class="form-grid">
    <?= Csrf::field() ?>
    <input type="hidden" name="mode" value="<?= e($mode) ?>">

    <label class="field">
      <span><?= $mode === 'admin' ? 'ชื่อผู้ใช้' : 'รหัสวิทยาลัย' ?></span>
      <input class="input" type="text" name="username" required autofocus autocomplete="username"
             <?= $mode === 'admin' ? '' : 'inputmode="numeric" placeholder="เช่น 1371016101"' ?>>
    </label>

    <label class="field">
      <span>รหัสผ่าน</span>
      <input class="input" type="password" name="password" required autocomplete="current-password">
    </label>

    <button class="btn btn-primary" type="submit" style="justify-content:center">เข้าสู่ระบบ</button>
  </form>

  <?php if ($mode !== 'admin'): ?>
    <div class="alert alert-info" style="margin-top:var(--s-4)">
      <span aria-hidden="true">i</span>
      <div>หากเข้าสู่ระบบครั้งแรกด้วยรหัสผ่านเริ่มต้น (เท่ากับรหัสวิทยาลัย)
        ระบบจะให้ตั้งรหัสผ่านใหม่ก่อนใช้งาน</div>
    </div>
  <?php endif; ?>

  <p class="hint" style="margin-top:var(--s-4)">
    <a href="<?= e(url('')) ?>">← กลับหน้าสาธารณะ</a>
  </p>
</div>
