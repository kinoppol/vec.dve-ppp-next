<?php
/** เมนูข้าง — ชุดเดียวต่อบทบาท */
use App\Core\Auth;
use App\Core\Csrf;
?>
<nav class="sidebar no-print">
  <div class="sidebar-title"><?= e($sidebar['title'] ?? '') ?></div>
  <?php foreach ($sidebar['items'] as $item): ?>
    <a href="<?= e($item['href']) ?>" class="<?= ($nav ?? '') === $item['id'] ? 'is-on' : '' ?>">
      <span class="icon" aria-hidden="true"><?= e($item['icon']) ?></span>
      <span class="grow"><?= e($item['label']) ?></span>
      <?php if (!empty($item['badge'])): ?><span class="count"><?= e($item['badge']) ?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>

  <?php if (Auth::isPveo() && !empty($activeEstate)): ?>
    <div class="sidebar-note">
      <div class="hint">นิคมฯ ที่กำลังทำงาน</div>
      <div style="font-size:12.5px;font-weight:600;margin-top:3px;line-height:1.4">
        <?= e($activeEstate['estate_name']) ?>
      </div>
      <div class="hint"><?= e($activeEstate['province_name'] ?? '') ?></div>
      <a class="btn btn-sm btn-secondary" style="margin-top:8px;width:100%;justify-content:center"
         href="<?= e(url('estate/picker')) ?>">เปลี่ยนนิคมฯ</a>
    </div>
  <?php endif; ?>
</nav>
