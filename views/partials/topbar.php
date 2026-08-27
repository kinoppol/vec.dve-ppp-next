<?php
/**
 * Top bar เดียวใช้ทุกกลุ่มหน้า — แก้ปัญหาเดิมที่มี navbar 5 ชุดหน้าตาไม่เหมือนกัน
 * มีตัวสลับนิคมฯ, ตัวเลือกปีการศึกษา, สลับโหมดมืด/สว่าง และเมนูผู้ใช้
 */
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Context;

$current = App\Core\Url::current();
$tabs = [
    ['label' => 'สาธารณะ',      'href' => url(''),      'on' => $current === '' || str_starts_with($current, 'search') || str_starts_with($current, 'downloads')],
    ['label' => 'สอจ.',         'href' => url('pveo'),  'on' => str_starts_with($current, 'pveo'),  'show' => Auth::isPveo()],
    ['label' => 'ผู้ดูแลระบบ', 'href' => url('admin'), 'on' => str_starts_with($current, 'admin'), 'show' => Auth::isAdmin()],
];
$initials = mb_substr(Auth::name(), 0, 2);
?>
<header class="topbar no-print">
  <div style="display:flex;align-items:center;gap:10px;flex:none">
    <div class="brand-mark">PPP</div>
    <div>
      <div class="brand-name"><?= e($appName ?? 'DVE PPP') ?></div>
      <div class="brand-sub hide-sm"><?= e($appTagline ?? 'ระบบติดตามความร่วมมือ') ?></div>
    </div>
  </div>

  <nav class="seg hide-sm">
    <?php foreach ($tabs as $tab): if (($tab['show'] ?? true) === false) { continue; } ?>
      <a href="<?= e($tab['href']) ?>" class="<?= $tab['on'] ? 'is-on' : '' ?>"><?= e($tab['label']) ?></a>
    <?php endforeach; ?>
  </nav>

  <span class="spacer"></span>

  <?php if (Auth::isPveo() && !empty($myEstates)): ?>
    <form method="post" action="<?= e(url('context/estate')) ?>" class="picker hide-sm">
      <?= Csrf::field() ?>
      <label for="estate-switch">นิคมฯ ที่ทำงาน</label>
      <select id="estate-switch" name="estate_id" onchange="this.form.submit()">
        <?php foreach ($myEstates as $estate): ?>
          <option value="<?= (int) $estate['id'] ?>" <?= (int) $estate['id'] === Context::activeEstateId() ? 'selected' : '' ?>>
            <?= e($estate['estate_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
  <?php endif; ?>

  <form method="post" action="<?= e(url('context/year')) ?>" class="picker">
    <?= Csrf::field() ?>
    <label for="year-switch">ปีการศึกษา</label>
    <select id="year-switch" name="year" onchange="this.form.submit()">
      <?php foreach (($years ?? [Context::year()]) as $y): ?>
        <option value="<?= e($y) ?>" <?= $y === ($year ?? Context::year()) ? 'selected' : '' ?>><?= e($y) ?></option>
      <?php endforeach; ?>
    </select>
  </form>

  <form method="post" action="<?= e(url('context/theme')) ?>">
    <?= Csrf::field() ?>
    <button class="btn btn-icon" type="submit" title="สลับโหมดสว่าง/มืด">
      <?= ($theme ?? 'light') === 'dark' ? '☀' : '☾' ?>
    </button>
  </form>

  <?php if (Auth::check()): ?>
    <div class="user-box">
      <div class="avatar"><?= e($initials) ?></div>
      <div class="hide-sm nw" style="font-size:12px;line-height:1.3">
        <div style="font-weight:600"><?= e(Auth::name()) ?></div>
        <div class="muted" style="font-size:11px"><?= e(Auth::user()['scope'] ?? '') ?></div>
      </div>
      <form method="post" action="<?= e(url('logout')) ?>">
        <?= Csrf::field() ?>
        <button class="btn btn-sm" type="submit">ออก</button>
      </form>
    </div>
  <?php else: ?>
    <a class="btn btn-secondary" href="<?= e(url('login')) ?>">เข้าสู่ระบบ</a>
  <?php endif; ?>
</header>
