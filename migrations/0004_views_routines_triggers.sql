-- 0004 — view / stored procedure ของแอปใหม่
--
-- ออกแบบให้ติดตั้งลง "ฐานข้อมูลเดียวกับระบบเดิม" ได้อย่างปลอดภัย
-- กติกาเดียวของไฟล์นี้: ห้ามแตะ object ที่ระบบเดิมเป็นเจ้าของ
--
--   1) ทุกอย่างที่สร้างที่นี่มีคำนำหน้า v_ppp_ / Ppp เสมอ จึงไม่ชนชื่อกับของเดิม
--   2) ไม่มี DROP ... IF EXISTS ของชื่อเดิม (SyncPveoEstateAssignments,
--      SyncReportCompletenessPveo, report_completeness_by_pveo) เด็ดขาด
--   3) ไม่สร้าง trigger ใด ๆ — ดูเหตุผลด้านล่าง
--
-- ระบบเดิมมี trigger after_survey_insert / after_survey_delete /
-- after_enterprise_completeness_update อยู่แล้ว การสร้างทับจะทำลายของเดิม
-- ส่วนการสร้างเพิ่มด้วยชื่อใหม่ก็ไม่ได้ เพราะ MariaDB 10.2.3+ ยอมให้มีหลาย
-- trigger ต่อหนึ่งเหตุการณ์ ของเราจะยิงซ้อนกับของเดิม แล้ว surveyed_count
-- จะถูกบวกสองรอบ ที่นี่จึงใช้วิธี "คำนวณใหม่" แทน "บวกเพิ่ม" ซึ่งให้ผล
-- เท่าเดิมเสมอไม่ว่าระบบเดิมจะมี trigger หรือไม่
--
-- ชื่อคอลัมน์ทั้งหมดเป็นชื่อจริงของ production แล้ว alias กลับเป็นคำที่โค้ด PHP
-- ใช้อยู่ (estate_id, estate_name, province_name) เพื่อไม่ต้องแก้ทุก view

DROP VIEW IF EXISTS `v_ppp_estate_progress`;
CREATE VIEW `v_ppp_estate_progress` AS
SELECT
    e.industrial_estate_id                      AS estate_id,
    e.industrial_estate_name                    AS estate_name,
    e.province_id                               AS province_id,
    COALESCE(p.province_name_th, 'ไม่ระบุจังหวัด') AS province_name,
    COALESCE(d.total_enterprises, 0)            AS enterprise_total,
    (SELECT COUNT(*) FROM enterprises en
      WHERE en.industrial_estate_id = e.industrial_estate_id) AS enterprise_recorded,
    (SELECT COUNT(DISTINCT s.enterprise_id)
       FROM surveys s
       JOIN enterprises en2 ON en2.id = s.enterprise_id
      WHERE en2.industrial_estate_id = e.industrial_estate_id) AS surveyed_count,
    (SELECT COUNT(DISTINCT s.enterprise_id)
       FROM surveys s
       JOIN enterprises en3 ON en3.id = s.enterprise_id
      WHERE en3.industrial_estate_id = e.industrial_estate_id
        AND s.no_student_required = 1)          AS no_student_count
FROM industrial_estates e
LEFT JOIN provinces p ON p.province_id = e.province_id
LEFT JOIN industrial_estate_details d ON d.industrial_estate_id = e.industrial_estate_id;

DROP VIEW IF EXISTS `v_ppp_pveo_progress`;
CREATE VIEW `v_ppp_pveo_progress` AS
SELECT
    o.pveo_id                                   AS pveo_id,
    o.college_code                              AS college_code,
    COALESCE(c.college_name, CONCAT('สอจ. ', o.college_code)) AS college_name,
    COALESCE(p.province_name_th, 'ไม่ระบุจังหวัด') AS province_name,
    a.survey_year                               AS survey_year,
    SUM(a.target_count)                         AS target_total,
    SUM(a.surveyed_count)                       AS surveyed_total,
    COUNT(DISTINCT a.industrial_estate_id)      AS estate_count
