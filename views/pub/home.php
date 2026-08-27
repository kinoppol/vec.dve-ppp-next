<?php /** หน้าแรกสาธารณะ — สถิติภาพรวมประเทศ */ ?>
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
    <div class="kpi">
      <div class="kpi-icon" aria-hidden="true"><?= e($kpi['icon']) ?></div>
      <div class="kpi-value">
        <span class="kpi-num"><?= e($kpi['value']) ?><?php if (!empty($kpi['unit'])): ?><span class="kpi-unit"><?= e($kpi['unit']) ?></span><?php endif; ?></span>
        <?php if (isset($kpi['extra'])): ?>
          <span class="kpi-sep" aria-hidden="true">/</span>
          <span class="kpi-num"><?= e($kpi['extra']) ?><?php if (!empty($kpi['extraUnit'])): ?><span class="kpi-unit"><?= e($kpi['extraUnit']) ?></span><?php endif; ?></span>
        <?php endif; ?>
      </div>
      <div class="kpi-label"><?= e($kpi['label']) ?></div>
      <?php if (isset($kpi['bar']) && $kpi['bar'] !== null): ?>
        <div class="progress kpi-bar <?= e($kpi['barClass'] ?? 'c1') ?>">
          <span style="width:<?= (int) min(100, (float) $kpi['bar']) ?>%"></span>
        </div>
      <?php endif; ?>
      <?php if (!empty($kpi['hint'])): ?>
        <div class="hint"><?= e($kpi['hint']) ?><?php if (isset($kpi['bar']) && $kpi['bar'] !== null): ?> · <?= num($kpi['bar']) ?>%<?php endif; ?></div>
      <?php elseif (isset($kpi['bar']) && $kpi['bar'] !== null): ?>
        <div class="hint"><?= num($kpi['bar']) ?>%</div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <h2>สัดส่วนความต้องการกำลังคน</h2>
  <?php if ($demandSplit['total'] === 0): ?>
    <div class="empty-state"><span class="big">👷</span>ยังไม่มีข้อมูลความต้องการของปีการศึกษานี้</div>
  <?php else: ?>
    <div class="stack" role="img" aria-label="สัดส่วนความต้องการกำลังคนแยกตามระบบและระดับชั้น">
      <?php foreach ($demandSplit['parts'] as $part): ?>
        <?php if ($part['value'] > 0): ?>
          <span class="<?= e($part['class']) ?>" style="width:<?= $part['share'] ?>%" title="<?= e($part['label']) ?> <?= num($part['value']) ?> คน"></span>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <div class="legend">
      <?php foreach ($demandSplit['parts'] as $part): ?>
        <span class="legend-item">
          <span class="legend-dot <?= e($part['class']) ?>" aria-hidden="true"></span>
          <?= e($part['label']) ?>
          <span class="legend-num"><?= num($part['value']) ?></span>
          <span class="muted">คน · <?= num($part['share'], 1) ?>%</span>
        </span>
      <?php endforeach; ?>
      <span class="legend-item muted">รวม <span class="legend-num"><?= num($demandSplit['total']) ?></span> คน</span>
    </div>
  <?php endif; ?>
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
            <div class="progress <?= e($row['class']) ?>"><span style="width:<?= (int) min(100, $row['share']) ?>%"></span></div>
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
            <div class="progress <?= e($row['class']) ?>"><span style="width:<?= (int) min(100, $row['share']) ?>%"></span></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <h2>สัดส่วนลักษณะกิจการของสถานประกอบการ</h2>
  <?php if ($businessTypes === []): ?>
    <div class="empty-state"><span class="big">🏢</span>ยังไม่มีการระบุลักษณะกิจการ</div>
  <?php else: ?>
    <div class="bar-list">
      <?php foreach ($businessTypes as $row): ?>
        <div class="bar-row">
          <div class="bar-head">
            <span><?= e($row['label']) ?></span>
            <span class="spacer"></span>
            <span class="bar-num"><?= num($row['count']) ?> แห่ง · <?= num($row['share']) ?>%</span>
          </div>
          <div class="progress <?= e($row['class']) ?>"><span style="width:<?= (int) min(100, $row['share']) ?>%"></span></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
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
          <?php $share = pct((int) $row['surveyed_count'], (int) $row['enterprise_total']); ?>
          <tr>
            <td><?= e($row['estate_name']) ?></td>
            <td><?= e($row['province_name']) ?></td>
            <td class="num"><?= num((int) $row['enterprise_total']) ?></td>
            <td class="num"><?= num((int) $row['surveyed_count']) ?></td>
            <td>
              <?php if ($share === null): ?>
                —
              <?php else: ?>
                <div class="progress <?= $share > 100 ? 'is-over' : 'c2' ?>"><span style="width:<?= (int) min(100, $share) ?>%"></span></div>
                <span class="progress-label"><?= num($share, 1) ?>%</span>
                <?php if ($share > 100): ?>
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
