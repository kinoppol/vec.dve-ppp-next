<?php
/**
 * หน้าแรกสาธารณะ — สถิติภาพรวมประเทศ
 *
 * โดนัทวาดด้วย conic-gradient ล้วน ๆ ไม่มี JavaScript ตามข้อกำหนดของระบบ
 * $parts ต้องมี share (ร้อยละ) และ cat (เลขสี 1-8) มาแล้วจาก controller
 */
$donut = static function (array $parts): string {
    $stops = [];
    $acc   = 0.0;
    $last  = count($parts) - 1;
    foreach (array_values($parts) as $i => $part) {
        $from = $acc;
        $acc  = $i === $last ? 100.0 : $acc + (float) $part['share'];
        $stops[] = sprintf('var(--cat-%d) %.2f%% %.2f%%', (int) $part['cat'], $from, $acc);
    }
    return 'conic-gradient(' . implode(',', $stops) . ')';
};
?>
<div class="page-head">
  <div>
    <h1>ภาพรวมความร่วมมือทั้งประเทศ</h1>
    <div class="sub">ระบบฐานข้อมูลความต้องการกำลังคน เพื่อการจัดการอาชีวศึกษาระบบทวิภาคี ภายใต้ความร่วมมือระหว่างสถานประกอบการและสำนักงานคณะกรรมการการอาชีวศึกษา · ปีการศึกษา <?= e($year) ?></div>
  </div>
  <span class="spacer"></span>
  <a class="btn" href="<?= e(url('search')) ?>">ค้นหาสถานประกอบการ</a>
  <a class="btn" href="<?= e(url('downloads')) ?>">ดาวน์โหลดแบบฟอร์ม</a>
</div>