FROM provincial_vocational_offices o
LEFT JOIN college c ON c.college_code = o.college_code
LEFT JOIN provinces p ON p.province_id = o.province_id
LEFT JOIN pveo_estate_assignments a ON a.pveo_id = o.pveo_id
GROUP BY o.pveo_id, o.college_code, c.college_name, p.province_name_th, a.survey_year;

DELIMITER $$

-- คำนวณยอดสำรวจจริงของปีที่ระบุ โดยไม่เขียนทับโควตาที่ตั้งเอง (is_manual = 1)
DROP PROCEDURE IF EXISTS `PppSyncPveoEstateAssignments`$$
CREATE PROCEDURE `PppSyncPveoEstateAssignments`(IN p_year INT)
BEGIN
    -- 1) สร้างแถวมอบหมายให้ครบตามความรับผิดชอบที่ยังใช้งานอยู่
    INSERT IGNORE INTO pveo_estate_assignments
        (pveo_id, industrial_estate_id, survey_year, target_count, surveyed_count, is_manual, updated_at)
    SELECT r.pveo_id, r.industrial_estate_id, p_year, 0, 0, 0, NOW()
      FROM industrial_estate_responsibility r
     WHERE r.is_active = 1;

    -- 2) ปรับยอดที่สำรวจแล้วของปีนั้น (นับสถานประกอบการที่ไม่ซ้ำ)
    UPDATE pveo_estate_assignments a
       SET a.surveyed_count = (
             SELECT COUNT(DISTINCT s.enterprise_id)
               FROM surveys s
               JOIN enterprises en ON en.id = s.enterprise_id
              WHERE en.industrial_estate_id = a.industrial_estate_id
                AND s.survey_year = a.survey_year
                AND s.pveo_id = a.pveo_id
           ),
           a.updated_at = NOW()
     WHERE a.survey_year = p_year;

    -- 3) เติมโควตาเริ่มต้นจากจำนวนสถานประกอบการในนิคมฯ เฉพาะแถวที่ยังไม่ตั้งเอง
    --    หนึ่งนิคมฯ อาจมีหลาย สอจ. จึงหารเป้าหมายตามจำนวน สอจ. ที่รับผิดชอบ
    --    เงื่อนไข target_count = 0 ทำให้โควตาที่ระบบเดิมตั้งไว้แล้วไม่ถูกแตะ
    UPDATE pveo_estate_assignments a
      JOIN industrial_estate_details d ON d.industrial_estate_id = a.industrial_estate_id
       SET a.target_count = GREATEST(
             1,
             CEIL(COALESCE(d.total_enterprises, 0) / GREATEST(1, (
                 SELECT COUNT(*) FROM industrial_estate_responsibility r
                  WHERE r.industrial_estate_id = a.industrial_estate_id AND r.is_active = 1
             )))
           ),
           a.updated_at = NOW()
     WHERE a.survey_year = p_year
       AND a.is_manual = 0
       AND a.target_count = 0;
END$$

-- คำนวณยอดสำรวจใหม่เฉพาะแถวที่เกี่ยวกับสถานประกอบการหนึ่งแห่ง
--
-- ใช้แทน trigger: เป็นการ "นับใหม่จาก surveys" ไม่ใช่ "บวกหนึ่ง" ผลลัพธ์จึง
-- ถูกต้องเสมอ ไม่ว่าระบบเดิมจะมี trigger คอยบวกให้อยู่แล้วหรือไม่
DROP PROCEDURE IF EXISTS `PppRecountSurveyed`$$
CREATE PROCEDURE `PppRecountSurveyed`(IN p_enterprise_id INT, IN p_year INT)
BEGIN
    UPDATE pveo_estate_assignments a
      JOIN enterprises en ON en.industrial_estate_id = a.industrial_estate_id
       SET a.surveyed_count = (
             SELECT COUNT(DISTINCT s.enterprise_id)
               FROM surveys s
               JOIN enterprises en2 ON en2.id = s.enterprise_id
              WHERE en2.industrial_estate_id = a.industrial_estate_id
                AND s.survey_year = a.survey_year
                AND s.pveo_id = a.pveo_id
           ),
           a.updated_at = NOW()
     WHERE en.id = p_enterprise_id
       AND a.survey_year = p_year;
