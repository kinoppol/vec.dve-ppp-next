<?php
/** โครงหน้าเปล่า — ใช้กับหน้าเข้าสู่ระบบและตั้งรหัสผ่าน */
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
<div class="auth-wrap">
  <div class="auth-card">
    <?= \App\Core\View::partial('partials/flash') ?>
    <?= $content ?>
  </div>
</div>
</body>
</html>
