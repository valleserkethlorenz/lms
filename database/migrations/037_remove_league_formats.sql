-- ============================================================================
-- Remove the league "format" concept (Structure is the only tournament model)
--
-- Run AFTER deploying the code that no longer reads/writes:
--   tbl_league.format, tbl_league.format_code, tbl_utility.format_code,
--   tbl_utility.category = 'league_format'
--
-- The legacy engine (single/double elimination, Swiss, group+knockout as
-- formats) is gone. New leagues use stages created from a Structure preset.
--
-- A full database backup is required before running this file.
-- ============================================================================

-- 1. Relax the legacy columns first so a mixed-version rollout cannot fail.
ALTER TABLE tbl_league MODIFY COLUMN format VARCHAR(255) NULL;
ALTER TABLE tbl_league MODIFY COLUMN format_code VARCHAR(50) NULL;

-- 2. Backfill a group stage for every active league that has no stage yet.
INSERT INTO tbl_league_stage
    (league_id, name, stage_type, display_order, qualifiers_per_group,
     carries_previous_results, is_active, created_at)
SELECT l.id, 'League Stage', 'group', 1, NULL, 0, 1, NOW()
FROM tbl_league l
WHERE (l.is_archived = 0 OR l.is_archived IS NULL)
  AND NOT EXISTS (SELECT 1 FROM tbl_league_stage s WHERE s.league_id = l.id);

INSERT INTO tbl_league_stage_group (stage_id, name, display_order, created_at)
SELECT s.id, 'Group A', 1, NOW()
FROM tbl_league_stage s
WHERE NOT EXISTS (SELECT 1 FROM tbl_league_stage_group g WHERE g.stage_id = s.id);

-- 3. Remove the Admin -> Utilities "League Formats" category and its column.
DELETE FROM tbl_utility WHERE category = 'league_format';
SET @idx_exists := (SELECT COUNT(*) FROM information_schema.statistics
                    WHERE table_schema = DATABASE() AND table_name = 'tbl_utility'
                      AND index_name = 'uq_utility_format_code');
SET @drop_idx := IF(@idx_exists > 0,
    'ALTER TABLE tbl_utility DROP INDEX uq_utility_format_code',
    'SELECT 1');
PREPARE stmt FROM @drop_idx; EXECUTE stmt; DEALLOCATE PREPARE stmt;
ALTER TABLE tbl_utility DROP COLUMN format_code;
ALTER TABLE tbl_utility
    MODIFY COLUMN category ENUM('sport','division','venue','document_type','category') NOT NULL;

-- 4. Drop the format columns from the league itself.
ALTER TABLE tbl_league DROP COLUMN format;
ALTER TABLE tbl_league DROP COLUMN format_code;