END$$

-- คำนวณคะแนนความสมบูรณ์ 0–100 ของสถานประกอบการหนึ่งแห่ง
-- เขียนลง ppp_enterprise_completeness ของแอปใหม่ ไม่แตะตารางของระบบเดิม
DROP PROCEDURE IF EXISTS `PppRecalcEnterpriseCompleteness`$$
CREATE PROCEDURE `PppRecalcEnterpriseCompleteness`(IN p_enterprise_id INT)
BEGIN
    DECLARE v_score      TINYINT UNSIGNED DEFAULT 0;
    DECLARE v_missing    VARCHAR(500) DEFAULT '';
    DECLARE v_survey_id  INT DEFAULT NULL;

    SELECT s.id INTO v_survey_id
      FROM surveys s
     WHERE s.enterprise_id = p_enterprise_id
     ORDER BY s.survey_year DESC, s.id DESC
     LIMIT 1;

    -- ข้อมูลติดต่อพื้นฐาน (30)
    IF EXISTS (SELECT 1 FROM enterprises e
                WHERE e.id = p_enterprise_id
                  AND e.name <> '' AND e.province_id IS NOT NULL
                  AND COALESCE(e.contact_person, '') <> '') THEN
        SET v_score = v_score + 30;
    ELSE
        SET v_missing = CONCAT(v_missing, 'ข้อมูลติดต่อ,');
    END IF;

    -- มีแบบสำรวจ (30)
    IF v_survey_id IS NOT NULL THEN
        SET v_score = v_score + 30;
    ELSE
        SET v_missing = CONCAT(v_missing, 'แบบสำรวจ,');
    END IF;

    -- ความต้องการกำลังคน หรือแจ้งไม่ประสงค์รับ (25)
    IF v_survey_id IS NOT NULL AND (
         EXISTS (SELECT 1 FROM survey_demands d WHERE d.survey_id = v_survey_id)
      OR EXISTS (SELECT 1 FROM surveys s2 WHERE s2.id = v_survey_id AND s2.no_student_required = 1)
    ) THEN
        SET v_score = v_score + 25;
    ELSE
        SET v_missing = CONCAT(v_missing, 'ความต้องการกำลังคน,');
    END IF;

    -- ผู้รับรองแบบสำรวจ (15)
    IF v_survey_id IS NOT NULL AND EXISTS (
         SELECT 1 FROM surveys s3
          WHERE s3.id = v_survey_id AND COALESCE(s3.certifier_name, '') <> ''
    ) THEN
        SET v_score = v_score + 15;
    ELSE
        SET v_missing = CONCAT(v_missing, 'ผู้รับรอง,');
    END IF;

    INSERT INTO ppp_enterprise_completeness (enterprise_id, score, missing_sections, calculated_at)
    VALUES (p_enterprise_id, v_score, TRIM(TRAILING ',' FROM v_missing), NOW())
    ON DUPLICATE KEY UPDATE
        score = VALUES(score),
        missing_sections = VALUES(missing_sections),
        calculated_at = NOW();
END$$

DELIMITER ;

-- @DOWN
-- ย้อนกลับได้ เพราะทุก object ที่ลบเป็นของแอปใหม่ล้วน ๆ (คำนำหน้า v_ppp_ / Ppp)
-- view, procedure และ trigger ของระบบเดิมไม่เคยถูกไฟล์นี้แตะตั้งแต่แรก
DROP PROCEDURE IF EXISTS `PppRecalcEnterpriseCompleteness`;
DROP PROCEDURE IF EXISTS `PppRecountSurveyed`;
DROP PROCEDURE IF EXISTS `PppSyncPveoEstateAssignments`;
DROP VIEW IF EXISTS `v_ppp_pveo_progress`;
DROP VIEW IF EXISTS `v_ppp_estate_progress`;
