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
        <?= e($kpi['value']) ?><?php if (!empty($kpi['unit'])): ?><span class="kpi-unit"><?= e($kpi['unit']) ?></span><?php endif; ?>
        <?php if (isset($kpi['extra'])): ?>
          <span class="kpi-sep">/</span><?= e($kpi['extra']) ?><span class="kpi-unit"><?= e($kpi['extraUnit'] ?? '') ?></span>
        <?php endif; ?>
      </div>
      <div class="kpi-label"><?= e($kpi['label']) ?></div>
      <?php if (!empty($kpi['hint'])): ?><div class="hint"><?= e($kpi['hint']) ?></div><?php endif; ?>
    </div>
  <?php endforeach; ?>
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
            <div class="bar-head"><span><?= e($row['label']) ?></span><span class="spacer"></span><span class="bar-num"><?= num($row['total']) ?></span></div>
            <div class="progress is-brand"><span style="width:<?= (int) min(100, $row['share']) ?>%"></span></div>
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
            <div class="bar-head"><span><?= e($row['label']) ?></span><span class="spacer"></span><span class="bar-num"><?= num($row['total']) ?></span></div>
            <div class="progress is-info"><span style="width:<?= (int) min(100, $row['share']) ?>%"></span></div>
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
          <div class="progress is-brand"><span style="width:<?= (int) min(100, $row['share']) ?>%"></span></div>
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
        <thead><tr><th>นิคมอุตสาหกรรม</th><th>จังหวัด</th><th class="num">สถานประกอบการ</th><th class="num">สำรวจแล้ว</th><th class="num">คิดเป็น</th></tr></thead>
        <tbody>
        <?php foreach ($estates as $row): ?>
          <?php $share = pct((int) $row['surveyed_count'], (int) $row['enterprise_total']); ?>
          <tr>
            <td><?= e($row['estate_name']) ?></td>
            <td><?= e($row['province_name']) ?></td>
            <td class="num"><?= num((int) $row['enterprise_total']) ?></td>
            <td class="num"><?= num((int) $row['surveyed_count']) ?></td>
            <td class="num"><?= $share === null ? '—' : num($share, 1) . '%' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
