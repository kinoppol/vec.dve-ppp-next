-- 0003 — ระบบสิทธิ์เดียว + ตารางของแอปพลิเคชันใหม่
--
-- เพิ่มคอลัมน์แบบ "ต่อเติม" เท่านั้น ไม่แก้ไข/ไม่ลบคอลัมน์ของโครงสร้างเดิม
-- (college_password ยังอยู่ครบ เพื่อให้ระบบเดิมอ่านข้อมูลได้ต่อ)

-- ใช้ IF NOT EXISTS (MariaDB รองรับ) เพื่อให้รันซ้ำได้ปลอดภัย — DDL ของ MariaDB
-- ไม่เป็น transaction ถ้าไฟล์ล้มกลางคัน คำสั่งก่อนหน้าจะค้างอยู่แล้วรันใหม่ไม่ได้
--
-- ทุกคอลัมน์ที่เพิ่มเป็น NULL ได้ หรือมี DEFAULT ทั้งหมด ระบบเดิมจึงยัง
-- INSERT แบบระบุชื่อคอลัมน์ได้ตามปกติโดยไม่ต้องแก้โค้ด
ALTER TABLE `provincial_vocational_offices`
  ADD COLUMN IF NOT EXISTS `password_hash`        VARCHAR(255) NULL AFTER `college_password`,
  ADD COLUMN IF NOT EXISTS `must_change_password` TINYINT(1) NOT NULL DEFAULT 1 AFTER `password_hash`,
  ADD COLUMN IF NOT EXISTS `password_changed_at`  DATETIME NULL AFTER `must_change_password`,
  ADD COLUMN IF NOT EXISTS `last_login_at`        DATETIME NULL AFTER `password_changed_at`;

ALTER TABLE `admins`
  ADD COLUMN IF NOT EXISTS `password_changed_at` DATETIME NULL AFTER `password`,
  ADD COLUMN IF NOT EXISTS `last_login_at`       DATETIME NULL AFTER `password_changed_at`;

-- ตั้งค่าระบบ: ปีการศึกษา, จำนวนขั้นตอนเอกสาร, กำหนดส่ง ฯลฯ
CREATE TABLE IF NOT EXISTS `app_settings` (
  `setting_key`   VARCHAR(100) NOT NULL,
  `setting_value` TEXT NULL,
  `updated_at`    DATETIME NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ชื่อขั้นตอนเอกสารรายปี — แทนที่การ hardcode 5 ขั้นตอนในระบบเดิม
CREATE TABLE IF NOT EXISTS `report_steps` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `survey_year` VARCHAR(4) NOT NULL,
  `step_no`     TINYINT UNSIGNED NOT NULL,
  `step_name`   VARCHAR(255) NOT NULL,
  `due_date`    DATE NULL,
  `is_enabled`  TINYINT(1) NOT NULL DEFAULT 1,
  `min_files`   TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_report_step` (`survey_year`, `step_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- คะแนนความสมบูรณ์ที่ "แอปใหม่" คำนวณเอง
--
-- ระบบเดิมมีตาราง enterprise_completeness อยู่แล้วและคำนวณด้วยสูตรของตัวเอง
-- ถ้าแอปใหม่เขียนทับตารางนั้น คะแนนจะสลับไปมาระหว่างสองสูตรทุกครั้งที่ฝั่งใด
-- ฝั่งหนึ่งบันทึก จึงแยกตารางของตัวเองออกมา ระบบเดิมไม่ถูกแตะเลย
CREATE TABLE IF NOT EXISTS `ppp_enterprise_completeness` (
  `enterprise_id`    INT UNSIGNED NOT NULL,
  `score`            TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `missing_sections` VARCHAR(500) NULL,
  `calculated_at`    DATETIME NULL,
  PRIMARY KEY (`enterprise_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ไม่ผูก FOREIGN KEY ไปที่ enterprises โดยตั้งใจ — บนฐานข้อมูลร่วม การเพิ่ม
-- constraint เข้าไปที่ตารางของระบบเดิมจะเปลี่ยนพฤติกรรมการลบข้อมูลของระบบเดิม

-- ลิงก์แชร์สาธารณะของหน้าติดตามผล (token ใน URL, อ่านอย่างเดียว)
CREATE TABLE IF NOT EXISTS `share_links` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `token`       CHAR(40) NOT NULL,
  `target`      VARCHAR(50) NOT NULL COMMENT 'estates | uploads',
  `survey_year` VARCHAR(4) NULL,
  `created_by`  INT UNSIGNED NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at`  DATETIME NULL,
  `revoked_at`  DATETIME NULL,
  `hit_count`   INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_share_token` (`token`),
  KEY `idx_share_target` (`target`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- @DOWN
-- ย้อนกลับได้ เพราะทุกอย่างในไฟล์นี้เป็นของแอปใหม่ล้วน ๆ
-- ตารางทั้งสามไม่มีในระบบเดิม และคอลัมน์ทั้งหกเป็นส่วนที่ 0003 เพิ่มเข้าไปเอง
-- (college_password ของเดิมไม่ถูกแตะ ระบบเดิมจึงยังล็อกอินได้เหมือนเดิม)
DROP TABLE IF EXISTS `share_links`;
DROP TABLE IF EXISTS `ppp_enterprise_completeness`;
DROP TABLE IF EXISTS `report_steps`;
DROP TABLE IF EXISTS `app_settings`;

ALTER TABLE `admins`
  DROP COLUMN IF EXISTS `password_changed_at`,
  DROP COLUMN IF EXISTS `last_login_at`;

ALTER TABLE `provincial_vocational_offices`
  DROP COLUMN IF EXISTS `password_hash`,
  DROP COLUMN IF EXISTS `must_change_password`,
  DROP COLUMN IF EXISTS `password_changed_at`,
  DROP COLUMN IF EXISTS `last_login_at`;
