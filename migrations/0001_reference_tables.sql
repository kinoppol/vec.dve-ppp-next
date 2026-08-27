-- 0001 — ตารางอ้างอิง (ภูมิศาสตร์ / จังหวัด / สถานศึกษา / หลักสูตร)
--
-- โครงสร้างคัดลอกจากฐานข้อมูล production (ppp_db) ตรงตัว ไม่ดัดแปลงชื่อคอลัมน์
-- เพราะระบบเดิมใช้ตารางชุดเดียวกันนี้อยู่ ชื่อที่เห็นแปลก ๆ เช่น province_id
-- เป็น PK แทน id หรือ province_name_th แทน province_name คือของจริงทั้งหมด
--
-- ทุกคำสั่งเป็น CREATE TABLE IF NOT EXISTS ติดตั้งลงฐานข้อมูลเดิมได้โดยไม่แตะของเดิม
-- PRIMARY KEY / INDEX ถูกรวมไว้ใน CREATE TABLE แล้ว ไม่แยกเป็น ALTER TABLE
-- เพราะ ALTER TABLE ADD PRIMARY KEY จะ error ทันทีถ้าตารางมีอยู่ก่อน

CREATE TABLE IF NOT EXISTS `geographies` (
  `geography_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'รหัสภูมิภาค',
  `geography_name` varchar(255) NOT NULL COMMENT 'ชื่อภูมิภาค',
  PRIMARY KEY (`geography_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2 COMMENT='ภูมิภาค';

CREATE TABLE IF NOT EXISTS `vec_region` (
  `vec_region_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'รหัสภาค',
  `region_name` varchar(200) DEFAULT NULL COMMENT 'ชื่อภาค',
  PRIMARY KEY (`vec_region_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2 COMMENT='ภาค สอศ.';

CREATE TABLE IF NOT EXISTS `provinces` (
  `province_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'รหัสจังหวัด',
  `province_name_th` varchar(150) NOT NULL COMMENT 'ชื่อจังหวัด (ไทย)',
  `province_name_en` varchar(150) NOT NULL COMMENT 'ชื่อจังหวัด (อังกฤษ)',
  `geography_id` int(11) NOT NULL COMMENT 'ภูมิภาค (FK)',
  `created_at` datetime DEFAULT NULL COMMENT 'วันที่สร้าง',
  `updated_at` datetime DEFAULT NULL COMMENT 'วันที่แก้ไข',
  `deleted_at` datetime DEFAULT NULL COMMENT 'วันที่ลบ',
  PRIMARY KEY (`province_id`),
  KEY `geography_id` (`geography_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2 COMMENT='จังหวัด';

CREATE TABLE IF NOT EXISTS `districts` (
  `district_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'รหัสอำเภอ',
  `district_name_th` varchar(150) NOT NULL COMMENT 'ชื่ออำเภอ (ไทย)',
  `district_name_en` varchar(150) NOT NULL COMMENT 'ชื่ออำเภอ (อังกฤษ)',
  `province_id` int(11) NOT NULL COMMENT 'รหัสจังหวัด (FK)',
  `created_at` datetime DEFAULT NULL COMMENT 'วันที่สร้าง',
  `updated_at` datetime DEFAULT NULL COMMENT 'วันที่แก้ไข',
  `deleted_at` datetime DEFAULT NULL COMMENT 'วันที่ลบ (Soft Delete)',
  PRIMARY KEY (`district_id`),
  KEY `province_id` (`province_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2 COMMENT='อำเภอ';

CREATE TABLE IF NOT EXISTS `sub_districts` (
  `subdistrict_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'รหัสตำบล',
  `zip_code` int(11) NOT NULL COMMENT 'รหัสไปรษณีย์',
  `subdistrict_name_th` varchar(150) NOT NULL COMMENT 'ชื่อตำบล (ไทย)',
  `subdistrict_name_en` varchar(150) NOT NULL COMMENT 'ชื่อตำบล (อังกฤษ)',
  `district_id` int(11) NOT NULL COMMENT 'อำเภอ (FK)',
  `lat` double DEFAULT NULL COMMENT 'ละติจูด',
  `long` double DEFAULT NULL COMMENT 'ลองจิจูด',
  `created_at` datetime DEFAULT NULL COMMENT 'วันที่สร้าง',
  `updated_at` datetime DEFAULT NULL COMMENT 'วันที่แก้ไข',
  `deleted_at` datetime DEFAULT NULL COMMENT 'วันที่ลบ',
  PRIMARY KEY (`subdistrict_id`),
  KEY `district_id` (`district_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2 COMMENT='ตำบล';

CREATE TABLE IF NOT EXISTS `college_types` (
  `type_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'รหัสลำดับประเภท',
  `type_name` varchar(255) NOT NULL COMMENT 'ชื่อประเภทสถานศึกษา',
  PRIMARY KEY (`type_id`),
  UNIQUE KEY `type_name` (`type_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2 COMMENT='ประเภทสถานศึกษา';

CREATE TABLE IF NOT EXISTS `college` (
  `id` int(11) NOT NULL COMMENT 'ลำดับ',
  `college_code` varchar(20) NOT NULL COMMENT 'รหัสสถานศึกษา',
  `college_name` varchar(255) NOT NULL COMMENT 'ชื่อสถานศึกษา',
  `province_vocational` varchar(100) DEFAULT NULL COMMENT 'อาชีวศึกษาจังหวัด',
  `region` varchar(100) DEFAULT NULL COMMENT 'ภาค',
  `college_type` varchar(100) DEFAULT NULL COMMENT 'ประเภทสถานศึกษา',
  `address_no` varchar(50) DEFAULT NULL COMMENT 'เลขที่',
  `moo` varchar(20) DEFAULT NULL COMMENT 'หมู่',
  `soi` varchar(100) DEFAULT NULL COMMENT 'ซอย',
  `road` varchar(100) DEFAULT NULL COMMENT 'ถนน',
  `sub_district` varchar(100) DEFAULT NULL COMMENT 'ตำบล',
  `district` varchar(100) DEFAULT NULL COMMENT 'อำเภอ',
  `province` varchar(100) DEFAULT NULL COMMENT 'จังหวัด',
  `postcode` varchar(5) DEFAULT NULL COMMENT 'รหัสไปรษณีย์',
  `phone` varchar(50) DEFAULT NULL COMMENT 'โทรศัพท์',
  `latitude` decimal(10,8) DEFAULT NULL COMMENT 'พิกัดละติจูด',
  `longitude` decimal(11,8) DEFAULT NULL COMMENT 'พิกัดลองจิจูด',
  `website` varchar(255) DEFAULT NULL COMMENT 'เว็บไซต์',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2 COMMENT='ข้อมูลสถานศึกษา';

CREATE TABLE IF NOT EXISTS `vocational_curriculum` (
  `course_code` varchar(20) NOT NULL COMMENT 'รหัสสาขาวิชา',
  `course_name` varchar(255) NOT NULL COMMENT 'ชื่อสาขาวิชา',
  `career_group` varchar(255) NOT NULL COMMENT 'กลุ่มอาชีพ',
  `subject_type` varchar(255) NOT NULL COMMENT 'ประเภทวิชา',
  `education_level` varchar(50) DEFAULT 'ปวช.' COMMENT 'ระดับการศึกษา',
  `curriculum_year` varchar(50) DEFAULT 'ปวช.67' COMMENT 'ปีหลักสูตร',
  PRIMARY KEY (`course_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2 COMMENT='หลักสูตร/สาขาวิชา';

-- @DOWN
-- ไม่มีส่วนย้อนกลับโดยเจตนา
--
-- ตารางทั้งหมดในไฟล์นี้เป็นของระบบเดิม และมีข้อมูลจริงอยู่ ส่วน UP ใช้
-- CREATE TABLE IF NOT EXISTS จึงข้ามไปเฉย ๆ เมื่อตารางมีอยู่แล้ว
-- ถ้าใส่ DROP TABLE ไว้ที่นี่ ปุ่ม "ย้อนกลับ" ในหน้า admin/migrations
-- จะลบฐานข้อมูลของระบบเดิมทิ้งได้จากหน้าเว็บ — ห้ามเพิ่มกลับเข้ามา
