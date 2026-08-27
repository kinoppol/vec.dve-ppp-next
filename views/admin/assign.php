<?php
/** จัดการ สอจ. — บัญชีผู้ใช้ / มอบหมายนิคมฯ / โควตา / สวมสิทธิ์ */
use App\Core\Csrf;
?>
<div class="page-head">
  <div>
    <h1>จัดการ สอจ. และโควตา</h1>
    <div class="sub">ปีการศึกษา <?= e($year) ?> · <?= num(count($rows)) ?> แห่ง</div>
  </div>
  <span class="spacer"></span>
  <form method="post" action="<?= e(url('admin/assign/sync')) ?>" class="no-print"
        onsubmit="return confirm('ปรับยอดสำรวจของปี <?= e($year) ?> ให้ตรงกับข้อมูลจริง? โควตาที่ตั้งเองจะไม่ถูกเขียนทับ')">
    <?= Csrf::field() ?>
    <button class="btn btn-primary" type="submit">ปรับยอดสำรวจให้ตรงกับข้อมูล</button>
  </form>
</div>

<div class="table-wrap">
  <table class="table">
    <thead>
      <tr>
        <th>สอจ.</th>
        <th>จังหวัด</th>
        <th class="num">นิคมฯ</th>
        <th class="num">เป้าหมาย</th>
        <th class="num">สำรวจแล้ว</th>
        <th>ความคืบหน้า</th>
        <th>บัญชี</th>
        <th class="nw no-print">การจัดการ</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
      <tr>
        <td><?= e($row['college_name']) ?><div class="hint mono"><?= e($row['college_code']) ?></div></td>
        <td><?= e($row['province_name']) ?></td>
        <td class="num"><?= num((int) $row['estate_count']) ?></td>
        <td class="num"><?= num((int) $row['target_total']) ?></td>
        <td class="num"><?= num((int) $row['surveyed_total']) ?></td>
        <td style="min-width:160px">
          <?php $p = $row['percent']; ?>
          <div class="progress <?= $row['is_over'] ? 'is-over' : ($p === null ? '' : ($p < 50 ? 'is-low' : ($p < 80 ? 'is-mid' : ''))) ?>">
            <span style="width:<?= $p === null ? 0 : min(100, $p) ?>%"></span>
          </div>
          <span class="progress-label"><?= $p === null ? 'ยังไม่ตั้งโควตา' : num($p, 2) . '%' ?></span>
          <?php if ($row['is_over']): ?><span class="badge badge-err">⚠ เกินเป้าหมาย</span><?php endif; ?>
        </td>
        <td>
          <?php if (!empty($row['has_password'])): ?>
            <span class="badge badge-ok">✔ ตั้งรหัสผ่านแล้ว</span>
          <?php else: ?>
            <span class="badge badge-err" title="รหัสผ่านยังเท่ากับรหัสวิทยาลัย">✕ รหัสผ่านเริ่มต้น</span>
          <?php endif; ?>
          <div class="hint">เข้าใช้ล่าสุด <?= e(thai_date($row['last_login_at'], true)) ?></div>
        </td>
        <td class="nw no-print">
          <form method="post" action="<?= e(url('admin/impersonate')) ?>" style="display:inline"
                onsubmit="return confirm('เข้าสู่โหมดสวมสิทธิ์ในฐานะ <?= e($row['college_name']) ?>?')">
            <?= Csrf::field() ?>
            <input type="hidden" name="pveo_id" value="<?= (int) $row['pveo_id'] ?>">
            <button class="btn btn-sm" type="submit">สวมสิทธิ์</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ($rows === []): ?>
      <tr><td colspan="8" class="empty-state">
        <span class="big">👥</span>ยังไม่มีข้อมูล สอจ. — นำเข้าจากฐานข้อมูล production ก่อนใช้งานจริง
      </td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="card" style="margin-top:var(--s-4)">
  <h3>ตั้งโควตาเป้าหมายรายนิคมฯ</h3>
  <p class="hint">โควตาที่ตั้งเองจะถูกทำเครื่องหมาย <code>is_manual = 1</code>
    เพื่อไม่ให้ <code>PppSyncPveoEstateAssignments</code> เขียนทับ
    ซึ่งสำคัญมากเมื่อมีหลาย สอจ. ดูแลนิคมเดียวกัน</p>
  <form method="post" action="<?= e(url('admin/assign/quota')) ?>" class="form-grid form-grid-2">
    <?= Csrf::field() ?>
    <label class="field">
      <span>สอจ.</span>
      <select class="input" name="pveo_id" required>
        <option value="">— เลือก สอจ. —</option>
        <?php foreach ($rows as $row): ?>
          <option value="<?= (int) $row['pveo_id'] ?>"><?= e($row['college_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="field">
      <span>รหัสนิคมอุตสาหกรรม</span>
      <input class="input" type="number" name="estate_id" min="1" required>
    </label>
    <label class="field">
      <span>โควตาเป้าหมาย (แห่ง)</span>
      <input class="input" type="number" name="target_count" min="0" value="0" required>
    </label>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit">บันทึกโควตา</button>
    </div>
  </form>
</div>
