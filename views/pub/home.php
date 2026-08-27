<?php /** หน้าแรกสาธารณะ — สถิติภาพรวมประเทศ */ ?>
<div class="page-head">
  <div>
    <h1>ภาพรวมความร่วมมือทั้งประเทศ</h1>
    <div class="sub">ระบบติดตามความร่วมมือระหว่างอาชีวศึกษากับสถานประกอบการ · ปีการศึกษา <?= e($year) ?></div>
  </div>
  <span class="spacer"></span>
  <a class="btn" href="<?= e(url('search')) ?>">ค้นหาสถานประกอบการ</a>
  <a class="btn" href="<?= e(url('downloads')) ?>">ดาวน์โหลดแบบฟอร์ม</a>
</div>

<div class="kpi-row">
  <?php foreach ($kpis as $kpi): ?>
    <div class="kpi">
      <div class="kpi-icon" aria-hidden="true"><?= e($kpi['icon']) ?></div>
      <div class="kpi-value"><?= e($kpi['value']) ?></div>
      <div class="kpi-label"><?= e($kpi['label']) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <h2>นิคมอุตสาหกรรมที่มีข้อมูลมากที่สุด</h2>
  <?php if ($estates === []): ?>
    <div class="empty-state"><span class="big">🏭</span>ยังไม่มีข้อมูลในระบบ</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>นิคมอุตสาหกรรม</th><th>จังหวัด</th><th class="num">สถานประกอบการ</th><th class="num">สำรวจแล้ว</th></tr></thead>
        <tbody>
        <?php foreach ($estates as $row): ?>
          <tr>
            <td><?= e($row['estate_name']) ?></td>
            <td><?= e($row['province_name']) ?></td>
            <td class="num"><?= num((int) $row['enterprise_total']) ?></td>
            <td class="num"><?= num((int) $row['surveyed_count']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
