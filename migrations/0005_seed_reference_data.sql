-- 0005 — ข้อมูลตั้งต้นที่ระบบต้องมีจึงจะใช้งานได้
-- (ภาค / ภูมิภาค สอศ. / ประเภทวิทยาลัย / ค่าตั้งค่าเริ่มต้น)
-- ข้อมูลจริง 77 จังหวัด, 75 นิคมฯ, 171 สาขา ให้นำเข้าจาก production ภายหลัง
--
-- ตารางอ้างอิงสามตารางแรกเป็นของระบบเดิมด้วย จึงเติมให้ "เฉพาะตอนที่ตารางว่าง"
-- เท่านั้น (JOIN กับ COUNT(*) = 0) บนฐานข้อมูลร่วมที่ระบบเดิมมีข้อมูลจริงอยู่แล้ว
-- migration นี้จะไม่เพิ่มแถวใด ๆ เข้าไปปนกับของเดิม
--
-- ใช้ INSERT IGNORE ซ้อนอีกชั้นกันกรณีมีแถวบางส่วนอยู่ก่อน

INSERT IGNORE INTO `geographies` (`id`, `name`)
SELECT s.id, s.name FROM (
            SELECT 1 AS id, 'ภาคเหนือ' AS name
  UNION ALL SELECT 2, 'ภาคตะวันออกเฉียงเหนือ'
  UNION ALL SELECT 3, 'ภาคกลาง'
  UNION ALL SELECT 4, 'ภาคตะวันออก'
  UNION ALL SELECT 5, 'ภาคตะวันตก'
  UNION ALL SELECT 6, 'ภาคใต้'
) s
JOIN (SELECT COUNT(*) AS n FROM `geographies`) g ON g.n = 0;

INSERT IGNORE INTO `vec_region` (`id`, `region_name`)
SELECT s.id, s.region_name FROM (
            SELECT 1 AS id, 'ภาคเหนือ' AS region_name
  UNION ALL SELECT 2, 'ภาคตะวันออกเฉียงเหนือ'
  UNION ALL SELECT 3, 'ภาคกลาง'
  UNION ALL SELECT 4, 'ภาคตะวันออกและกรุงเทพมหานคร'
  UNION ALL SELECT 5, 'ภาคใต้'
) s
JOIN (SELECT COUNT(*) AS n FROM `vec_region`) g ON g.n = 0;

INSERT IGNORE INTO `college_types` (`id`, `type_name`)
SELECT s.id, s.type_name FROM (
            SELECT 1 AS id, 'วิทยาลัยเทคนิค' AS type_name
  UNION ALL SELECT 2, 'วิทยาลัยอาชีวศึกษา'
  UNION ALL SELECT 3, 'วิทยาลัยการอาชีพ'
  UNION ALL SELECT 4, 'วิทยาลัยสารพัดช่าง'
  UNION ALL SELECT 5, 'วิทยาลัยเกษตรและเทคโนโลยี'
  UNION ALL SELECT 6, 'วิทยาลัยเทคโนโลยีและการจัดการ'
) s
JOIN (SELECT COUNT(*) AS n FROM `college_types`) g ON g.n = 0;

-- app_settings เป็นตารางของแอปใหม่ล้วน ๆ เติมได้เสมอ
INSERT IGNORE INTO `app_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
  ('site_name',           'DVE PPP',              NOW()),
  ('site_tagline',        'ระบบติดตามความร่วมมือ', NOW()),
  ('survey_round',        'Yearly',               NOW()),
  ('report_step_count',   '5',                    NOW()),
  ('rows_per_page',       '25',                   NOW()),
  ('allow_public_search', '1',                    NOW());

-- @DOWN
-- ลบเฉพาะค่าตั้งค่าของแอปใหม่ ซึ่งอยู่ในตาราง app_settings ที่ 0003 สร้างขึ้นเอง
DELETE FROM `app_settings` WHERE `setting_key` IN
  ('site_name','site_tagline','survey_round','report_step_count','rows_per_page','allow_public_search');

-- ไม่ลบข้อมูลใน geographies / vec_region / college_types
--
-- ส่วน UP เติมให้เฉพาะตอนตารางว่าง บนฐานข้อมูลร่วมจึงไม่ได้เพิ่มอะไรเลย
-- การสั่ง DELETE ... WHERE id BETWEEN 1 AND 6 ตอนย้อนกลับจะเท่ากับลบข้อมูล
-- อ้างอิงของระบบเดิมที่ตัวเองไม่ได้สร้าง และตารางอื่นที่อ้าง id เหล่านี้
-- (provinces, college, enterprises) จะกำพร้าทันที
