<?php /** ค้นหาสถานประกอบการ (สาธารณะ) */ ?>
<div class="page-head">
  <div>
    <h1>ค้นหาสถานประกอบการ</h1>
    <div class="sub">พบ <?= num($total) ?> รายการ</div>
  </div>
</div>

<form method="get" class="filter-bar">
  <label class="field" style="flex:1;max-width:320px">
    <span>คำค้นหา</span>
    <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="ชื่อสถานประกอบการ">
  </label>
  <label class="field">
    <span>จังหวัด</span>
    <select class="input" name="province_id">
      <option value="">ทุกจังหวัด</option>
      <?php foreach ($provinces as $p): ?>
        <option value="<?= (int) $p['id'] ?>" <?= $provinceId === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['province_name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="field">
    <span>นิคมอุตสาหกรรม</span>
    <select class="input" name="estate_id">
      <option value="">ทุกนิคมฯ</option>
      <?php foreach ($estates as $est): ?>
        <option value="<?= (int) $est['id'] ?>" <?= $estateId === (int) $est['id'] ? 'selected' : '' ?>><?= e($est['estate_name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button class="btn btn-primary" type="submit">ค้นหา</button>
  <a class="btn btn-ghost" href="<?= e(url('search')) ?>">ล้าง</a>
</form>

<div class="table-wrap">
  <table class="table">
    <thead><tr><th>สถานประกอบการ</th><th>ประเภทกิจการ</th><th>นิคมอุตสาหกรรม</th><th>จังหวัด</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
      <tr>
        <td><?= e($row['enterprise_name']) ?></td>
        <td><?= e($row['business_type'] ?? '—') ?></td>
        <td><?= e($row['estate_name'] ?? '—') ?></td>
        <td><?= e($row['province_name']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($rows === []): ?>
      <tr><td colspan="4" class="empty-state">
        <span class="big">🔍</span>ไม่พบสถานประกอบการตามเงื่อนไข
        <div class="hint">ลองลดเงื่อนไขการค้นหา</div>
      </td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($pages > 1): ?>
  <nav class="pagination">
    <?php for ($p = 1; $p <= min($pages, 20); $p++): ?>
      <?php if ($p === $page): ?><span class="is-on"><?= $p ?></span>
      <?php else: ?><a href="<?= e(App\Core\Url::withQuery(['page' => $p])) ?>"><?= $p ?></a><?php endif; ?>
    <?php endfor; ?>
  </nav>
<?php endif; ?>
