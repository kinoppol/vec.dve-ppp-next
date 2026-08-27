<?php
/**
 * โครงหน้าหลัก: top bar + แบนเนอร์สวมสิทธิ์ + sidebar + เนื้อหา
 * @var string $content
 */
use App\Core\Auth;
use App\Core\Context;

$theme = $theme ?? Context::theme();
?>
<!DOCTYPE html>
<html lang="th" data-theme="<?= e($theme) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'DVE PPP') ?> · <?= e($appName ?? 'DVE PPP') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>

<?= \App\Core\View::partial('partials/topbar') ?>

<?php if (Auth::isImpersonating()): ?>
  <div class="impersonation-banner no-print">
    <span aria-hidden="true">⚠</span>
    <span>โหมดสวมสิทธิ์ — กำลังดูข้อมูลในฐานะ <strong><?= e(Auth::name()) ?></strong></span>
    <span class="spacer"></span>
    <form method="post" action="<?= e(url('admin/impersonate/stop')) ?>">
      <?= \App\Core\Csrf::field() ?>
      <button class="btn btn-sm" type="submit">ออกจากโหมดสวมสิทธิ์</button>
    </form>
  </div>
<?php endif; ?>

<div class="shell">
  <?php if (!empty($sidebar['items'])): ?>
    <?= \App\Core\View::partial('partials/sidebar') ?>
  <?php endif; ?>

  <main class="content">
    <?= \App\Core\View::partial('partials/flash') ?>
    <?= $content ?>
  </main>
</div>

</body>
</html>