<div class="kpi-row">
  <?php foreach ($kpis as $kpi): ?>
    <?php $share = isset($kpi['bar']) && $kpi['bar'] !== null ? (float) $kpi['bar'] : null; ?>
    <div class="kpi">
      <div class="kpi-icon" aria-hidden="true"><?= e($kpi['icon']) ?></div>
      <div class="kpi-body">
        <div class="kpi-main">
          <div class="kpi-value">
            <span class="kpi-num"><?= e($kpi['value']) ?><?php if (!empty($kpi['unit'])): ?><span class="kpi-unit"><?= e($kpi['unit']) ?></span><?php endif; ?></span>
            <?php if (isset($kpi['extra'])): ?>
              <span class="kpi-sep" aria-hidden="true">/</span>
              <span class="kpi-num"><?= e($kpi['extra']) ?><?php if (!empty($kpi['extraUnit'])): ?><span class="kpi-unit"><?= e($kpi['extraUnit']) ?></span><?php endif; ?></span>
            <?php endif; ?>
          </div>
          <div class="kpi-label"><?= e($kpi['label']) ?></div>
          <?php if (!empty($kpi['hint'])): ?><div class="hint"><?= e($kpi['hint']) ?></div><?php endif; ?>
        </div>
        <?php if ($share !== null): ?>
          <?php $cat = (int) ($kpi['cat'] ?? 1); $fill = min(100, $share); ?>
          <div class="donut-fig donut-sm">
            <div class="donut donut-sm"
                 style="background: conic-gradient(var(--cat-<?= $cat ?>) 0 <?= $fill ?>%, var(--mute-bg) <?= $fill ?>% 100%)"
                 role="img" aria-label="<?= e($kpi['label']) ?> คิดเป็น <?= num($share) ?>%"></div>
            <div class="donut-center"><strong><?= num($share) ?>%</strong></div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid-2">
  <div class="card">
    <h2>สัดส่วนความต้องการกำลังคน</h2>
    <?php if ($demandSplit['total'] === 0): ?>
      <div class="empty-state"><span class="big">👷</span>ยังไม่มีข้อมูลความต้องการของปีการศึกษานี้</div>
    <?php else: ?>
      <?php $parts = array_values(array_filter($demandSplit['parts'], static fn (array $p): bool => $p['value'] > 0)); ?>
      <div class="chart-row">
        <div class="donut-fig donut-lg">
          <div class="donut donut-lg" style="background: <?= e($donut($parts)) ?>"
               role="img" aria-label="สัดส่วนความต้องการกำลังคนแยกตามระบบและระดับชั้น"></div>
          <div class="donut-center">
            <strong><?= num($demandSplit['total']) ?></strong>
            <span class="muted">คน</span>
          </div>
        </div>
        <div class="legend">
          <?php foreach ($parts as $part): ?>
            <span class="legend-item">
              <span class="legend-dot c<?= (int) $part['cat'] ?>" aria-hidden="true"></span>
              <?= e($part['label']) ?>
              <span class="legend-num"><?= num($part['value']) ?></span>
              <span class="muted">คน · <?= num($part['share'], 1) ?>%</span>
            </span>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>สัดส่วนลักษณะกิจการของสถานประกอบการ</h2>
    <?php if ($businessTypes === []): ?>
      <div class="empty-state"><span class="big">🏢</span>ยังไม่มีการระบุลักษณะกิจการ</div>
    <?php else: ?>
      <?php $bizTotal = array_sum(array_column($businessTypes, 'count')); ?>
      <div class="chart-row">
        <div class="donut-fig donut-lg">
          <div class="donut donut-lg" style="background: <?= e($donut($businessTypes)) ?>"
               role="img" aria-label="สัดส่วนลักษณะกิจการของสถานประกอบการ"></div>
          <div class="donut-center">
            <strong><?= num($bizTotal) ?></strong>
            <span class="muted">แห่ง</span>
          </div>
        </div>
        <div class="legend">
          <?php foreach ($businessTypes as $row): ?>
            <span class="legend-item">
              <span class="legend-dot c<?= (int) $row['cat'] ?>" aria-hidden="true"></span>
              <?= e(str_excerpt($row['label'], 38)) ?>
              <span class="legend-num"><?= num($row['count']) ?></span>
              <span class="muted">แห่ง · <?= num($row['share']) ?>%</span>
            </span>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <h2>10 อันดับสาขาวิชา · ทวิภาคี</h2>
    <?php if ($topDve === []): ?>
      <div class="empty-state"><span class="big">🤝</span>ยังไม่มีข้อมูลความต้องการของปีการศึกษานี้</div>
    <?php else: ?>
      <div class="bar-list">
        <?php foreach ($topDve as $row): ?>
          <div class="bar-row">
            <div class="bar-head"><span><?= e($row['label']) ?></span><span class="spacer"></span><span class="bar-num"><?= num($row['total']) ?> คน</span></div>
            <div class="progress c<?= (int) $row['cat'] ?>"><span style="width:<?= (int) min(100, $row['share']) ?>%"></span></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>10 อันดับสาขาวิชา · ฝึกงาน</h2>
    <?php if ($topIntern === []): ?>
      <div class="empty-state"><span class="big">🧑‍🏭</span>ยังไม่มีข้อมูลความต้องการของปีการศึกษานี้</div>
    <?php else: ?>
      <div class="bar-list">
        <?php foreach ($topIntern as $row): ?>
          <div class="bar-row">
            <div class="bar-head"><span><?= e($row['label']) ?></span><span class="spacer"></span><span class="bar-num"><?= num($row['total']) ?> คน</span></div>
            <div class="progress c<?= (int) $row['cat'] ?>"><span style="width:<?= (int) min(100, $row['share']) ?>%"></span></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <h2>นิคมอุตสาหกรรมที่มีข้อมูลมากที่สุด</h2>
  <?php if ($estates === []): ?>
    <div class="empty-state"><span class="big">🏭</span>ยังไม่มีข้อมูลในระบบ</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>นิคมอุตสาหกรรม</th><th>จังหวัด</th><th class="num">สถานประกอบการ</th><th class="num">สำรวจแล้ว</th><th>ความคืบหน้า</th></tr></thead>
        <tbody>
        <?php foreach ($estates as $row): ?>
          <?php $done = pct((int) $row['surveyed_count'], (int) $row['enterprise_total']); ?>
          <tr>
            <td><?= e($row['estate_name']) ?></td>
            <td><?= e($row['province_name']) ?></td>
            <td class="num"><?= num((int) $row['enterprise_total']) ?></td>
            <td class="num"><?= num((int) $row['surveyed_count']) ?></td>
            <td>
              <?php if ($done === null): ?>
                —
              <?php else: ?>
                <div class="progress <?= $done > 100 ? 'is-over' : 'c2' ?>"><span style="width:<?= (int) min(100, $done) ?>%"></span></div>
                <span class="progress-label"><?= num($done, 1) ?>%</span>
                <?php if ($done > 100): ?>
                  <span class="badge badge-warn">⚠ เกินจำนวนที่แจ้งไว้</span>
                <?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
