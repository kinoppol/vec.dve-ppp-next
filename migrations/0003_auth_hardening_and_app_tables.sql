-- 0003 — ระบบสิทธิ์เดียว + ตารางของแอปพลิเคชันใหม่
--
-- เพิ่มคอลัมน์แบบ "ต่อเติม" เท่านั้น ไม่แก้ไข/ไม่ลบคอลัมน์ของโครงสร้างเดิม
-- (college_password ยังอยู่ครบ เพื่อให้ระบบเดิมอ่านข้อมูลได้ต่อ)

ALTER TABLE `provincial_vocational_offices`
  ADD COLUMN `password_hash`        VARCHAR(255) NULL AFTER `college_password`,
  ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 1 AFTER `password_hash`,
  ADD COLUMN `password_changed_at`  DATETIME NULL AFTER `must_change_password`,
  ADD COLUMN `last_login_at`        DATETIME NULL AFTER `password_changed_at`;

ALTER TABLE `admins`
  ADD COLUMN `password_changed_at` DATETIME NULL AFTER `password`,
  ADD COLUMN `last_login_at`       DATETIME NULL AFTER `password_changed_at`;

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
DROP TABLE IF EXISTS `share_links`;
DROP TABLE IF EXISTS `report_steps`;
DROP TABLE IF EXISTS `app_settings`;

ALTER TABLE `admins`
  DROP COLUMN `password_changed_at`,
  DROP COLUMN `last_login_at`;

ALTER TABLE `provincial_vocational_offices`
  DROP COLUMN `password_hash`,
  DROP COLUMN `must_change_password`,
  DROP COLUMN `password_changed_at`,
  DROP COLUMN `last_login_at`;
