-- 0004 — view / stored procedure / trigger
--
-- หมายเหตุ: schema.sql ที่ export จาก production มี trigger 3 ตัวที่ body ถูกตัด
-- ไม่สมบูรณ์ ไฟล์นี้จึงเขียน body ขึ้นใหม่ตามพฤติกรรมที่ระบบต้องการจริง
-- (ปรับปรุงยอดที่สำรวจแล้วให้ตรงกับข้อมูลใน surveys เสมอ)

DROP VIEW IF EXISTS `v_estate_progress`;
CREATE VIEW `v_estate_progress` AS
SELECT
    e.id                                   AS estate_id,
    e.estate_name                          AS estate_name,
    e.province_id                          AS province_id,
    COALESCE(p.province_name, 'ไม่ระบุจังหวัด') AS province_name,
    e.enterprise_total                     AS enterprise_total,
    (SELECT COUNT(*) FROM enterprises en WHERE en.estate_id = e.id) AS enterprise_recorded,
    (SELECT COUNT(DISTINCT s.enterprise_id)
       FROM surveys s
       JOIN enterprises en2 ON en2.id = s.enterprise_id
      WHERE en2.estate_id = e.id)          AS surveyed_count,
    (SELECT COUNT(DISTINCT s.enterprise_id)
       FROM surveys s
       JOIN enterprises en3 ON en3.id = s.enterprise_id
      WHERE en3.estate_id = e.id AND s.no_student_required = 1) AS no_student_count
FROM industrial_estates e
LEFT JOIN provinces p ON p.id = e.province_id
WHERE e.is_active = 1;

DROP VIEW IF EXISTS `v_pveo_progress`;
CREATE VIEW `v_pveo_progress` AS
SELECT
    o.id                                   AS pveo_id,
    o.college_code                         AS college_code,
    o.college_name                         AS college_name,
    COALESCE(p.province_name, 'ไม่ระบุจังหวัด') AS province_name,
    a.survey_year                          AS survey_year,
    SUM(a.target_count)                    AS target_total,
    SUM(a.surveyed_count)                  AS surveyed_total,
    COUNT(DISTINCT a.estate_id)            AS estate_count
FROM provincial_vocational_offices o
LEFT JOIN provinces p ON p.id = o.province_id
LEFT JOIN pveo_estate_assignments a ON a.pveo_id = o.id
WHERE o.is_active = 1
GROUP BY o.id, o.college_code, o.college_name, p.province_name, a.survey_year;

DELIMITER $$

-- คำนวณยอดสำรวจจริงของปีที่ระบุ โดยไม่เขียนทับโควตาที่ตั้งเอง (is_manual = 1)
DROP PROCEDURE IF EXISTS `SyncPveoEstateAssignments`$$
CREATE PROCEDURE `SyncPveoEstateAssignments`(IN p_year VARCHAR(4))
BEGIN
    -- 1) สร้างแถวมอบหมายให้ครบตามความรับผิดชอบที่ยังใช้งานอยู่
    INSERT IGNORE INTO pveo_estate_assignments (pveo_id, estate_id, survey_year, target_count, surveyed_count, is_manual, updated_at)
    SELECT r.pveo_id, r.estate_id, p_year, 0, 0, 0, NOW()
      FROM industrial_estate_responsibility r
     WHERE r.is_active = 1;

    -- 2) ปรับยอดที่สำรวจแล้วของปีนั้น (นับสถานประกอบการที่ไม่ซ้ำ)
    UPDATE pveo_estate_assignments a
       SET a.surveyed_count = (
             SELECT COUNT(DISTINCT s.enterprise_id)
               FROM surveys s
               JOIN enterprises en ON en.id = s.enterprise_id
              WHERE en.estate_id = a.estate_id
                AND s.survey_year = a.survey_year
                AND s.pveo_id = a.pveo_id
           ),
           a.updated_at = NOW()
     WHERE a.survey_year = p_year;

    -- 3) เติมโควตาเริ่มต้นจากจำนวนสถานประกอบการในนิคมฯ เฉพาะแถวที่ยังไม่ตั้งเอง
    --    หนึ่งนิคมฯ อาจมีหลาย สอจ. จึงหารเป้าหมายตามจำนวน สอจ. ที่รับผิดชอบ
    UPDATE pveo_estate_assignments a
      JOIN industrial_estates e ON e.id = a.estate_id
       SET a.target_count = GREATEST(
             1,
             CEIL(e.enterprise_total / GREATEST(1, (
                 SELECT COUNT(*) FROM industrial_estate_responsibility r
                  WHERE r.estate_id = a.estate_id AND r.is_active = 1
             )))
           ),
           a.updated_at = NOW()
     WHERE a.survey_year = p_year
       AND a.is_manual = 0
       AND a.target_count = 0;
