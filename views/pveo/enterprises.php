<?php
/** 5.5 รายการสถานประกอบการของ สอจ. — ค้นหา/กรอง/เรียง/แบ่งหน้า + เวอร์ชันการ์ดบนมือถือ */
?>
<div class="page-head">
  <div>
    <h1>สถานประกอบการ</h1>
    <div class="sub">
      <?php if ($activeEstate): ?><?= e($activeEstate['estate_name']) ?> · <?php endif; ?>
      พบ <?= num($total) ?> แห่ง · ปีการศึกษา <?= e($year) ?>
    </div>
  </div>
  <span class="spacer"></span>
  <a class="btn btn-primary no-print" href="<?= e(url('pveo/enterprises/new')) ?>">เพิ่มสถานประกอบการ</a>
</div>

<form method="get" class="filter-bar no-print">
  <label class="field" style="flex:1;max-width:320px">
    <span>ค้นหา</span>
    <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="ชื่อสถานประกอบการ หรือประเภทกิจการ">
  </label>
  <label class="field">
    <span>สถานะแบบสำรวจ</span>
    <select class="input" name="status">
      <option value="">ทั้งหมด</option>
      <option value="surveyed"  <?= $status === 'surveyed'  ? 'selected' : '' ?>>สำรวจแล้ว</option>
      <option value="pending"   <?= $status === 'pending'   ? 'selected' : '' ?>>ยังไม่สำรวจ</option>
      <option value="nostudent" <?= $status === 'nostudent' ? 'selected' : '' ?>>ไม่ประสงค์รับนักศึกษา</option>
    </select>
  </label>
  <label class="field">
    <span>เรียงตาม</span>
    <select class="input" name="sort">
      <option value="name"    <?= $sort === 'name'    ? 'selected' : '' ?>>ชื่อ (ก-ฮ)</option>
      <option value="score"   <?= $sort === 'score'   ? 'selected' : '' ?>>คะแนนความสมบูรณ์</option>
      <option value="updated" <?= $sort === 'updated' ? 'selected' : '' ?>>แก้ไขล่าสุด</option>
    </select>
  </label>
  <button class="btn" type="submit">กรอง</button>
  <?php if ($q !== '' || $status !== '' || $sort !== 'name'): ?>
    <a class="btn btn-ghost" href="<?= e(url('pveo/enterprises')) ?>">ล้าง</a>
  <?php endif; ?>
</form>

<div class="table-cards">
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>สถานประกอบการ</th>
          <th>ประเภทกิจการ</th>
          <th class="num">ความสมบูรณ์</th>
          <th>แบบสำรวจ <?= e($year) ?></th>
          <th class="nw no-print">การจัดการ</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td>
            <a href="<?= e(url('pveo/enterprises/' . $row['id'])) ?>"><?= e($row['enterprise_name']) ?></a>
            <?php if (!empty($row['contact_name'])): ?>
              <div class="hint">ผู้ติดต่อ: <?= e($row['contact_name']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= e($row['business_type'] ?? '—') ?></td>
          <td class="num" style="min-width:130px">
            <?php $score = (int) $row['score']; ?>
            <div class="progress <?= $score < 50 ? 'is-low' : ($score < 80 ? 'is-mid' : '') ?>">
              <span style="width:<?= $score ?>%"></span>
            </div>
            <span class="progress-label"><?= $score ?>/100</span>
          </td>
          <td>
            <?php if (!empty($row['no_student'])): ?>
              <span class="badge badge-mute">⊘ ไม่ประสงค์รับ</span>
            <?php elseif (($row['survey_status'] ?? '') === 'submitted'): ?>
              <span class="badge badge-ok">✔ ส่งแล้ว</span>
            <?php elseif (($row['survey_status'] ?? '') === 'draft'): ?>
              <span class="badge badge-warn">◐ ร่าง</span>
            <?php else: ?>
              <span class="badge badge-err">✕ ยังไม่สำรวจ</span>
            <?php endif; ?>
          </td>
          <td class="nw no-print">
            <a class="btn btn-sm btn-primary" href="<?= e(url('pveo/survey/' . $row['id'])) ?>">
              <?= ($row['survey_status'] ?? '') === '' ? 'เริ่มแบบสำรวจ' : 'แก้ไขแบบสำรวจ' ?>
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($rows === []): ?>
        <tr><td colspan="5" class="empty-state">
          <span class="big">🏢</span>
          ยังไม่มีสถานประกอบการในนิคมฯ นี้
          <div><a href="<?= e(url('pveo/enterprises/new')) ?>">เพิ่มสถานประกอบการแรก</a></div>
        </td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- มือถือ: ผู้ใช้ลงพื้นที่จริงกรอกข้อมูลบนโทรศัพท์ -->
  <div class="card-list">
    <?php foreach ($rows as $row): ?>
      <div class="row-card">
        <a href="<?= e(url('pveo/enterprises/' . $row['id'])) ?>"><strong><?= e($row['enterprise_name']) ?></strong></a>
        <div class="hint"><?= e($row['business_type'] ?? '—') ?></div>
        <dl>
          <dt>ความสมบูรณ์</dt><dd><?= (int) $row['score'] ?>/100</dd>
          <dt>แบบสำรวจ</dt>
          <dd><?= ($row['survey_status'] ?? '') === 'submitted' ? '✔ ส่งแล้ว' : (($row['survey_status'] ?? '') === 'draft' ? '◐ ร่าง' : '✕ ยังไม่สำรวจ') ?></dd>
        </dl>
        <a class="btn btn-sm btn-primary" style="margin-top:8px;width:100%;justify-content:center"
           href="<?= e(url('pveo/survey/' . $row['id'])) ?>">กรอกแบบสำรวจ</a>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($pages > 1): ?>
  <nav class="pagination no-print">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
      <?php if ($p === $page): ?>
        <span class="is-on"><?= $p ?></span>
      <?php else: ?>
        <a href="<?= e(App\Core\Url::withQuery(['page' => $p])) ?>"><?= $p ?></a>
      <?php endif; ?>
    <?php endfor; ?>
  </nav>
<?php endif; ?>
