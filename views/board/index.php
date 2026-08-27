<?php
/** กระดานถามตอบ — รายการกระทู้ (อ่านได้ทุกคน) */
use App\Core\Auth;
use App\Core\Url;
?>
<div class="page-head">
  <div>
    <h1>กระดานถามตอบ</h1>
    <div class="sub">ถาม-ตอบเรื่องการใช้งานระบบและการเก็บข้อมูลความต้องการกำลังคน · <?= num($total) ?> กระทู้</div>
  </div>
  <span class="spacer"></span>
  <?php if (Auth::check()): ?>
    <a class="btn btn-primary" href="<?= e(url('board/new')) ?>">ตั้งกระทู้ใหม่</a>
  <?php else: ?>
    <a class="btn" href="<?= e(url('login')) ?>">เข้าสู่ระบบเพื่อตั้งกระทู้</a>
  <?php endif; ?>
</div>

<form method="get" class="filter-bar">
  <label class="field" style="flex:1;max-width:320px">
    <span>คำค้นหา</span>
    <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="หัวข้อ เนื้อหา หรือผู้ตั้งกระทู้">
  </label>
  <label class="field">
    <span>หมวด</span>
    <select class="input" name="category">
      <option value="">ทุกหมวด</option>
      <?php foreach ($categories as $c): ?>
        <option value="<?= e($c) ?>" <?= $category === $c ? 'selected' : '' ?>><?= e($c) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button class="btn btn-primary" type="submit">ค้นหา</button>
  <a class="btn btn-ghost" href="<?= e(url('board')) ?>">ล้าง</a>
</form>

<?php if ($rows === []): ?>
  <div class="card">
    <div class="empty-state">
      <span class="big">💬</span>
      <?= $q !== '' || $category !== '' ? 'ไม่พบกระทู้ตามเงื่อนไข' : 'ยังไม่มีกระทู้ในกระดาน' ?>
      <div class="hint"><?= $q !== '' || $category !== '' ? 'ลองลดเงื่อนไขการค้นหา' : 'เข้าสู่ระบบแล้วตั้งกระทู้แรกได้เลย' ?></div>
    </div>
  </div>
<?php else: ?>
  <div class="topic-list">
    <?php foreach ($rows as $row): ?>
      <article class="topic-item">
        <div class="topic-main">
          <div class="topic-title">
            <a href="<?= e(url('board/' . $row['id'])) ?>"><?= e($row['title']) ?></a>
            <?php if (!empty($row['has_image'])): ?><span class="badge badge-mute" title="มีรูปแนบ">📎 รูป</span><?php endif; ?>
          </div>
          <div class="hint"><?= e(str_excerpt($row['content'], 140)) ?></div>
          <div class="topic-meta">
            <span class="badge badge-brand"><?= e($row['category']) ?></span>
            <span>โดย <?= e($row['author']) ?><?= $row['college_code'] !== '' ? ' · ' . e($row['college_code']) : '' ?></span>
            <span>ตั้งเมื่อ <?= e(thai_date($row['created_at'], true)) ?></span>
          </div>
        </div>
        <div class="topic-stats">
          <div><strong><?= num((int) $row['reply_count']) ?></strong><span class="muted"> ตอบ</span></div>
          <div><strong><?= num((int) $row['views']) ?></strong><span class="muted"> อ่าน</span></div>
          <?php if (!empty($row['last_reply_at'])): ?>
            <div class="hint">ตอบล่าสุด <?= e(thai_date($row['last_reply_at'], true)) ?></div>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($pages > 1): ?>
  <nav class="pagination">
    <?php for ($p = 1; $p <= min($pages, 20); $p++): ?>
      <?php if ($p === $page): ?><span class="is-on"><?= $p ?></span>
      <?php else: ?><a href="<?= e(Url::withQuery(['page' => $p])) ?>"><?= $p ?></a><?php endif; ?>
    <?php endfor; ?>
  </nav>
<?php endif; ?>
