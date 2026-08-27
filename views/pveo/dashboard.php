<?php
/** แดชบอร์ดของ สอจ. — ความคืบหน้าเทียบโควตา */
?>
<div class="page-head">
  <div>
    <h1>แดชบอร์ดของฉัน</h1>
    <div class="sub">ปีการศึกษา <?= e($year) ?>
      <?php if ($activeEstate): ?>· นิคมฯ ที่ทำงาน: <?= e($activeEstate['estate_name']) ?><?php endif; ?>
    </div>
  </div>
  <span class="spacer"></span>
  <a class="btn btn-primary no-print" href="<?= e(url('pveo/enterprises/new')) ?>">เพิ่มสถานประกอบการ</a>
</div>

<div class="kpi-row">
  <div class="kpi">
    <div class="kpi-icon" aria-hidden="true">🎯</div>
    <div class="kpi-value"><?= num($targetTotal) ?></div>
    <div class="kpi-label">โควตาเป้าหมาย</div>
    <div class="hint">รวมทุกนิคมฯ ที่รับผิดชอบ</div>
  </div>
  <div class="kpi">
    <div class="kpi-icon" aria-hidden="true">✔</div>
    <div class="kpi-value"><?= num($doneTotal) ?></div>
    <div class="kpi-label">สำรวจแล้ว</div>
    <div class="hint">ปีการศึกษา <?= e($year) ?></div>
  </div>
  <div class="kpi">
    <div class="kpi-icon" aria-hidden="true">％</div>
    <div class="kpi-value"><?= $percent === null ? '—' : num($percent, 2) . '%' ?></div>
    <div class="kpi-label">ความคืบหน้า</div>
    <div class="hint"><?= $percent !== null && $percent > 100 ? '⚠ เกินเป้าหมายที่ตั้งไว้' : 'สำรวจแล้ว ÷ โควตา' ?></div>
  </div>
  <a class="kpi" href="<?= e(url('pveo/enterprises', ['status' => 'pending'])) ?>">
    <div class="kpi-icon" aria-hidden="true">📝</div>
    <div class="kpi-value"><?= num($draftCount) ?></div>
    <div class="kpi-label">แบบสำรวจที่เป็นร่าง</div>
    <div class="hint">คลิกเพื่อดูรายการ</div>
  </a>
</div>

<div class="card">
  <h2>ความคืบหน้ารายนิคมอุตสาหกรรม</h2>
  <?php if ($assignments === []): ?>
    <div class="empty-state">
      <span class="big">🏭</span>
      ยังไม่ได้รับมอบหมายนิคมอุตสาหกรรมสำหรับปี <?= e($year) ?>
      <div class="hint">ติดต่อผู้ดูแลระบบเพื่อขอรับมอบหมายและกำหนดโควตา</div>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>นิคมอุตสาหกรรม</th><th class="num">โควตา</th><th class="num">สำรวจแล้ว</th><th>ความคืบหน้า</th></tr></thead>
        <tbody>
        <?php foreach ($assignments as $a): $p = $a['percent']; ?>
          <tr>
            <td><?= e($a['estate_name']) ?><div class="hint"><?= e($a['province_name']) ?></div></td>
            <td class="num"><?= num((int) $a['target_count']) ?></td>
            <td class="num"><?= num((int) $a['surveyed_count']) ?></td>
            <td style="min-width:170px">
              <div class="progress <?= $a['is_over'] ? 'is-over' : ($p === null ? '' : ($p < 50 ? 'is-low' : ($p < 80 ? 'is-mid' : ''))) ?>">
                <span style="width:<?= $p === null ? 0 : min(100, $p) ?>%"></span>
              </div>
              <span class="progress-label"><?= $p === null ? 'ยังไม่ตั้งโควตา' : num($p, 2) . '%' ?></span>
              <?php if ($a['is_over']): ?><span class="badge badge-err">⚠ เกินเป้าหมาย</span><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