END$$

-- คำนวณคะแนนความสมบูรณ์ 0–100 ของสถานประกอบการหนึ่งแห่ง
DROP PROCEDURE IF EXISTS `RecalcEnterpriseCompleteness`$$
CREATE PROCEDURE `RecalcEnterpriseCompleteness`(IN p_enterprise_id INT UNSIGNED)
BEGIN
    DECLARE v_score      TINYINT UNSIGNED DEFAULT 0;
    DECLARE v_missing    VARCHAR(500) DEFAULT '';
    DECLARE v_survey_id  INT UNSIGNED DEFAULT NULL;

    SELECT s.id INTO v_survey_id
      FROM surveys s
     WHERE s.enterprise_id = p_enterprise_id
     ORDER BY s.survey_year DESC, s.id DESC
     LIMIT 1;

    -- ข้อมูลติดต่อพื้นฐาน (30)
    IF EXISTS (SELECT 1 FROM enterprises e
                WHERE e.id = p_enterprise_id
                  AND e.enterprise_name <> '' AND e.province_id IS NOT NULL
                  AND COALESCE(e.contact_name, '') <> '') THEN
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

    INSERT INTO enterprise_completeness (enterprise_id, score, missing_sections, calculated_at)
    VALUES (p_enterprise_id, v_score, TRIM(TRAILING ',' FROM v_missing), NOW())
    ON DUPLICATE KEY UPDATE
        score = VALUES(score),
        missing_sections = VALUES(missing_sections),
        calculated_at = NOW();
END$$

-- trigger 1/3 — บันทึกแบบสำรวจใหม่: อัปเดตยอดสำรวจและคะแนนความสมบูรณ์
DROP TRIGGER IF EXISTS `after_survey_insert`$$
CREATE TRIGGER `after_survey_insert` AFTER INSERT ON `surveys`
FOR EACH ROW
BEGIN
    UPDATE pveo_estate_assignments a
      JOIN enterprises en ON en.id = NEW.enterprise_id
       SET a.surveyed_count = a.surveyed_count + 1,
           a.updated_at = NOW()
     WHERE a.pveo_id = NEW.pveo_id
       AND a.estate_id = en.estate_id
       AND a.survey_year = NEW.survey_year;

    CALL RecalcEnterpriseCompleteness(NEW.enterprise_id);
END$$

-- trigger 2/3 — ลบแบบสำรวจ: ลดยอดสำรวจโดยไม่ให้ติดลบ
DROP TRIGGER IF EXISTS `after_survey_delete`$$
CREATE TRIGGER `after_survey_delete` AFTER DELETE ON `surveys`
FOR EACH ROW
BEGIN
    UPDATE pveo_estate_assignments a
      JOIN enterprises en ON en.id = OLD.enterprise_id
       SET a.surveyed_count = GREATEST(0, a.surveyed_count - 1),
           a.updated_at = NOW()
     WHERE a.pveo_id = OLD.pveo_id
       AND a.estate_id = en.estate_id
       AND a.survey_year = OLD.survey_year;
END$$

-- trigger 3/3 — คะแนนความสมบูรณ์เปลี่ยน: ประทับเวลาที่สถานประกอบการ
DROP TRIGGER IF EXISTS `after_enterprise_completeness_update`$$
CREATE TRIGGER `after_enterprise_completeness_update` AFTER UPDATE ON `enterprise_completeness`
FOR EACH ROW
BEGIN
    IF NEW.score <> OLD.score THEN
        UPDATE enterprises e SET e.updated_at = NOW() WHERE e.id = NEW.enterprise_id;
    END IF;
END$$

DELIMITER ;

-- @DOWN
DROP TRIGGER IF EXISTS `after_enterprise_completeness_update`;
DROP TRIGGER IF EXISTS `after_survey_delete`;
DROP TRIGGER IF EXISTS `after_survey_insert`;
DROP PROCEDURE IF EXISTS `RecalcEnterpriseCompleteness`;
DROP PROCEDURE IF EXISTS `SyncPveoEstateAssignments`;
DROP VIEW IF EXISTS `v_pveo_progress`;
DROP VIEW IF EXISTS `v_estate_progress`;
