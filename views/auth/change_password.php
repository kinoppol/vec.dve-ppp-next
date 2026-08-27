<?php
use App\Core\Csrf;
?>
<div class="card">
  <h1>ตั้งรหัสผ่านใหม่</h1>
  <?php if (!empty($first)): ?>
    <div class="alert alert-warn">
      <span aria-hidden="true">◐</span>
      <div>รหัสผ่านของคุณยังเป็นค่าเริ่มต้น (เท่ากับรหัสวิทยาลัย)
        กรุณาตั้งรหัสผ่านใหม่ก่อนใช้งานระบบ</div>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= e(url('password/change')) ?>" class="form-grid">
    <?= Csrf::field() ?>
    <label class="field">
      <span>รหัสผ่านเดิม</span>
      <input class="input" type="password" name="current_password" required autocomplete="current-password">
    </label>
    <label class="field">
      <span>รหัสผ่านใหม่</span>
      <input class="input" type="password" name="password" required minlength="8" autocomplete="new-password">
      <span class="hint">อย่างน้อย 8 ตัวอักษร และต้องไม่เหมือนชื่อผู้ใช้</span>
    </label>
    <label class="field">
      <span>ยืนยันรหัสผ่านใหม่</span>
      <input class="input" type="password" name="password_confirm" required minlength="8" autocomplete="new-password">
    </label>
    <button class="btn btn-primary" type="submit" style="justify-content:center">บันทึกรหัสผ่านใหม่</button>
  </form>
</div>
