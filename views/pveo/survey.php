<?php
/**
 * แบบสำรวจ PPP-002 — แบ่ง 10 ขั้นตอน มีตัวบอกความคืบหน้าและบันทึกร่างทุกขั้น
 * แทนหน้าเดียวยาว 1,200 บรรทัดของระบบเดิม
 */
use App\Core\Csrf;

$action = url('pveo/survey/' . $enterprise['id']);
?>
<div class="page-head">
  <div>
    <h1>แบบสำรวจ PPP-002</h1>
    <div class="sub">
      <?= e($enterprise['enterprise_name']) ?> · ปีการศึกษา <?= e($survey['survey_year']) ?>
      <?php if ($survey['status'] === 'submitted'): ?>
        <span class="badge badge-ok">✔ ส่งแล้ว</span>
      <?php else: ?>
        <span class="badge badge-warn">◐ ร่าง — บันทึกอัตโนมัติทุกขั้นตอน</span>
      <?php endif; ?>
    </div>
  </div>
  <span class="spacer"></span>
  <a class="btn btn-ghost no-print" href="<?= e(url('pveo/enterprises/' . $enterprise['id'])) ?>">← กลับ</a>
</div>

<ol class="stepper no-print">
  <?php foreach ($steps as $no => $label): ?>
    <li class="stepper-item <?= $no === $step ? 'is-current' : ($no < (int) $survey['current_step'] ? 'is-done' : '') ?>">
      <span class="stepper-dot"><?= $no < (int) $survey['current_step'] && $no !== $step ? '✔' : $no ?></span>
      <span class="stepper-label hide-sm"><?= e($label) ?></span>
    </li>
  <?php endforeach; ?>
</ol>

