<?php
/** 5.1 แดชบอร์ดผู้ดูแลระบบ — เห็นสถานะทั้งประเทศใน 5 วินาที */
$maxRegion = 0;
foreach ($regionProgress as $r) {
    $maxRegion = max($maxRegion, (int) $r['target']);
}
$demandTotal = max(1, $demandSplit['internship'] + $demandSplit['dve']);
$maxCourse   = 0;
foreach ($topCourses as $c) {
    $maxCourse = max($maxCourse, (int) $c['total']);
}
?>
<div class="page-head">
  <div>
    <h1>แดชบอร์ดภาพรวมประเทศ</h1>
    <div class="sub">ปีการศึกษา <?= e($year) ?></div>
  </div>
  <span class="spacer"></span>
  <button class="btn no-print" type="button" onclick="window.print()">พิมพ์รายงาน</button>
</div>

<div class="kpi-row">
  <?php foreach ($kpis as $kpi): ?>
    <?php $href = $kpi['key'] !== '' ? url('admin/estates', ['kpi' => $kpi['key']]) : null; ?>
    <<?= $href ? 'a' : 'div' ?> class="kpi" <?= $href ? 'href="' . e($href) . '"' : '' ?>>
      <div class="kpi-icon" aria-hidden="true"><?= e($kpi['icon']) ?></div>
      <div class="kpi-value"><?= e($kpi['value']) ?></div>
      <div class="kpi-label"><?= e($kpi['label']) ?></div>
      <div class="hint"><?= e($kpi['note']) ?></div>
    </<?= $href ? 'a' : 'div' ?>>
  <?php endforeach; ?>
</div>

<div class="grid-2">
  <div class="card">
    <h2>ความคืบหน้ารายภาค</h2>
    <?php if ($regionProgress === []): ?>
      <div class="empty-state"><span class="big">📊</span>ยังไม่มีข้อมูลนิคมอุตสาหกรรม</div>
    <?php else: ?>
      <table class="table">
        <thead><tr><th>ภาค</th><th class="num">บันทึกแล้ว</th><th>ความคืบหน้า</th></tr></thead>
        <tbody>
        <?php foreach ($regionProgress as $r):
          $percent = pct((int) $r['recorded'], (int) $r['target']);
          $cls = $percent === null ? '' : ($percent > 100 ? 'is-over' : ($percent < 50 ? 'is-low' : ($percent < 80 ? 'is-mid' : '')));
        ?>
          <tr>
            <td><?= e($r['region']) ?></td>
            <td class="num"><?= num((int) $r['recorded']) ?> / <?= num((int) $r['target']) ?></td>
            <td>
              <div class="progress <?= $cls ?>">
                <span style="width:<?= $percent === null ? 0 : min(100, $percent) ?>%"></span>
              </div>
              <span class="progress-label"><?= $percent === null ? 'ไม่มีเป้าหมาย' : num($percent, 1) . '%' ?></span>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>สัดส่วนความต้องการ</h2>
    <p class="hint">ฝึกงาน (Internship) เทียบกับทวิภาคี (DVE) ปี <?= e($year) ?></p>
    <?php
      $internPct = round($demandSplit['internship'] / $demandTotal * 100);
    ?>
    <div class="progress" style="height:14px">
      <span style="width:<?= $internPct ?>%"></span>
    </div>
    <table class="table" style="margin-top:var(--s-3)">
      <tbody>
        <tr><td>ฝึกงาน</td><td class="num"><?= num($demandSplit['internship']) ?> คน</td><td class="num"><?= $internPct ?>%</td></tr>
        <tr><td>ทวิภาคี</td><td class="num"><?= num($demandSplit['dve']) ?> คน</td><td class="num"><?= 100 - $internPct ?>%</td></tr>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <h2>ความต้องการกำลังคน 10 สาขายอดนิยม</h2>
  <?php if ($topCourses === []): ?>
    <div class="empty-state"><span class="big">📋</span>ยังไม่มีข้อมูลความต้องการกำลังคนในปีนี้</div>
  <?php else: ?>
    <table class="table">
      <tbody>
      <?php foreach ($topCourses as $c): ?>
        <tr>
          <td style="width:38%"><?= e($c['course']) ?></td>
          <td>
            <div class="progress"><span style="width:<?= $maxCourse > 0 ? round((int) $c['total'] / $maxCourse * 100) : 0 ?>%"></span></div>
          </td>
          <td class="num nw"><?= num((int) $c['total']) ?> คน</td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-head">
    <h2 style="margin:0">สอจ. ที่คืบหน้าน้อยที่สุด 10 อันดับ</h2>
    <span class="spacer"></span>
    <a class="btn btn-sm no-print" href="<?= e(url('admin/assign')) ?>">จัดการ สอจ. และโควตา</a>
  </div>
  <?php if ($laggards === []): ?>
    <div class="empty-state">
      <span class="big">👥</span>
      ยังไม่มีการมอบหมายโควตาในปี <?= e($year) ?>
      <div><a href="<?= e(url('admin/assign')) ?>">ไปหน้าจัดการ สอจ. และโควตา</a></div>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>สอจ.</th><th class="num">เป้าหมาย</th><th class="num">สำรวจแล้ว</th><th>ความคืบหน้า</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($laggards as $l): ?>
          <tr>
            <td><?= e($l['college_name']) ?><div class="hint mono"><?= e($l['college_code']) ?></div></td>
            <td class="num"><?= num((int) $l['target_total']) ?></td>
            <td class="num"><?= num((int) $l['surveyed_total']) ?></td>
            <td>
              <div class="progress <?= $l['is_over'] ? 'is-over' : (($l['percent'] ?? 0) < 50 ? 'is-low' : 'is-mid') ?>">
                <span style="width:<?= min(100, $l['percent'] ?? 0) ?>%"></span>
              </div>
              <span class="progress-label"><?= $l['percent'] === null ? '—' : num($l['percent'], 2) . '%' ?></span>
              <?php if ($l['is_over']): ?><span class="badge badge-err">⚠ เกินเป้าหมาย</span><?php endif; ?>
            </td>
            <td class="nw no-print"><a class="btn btn-sm" href="<?= e(url('admin/assign')) ?>">ดูรายละเอียด</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
