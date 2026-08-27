-- 0002 — ตารางหลักของระบบ: สอจ. / นิคมฯ / สถานประกอบการ / แบบสำรวจ / รายงาน

CREATE TABLE IF NOT EXISTS `admins` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(100) NOT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `full_name`  VARCHAR(255) NULL,
  `email`      VARCHAR(150) NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admins_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- สอจ. — ระบบเดิมเก็บ college_password เป็น plaintext และเท่ากับ college_code
-- migration 0003 เพิ่ม password_hash โดยไม่แตะคอลัมน์เดิม
CREATE TABLE IF NOT EXISTS `provincial_vocational_offices` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `college_code`     VARCHAR(20)  NOT NULL,
  `college_name`     VARCHAR(255) NOT NULL,
  `college_password` VARCHAR(255) NULL,
  `province_id`      INT UNSIGNED NULL,
  `phone`            VARCHAR(50)  NULL,
  `email`            VARCHAR(150) NULL,
  `is_active`        TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pveo_code` (`college_code`),
  KEY `idx_pveo_province` (`province_id`),
  CONSTRAINT `fk_pveo_province` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- นิคมอุตสาหกรรม (75 แห่ง) — province_id อาจเป็น NULL ("ไม่ระบุจังหวัด")
CREATE TABLE IF NOT EXISTS `industrial_estates` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `estate_name`      VARCHAR(255) NOT NULL,
  `province_id`      INT UNSIGNED NULL,
  `enterprise_total` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`        TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_estates_province` (`province_id`),
  CONSTRAINT `fk_estates_province` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `industrial_estate_details` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `estate_id`          INT UNSIGNED NOT NULL,
  `area_total`         DECIMAL(12,2) NULL COMMENT 'พื้นที่รวม (ไร่)',
  `area_sold`          DECIMAL(12,2) NULL COMMENT 'พื้นที่ขายแล้ว (ไร่)',
  `area_remaining`     DECIMAL(12,2) NULL COMMENT 'พื้นที่คงเหลือ (ไร่)',
  `factory_engineering` INT UNSIGNED NOT NULL DEFAULT 0,
  `factory_digital`     INT UNSIGNED NOT NULL DEFAULT 0,
  `factory_service`     INT UNSIGNED NOT NULL DEFAULT 0,
  `factory_biological`  INT UNSIGNED NOT NULL DEFAULT 0,
  `service_oss`        TINYINT(1) NOT NULL DEFAULT 0,
  `service_eec`        TINYINT(1) NOT NULL DEFAULT 0,
  `service_boi`        TINYINT(1) NOT NULL DEFAULT 0,
  `service_skill_dev`  TINYINT(1) NOT NULL DEFAULT 0,
  `coordinator_name`   VARCHAR(255) NULL,
  `coordinator_phone`  VARCHAR(50)  NULL,
  `coordinator_email`  VARCHAR(150) NULL,
  `updated_at`         DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_estate_details_estate` (`estate_id`),
  CONSTRAINT `fk_estate_details_estate` FOREIGN KEY (`estate_id`) REFERENCES `industrial_estates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- มอบหมายนิคมฯ ให้ สอจ. — หนึ่งนิคมฯ อาจมีหลาย สอจ.
CREATE TABLE IF NOT EXISTS `industrial_estate_responsibility` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `estate_id`  INT UNSIGNED NOT NULL,
  `pveo_id`    INT UNSIGNED NOT NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_responsibility` (`estate_id`, `pveo_id`),
  KEY `idx_responsibility_pveo` (`pveo_id`),
  CONSTRAINT `fk_resp_estate` FOREIGN KEY (`estate_id`) REFERENCES `industrial_estates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_resp_pveo` FOREIGN KEY (`pveo_id`) REFERENCES `provincial_vocational_offices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- โควตาเป้าหมายรายปี (unique: pveo + estate + year)
CREATE TABLE IF NOT EXISTS `pveo_estate_assignments` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pveo_id`        INT UNSIGNED NOT NULL,
  `estate_id`      INT UNSIGNED NOT NULL,
  `survey_year`    VARCHAR(4) NOT NULL COMMENT 'พ.ศ.',
  `target_count`   INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'โควตาที่ตั้งไว้',
  `surveyed_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'คำนวณโดย SyncPveoEstateAssignments',
  `is_manual`      TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = ตั้งโควตาเอง ห้ามเขียนทับ',
  `updated_at`     DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_assignment` (`pveo_id`, `estate_id`, `survey_year`),
  KEY `idx_assignment_year` (`survey_year`),
  CONSTRAINT `fk_assign_pveo` FOREIGN KEY (`pveo_id`) REFERENCES `provincial_vocational_offices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assign_estate` FOREIGN KEY (`estate_id`) REFERENCES `industrial_estates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `enterprises` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `enterprise_name`  VARCHAR(255) NOT NULL,
  `business_type`    VARCHAR(255) NULL,
  `estate_id`        INT UNSIGNED NULL,
  `province_id`      INT UNSIGNED NULL,
  `district_id`      INT UNSIGNED NULL,
  `sub_district_id`  INT UNSIGNED NULL,
  `address`          VARCHAR(255) NULL,
  `zip_code`         VARCHAR(10)  NULL,
  `phone`            VARCHAR(50)  NULL,
  `email`            VARCHAR(150) NULL,
  `website`          VARCHAR(255) NULL,
  `contact_name`     VARCHAR(255) NULL,
  `contact_position` VARCHAR(255) NULL,
  `contact_phone`    VARCHAR(50)  NULL,
  `created_by_pveo`  INT UNSIGNED NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_enterprises_estate` (`estate_id`),
  KEY `idx_enterprises_province` (`province_id`),
  KEY `idx_enterprises_name` (`enterprise_name`(100)),
  CONSTRAINT `fk_enterprises_estate` FOREIGN KEY (`estate_id`) REFERENCES `industrial_estates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_enterprises_province` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- คะแนนความสมบูรณ์ของข้อมูล 0–100 (1:1 กับ enterprises)
