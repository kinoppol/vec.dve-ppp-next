-- 0002 — ตารางหลักของระบบ (ผู้ใช้ / นิคมฯ / สถานประกอบการ / แบบสำรวจ / รายงาน)
--
-- โครงสร้างคัดลอกจากฐานข้อมูล production (ppp_db) ตรงตัว เช่นเดียวกับ 0001
-- จุดที่ต้องระวังเป็นพิเศษ:
--   admins                    PK คือ admin_id  ชื่อคือ admin_name (ไม่มี email / is_active)
--   provincial_vocational_offices  PK คือ pveo_id  (ไม่มี college_name — ต้อง join college)
--   industrial_estates        PK คือ industrial_estate_id  ชื่อคือ industrial_estate_name
--                             จำนวนสถานประกอบการอยู่ที่ industrial_estate_details.total_enterprises
--   pveo_estate_assignments   PK คือ assignment_id  และใช้ industrial_estate_id
--   enterprises               ชื่อคือ name  ผู้ติดต่อคือ contact_person
--   surveys                   survey_year เป็น int(4) ไม่ใช่ varchar
--
-- คอลัมน์ที่แอปใหม่ต้องใช้เพิ่มแต่ production ไม่มี ถูกเพิ่มใน 0006 แบบต่อเติมล้วน