<form method="post" action="<?= e($action) ?>">
  <?= Csrf::field() ?>
  <input type="hidden" name="step" value="<?= (int) $step ?>">

  <div class="card">
    <h2>ขั้นตอนที่ <?= (int) $step ?> · <?= e($steps[$step]) ?></h2>

    <?php if ($step === 1): ?>
      <p class="hint">ข้อมูลนี้มาจากทะเบียนสถานประกอบการ แก้ไขได้ที่หน้ารายละเอียด</p>
      <table class="table">
        <tbody>
          <tr><th style="width:220px">ชื่อสถานประกอบการ</th><td><?= e($enterprise['enterprise_name']) ?></td></tr>
          <tr><th>ประเภทกิจการ</th><td><?= e($enterprise['business_type'] ?? '—') ?></td></tr>
          <tr><th>ที่อยู่</th><td><?= e($enterprise['address'] ?? '—') ?></td></tr>
          <tr><th>ผู้ติดต่อ</th><td><?= e($enterprise['contact_name'] ?? '—') ?> <?= e($enterprise['contact_phone'] ?? '') ?></td></tr>
        </tbody>
      </table>

    <?php elseif ($step === 2): ?>
      <div class="form-grid form-grid-2">
        <label class="field">
          <span>วันที่ลงพื้นที่</span>
          <input class="input" type="date" name="survey_date" value="<?= e($survey['survey_date'] ?? '') ?>">
          <span class="hint">แสดงผลเป็น พ.ศ. ในรายงาน</span>
        </label>
        <label class="field field-wide">
          <label style="display:flex;align-items:center;gap:8px;font-size:14px;color:var(--ink)">
            <input type="checkbox" name="no_student_required" value="1" <?= (int) $survey['no_student_required'] === 1 ? 'checked' : '' ?>>
            สถานประกอบการแจ้ง “ไม่ประสงค์รับนักเรียน/นักศึกษา”
          </label>
          <span class="hint">เลือกข้อนี้แล้วไม่ต้องกรอกความต้องการกำลังคนในขั้นตอนที่ 4</span>
        </label>
      </div>

    <?php elseif ($step === 3): ?>
      <p class="hint">ประวัติการรับนักเรียน/นักศึกษาย้อนหลัง (ไม่บังคับ)</p>
      <table class="table">
        <thead><tr><th>ปีการศึกษา</th><th>สถานศึกษา</th><th>สาขา</th><th class="num">จำนวน</th><th>ระบบ</th></tr></thead>
        <tbody>
        <?php for ($i = 0; $i < 3; $i++): $t = $trainings[$i] ?? []; ?>
          <tr>
            <td><input class="input" type="text" name="training[<?= $i ?>][academic_year]" pattern="25[0-9]{2}" value="<?= e($t['academic_year'] ?? '') ?>"></td>
            <td><input class="input" type="text" name="training[<?= $i ?>][college_name]" value="<?= e($t['college_name'] ?? '') ?>"></td>
            <td><input class="input" type="text" name="training[<?= $i ?>][course_name]" value="<?= e($t['course_name'] ?? '') ?>"></td>
            <td><input class="input" type="number" min="0" name="training[<?= $i ?>][student_count]" value="<?= (int) ($t['student_count'] ?? 0) ?>"></td>
            <td>
              <select class="input" name="training[<?= $i ?>][system_type]">
                <option value="">—</option>
                <option value="internship" <?= ($t['system_type'] ?? '') === 'internship' ? 'selected' : '' ?>>ฝึกงาน</option>
                <option value="dve"        <?= ($t['system_type'] ?? '') === 'dve'        ? 'selected' : '' ?>>ทวิภาคี</option>
              </select>
            </td>
          </tr>
        <?php endfor; ?>
        </tbody>
      </table>

    <?php elseif ($step === 4): ?>
      <?php if ((int) $survey['no_student_required'] === 1): ?>
        <div class="alert alert-info">
          <span aria-hidden="true">i</span>
          <div>สถานประกอบการนี้แจ้ง “ไม่ประสงค์รับนักเรียน/นักศึกษา” — ข้ามขั้นตอนนี้ได้</div>
        </div>
      <?php endif; ?>
      <p class="hint">แถวละสาขา แยกจำนวน ปวช./ปวส. และ ชาย/หญิง (ปล่อยว่างได้ถ้าไม่ใช้)</p>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>ระบบ</th><th style="min-width:220px">สาขาวิชา</th>
              <th class="num">ปวช. ช</th><th class="num">ปวช. ญ</th>
              <th class="num">ปวส. ช</th><th class="num">ปวส. ญ</th>
              <th>รับผู้พิการ</th>
            </tr>
          </thead>
          <tbody>
          <?php for ($i = 0; $i < max(4, count($demands) + 1); $i++): $d = $demands[$i] ?? []; ?>
            <tr>
              <td>
                <select class="input" name="demand[<?= $i ?>][system_type]">
                  <option value="internship" <?= ($d['system_type'] ?? '') === 'internship' ? 'selected' : '' ?>>ฝึกงาน</option>
                  <option value="dve"        <?= ($d['system_type'] ?? '') === 'dve'        ? 'selected' : '' ?>>ทวิภาคี</option>
                </select>
              </td>
              <td>
                <input class="input" list="course-list" name="demand[<?= $i ?>][course_name]" value="<?= e($d['course_name'] ?? '') ?>">
              </td>
              <td><input class="input" type="number" min="0" name="demand[<?= $i ?>][vc_male]"    value="<?= (int) ($d['vc_male'] ?? 0) ?>"></td>
              <td><input class="input" type="number" min="0" name="demand[<?= $i ?>][vc_female]"  value="<?= (int) ($d['vc_female'] ?? 0) ?>"></td>
              <td><input class="input" type="number" min="0" name="demand[<?= $i ?>][hvc_male]"   value="<?= (int) ($d['hvc_male'] ?? 0) ?>"></td>
              <td><input class="input" type="number" min="0" name="demand[<?= $i ?>][hvc_female]" value="<?= (int) ($d['hvc_female'] ?? 0) ?>"></td>
              <td><input type="checkbox" name="demand[<?= $i ?>][disability_flag]" value="1" <?= !empty($d['disability_flag']) ? 'checked' : '' ?>></td>
            </tr>
          <?php endfor; ?>
          </tbody>
        </table>
      </div>
      <datalist id="course-list">
        <?php foreach ($courses as $c): ?>
          <option value="<?= e($c['course_name']) ?>"><?= e(($c['level'] === 'hvc' ? 'ปวส. ' : 'ปวช. ') . ($c['course_type'] ?? '')) ?></option>
        <?php endforeach; ?>
      </datalist>
      <?php if ($courses === []): ?>
        <p class="hint">ยังไม่ได้นำเข้ารายการสาขาวิชา (171 สาขา) — พิมพ์ชื่อสาขาเองได้ก่อน</p>
      <?php endif; ?>

    <?php elseif ($step === 5): ?>
      <label class="field">
        <span>สถานะครูฝึกประสบการณ์ในสถานประกอบการ</span>
        <textarea class="input" name="teacher_training_status" rows="4"><?= e($survey['teacher_training_status'] ?? '') ?></textarea>
      </label>

    <?php elseif ($step === 6): ?>
      <p class="hint">สวัสดิการที่สถานประกอบการจัดให้นักเรียน/นักศึกษา</p>
      <div class="form-grid form-grid-2">
        <?php
        $welfare = [
            'welfare_accommodation' => 'ที่พัก',
            'welfare_meal'          => 'อาหาร',
            'welfare_transport'     => 'ค่าเดินทาง/รถรับส่ง',
            'welfare_allowance'     => 'เบี้ยเลี้ยง',
            'welfare_insurance'     => 'ประกันอุบัติเหตุ',
        ];
        foreach ($welfare as $field => $label): ?>
          <label style="display:flex;align-items:center;gap:8px;font-size:14px">
            <input type="checkbox" name="<?= e($field) ?>" value="1" <?= (int) ($survey[$field] ?? 0) === 1 ? 'checked' : '' ?>>
            <?= e($label) ?>
          </label>
        <?php endforeach; ?>
        <label class="field field-wide">
          <span>สวัสดิการอื่น ๆ</span>
          <input class="input" type="text" name="welfare_other" value="<?= e($survey['welfare_other'] ?? '') ?>">
        </label>
      </div>

    <?php elseif ($step === 7): ?>
      <p class="hint">ข้อสรุปจากการประชุม/หารือกับสถานประกอบการ</p>
      <?php for ($i = 0; $i < max(3, count($notes) + 1); $i++): $n = $notes[$i] ?? []; ?>
        <div class="form-grid form-grid-2" style="margin-bottom:var(--s-3)">
          <label class="field">
            <span>หัวข้อที่ <?= $i + 1 ?></span>
            <input class="input" type="text" name="note[<?= $i ?>][topic]" value="<?= e($n['topic'] ?? '') ?>">
          </label>
          <label class="field">
            <span>ข้อสรุป</span>
            <input class="input" type="text" name="note[<?= $i ?>][conclusion]" value="<?= e($n['conclusion'] ?? '') ?>">
          </label>
        </div>
      <?php endfor; ?>

    <?php elseif ($step === 8): ?>
      <label class="field">
        <span>ข้อเสนอแนะจากสถานประกอบการ</span>
        <textarea class="input" name="suggestion_text" rows="6"><?= e($survey['suggestion_text'] ?? '') ?></textarea>
      </label>

    <?php elseif ($step === 9): ?>
      <label class="field">
        <span>ปัญหาและอุปสรรคในการดำเนินงาน</span>
        <textarea class="input" name="problem_obstacle" rows="6"><?= e($survey['problem_obstacle'] ?? '') ?></textarea>
      </label>

    <?php else: ?>
      <p class="hint">ตรวจสอบความถูกต้องก่อนส่ง เมื่อส่งแล้วสถานะจะเปลี่ยนเป็น “ส่งแล้ว”</p>
      <table class="table">
        <tbody>
          <tr><th style="width:240px">วันที่ลงพื้นที่</th><td><?= e(thai_date($survey['survey_date'])) ?></td></tr>
          <tr><th>ไม่ประสงค์รับนักศึกษา</th><td><?= (int) $survey['no_student_required'] === 1 ? 'ใช่' : 'ไม่ใช่' ?></td></tr>
          <tr><th>รายการความต้องการกำลังคน</th><td><?= num(count($demands)) ?> รายการ</td></tr>
          <tr><th>ข้อสรุปการประชุม</th><td><?= num(count($notes)) ?> ข้อ</td></tr>
        </tbody>
      </table>

      <div class="form-grid form-grid-2" style="margin-top:var(--s-4)">
        <label class="field">
          <span>ชื่อผู้รับรองข้อมูล <em class="req">*</em></span>
          <input class="input" type="text" name="certifier_name" value="<?= e($survey['certifier_name'] ?? '') ?>">
        </label>
        <label class="field">
          <span>ตำแหน่ง</span>
          <input class="input" type="text" name="certifier_position" value="<?= e($survey['certifier_position'] ?? '') ?>">
        </label>
        <label class="field">
          <span>วันที่รับรอง</span>
          <input class="input" type="date" name="certifier_date" value="<?= e($survey['certifier_date'] ?? '') ?>">
        </label>
      </div>
    <?php endif; ?>
  </div>

  <div class="form-actions no-print">
    <?php if ($step > 1): ?>
      <button class="btn" type="submit" name="action" value="prev">← ขั้นตอนก่อนหน้า</button>
    <?php endif; ?>
    <span class="spacer" style="flex:1"></span>
    <?php if ($step < count($steps)): ?>
      <button class="btn btn-primary" type="submit" name="action" value="next">บันทึกร่างและไปต่อ →</button>
    <?php else: ?>
      <button class="btn btn-primary" type="submit" name="action" value="submit">ส่งแบบสำรวจ</button>
    <?php endif; ?>
  </div>
</form>