CREATE TABLE IF NOT EXISTS `enterprise_completeness` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `enterprise_id`    INT UNSIGNED NOT NULL,
  `score`            TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `missing_sections` VARCHAR(500) NULL,
  `calculated_at`    DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_completeness_enterprise` (`enterprise_id`),
  CONSTRAINT `fk_completeness_enterprise` FOREIGN KEY (`enterprise_id`) REFERENCES `enterprises` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- แบบสำรวจ PPP-002 — 1 ชุด ต่อ สถานประกอบการ/ปี/รอบ
CREATE TABLE IF NOT EXISTS `surveys` (
  `id`                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `enterprise_id`           INT UNSIGNED NOT NULL,
  `pveo_id`                 INT UNSIGNED NULL,
  `survey_year`             VARCHAR(4) NOT NULL COMMENT 'พ.ศ.',
  `survey_round`            ENUM('1','2','3','Yearly') NOT NULL DEFAULT 'Yearly',
  `survey_date`             DATE NULL,
  `no_student_required`     TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'ไม่ประสงค์รับนักเรียน/นักศึกษา',
  `welfare_accommodation`   TINYINT(1) NOT NULL DEFAULT 0,
  `welfare_meal`            TINYINT(1) NOT NULL DEFAULT 0,
  `welfare_transport`       TINYINT(1) NOT NULL DEFAULT 0,
  `welfare_allowance`       TINYINT(1) NOT NULL DEFAULT 0,
  `welfare_insurance`       TINYINT(1) NOT NULL DEFAULT 0,
  `welfare_other`           VARCHAR(255) NULL,
  `teacher_training_status` VARCHAR(255) NULL,
  `suggestion_text`         TEXT NULL,
  `problem_obstacle`        TEXT NULL,
  `certifier_name`          VARCHAR(255) NULL,
  `certifier_position`      VARCHAR(255) NULL,
  `certifier_date`          DATE NULL,
  `status`                  ENUM('draft','submitted') NOT NULL DEFAULT 'draft',
  `current_step`            TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`              DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_survey_ent_year_round` (`enterprise_id`, `survey_year`, `survey_round`),
  KEY `idx_surveys_year` (`survey_year`),
  KEY `idx_surveys_pveo` (`pveo_id`),
  CONSTRAINT `fk_surveys_enterprise` FOREIGN KEY (`enterprise_id`) REFERENCES `enterprises` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ความต้องการกำลังคน — แถวละสาขา
CREATE TABLE IF NOT EXISTS `survey_demands` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `survey_id`       INT UNSIGNED NOT NULL,
  `system_type`     ENUM('internship','dve') NOT NULL,
  `course_code`     VARCHAR(20) NULL,
  `course_name`     VARCHAR(255) NULL,
  `vc_male`         SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'ปวช. ชาย',
  `vc_female`       SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'ปวช. หญิง',
  `hvc_male`        SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'ปวส. ชาย',
  `hvc_female`      SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'ปวส. หญิง',
  `disability_flag` TINYINT(1) NOT NULL DEFAULT 0,
  `job_description` TEXT NULL,
  `required_skills` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_demands_survey` (`survey_id`),
  KEY `idx_demands_course` (`course_code`),
  CONSTRAINT `fk_demands_survey` FOREIGN KEY (`survey_id`) REFERENCES `surveys` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `survey_demand_disabilities` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `demand_id`       INT UNSIGNED NOT NULL,
  `disability_type` VARCHAR(150) NOT NULL,
  `quantity`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_disabilities_demand` (`demand_id`),
  CONSTRAINT `fk_disabilities_demand` FOREIGN KEY (`demand_id`) REFERENCES `survey_demands` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `survey_meeting_notes` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `survey_id`  INT UNSIGNED NOT NULL,
  `topic`      VARCHAR(255) NULL,
  `conclusion` TEXT NULL,
  `note_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_notes_survey` (`survey_id`),
  CONSTRAINT `fk_notes_survey` FOREIGN KEY (`survey_id`) REFERENCES `surveys` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `survey_past_trainings` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `survey_id`      INT UNSIGNED NOT NULL,
  `academic_year`  VARCHAR(4) NULL COMMENT 'พ.ศ.',
  `college_name`   VARCHAR(255) NULL,
  `course_name`    VARCHAR(255) NULL,
  `student_count`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `system_type`    ENUM('internship','dve') NULL,
  PRIMARY KEY (`id`),
  KEY `idx_trainings_survey` (`survey_id`),
  CONSTRAINT `fk_trainings_survey` FOREIGN KEY (`survey_id`) REFERENCES `surveys` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ขั้นตอนเอกสาร — จำนวนขั้นตอนกำหนดค่าได้ (app_settings.report_step_count)
CREATE TABLE IF NOT EXISTS `report_progress` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pveo_id`      INT UNSIGNED NOT NULL,
  `estate_id`    INT UNSIGNED NULL,
  `survey_year`  VARCHAR(4) NOT NULL,
  `step_no`      TINYINT UNSIGNED NOT NULL,
  `step_name`    VARCHAR(255) NULL,
  `status`       ENUM('pending','partial','complete','locked') NOT NULL DEFAULT 'pending',
  `submitted_at` DATETIME NULL,
  `due_date`     DATE NULL,
  `note`         VARCHAR(500) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_progress` (`pveo_id`, `estate_id`, `survey_year`, `step_no`),
  KEY `idx_progress_year` (`survey_year`),
  CONSTRAINT `fk_progress_pveo` FOREIGN KEY (`pveo_id`) REFERENCES `provincial_vocational_offices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `report_files` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `progress_id`    INT UNSIGNED NOT NULL,
  `original_name`  VARCHAR(255) NOT NULL,
  `stored_name`    VARCHAR(255) NOT NULL,
  `mime_type`      VARCHAR(100) NULL,
  `file_size`      INT UNSIGNED NOT NULL DEFAULT 0,
  `uploaded_by`    INT UNSIGNED NULL,
  `uploaded_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_files_progress` (`progress_id`),
  CONSTRAINT `fk_files_progress` FOREIGN KEY (`progress_id`) REFERENCES `report_progress` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `report_activity_log` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `progress_id` INT UNSIGNED NULL,
  `actor_role`  VARCHAR(20)  NULL,
  `actor_id`    INT UNSIGNED NULL,
  `action`      VARCHAR(100) NOT NULL,
  `detail`      VARCHAR(500) NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_log_progress` (`progress_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- กระดานสนทนา — ระบบเดิมเก็บ created_at เป็น varchar และรูปเป็น base64
