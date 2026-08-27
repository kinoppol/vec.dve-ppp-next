<?php
use App\Core\Csrf;
?>
<div class="page-head">
  <div>
    <h1>เพิ่มสถานประกอบการ</h1>
    <div class="sub">
      <?php if ($activeEstate): ?>บันทึกเข้านิคมฯ <?= e($activeEstate['estate_name']) ?><?php endif; ?>
    </div>
  </div>
</div>

<form method="post" action="<?= e(url('pveo/enterprises/new')) ?>" class="card">
  <?= Csrf::field() ?>
  <div class="form-grid form-grid-2">
    <label class="field field-wide">
      <span>ชื่อสถานประกอบการ <em class="req">*</em></span>
      <input class="input" type="text" name="enterprise_name" required autofocus>
    </label>
    <label class="field">
      <span>ประเภทกิจการ</span>
      <input class="input" type="text" name="business_type" placeholder="เช่น ผลิตชิ้นส่วนยานยนต์">
    </label>
    <label class="field">
      <span>จังหวัด</span>
      <select class="input" name="province_id">
        <option value="">— เลือกจังหวัด —</option>
        <?php foreach ($provinces as $p): ?>
          <option value="<?= (int) $p['id'] ?>"><?= e($p['province_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="field field-wide">
      <span>ที่อยู่</span>
      <input class="input" type="text" name="address">
    </label>
    <label class="field">
      <span>โทรศัพท์</span>
      <input class="input" type="text" name="phone">
    </label>
    <label class="field">
      <span>อีเมล</span>
      <input class="input" type="email" name="email">
    </label>
    <label class="field">
      <span>ชื่อผู้ติดต่อ</span>
      <input class="input" type="text" name="contact_name">
    </label>
    <label class="field">
      <span>ตำแหน่งผู้ติดต่อ</span>
      <input class="input" type="text" name="contact_position">
    </label>
    <label class="field">
      <span>โทรศัพท์ผู้ติดต่อ</span>
      <input class="input" type="text" name="contact_phone">
    </label>
  </div>
  <div class="form-actions">
    <a class="btn btn-ghost" href="<?= e(url('pveo/enterprises')) ?>">ยกเลิก</a>
    <button class="btn btn-primary" type="submit">บันทึก</button>
  </div>
</form>
