-- 0005 — ข้อมูลตั้งต้นที่ระบบต้องมีจึงจะใช้งานได้
-- (ภาค / ภูมิภาค สอศ. / ประเภทความพิการ / ค่าตั้งค่าเริ่มต้น / ขั้นตอนเอกสาร)
-- ข้อมูลจริง 77 จังหวัด, 75 นิคมฯ, 171 สาขา ให้นำเข้าจาก production ภายหลัง

INSERT IGNORE INTO `geographies` (`id`, `name`) VALUES
  (1,'ภาคเหนือ'), (2,'ภาคตะวันออกเฉียงเหนือ'), (3,'ภาคกลาง'),
  (4,'ภาคตะวันออก'), (5,'ภาคตะวันตก'), (6,'ภาคใต้');

INSERT IGNORE INTO `vec_region` (`id`, `region_name`) VALUES
  (1,'ภาคเหนือ'), (2,'ภาคตะวันออกเฉียงเหนือ'), (3,'ภาคกลาง'),
  (4,'ภาคตะวันออกและกรุงเทพมหานคร'), (5,'ภาคใต้');

INSERT IGNORE INTO `college_types` (`id`, `type_name`) VALUES
  (1,'วิทยาลัยเทคนิค'), (2,'วิทยาลัยอาชีวศึกษา'), (3,'วิทยาลัยการอาชีพ'),
  (4,'วิทยาลัยสารพัดช่าง'), (5,'วิทยาลัยเกษตรและเทคโนโลยี'), (6,'วิทยาลัยเทคโนโลยีและการจัดการ');

INSERT IGNORE INTO `app_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
  ('site_name',           'DVE PPP',              NOW()),
  ('site_tagline',        'ระบบติดตามความร่วมมือ', NOW()),
  ('survey_round',        'Yearly',               NOW()),
  ('report_step_count',   '5',                    NOW()),
  ('rows_per_page',       '25',                   NOW()),
  ('allow_public_search', '1',                    NOW());

-- @DOWN
DELETE FROM `app_settings` WHERE `setting_key` IN
  ('site_name','site_tagline','survey_round','report_step_count','rows_per_page','allow_public_search');
DELETE FROM `college_types` WHERE `id` BETWEEN 1 AND 6;
DELETE FROM `vec_region` WHERE `id` BETWEEN 1 AND 5;
DELETE FROM `geographies` WHERE `id` BETWEEN 1 AND 6;