CREATE TABLE IF NOT EXISTS `topics` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`      VARCHAR(255) NOT NULL,
  `body`       MEDIUMTEXT NULL,
  `author`     VARCHAR(255) NULL,
  `image`      MEDIUMTEXT NULL COMMENT 'base64 (โครงสร้างเดิม)',
  `created_at` VARCHAR(30) NULL COMMENT 'varchar ตามโครงสร้างเดิม',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `replies` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `topic_id`   INT UNSIGNED NOT NULL,
  `body`       MEDIUMTEXT NULL,
  `author`     VARCHAR(255) NULL,
  `image`      MEDIUMTEXT NULL COMMENT 'base64 (โครงสร้างเดิม)',
  `created_at` VARCHAR(30) NULL COMMENT 'varchar ตามโครงสร้างเดิม',
  PRIMARY KEY (`id`),
  KEY `idx_replies_topic` (`topic_id`),
  CONSTRAINT `fk_replies_topic` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- @DOWN
DROP TABLE IF EXISTS `replies`;
DROP TABLE IF EXISTS `topics`;
DROP TABLE IF EXISTS `report_activity_log`;
DROP TABLE IF EXISTS `report_files`;
DROP TABLE IF EXISTS `report_progress`;
DROP TABLE IF EXISTS `survey_past_trainings`;
DROP TABLE IF EXISTS `survey_meeting_notes`;
DROP TABLE IF EXISTS `survey_demand_disabilities`;
DROP TABLE IF EXISTS `survey_demands`;
DROP TABLE IF EXISTS `surveys`;
DROP TABLE IF EXISTS `enterprise_completeness`;
DROP TABLE IF EXISTS `enterprises`;
DROP TABLE IF EXISTS `pveo_estate_assignments`;
DROP TABLE IF EXISTS `industrial_estate_responsibility`;
DROP TABLE IF EXISTS `industrial_estate_details`;
DROP TABLE IF EXISTS `industrial_estates`;
DROP TABLE IF EXISTS `provincial_vocational_offices`;
DROP TABLE IF EXISTS `admins`;
