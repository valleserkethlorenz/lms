-- ============================================================================
-- LMS  -  Step 03: post-install verification
--
-- Read-only. Run after 00 -> 01 -> 02 and compare with the "expected" column.
-- The app itself only needs tbl_user (to log in) + tbl_utility (dropdowns).
--
--     mysql -u root lms < 03_verify.sql
-- ============================================================================

USE `lms`;

-- 1. Table count -------------------------------------------------------------
--    expected: 32
SELECT COUNT(*) AS table_count_expected_32
FROM information_schema.tables
WHERE table_schema = 'lms';

-- 2. Every table the application code references must exist -------------------
--    expected: 0 rows (any row listed here is a missing table)
SELECT t.name AS missing_table
FROM (
            SELECT 'tbl_announcement' AS name
  UNION ALL SELECT 'tbl_attempts'
  UNION ALL SELECT 'tbl_audit_log'
  UNION ALL SELECT 'tbl_email_verification'
  UNION ALL SELECT 'tbl_gallery'
  UNION ALL SELECT 'tbl_gallery_images'
  UNION ALL SELECT 'tbl_gallery_posts'
  UNION ALL SELECT 'tbl_league'
  UNION ALL SELECT 'tbl_league_document_requirement'
  UNION ALL SELECT 'tbl_league_group'
  UNION ALL SELECT 'tbl_league_group_team'
  UNION ALL SELECT 'tbl_league_guideline_document'
  UNION ALL SELECT 'tbl_league_stage'
  UNION ALL SELECT 'tbl_league_stage_group'
  UNION ALL SELECT 'tbl_league_stage_group_source'
  UNION ALL SELECT 'tbl_league_stage_group_team'
  UNION ALL SELECT 'tbl_login_attempts'
  UNION ALL SELECT 'tbl_match_history'
  UNION ALL SELECT 'tbl_match_set'
  UNION ALL SELECT 'tbl_notification'
  UNION ALL SELECT 'tbl_password_history'
  UNION ALL SELECT 'tbl_password_reset'
  UNION ALL SELECT 'tbl_player'
  UNION ALL SELECT 'tbl_progression_log'
  UNION ALL SELECT 'tbl_result_protest'
  UNION ALL SELECT 'tbl_schedule'
  UNION ALL SELECT 'tbl_scheduling_config'
  UNION ALL SELECT 'tbl_security_events'
  UNION ALL SELECT 'tbl_submitted_document'
  UNION ALL SELECT 'tbl_team'
  UNION ALL SELECT 'tbl_user'
  UNION ALL SELECT 'tbl_utility'
) t
LEFT JOIN information_schema.tables it
       ON it.table_schema = 'lms' AND it.table_name = t.name
WHERE it.table_name IS NULL;

-- 3. Schema milestones -------------------------------------------------------
--    expected: publication_status present, format / format_code absent
SELECT column_name, column_type, is_nullable, column_default
FROM information_schema.columns
WHERE table_schema = 'lms'
  AND table_name = 'tbl_league'
  AND column_name IN ('publication_status', 'active_unique', 'is_archived',
                      'format', 'format_code')
ORDER BY column_name;

-- 4. Reference data ----------------------------------------------------------
--    expected: 8 sports, 4 divisions, 2 venues, 3 document_types, 3 categories
SELECT category, COUNT(*) AS rows_expected
FROM tbl_utility
GROUP BY category
ORDER BY category;

-- 5. Legacy format model must be gone ---------------------------------------
--    expected: 0
SELECT COUNT(*) AS league_format_rows_expected_0
FROM tbl_utility
WHERE category = 'league_format';

-- 6. Accounts ----------------------------------------------------------------
--    expected on a FRESH install: 0
--    expected after restoring database/backups/...: 16
SELECT COUNT(*) AS user_count,
       SUM(role = '3') AS admins,
       SUM(role = '1') AS organizers,
       SUM(role = '4') AS committee,
       SUM(role = '2') AS team_reps
FROM tbl_user;
