-- 0006 — คอลัมน์ที่แอปใหม่ต้องใช้ แต่ฐานข้อมูล production ไม่มี
--
-- เพิ่มแบบต่อเติมล้วน ทุกคอลัมน์เป็น NULL ได้หรือมี DEFAULT ระบบเดิมจึงยัง
-- INSERT/SELECT ได้เหมือนเดิมโดยไม่ต้องแก้โค้ด และ ADD COLUMN IF NOT EXISTS
-- ทำให้รันซ้ำได้ปลอดภัย (DDL ของ MariaDB ไม่เป็น transaction)
--
-- ตั้งใจเพิ่มให้น้อยที่สุด สิ่งที่ production ไม่มีแนวคิดนั้นอยู่แล้ว เช่น
-- is_active ของ industrial_estates / provincial_vocational_offices
-- เลือกวิธี "ตัดเงื่อนไขออกจาก query" แทนการเพิ่มคอลัมน์เข้าไปในตารางของเดิม

-- แบบสำรวจ: ระบบเดิมบันทึกครั้งเดียวจบ ไม่มีสถานะร่างและไม่มีตัวชี้ขั้นตอน
-- แอปใหม่แบ่งเป็น 10 ขั้นตอนและบันทึกร่างระหว่างทาง จึงต้องมีที่เก็บสถานะ
--
-- DEFAULT ของสองคอลัมน์แรกตั้งไว้ที่ "ทำเสร็จแล้ว" โดยตั้งใจ เพราะแถวที่มีอยู่
-- ก่อนหน้านี้คือแบบสำรวจที่ระบบเดิมบันทึกจบไปแล้วทั้งหมด
ALTER TABLE `surveys`
  ADD COLUMN IF NOT EXISTS `status`         VARCHAR(20)      NOT NULL DEFAULT 'submitted'
      COMMENT 'draft = กำลังกรอก, submitted = ส่งแล้ว (แถวเดิมของระบบเดิมถือว่าส่งแล้ว)',
  ADD COLUMN IF NOT EXISTS `current_step`   TINYINT UNSIGNED NOT NULL DEFAULT 10
      COMMENT 'ขั้นตอนล่าสุดที่กรอกถึงใน wizard 10 ขั้น',
  ADD COLUMN IF NOT EXISTS `certifier_date` DATE             NULL
      COMMENT 'วันที่รับรองแบบสำรวจ (ระบบเดิมมีแต่ชื่อ/ตำแหน่ง/เบอร์)';

-- โควตา: ระบบเดิมกันการเขียนทับด้วยการนับว่านิคมฯ นี้มี สอจ. ดูแลกี่แห่ง
-- แอปใหม่ให้ผู้ดูแลตั้งโควตาเองได้ จึงต้องมีธงบอกว่าแถวนี้ตั้งมือ ห้าม sync ทับ
ALTER TABLE `pveo_estate_assignments`
  ADD COLUMN IF NOT EXISTS `is_manual` TINYINT(1) NOT NULL DEFAULT 0
      COMMENT '1 = ผู้ดูแลตั้งโควตาเอง PppSyncPveoEstateAssignments จะไม่เขียนทับ';

-- @DOWN
-- ย้อนกลับได้ เพราะทั้งสี่คอลัมน์เป็นของแอปใหม่ล้วน ๆ ระบบเดิมไม่รู้จัก
-- ข้อมูลในคอลัมน์เดิมของทั้งสองตารางไม่ถูกแตะ
ALTER TABLE `pveo_estate_assignments`
  DROP COLUMN IF EXISTS `is_manual`;

ALTER TABLE `surveys`
  DROP COLUMN IF EXISTS `certifier_date`,
  DROP COLUMN IF EXISTS `current_step`,
  DROP COLUMN IF EXISTS `status`;
