-- NIELIT O Level 'IT' (NOL'D-2026) — approve WhatsApp list, de-approve the rest
--
-- Official O Level eligibility (for your review, not auto-enforced here):
--   12th  OR  ITI 2yr after 10th  OR  ITI 1yr after 10th + 1yr experience
--   OR 2nd year of Govt polytechnic diploma (O Level during 3rd year)
--   OR 10th + previous NSQF level in relevant field
--   OR previous NSQF level + 2 years experience
--
-- This script matches students by NAME inside the O Level 'IT' course / NOL'D-2026
-- batch (about 55 on file). The WhatsApp sheet listed 50 names — the extra
-- students in that course/batch are returned to pending.
--
-- HOW TO USE
-- 1. Backup first.
-- 2. Run STEP 0–3 (preview only). Check unmatched names (spelling) and extras.
-- 3. If the lists look right, run STEP 4 (updates).
--
-- phpMyAdmin: select the live database, paste one step at a time.

-- =============================================================================
-- STEP 0 — find the course and batch (do not skip)
-- =============================================================================
SELECT id, course_code, course_name, eligibility
FROM courses
WHERE course_code LIKE 'OLIT%'
   OR course_name LIKE '%O Level%IT%'
   OR course_name LIKE '%O Level ''IT''%'
   OR course_name LIKE '%NIELIT O Level%';

SELECT b.id, b.batch_code, b.batch_name, b.course_id, c.course_code, c.course_name
FROM batches b
LEFT JOIN courses c ON c.id = b.course_id
WHERE REPLACE(REPLACE(IFNULL(b.batch_code,''), '''', ''), '’', '') LIKE '%NOLD-2026%'
   OR REPLACE(REPLACE(IFNULL(b.batch_name,''), '''', ''), '’', '') LIKE '%NOLD-2026%'
   OR IFNULL(b.batch_code,'') LIKE '%NOL%2026%'
   OR IFNULL(b.batch_name,'') LIKE '%NOL%2026%';

-- =============================================================================
-- STEP 1 — names from the WhatsApp list (50 names)
-- =============================================================================
DROP TEMPORARY TABLE IF EXISTS tmp_olevel_approve_names;
CREATE TEMPORARY TABLE tmp_olevel_approve_names (
    list_name VARCHAR(255) NOT NULL,
    norm_name VARCHAR(255) NOT NULL,
    PRIMARY KEY (norm_name)
);

INSERT INTO tmp_olevel_approve_names (list_name, norm_name) VALUES
('AJIT BIVAR', 'AJIT BIVAR'),
('AMIT KANHAR', 'AMIT KANHAR'),
('ANIMA DIGAL', 'ANIMA DIGAL'),
('ARJUNA HUIKA', 'ARJUNA HUIKA'),
('BALARAM HANSDAH', 'BALARAM HANSDAH'),
('BARSHA NAYAK', 'BARSHA NAYAK'),
('BHIMA CHARAN SOY', 'BHIMA CHARAN SOY'),
('BIKASH SETHI', 'BIKASH SETHI'),
('BIRANG BULIULI', 'BIRANG BULIULI'),
('BUDHIRAM BEHERA', 'BUDHIRAM BEHERA'),
('DEEPAK KUMAR SIRKA', 'DEEPAK KUMAR SIRKA'),
('DEEPTIMAYEE BEHERA', 'DEEPTIMAYEE BEHERA'),
('DHANABANTA MAJHI', 'DHANABANTA MAJHI'),
('DINESH KUMAR NAIK', 'DINESH KUMAR NAIK'),
('DULARAM BASKEY', 'DULARAM BASKEY'),
('DULARI SOREN', 'DULARI SOREN'),
('ITISHREE GAGARAI', 'ITISHREE GAGARAI'),
('JAGAT HEMBRAM', 'JAGAT HEMBRAM'),
('JAYANTI RAITA', 'JAYANTI RAITA'),
('KUNI JARIKA', 'KUNI JARIKA'),
('LIJA NAYAK', 'LIJA NAYAK'),
('MAMITA PURTY', 'MAMITA PURTY'),
('MONALISA TUDU', 'MONALISA TUDU'),
('PANKAJ KUMAR NAIK', 'PANKAJ KUMAR NAIK'),
('PRIYANKA BIRUA', 'PRIYANKA BIRUA'),
('PUJA DIGAL', 'PUJA DIGAL'),
('PUSPALATA HEMBRAM', 'PUSPALATA HEMBRAM'),
('RANIMA PRADHAN', 'RANIMA PRADHAN'),
('RATNARAJ PRADHAN', 'RATNARAJ PRADHAN'),
('RITA MURMU', 'RITA MURMU'),
('RUJANTA BARGE', 'RUJANTA BARGE'),
('RUPALI MURMU', 'RUPALI MURMU'),
('SABITRI KISKU', 'SABITRI KISKU'),
('SACHIN KUMAR SETHY', 'SACHIN KUMAR SETHY'),
('SAHIL TUDU', 'SAHIL TUDU'),
('SAMUKA MALLICK', 'SAMUKA MALLICK'),
('SANDHYA RANI NAIK', 'SANDHYA RANI NAIK'),
('SANIMA PRADHAN', 'SANIMA PRADHAN'),
('SANJEEB KUMAR MURMU', 'SANJEEB KUMAR MURMU'),
('SARNA DUMNIMAI SOREN', 'SARNA DUMNIMAI SOREN'),
('SATYABAN BHOSAGAR', 'SATYABAN BHOSAGAR'),
('SHAKTI BEHERA', 'SHAKTI BEHERA'),
('SITA HASDAH', 'SITA HASDAH'),
('SOUMYA RANJAN SETHI', 'SOUMYA RANJAN SETHI'),
('SRUSTISUDESHNA MAJHI', 'SRUSTISUDESHNA MAJHI'),
('SUBHALAXMI KANHAR', 'SUBHALAXMI KANHAR'),
('SUNITA HANSDAH', 'SUNITA HANSDAH'),
('TAPASWINI MALLICK', 'TAPASWINI MALLICK'),
('URMILA RAITA', 'URMILA RAITA'),
('UTTAM KUMAR MUKHI', 'UTTAM KUMAR MUKHI');

-- =============================================================================
-- STEP 2 — students in O Level 'IT' / NOL'D-2026
-- If STEP 0 showed a different course_id / batch_id, replace the JOIN filters.
-- =============================================================================
DROP TEMPORARY TABLE IF EXISTS tmp_olevel_pool;
CREATE TEMPORARY TABLE tmp_olevel_pool AS
SELECT DISTINCT
    s.id,
    s.student_id,
    s.name,
    UPPER(TRIM(s.name)) AS norm_name,
    s.status,
    s.course_id,
    s.batch_id,
    c.course_code,
    c.course_name,
    b.batch_code,
    b.batch_name
FROM students s
LEFT JOIN courses c ON c.id = s.course_id
LEFT JOIN batches b ON b.id = s.batch_id
LEFT JOIN batch_students bs ON bs.student_id = s.student_id
LEFT JOIN batches b2 ON b2.id = bs.batch_id
LEFT JOIN student_enrollments se ON se.student_record_id = s.id
WHERE LOWER(IFNULL(s.status,'')) NOT IN ('rejected', 'inactive')
  AND (
        IFNULL(c.course_code,'') LIKE 'OLIT%'
     OR IFNULL(c.course_name,'') LIKE '%O Level%IT%'
     OR IFNULL(c.course_name,'') LIKE '%O Level ''IT''%'
     OR REPLACE(REPLACE(IFNULL(b.batch_code,''), '''', ''), '’', '') LIKE '%NOLD-2026%'
     OR REPLACE(REPLACE(IFNULL(b.batch_name,''), '''', ''), '’', '') LIKE '%NOLD-2026%'
     OR REPLACE(REPLACE(IFNULL(b2.batch_code,''), '''', ''), '’', '') LIKE '%NOLD-2026%'
     OR REPLACE(REPLACE(IFNULL(b2.batch_name,''), '''', ''), '’', '') LIKE '%NOLD-2026%'
     OR se.course_id IN (
            SELECT id FROM courses
            WHERE course_code LIKE 'OLIT%'
               OR course_name LIKE '%O Level%IT%'
               OR course_name LIKE '%O Level ''IT''%'
        )
  );

SELECT 'POOL_COUNT' AS label, COUNT(*) AS n FROM tmp_olevel_pool;

-- Who is on the WhatsApp list (will be approved)
SELECT p.student_id, p.name, p.status, p.course_code, p.batch_code, n.list_name
FROM tmp_olevel_pool p
INNER JOIN tmp_olevel_approve_names n ON n.norm_name = p.norm_name
ORDER BY p.name;

-- Extra students in the course/batch who are NOT on the list (will go to pending)
SELECT p.student_id, p.name, p.status, p.course_code, p.batch_code
FROM tmp_olevel_pool p
LEFT JOIN tmp_olevel_approve_names n ON n.norm_name = p.norm_name
WHERE n.norm_name IS NULL
ORDER BY p.name;

-- WhatsApp names that did not match any student (spelling / missing)
SELECT n.list_name
FROM tmp_olevel_approve_names n
LEFT JOIN tmp_olevel_pool p ON p.norm_name = n.norm_name
WHERE p.id IS NULL
ORDER BY n.list_name;

-- Optional: education vs O Level eligibility (review only)
SELECT
    p.student_id,
    p.name,
    p.status,
    GROUP_CONCAT(DISTINCT ed.exam_passed ORDER BY ed.id SEPARATOR ', ') AS exams,
    CASE
        WHEN SUM(CASE WHEN LOWER(IFNULL(ed.exam_passed,'')) IN ('intermediate','iti','diploma','graduation','post graduation','phd') THEN 1 ELSE 0 END) > 0
            THEN 'likely_ok_12th_iti_diploma_or_higher'
        WHEN SUM(CASE WHEN LOWER(IFNULL(ed.exam_passed,'')) = 'matriculation' THEN 1 ELSE 0 END) > 0
            THEN 'only_10th_review_nsqf_or_experience'
        ELSE 'no_education_row_review_manually'
    END AS eligibility_hint
FROM tmp_olevel_pool p
LEFT JOIN education_details ed ON ed.student_id = p.student_id
GROUP BY p.id, p.student_id, p.name, p.status
ORDER BY p.name;

-- =============================================================================
-- STEP 4 — APPLY (UNCOMMENT this block only after STEP 2 lists look correct)
-- Re-run STEP 1–2 in the same phpMyAdmin session first (temp tables are session-only).
-- =============================================================================
/*
START TRANSACTION;

UPDATE students s
INNER JOIN tmp_olevel_pool p ON p.id = s.id
INNER JOIN tmp_olevel_approve_names n ON n.norm_name = p.norm_name
SET s.status = 'active'
WHERE LOWER(IFNULL(s.status,'')) NOT IN ('rejected', 'inactive');

UPDATE student_enrollments se
INNER JOIN students s ON s.id = se.student_record_id
INNER JOIN tmp_olevel_pool p ON p.id = s.id
INNER JOIN tmp_olevel_approve_names n ON n.norm_name = p.norm_name
SET se.status = 'active',
    se.approved_at = NOW(),
    se.approved_by = 'sql:olevel-nold-2026'
WHERE LOWER(IFNULL(se.status,'')) NOT IN ('rejected', 'cancelled', 'inactive');

UPDATE students s
INNER JOIN tmp_olevel_pool p ON p.id = s.id
LEFT JOIN tmp_olevel_approve_names n ON n.norm_name = p.norm_name
SET s.status = 'pending'
WHERE n.norm_name IS NULL
  AND LOWER(IFNULL(s.status,'')) IN ('active', 'approved');

UPDATE student_enrollments se
INNER JOIN students s ON s.id = se.student_record_id
INNER JOIN tmp_olevel_pool p ON p.id = s.id
LEFT JOIN tmp_olevel_approve_names n ON n.norm_name = p.norm_name
SET se.status = 'pending'
WHERE n.norm_name IS NULL
  AND LOWER(IFNULL(se.status,'')) IN ('active', 'approved');

SELECT 'approved_now' AS action, COUNT(*) AS n
FROM students s
INNER JOIN tmp_olevel_pool p ON p.id = s.id
INNER JOIN tmp_olevel_approve_names n ON n.norm_name = p.norm_name
WHERE LOWER(s.status) IN ('active', 'approved');

SELECT 'pending_extras' AS action, COUNT(*) AS n
FROM students s
INNER JOIN tmp_olevel_pool p ON p.id = s.id
LEFT JOIN tmp_olevel_approve_names n ON n.norm_name = p.norm_name
WHERE n.norm_name IS NULL
  AND LOWER(s.status) = 'pending';

COMMIT;
*/

