<?php
/**
 * 5.2 ติดตามข้อมูลนิคมอุตสาหกรรม
 * ใช้ทั้งฝั่ง Admin และผู้รับลิงก์แชร์ ($readOnly = true) — หน้าจอเดียว ไม่ซ้ำซ้อน
 */
use App\Core\Csrf;
?>
<div class="page-head">
  <div>
    <h1>ติดตามข้อมูลนิคมอุตสาหกรรม</h1>
    <div class="sub">
      ปีการศึกษา <?= e($year) ?> · <?= num(count($rows)) ?> นิคมฯ
      <?php if ($readOnly): ?><span class="badge badge-info">โหมดดูสาธารณะ · อ่านอย่างเดียว</span><?php endif; ?>
    </div>
  </div>
  <span class="spacer"></span>
  <?php if (!$readOnly): ?>
    <form method="post" action="<?= e(url('admin/share')) ?>" class="no-print">
      <?= Csrf::field() ?>
      <input type="hidden" name="target" value="estates">
      <button class="btn" type="submit">แชร์ลิงก์สาธารณะ</button>
    </form>
  <?php endif; ?>
  <button class="btn no-print" type="button" onclick="window.print()">พิมพ์</button>
</div>

<div class="kpi-row no-print">
  <?php foreach ($kpis as $kpi): ?>
    <?php
      $isFilter = $kpi['key'] !== '';
      $isOn     = $isFilter && $filter === $kpi['key'];
      // คลิก KPI = กรองตาราง / คลิกซ้ำ = ยกเลิกตัวกรอง
      $href = $isFilter ? url('admin/estates', array_filter(['kpi' => $isOn ? null : $kpi['key'], 'q' => $q ?: null])) : null;
    ?>
    <<?= $href ? 'a' : 'div' ?> class="kpi <?= $isOn ? 'is-active' : '' ?>" <?= $href ? 'href="' . e($href) . '"' : '' ?>>
      <div class="kpi-icon" aria-hidden="true"><?= e($kpi['icon']) ?></div>
      <div class="kpi-value"><?= e($kpi['value']) ?></div>
      <div class="kpi-label"><?= e($kpi['label']) ?></div>
      <div class="hint"><?= $isOn ? 'คลิกอีกครั้งเพื่อยกเลิกตัวกรอง' : e($kpi['note']) ?></div>
    </<?= $href ? 'a' : 'div' ?>>
  <?php endforeach; ?>
</div>

<form method="get" class="filter-bar no-print">
  <?php if ($filter !== ''): ?><input type="hidden" name="kpi" value="<?= e($filter) ?>"><?php endif; ?>
  <label class="field" style="flex:1;max-width:360px">
    <span>ค้นหานิคมฯ หรือจังหวัด</span>
    <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="เช่น มาบตาพุด, ระยอง">
  </label>
  <button class="btn" type="submit">ค้นหา</button>
  <?php if ($q !== '' || $filter !== ''): ?>
    <a class="btn btn-ghost" href="<?= e(url('admin/estates')) ?>">ล้างตัวกรอง</a>
  <?php endif; ?>
</form>

<div class="table-cards">
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>นิคมอุตสาหกรรม</th>
          <th>สอจ. ที่รับผิดชอบ</th>
          <th class="num">สถานประกอบการ</th>
          <th class="num">ในระบบ</th>
          <th>ความคืบหน้า</th>
          <th class="num">ไม่ประสงค์รับ</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td>
            <strong><?= e($row['estate_name']) ?></strong>
            <div class="hint"><?= e($row['province_name']) ?></div>
          </td>
          <td>
            <?php foreach ($row['pveo_list'] as $name): ?>
              <span class="chip"><?= e($name) ?></span>
            <?php endforeach; ?>
            <?php if ($row['pveo_list'] === []): ?>
              <span class="badge badge-warn">◐ ยังไม่มอบหมาย</span>
            <?php endif; ?>
          </td>
          <td class="num"><?= num((int) $row['target_effective']) ?></td>
          <td class="num"><?= num((int) $row['surveyed_count']) ?></td>
          <td style="min-width:170px">
            <?php $p = $row['percent']; ?>
            <div class="progress <?= $row['is_over'] ? 'is-over' : ($p === null ? '' : ($p < 50 ? 'is-low' : ($p < 80 ? 'is-mid' : ''))) ?>">
              <span style="width:<?= $p === null ? 0 : min(100, $p) ?>%"></span>
            </div>
            <span class="progress-label"><?= $p === null ? 'ไม่มีเป้าหมาย' : num($p, 2) . '%' ?></span>
            <?php if ($row['is_over']): ?>
              <span class="badge badge-err" title="จำนวนที่สำรวจมากกว่าเป้าหมายที่ตั้งไว้">⚠ เกินเป้าหมาย</span>
            <?php endif; ?>
          </td>
          <td class="num"><?= num((int) $row['no_student_count']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($rows === []): ?>
        <tr><td colspan="6" class="empty-state">
          <span class="big">🏭</span>ไม่พบนิคมอุตสาหกรรมตามเงื่อนไขที่เลือก
        </td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- เวอร์ชันการ์ดสำหรับมือถือ (ตารางกว้างเกินจอ 375px) -->
  <div class="card-list">
    <?php foreach ($rows as $row): ?>
      <div class="row-card">
        <strong><?= e($row['estate_name']) ?></strong>
        <div class="hint"><?= e($row['province_name']) ?></div>
        <?php $p = $row['percent']; ?>
        <div class="progress <?= $row['is_over'] ? 'is-over' : '' ?>" style="margin-top:8px">
          <span style="width:<?= $p === null ? 0 : min(100, $p) ?>%"></span>
        </div>
        <dl>
          <dt>ความคืบหน้า</dt>
          <dd><?= $p === null ? '—' : num($p, 2) . '%' ?>
            <?php if ($row['is_over']): ?><span class="badge badge-err">⚠</span><?php endif; ?></dd>
          <dt>สถานประกอบการ</dt><dd><?= num((int) $row['target_effective']) ?></dd>
          <dt>ในระบบ</dt><dd><?= num((int) $row['surveyed_count']) ?></dd>
          <dt>ไม่ประสงค์รับ</dt><dd><?= num((int) $row['no_student_count']) ?></dd>
        </dl>
      </div>
    <?php endforeach; ?>
  </div>
</div>
