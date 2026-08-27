<?php
/** กระดานถามตอบ — ตั้งกระทู้ใหม่ */
use App\Core\Auth;
use App\Core\Csrf;
?>
<div class="page-head">
  <div>
    <h1>ตั้งกระทู้ใหม่</h1>
    <div class="sub">ตั้งในนาม <?= e(Auth::name()) ?></div>
  </div>
  <span class="spacer"></span>
  <a class="btn" href="<?= e(url('board')) ?>">← กลับกระดาน</a>
</div>

<div class="card">
  <form method="post" action="<?= e(url('board/new')) ?>" class="form-grid" enctype="multipart/form-data">
    <?= Csrf::field() ?>
    <label class="field">
      <span>หัวข้อ <i class="req">*</i></span>
      <input class="input" type="text" name="title" required maxlength="200" placeholder="สรุปคำถามให้สั้นและชัด">
    </label>
    <label class="field">
      <span>หมวด</span>
      <select class="input" name="category">
        <?php foreach ($categories as $c): ?>
          <option value="<?= e($c) ?>"><?= e($c) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="field field-wide">
      <span>รายละเอียด <i class="req">*</i></span>
      <textarea class="input" name="content" required rows="8" placeholder="อธิบายสิ่งที่ต้องการถาม พร้อมขั้นตอนที่ทำไปแล้ว"></textarea>
    </label>
    <label class="field field-wide">
      <span>แนบรูป (ไม่บังคับ)</span>
      <input class="input" type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
      <span class="hint">JPG, PNG, GIF หรือ WebP ขนาดไม่เกิน <?= e(file_size_human($maxImage)) ?></span>
    </label>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit">ตั้งกระทู้</button>
      <a class="btn btn-ghost" href="<?= e(url('board')) ?>">ยกเลิก</a>
    </div>
  </form>
</div>
