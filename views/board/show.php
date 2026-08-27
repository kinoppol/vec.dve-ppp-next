<?php
/** กระดานถามตอบ — อ่านกระทู้และตอบ */
use App\Core\Auth;
use App\Core\Csrf;

$topicImage = image_src($topic['image'] ?? null);
?>
<div class="page-head">
  <div>
    <h1><?= e($topic['title']) ?></h1>
    <div class="sub">
      <span class="badge badge-brand"><?= e($topic['category']) ?></span>
      โดย <?= e($topic['author']) ?><?= $topic['college_code'] !== '' ? ' · ' . e($topic['college_code']) : '' ?>
      · <?= e(thai_date($topic['created_at'], true)) ?>
      · อ่าน <?= num((int) $topic['views']) ?> ครั้ง
      · ตอบ <?= num(count($replies)) ?> ครั้ง
    </div>
  </div>
  <span class="spacer"></span>
  <a class="btn" href="<?= e(url('board')) ?>">← กลับกระดาน</a>
  <?php if (Auth::isAdmin()): ?>
    <form method="post" action="<?= e(url('board/' . $topic['id'] . '/delete')) ?>" class="no-print"
          onsubmit="return confirm('ลบกระทู้นี้พร้อมคำตอบทั้งหมด?')">
      <?= Csrf::field() ?>
      <button class="btn btn-danger" type="submit">ลบกระทู้</button>
    </form>
  <?php endif; ?>
</div>

<article class="card post">
  <div class="post-body"><?= nl2br(e($topic['content'])) ?></div>
  <?php if ($topicImage !== null): ?>
    <img class="post-image" src="<?= e($topicImage) ?>" alt="รูปแนบในกระทู้">
  <?php endif; ?>
</article>

<h2 class="section-title">คำตอบ <?= num(count($replies)) ?> รายการ</h2>

<?php if ($replies === []): ?>
  <div class="card">
    <div class="empty-state"><span class="big">💬</span>ยังไม่มีใครตอบกระทู้นี้</div>
  </div>
<?php else: ?>
  <?php foreach ($replies as $reply): ?>
    <?php $replyImage = image_src($reply['image'] ?? null); ?>
    <article class="card post">
      <div class="post-head">
        <strong><?= e($reply['author']) ?></strong>
        <?php if ($reply['college_code'] !== ''): ?><span class="muted">· <?= e($reply['college_code']) ?></span><?php endif; ?>
        <span class="muted">· <?= e(thai_date($reply['created_at'], true)) ?></span>
        <span class="spacer"></span>
        <?php if (Auth::isAdmin()): ?>
          <form method="post" action="<?= e(url('board/reply/' . (int) $reply['id'] . '/delete')) ?>" class="no-print"
                onsubmit="return confirm('ลบคำตอบนี้?')">
            <?= Csrf::field() ?>
            <button class="btn btn-ghost btn-sm" type="submit">ลบ</button>
          </form>
        <?php endif; ?>
      </div>
      <div class="post-body"><?= nl2br(e($reply['content'])) ?></div>
      <?php if ($replyImage !== null): ?>
        <img class="post-image" src="<?= e($replyImage) ?>" alt="รูปแนบในคำตอบ">
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
<?php endif; ?>

<div class="card no-print">
  <h2>ตอบกระทู้</h2>
  <?php if (Auth::check()): ?>
    <form method="post" action="<?= e(url('board/' . $topic['id'] . '/reply')) ?>" class="form-grid" enctype="multipart/form-data">
      <?= Csrf::field() ?>
      <label class="field">
        <span>ข้อความ <i class="req">*</i></span>
        <textarea class="input" name="content" required placeholder="พิมพ์คำตอบหรือข้อเสนอแนะ"></textarea>
      </label>
      <label class="field">
        <span>แนบรูป (ไม่บังคับ)</span>
        <input class="input" type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
        <span class="hint">JPG, PNG, GIF หรือ WebP ขนาดไม่เกิน <?= e(file_size_human($maxImage)) ?></span>
      </label>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit">ส่งคำตอบ</button>
        <span class="hint">ตอบในนาม <?= e(Auth::name()) ?></span>
      </div>
    </form>
  <?php else: ?>
    <div class="alert alert-info">
      <span aria-hidden="true">◐</span>
      <div>ต้องเข้าสู่ระบบก่อนจึงจะตอบกระทู้ได้ — <a href="<?= e(url('login')) ?>">เข้าสู่ระบบ</a></div>
    </div>
  <?php endif; ?>
</div>
