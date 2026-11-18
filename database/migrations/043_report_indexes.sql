-- ============================================================================
-- Reporting module indexes
--
-- The organizer reports filter leagues by owner/sport/division/category,
-- teams by league/status/created date, schedules by league/status/published
-- date and players by team/created date. These composite indexes keep every
-- report type responsive as the database grows.
--
-- Everything here is ADDITIVE and safe to re-run: each index is created only
-- when it does not exist yet (no column, row or behaviour changes).
-- ============================================================================

-- Organizer league picker + report league filters
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_league'
      AND INDEX_NAME = 'idx_league_report_owner');
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE tbl_league ADD INDEX idx_league_report_owner (created_by, is_archived, sport, division, category)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Team reports: league approval + registration date
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_team'
      AND INDEX_NAME = 'idx_team_league_status');
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE tbl_team ADD INDEX idx_team_league_status (league_id, status)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_team'
      AND INDEX_NAME = 'idx_team_created');
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE tbl_team ADD INDEX idx_team_created (created)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Match reports: published/completed lookups per league
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_schedule'
      AND INDEX_NAME = 'idx_sched_report_status');
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE tbl_schedule ADD INDEX idx_sched_report_status (league_id, status, published_at, is_archived)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Roster report: players per team ordered by creation
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_player'
      AND INDEX_NAME = 'idx_player_team_created');
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE tbl_player ADD INDEX idx_player_team_created (team_id, created)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Migration complete