CREATE TABLE IF NOT EXISTS `admins` (
  `admin_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'รหัสลำดับผู้ดูแลระบบ',
  `username` varchar(50) NOT NULL COMMENT 'ชื่อผู้ใช้งาน (User)',
  `password` varchar(255) NOT NULL COMMENT 'รหัสผ่าน (Hash)',
  `admin_name` varchar(100) NOT NULL COMMENT 'ชื่อ-นามสกุล',
  `created_at` datetime DEFAULT current_timestamp() COMMENT 'วันที่สร้าง',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2 COMMENT='ผู้ดูแลระบบ';

CREATE TABLE IF NOT EXISTS `provincial_vocational_offices` (
  `pveo_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'รหัสลำดับ สอจ.',
  `province_id` int(11) DEFAULT NULL COMMENT 'จังหวัดที่ตั้ง (FK)',
  `college_code` varchar(20) DEFAULT NULL COMMENT 'รหัสสถานศึกษาที่เป็นศูนย์ฯ (Username)',
  `college_password` varchar(200) DEFAULT NULL COMMENT 'รหัสผ่าน (Password)',
  PRIMARY KEY (`pveo_id`),
  KEY `province_id` (`province_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2 COMMENT='สอจ.';

CREATE TABLE IF NOT EXISTS `industrial_estates` (
  `industrial_estate_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'รหัสลำดับนิคม',
  `industrial_estate_name` varchar(200) DEFAULT NULL COMMENT 'ชื่อนิคมอุตสาหกรรม',
  `vec_region_id` int(11) DEFAULT NULL COMMENT 'รหัสภาค สอศ. (FK)',
  `province_id` int(11) DEFAULT NULL COMMENT 'จังหวัดที่ตั้ง (FK)',
  PRIMARY KEY (`industrial_estate_id`),
  KEY `vec_region_id` (`vec_region_id`),
  KEY `province_id` (`province_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2 COMMENT='นิคมอุตสาหกรรม';

CREATE TABLE IF NOT EXISTS `industrial_estate_details` (
  `detail_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'รหัสลำดับรายละเอียด',
  `industrial_estate_id` int(11) NOT NULL COMMENT 'รหัสนิคมอุตสาหกรรม (FK)',
  `address_detail` text DEFAULT NULL COMMENT 'ที่ตั้ง/รายละเอียดที่อยู่',
  `sub_district_id` int(11) DEFAULT NULL COMMENT 'ตำบล',
  `district_id` int(11) DEFAULT NULL COMMENT 'อำเภอ',
  `province_id` int(11) DEFAULT NULL COMMENT 'จังหวัด',
  `zipcode` varchar(10) DEFAULT NULL COMMENT 'รหัสไปรษณีย์',
  `supervising_agency` varchar(50) DEFAULT NULL COMMENT 'หน่วยงานกำกับดูแล (IEAT, Zone, Park)',
  `area_total` decimal(10,2) DEFAULT 0.00 COMMENT 'ขนาดพื้นที่รวม (ไร่)',
  `area_sold` decimal(10,2) DEFAULT 0.00 COMMENT 'พื้นที่ขาย/เช่า (ไร่)',
  `area_remaining` decimal(10,2) DEFAULT 0.00 COMMENT 'พื้นที่ว่างคงเหลือ (ไร่)',
  `total_enterprises` int(11) DEFAULT 0 COMMENT 'จำนวนสถานประกอบการ',
  `ind_eng_count` int(11) DEFAULT 0 COMMENT 'จำนวนอุตฯ วิศวกรรมและการผลิต',
  `ind_digital_count` int(11) DEFAULT 0 COMMENT 'จำนวนอุตฯ ดิจิทัล',
  `ind_service_count` int(11) DEFAULT 0 COMMENT 'จำนวนอุตฯ บริการ',
  `ind_bio_count` int(11) DEFAULT 0 COMMENT 'จำนวนอุตฯ ชีวภาพ',
  `service_oss` tinyint(1) DEFAULT 0 COMMENT 'บริการ One Stop Service',
  `service_eec` tinyint(1) DEFAULT 0 COMMENT 'เขต EEC',
  `service_boi` tinyint(1) DEFAULT 0 COMMENT 'สนับสนุน BOI',
  `service_skill` tinyint(1) DEFAULT 0 COMMENT 'พัฒนาทักษะแรงงาน',
  `service_desc` text DEFAULT NULL COMMENT 'คำอธิบายบริการเพิ่มเติม',
  `coord_name` varchar(255) DEFAULT NULL COMMENT 'ชื่อผู้ประสานงาน',
  `coord_position` varchar(255) DEFAULT NULL COMMENT 'ตำแหน่งผู้ประสานงาน',
  `coord_phone_direct` varchar(50) DEFAULT NULL COMMENT 'เบอร์ตรง',
  `coord_phone_main` varchar(50) DEFAULT NULL COMMENT 'เบอร์หลัก',
  `coord_email` varchar(100) DEFAULT NULL COMMENT 'อีเมล',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`detail_id`),
  UNIQUE KEY `industrial_estate_id` (`industrial_estate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2 COMMENT='ตารางเก็บรายละเอียดนิคมอุตสาหกรรมเพิ่มเติม';

CREATE TABLE IF NOT EXISTS `industrial_estate_responsibility` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'รหัสลำดับ',
  `pveo_id` int(11) NOT NULL COMMENT 'รหัส สอจ. (FK)',
  `industrial_estate_id` int(11) NOT NULL COMMENT 'รหัสนิคม (FK)',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'สถานะ (1=ใช้งาน, 0=ยกเลิก)',
  `assigned_date` date DEFAULT current_timestamp() COMMENT 'วันที่มอบหมาย',
  `created_at` datetime DEFAULT current_timestamp() COMMENT 'วันที่สร้าง',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`id`),
  KEY `idx_pveo` (`pveo_id`),
  KEY `idx_estate` (`industrial_estate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2 COMMENT='ตารางกำหนดสิทธิ์การดูแลนิคมของ สอจ.';

CREATE TABLE IF NOT EXISTS `pveo_estate_assignments` (
  `assignment_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'รหัสลำดับการมอบหมาย',
  `pveo_id` int(11) NOT NULL COMMENT 'รหัส สอจ. ที่รับผิดชอบ',
  `industrial_estate_id` int(11) NOT NULL COMMENT 'รหัสนิคมอุตสาหกรรมเป้าหมาย',
  `survey_year` int(4) NOT NULL COMMENT 'ปี พ.ศ. ที่ออกสำรวจ (เช่น 2569)',
  `target_count` int(11) NOT NULL DEFAULT 0 COMMENT 'จำนวนสถานประกอบการที่ต้องออกสำรวจ (เป้าหมาย)',
  `surveyed_count` int(11) NOT NULL DEFAULT 0 COMMENT 'จำนวนสถานประกอบการที่สำรวจแล้ว',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'อัปเดตล่าสุดเมื่อมีการสำรวจเพิ่ม',
  PRIMARY KEY (`assignment_id`),
  UNIQUE KEY `unique_assignment` (`pveo_id`,`industrial_estate_id`,`survey_year`),
  KEY `fk_quota_estate` (`industrial_estate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='ตารางกำหนดโควตาและติดตามความก้าวหน้าการสำรวจนิคมฯ ของ สอจ.';

CREATE TABLE IF NOT EXISTS `enterprises` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'รหัสลำดับสถานประกอบการ',
  `tax_id` varchar(20) DEFAULT NULL COMMENT 'เลขผู้เสียภาษี',
  `name` varchar(255) NOT NULL COMMENT 'ชื่อสถานประกอบการ',
  `business_type` varchar(200) DEFAULT NULL COMMENT 'ลักษณะกิจการ',
  `business_type_other` varchar(255) DEFAULT NULL COMMENT 'ระบุลักษณะอื่นๆ',
  `address_no` varchar(150) DEFAULT NULL COMMENT 'ที่อยู่',
  `province_id` int(11) DEFAULT NULL COMMENT 'จังหวัด (FK)',
  `district_id` int(11) DEFAULT NULL COMMENT 'อำเภอ (FK)',
  `sub_district_id` int(11) DEFAULT NULL COMMENT 'ตำบล (FK)',
  `zipcode` varchar(10) DEFAULT NULL COMMENT 'รหัสไปรษณีย์',
  `industrial_estate_id` int(11) DEFAULT NULL COMMENT 'นิคมอุตสาหกรรม (FK)',
  `phone` varchar(100) DEFAULT NULL COMMENT 'เบอร์โทรศัพท์',
  `email` varchar(150) DEFAULT NULL COMMENT 'อีเมล',
  `contact_person` varchar(255) DEFAULT NULL COMMENT 'ชื่อผู้ประสานงาน',
  `contact_position` varchar(150) DEFAULT NULL COMMENT 'ตำแหน่งผู้ประสานงาน',
  `contact_phone` varchar(100) DEFAULT NULL COMMENT 'เบอร์โทรผู้ประสานงาน',
  `contact_line_id` varchar(100) DEFAULT NULL COMMENT 'Line ID',
  `created_at` datetime DEFAULT current_timestamp() COMMENT 'วันที่สร้าง',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`id`),
  KEY `idx_tax_id` (`tax_id`),
  KEY `idx_province` (`province_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2 COMMENT='สถานประกอบการ';

CREATE TABLE IF NOT EXISTS `enterprise_completeness` (
  `enterprise_id` int(11) NOT NULL,
  `completeness_score` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'คะแนนรวมความสมบูรณ์ของข้อมูลสถานประกอบการ (0-100)',
  `basic_score` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'คะแนนข้อมูลพื้นฐานสถานประกอบการ 10%',
  `contact_score` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'คะแนนข้อมูลผู้ติดต่อ 5%',
  `communication_score` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'คะแนนช่องทางการติดต่อ 5%',
  `demand_score` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'คะแนนข้อมูลความต้องการกำลังคน 60%',
  `meeting_score` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'คะแนนข้อสรุปการประชุม 10%',
  `suggestion_score` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'คะแนนข้อเสนอแนะ 10%',
  `survey_count` int(11) NOT NULL DEFAULT 0 COMMENT 'จำนวนครั้งที่มีการสำรวจสถานประกอบการ',
  `last_survey_date` datetime DEFAULT NULL COMMENT 'วันที่สำรวจล่าสุด',
  `last_updated_at` datetime DEFAULT NULL COMMENT 'วันที่มีการอัปเดตข้อมูลล่าสุด',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างข้อมูลสรุป',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่ปรับปรุงข้อมูลสรุปล่าสุด',
  PRIMARY KEY (`enterprise_id`),
  KEY `idx_completeness_score` (`completeness_score`),
  KEY `idx_last_updated_at` (`last_updated_at`),
  KEY `idx_last_survey_date` (`last_survey_date`),
  KEY `idx_survey_count` (`survey_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2 COMMENT='ตารางสรุปคะแนนความสมบูรณ์ข้อมูลสถานประกอบการสำหรับระบบ Dashboard และวิเคราะห์ข้อมูล';

CREATE TABLE IF NOT EXISTS `surveys` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'รหัสลำดับการสำรวจ',
  `enterprise_id` int(11) NOT NULL COMMENT 'สถานประกอบการ (FK)',
  `pveo_id` int(11) NOT NULL COMMENT 'สอจ. เจ้าของพื้นที่ (FK)',
  `recorder_college_code` varchar(20) NOT NULL COMMENT 'ผู้บันทึกข้อมูล (User)',
  `target_college_code` varchar(20) NOT NULL COMMENT 'สถานศึกษาที่ลงพื้นที่',
  `province_id_report` int(11) NOT NULL COMMENT 'จังหวัดพื้นที่ดำเนินการ',
  `operation_date` date NOT NULL COMMENT 'วันที่ลงพื้นที่',
  `survey_year` int(4) NOT NULL COMMENT 'ปีการศึกษา',
  `survey_round` varchar(50) DEFAULT 'Yearly' COMMENT 'รอบการสำรวจ',
  `internship_period_start` varchar(2) DEFAULT NULL COMMENT 'เดือนเริ่มฝึกงาน',
  `internship_period_end` varchar(2) DEFAULT NULL COMMENT 'เดือนสิ้นสุดฝึกงาน',
  `dve_period_start` varchar(2) DEFAULT NULL COMMENT 'เดือนเริ่มทวิภาคี',
  `dve_period_end` varchar(2) DEFAULT NULL COMMENT 'เดือนสิ้นสุดทวิภาคี',
  `no_student_required` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'สถานประกอบการไม่ประสงค์รับนักเรียนนักศึกษาเข้าฝึกงาน/ฝึกอาชีพ',
  `welfare_scholarship` tinyint(1) DEFAULT 0 COMMENT 'มีทุนการศึกษา',
  `welfare_scholarship_amount` decimal(10,2) DEFAULT 0.00 COMMENT 'จำนวนเงินทุน',
  `welfare_allowance` tinyint(1) DEFAULT 0 COMMENT 'มีเบี้ยเลี้ยง',
  `welfare_allowance_amount` varchar(100) DEFAULT NULL COMMENT 'จำนวนเบี้ยเลี้ยง',
  `welfare_accident` tinyint(1) DEFAULT 0 COMMENT 'มีประกันอุบัติเหตุ',
  `welfare_uniform` tinyint(1) DEFAULT 0 COMMENT 'มีชุดยูนิฟอร์ม',
  `welfare_accommodation` tinyint(1) DEFAULT 0 COMMENT 'มีที่พัก',
  `welfare_accommodation_detail` varchar(255) DEFAULT NULL COMMENT 'รายละเอียดที่พัก',
  `welfare_other_flag` tinyint(1) DEFAULT 0 COMMENT 'มีสวัสดิการอื่น',
  `welfare_other_text` varchar(255) DEFAULT NULL COMMENT 'รายละเอียดสวัสดิการอื่น',
  `teacher_training_status` enum('yes','no') DEFAULT 'no' COMMENT 'รับครูฝึกงาน',
  `teacher_training_text` text DEFAULT NULL COMMENT 'รายละเอียดรับครู',
  `suggestion_text` text DEFAULT NULL COMMENT 'ข้อเสนอแนะ',
  `problem_obstacle` text DEFAULT NULL COMMENT 'ปัญหาและอุปสรรคในการดำเนินความร่วมมือหรือจัดการอาชีวศึกษาระบบทวิภาคี',
  `certifier_name` varchar(255) DEFAULT NULL COMMENT 'ชื่อผู้รับรอง',
  `certifier_position` varchar(255) DEFAULT NULL COMMENT 'ตำแหน่งผู้รับรอง',
  `certifier_phone` varchar(100) DEFAULT NULL COMMENT 'เบอร์โทรผู้รับรอง',
  `created_at` datetime DEFAULT current_timestamp() COMMENT 'วันที่สร้าง',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`id`),
  KEY `enterprise_id` (`enterprise_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2 COMMENT='ข้อมูลการสำรวจหลัก';

CREATE TABLE IF NOT EXISTS `survey_demands` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'รหัสลำดับ',
  `survey_id` int(11) NOT NULL COMMENT 'รหัสการสำรวจ (FK)',
  `system_type` enum('internship','dve') NOT NULL COMMENT 'ระบบ (ฝึกงาน/ทวิภาคี)',
  `course_code` varchar(20) NOT NULL COMMENT 'รหัสสาขาวิชา',
  `vc_male` int(11) DEFAULT 0 COMMENT 'ปวช. ชาย',
  `vc_female` int(11) DEFAULT 0 COMMENT 'ปวช. หญิง',
  `hvc_male` int(11) DEFAULT 0 COMMENT 'ปวส. ชาย',
  `hvc_female` int(11) DEFAULT 0 COMMENT 'ปวส. หญิง',
  `disability_flag` enum('yes','no') DEFAULT 'no' COMMENT 'รับผู้พิการ',
  `job_description` text DEFAULT NULL COMMENT 'ลักษณะงาน',
  `required_skills` text DEFAULT NULL COMMENT 'ทักษะหรือความรู้เพิ่มเติมที่ประสงค์',
  `period_type` varchar(50) DEFAULT NULL COMMENT 'ประเภทช่วงเวลา',
  `period_custom_start` varchar(2) DEFAULT NULL COMMENT 'เดือนเริ่ม (กำหนดเอง)',
  `period_custom_end` varchar(2) DEFAULT NULL COMMENT 'เดือนสิ้นสุด (กำหนดเอง)',
  PRIMARY KEY (`id`),
  KEY `survey_id` (`survey_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2 COMMENT='ความต้องการกำลังคน';

CREATE TABLE IF NOT EXISTS `survey_demand_disabilities` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'รหัสลำดับ',
  `survey_demand_id` int(11) NOT NULL COMMENT 'รหัสความต้องการ (FK)',
  `disability_type` varchar(200) NOT NULL COMMENT 'ประเภทความพิการที่รับ',
  PRIMARY KEY (`id`),
  KEY `survey_demand_id` (`survey_demand_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2 COMMENT='รายละเอียดความพิการ';

CREATE TABLE IF NOT EXISTS `survey_meeting_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'รหัสลำดับ',
  `survey_id` int(11) NOT NULL COMMENT 'รหัสการสำรวจ (FK)',
  `note_text` text NOT NULL COMMENT 'ข้อความบันทึก',
  `seq_order` int(11) DEFAULT 0 COMMENT 'ลำดับข้อ',
  PRIMARY KEY (`id`),
  KEY `survey_id` (`survey_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2 COMMENT='บันทึกการประชุม';

CREATE TABLE IF NOT EXISTS `survey_past_trainings` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'รหัสลำดับ',
  `survey_id` int(11) NOT NULL COMMENT 'รหัสการสำรวจ (FK)',
  `system_type` enum('internship','dve') NOT NULL COMMENT 'ระบบ (ฝึกงาน/ทวิภาคี)',
  `education_level` enum('vc','hvc') NOT NULL COMMENT 'ระดับชั้น (ปวช/ปวส)',
  `course_code` varchar(20) NOT NULL COMMENT 'รหัสสาขาวิชา',
  `amount` int(11) DEFAULT 0 COMMENT 'จำนวนที่เคยรับ',
  PRIMARY KEY (`id`),
  KEY `survey_id` (`survey_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_thai_520_w2 COMMENT='ประวัติการรับ';

CREATE TABLE IF NOT EXISTS `report_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pveo_id` int(11) NOT NULL COMMENT 'รหัส สอจ. (FK → provincial_vocational_offices)',
  `industrial_estate_id` int(11) NOT NULL COMMENT 'รหัสนิคม (FK → industrial_estates)',
  `academic_year` char(4) NOT NULL COMMENT 'ปีการศึกษา เช่น 2568',
  `stage` tinyint(1) NOT NULL COMMENT '1-5',
  `status` enum('draft','submitted','done') NOT NULL DEFAULT 'draft' COMMENT 'draft=บันทึกร่าง, submitted=ส่งแล้ว, done=เสร็จสิ้น',
  `note` text DEFAULT NULL COMMENT 'หมายเหตุ/บันทึกเพิ่มเติม',
  `submitted_at` datetime DEFAULT NULL COMMENT 'วันที่ submit',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stage` (`pveo_id`,`industrial_estate_id`,`academic_year`,`stage`),
  KEY `idx_pveo_estate_year` (`pveo_id`,`industrial_estate_id`,`academic_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='สถานะความคืบหน้าแต่ละ Stage';

CREATE TABLE IF NOT EXISTS `report_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `progress_id` int(11) NOT NULL COMMENT 'FK → report_progress.id',
  `file_type` varchar(50) NOT NULL COMMENT 'เช่น letter, order, plan, report1, enterprise, report2, summary',
  `original_name` varchar(255) NOT NULL COMMENT 'ชื่อไฟล์ต้นฉบับ',
  `stored_name` varchar(255) NOT NULL COMMENT 'ชื่อไฟล์ที่เก็บจริง (uuid)',
  `file_size` int(11) NOT NULL DEFAULT 0 COMMENT 'ขนาดไฟล์ bytes',
  `company_name` varchar(255) DEFAULT NULL COMMENT 'ชื่อสถานประกอบการ (สำหรับ stage 3,4)',
  `visit_date` date DEFAULT NULL COMMENT 'วันที่ลงพื้นที่จริง',
  `uploaded_by_name` varchar(255) NOT NULL COMMENT 'ชื่อผู้อัปโหลด (college_name)',
  `uploaded_by_code` varchar(20) NOT NULL COMMENT 'รหัสวิทยาลัย',
  `deleted_at` datetime DEFAULT NULL COMMENT 'soft delete',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_progress` (`progress_id`),
  KEY `idx_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ไฟล์เอกสารในแต่ละ Stage';

CREATE TABLE IF NOT EXISTS `report_activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pveo_id` int(11) NOT NULL,
  `industrial_estate_id` int(11) NOT NULL,
  `academic_year` char(4) NOT NULL,
  `stage` tinyint(1) DEFAULT NULL,
  `action` varchar(50) NOT NULL COMMENT 'upload, delete, submit, save_draft, replace',
  `description` text NOT NULL COMMENT 'คำอธิบาย',
  `actor_name` varchar(255) NOT NULL COMMENT 'ชื่อผู้กระทำ',
  `actor_code` varchar(20) NOT NULL COMMENT 'รหัสวิทยาลัย',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_context` (`pveo_id`,`industrial_estate_id`,`academic_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Activity Log การดำเนินงาน';

CREATE TABLE IF NOT EXISTS `report_completeness_pveo` (
  `pveo_id` int(11) NOT NULL COMMENT 'รหัส สอจ.',
  `province_name_th` varchar(150) NOT NULL COMMENT 'ชื่อจังหวัดภาษาไทย',
  `province_name_en` varchar(150) NOT NULL COMMENT 'ชื่อจังหวัดภาษาอังกฤษ',
  `completeness` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'ร้อยละความสมบูรณ์ข้อมูลเฉลี่ย',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'เวลาอัปเดตล่าสุด',
  PRIMARY KEY (`pveo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ตารางสรุปความสมบูรณ์ข้อมูลราย สอจ.';

CREATE TABLE IF NOT EXISTS `topics` (
  `id` varchar(16) NOT NULL,
  `title` varchar(200) NOT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'ทั่วไป',
  `content` text NOT NULL,
  `image` mediumtext DEFAULT NULL,
  `author` varchar(200) NOT NULL,
  `college_code` varchar(20) NOT NULL DEFAULT '',
  `created_at` varchar(35) NOT NULL,
  `views` int(10) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `replies` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `topic_id` varchar(16) NOT NULL,
  `author` varchar(200) NOT NULL,
  `college_code` varchar(20) NOT NULL DEFAULT '',
  `content` text NOT NULL,
  `image` mediumtext DEFAULT NULL,
  `created_at` varchar(35) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_topic_id` (`topic_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @DOWN
-- ไม่มีส่วนย้อนกลับโดยเจตนา
--
-- ตารางทั้งหมดในไฟล์นี้เป็นของระบบเดิม และมีข้อมูลจริงอยู่ ส่วน UP ใช้
-- CREATE TABLE IF NOT EXISTS จึงข้ามไปเฉย ๆ เมื่อตารางมีอยู่แล้ว
-- ถ้าใส่ DROP TABLE ไว้ที่นี่ ปุ่ม "ย้อนกลับ" ในหน้า admin/migrations
-- จะลบฐานข้อมูลของระบบเดิมทิ้งได้จากหน้าเว็บ — ห้ามเพิ่มกลับเข้ามา
