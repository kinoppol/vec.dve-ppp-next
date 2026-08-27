<?php
/** ข้อความแจ้งเตือน — ทุกสถานะมีไอคอนกำกับ ไม่พึ่งสีอย่างเดียว */
$icons = ['ok' => '✔', 'warn' => '◐', 'err' => '✕', 'info' => 'i'];
foreach (($flash ?? []) as $item):
    $type = $item['type'] ?? 'info';
?>
  <div class="alert alert-<?= e($type) ?> no-print">
    <span aria-hidden="true"><?= e($icons[$type] ?? 'i') ?></span>
    <div><?= e($item['message']) ?></div>
  </div>
<?php endforeach; ?>
