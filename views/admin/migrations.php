<?php
/**
 * เมนู Migration — สถานะโครงสร้างฐานข้อมูลทั้งหมดในหน้าเดียว
 * @var array $rows
 * @var array $summary
 */
use App\Core\Csrf;

$stateBadge = static function (string $state): string {
    return match ($state) {
        'applied' => '<span class="badge badge-ok">✔ ใช้งานแล้ว</span>',
        'pending' => '<span class="badge badge-warn">◐ รอดำเนินการ</span>',
        'drifted' => '<span class="badge badge-err">⚠ ไฟล์ถูกแก้ไข</span>',
        default   => '<span class="badge badge-mute">🔒 ไม่พบไฟล์</span>',
    };
};
?>
<div class="page-head">
  <div>
    <h1>Migration ฐานข้อมูล</h1>
    <div class="sub">
      ฐานข้อมูล <code><?= e($dbName) ?></code> · เซิร์ฟเวอร์ <?= e($dbServer) ?>
    </div>
  </div>
  <span class="spacer"></span>
  <?php if ($summary['pending'] > 0 && !$locked): ?>
    <form method="post" action="<?= e(url('admin/migrations/run')) ?>"
          onsubmit="return confirm('ยืนยันรัน migration ที่ค้างอยู่ <?= (int) $summary['pending'] ?> รุ่น?')">
      <?= Csrf::field() ?>
      <button class="btn btn-primary" type="submit">
        รัน migration ที่ค้างอยู่ (<?= (int) $summary['pending'] ?>)
      </button>
    </form>
  <?php endif; ?>
</div>

<div class="kpi-row">
  <div class="kpi"><div class="kpi-icon">✔</div><div class="kpi-value"><?= (int) $summary['applied'] ?></div><div class="kpi-label">ใช้งานแล้ว</div></div>
  <div class="kpi"><div class="kpi-icon">◐</div><div class="kpi-value"><?= (int) $summary['pending'] ?></div><div class="kpi-label">รอดำเนินการ</div></div>
  <div class="kpi"><div class="kpi-icon">⚠</div><div class="kpi-value"><?= (int) $summary['drifted'] ?></div><div class="kpi-label">ไฟล์ถูกแก้หลังรัน</div></div>
  <div class="kpi"><div class="kpi-icon">🔒</div><div class="kpi-value"><?= (int) $summary['missing'] ?></div><div class="kpi-label">ไม่พบไฟล์</div></div>
</div>

<?php if ($summary['drifted'] > 0): ?>
  <div class="alert alert-warn">
    <span aria-hidden="true">⚠</span>
    <div>มีไฟล์ migration ที่ถูกแก้ไขหลังจากรันไปแล้ว — เนื้อหาในไฟล์ไม่ตรงกับสิ่งที่ฐานข้อมูลเคยรัน
      ถ้าตั้งใจแก้ ให้กด “ยอมรับการแก้ไข” เพื่ออัปเดต checksum
      ถ้าไม่ได้ตั้งใจ ควรสร้าง migration รุ่นใหม่แทนการแก้ไฟล์เดิม</div>
  </div>
<?php endif; ?>

<?php if ($locked): ?>
  <div class="alert alert-info">
    <span aria-hidden="true">i</span>
    <div>อยู่ในโหมดสวมสิทธิ์ — ดูได้อย่างเดียว ออกจากโหมดก่อนจึงจะจัดการ migration ได้</div>
  </div>
<?php endif; ?>

<div class="table-wrap">
  <table class="table">
    <thead>
      <tr>
        <th>รุ่น</th>
        <th>ชื่อ / ไฟล์</th>
        <th>สถานะ</th>
        <th class="nw">ใช้งานเมื่อ</th>
        <th class="num">ใช้เวลา</th>
        <th>โดย</th>
        <th class="nw">การจัดการ</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
      <tr>
        <td class="mono"><?= e($row['version']) ?></td>
        <td>
          <a href="<?= e(url('admin/migrations/' . $row['version'])) ?>"><?= e($row['name']) ?></a>
          <div class="hint mono"><?= e($row['file']) ?></div>
        </td>
        <td>
          <?= $stateBadge($row['state']) ?>
          <?php if (!$row['reversible'] && $row['state'] !== 'missing'): ?>
            <div class="hint">ย้อนกลับไม่ได้</div>
          <?php endif; ?>
        </td>
        <td class="nw"><?= e(thai_date($row['applied_at'], true)) ?></td>
        <td class="num"><?= $row['duration_ms'] === null ? '—' : num((int) $row['duration_ms']) . ' ms' ?></td>
        <td><?= e($row['applied_by'] ?? '—') ?></td>
        <td class="nw">
          <?php if (!$locked && $row['state'] === 'pending'): ?>
            <form method="post" action="<?= e(url('admin/migrations/run')) ?>" style="display:inline">
              <?= Csrf::field() ?>
              <input type="hidden" name="version" value="<?= e($row['version']) ?>">
              <button class="btn btn-sm btn-primary" type="submit">รันรุ่นนี้</button>
            </form>
          <?php elseif (!$locked && $row['state'] === 'drifted'): ?>
            <form method="post" action="<?= e(url('admin/migrations/resync')) ?>" style="display:inline"
                  onsubmit="return confirm('ยอมรับเนื้อหาไฟล์ปัจจุบันของรุ่น <?= e($row['version']) ?>?')">
              <?= Csrf::field() ?>
              <input type="hidden" name="version" value="<?= e($row['version']) ?>">
              <button class="btn btn-sm" type="submit">ยอมรับการแก้ไข</button>
            </form>
          <?php else: ?>
            <a class="btn btn-sm btn-ghost" href="<?= e(url('admin/migrations/' . $row['version'])) ?>">ดู SQL</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ($rows === []): ?>
      <tr><td colspan="7" class="empty-state">ไม่พบไฟล์ migration ในโฟลเดอร์ migrations/</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="card" style="margin-top:var(--s-4)">
  <h3>วิธีเพิ่ม migration ใหม่</h3>
  <p class="muted">สร้างไฟล์ <code>migrations/NNNN_ชื่อ_งาน.sql</code> โดยให้เลขรุ่นถัดจากรุ่นล่าสุด
    เขียนคำสั่งส่วนที่ต้องการปรับโครงสร้างไว้ด้านบน แล้วคั่นด้วยบรรทัด <code>-- @DOWN</code>
    ตามด้วยคำสั่งย้อนกลับ จากนั้นกลับมาที่หน้านี้แล้วกด “รัน migration ที่ค้างอยู่”</p>
  <p class="hint">ข้อควรระวัง: MariaDB ไม่รองรับ transaction สำหรับคำสั่ง DDL
    ถ้ารุ่นใดล้มเหลวกลางคัน รุ่นก่อนหน้าที่รันสำเร็จจะยังคงอยู่ จึงควรเขียนแต่ละไฟล์ให้จบในตัว
    และสำรองฐานข้อมูลก่อนรันบนเครื่อง production เสมอ</p>
</div>
