-- 0001 — ตารางข้อมูลอ้างอิง (พื้นที่ / ภาค / หลักสูตร / สถานศึกษา)
-- โครงสร้างอ้างอิงจาก REDESIGNBRIEF.md §8 — ห้ามแก้ชื่อคอลัมน์ที่ระบบเดิมใช้อยู่

CREATE TABLE IF NOT EXISTS `geographies` (
  `id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `vec_region` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `region_name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `provinces` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `province_name` VARCHAR(150) NOT NULL,
  `geography_id`  INT UNSIGNED NULL,
  `vec_region_id` INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `idx_provinces_geography` (`geography_id`),
  KEY `idx_provinces_region` (`vec_region_id`),
  CONSTRAINT `fk_provinces_geography` FOREIGN KEY (`geography_id`) REFERENCES `geographies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_provinces_region` FOREIGN KEY (`vec_region_id`) REFERENCES `vec_region` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `districts` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `district_name` VARCHAR(150) NOT NULL,
  `province_id`   INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_districts_province` (`province_id`),
  CONSTRAINT `fk_districts_province` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sub_districts` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sub_district_name` VARCHAR(150) NOT NULL,
  `district_id`       INT UNSIGNED NOT NULL,
  `zip_code`          VARCHAR(10) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_subdistricts_district` (`district_id`),
  CONSTRAINT `fk_subdistricts_district` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `college_types` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type_name` VARCHAR(150) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `college` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `college_code`    VARCHAR(20)  NOT NULL,
  `college_name`    VARCHAR(255) NOT NULL,
  `college_type_id` INT UNSIGNED NULL,
  `province_id`     INT UNSIGNED NULL,
  `address`         VARCHAR(255) NULL,
  `phone`           VARCHAR(50)  NULL,
  `email`           VARCHAR(150) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_college_code` (`college_code`),
  KEY `idx_college_province` (`province_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 171 สาขา (ปวช. 67 / ปวส. 105) — level: vc = ปวช., hvc = ปวส.
CREATE TABLE IF NOT EXISTS `vocational_curriculum` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_code` VARCHAR(20)  NOT NULL,
  `course_name` VARCHAR(255) NOT NULL,
  `course_type` VARCHAR(150) NULL,
  `level`       ENUM('vc','hvc') NOT NULL DEFAULT 'vc',
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_curriculum_code_level` (`course_code`, `level`),
  KEY `idx_curriculum_type` (`course_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- @DOWN
-- ไม่มีส่วนย้อนกลับโดยเจตนา
--
-- ตารางอ้างอิงทั้งหมดในไฟล์นี้เป็นของ "ระบบเดิม" ด้วย และอาจมีข้อมูลจริง
-- (77 จังหวัด, อำเภอ/ตำบล, วิทยาลัย, 171 สาขาวิชา) อยู่ก่อนแล้ว
-- ส่วน UP ใช้ CREATE TABLE IF NOT EXISTS จึงไม่เคยเขียนทับของเดิม
-- ถ้าใส่ DROP TABLE ไว้ที่นี่ ปุ่ม "ย้อนกลับ" ในหน้า admin/migrations
-- จะลบข้อมูลของระบบเดิมทิ้งทั้งหมด — ห้ามเพิ่มกลับเข้ามา
--
-- ต้องการรื้อจริง ให้ DBA ทำเองบนฐานข้อมูลที่ตั้งใจ พร้อมสำรองข้อมูลก่อน
