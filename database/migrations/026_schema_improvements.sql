-- Migration: Add foreign keys and convert varchar dates to DATE type
-- Date: 2026-05-29
-- Description: Improves database integrity with foreign key constraints and proper date types
-- WARNING: This migration modifies column types. Backup your database before running.

-- Step 1: Convert varchar date columns to DATE in tbl_league
-- First, ensure all existing data is valid
UPDATE tbl_league SET start_date = NULL WHERE start_date = '' OR start_date = '0000-00-00';
UPDATE tbl_league SET end_date = NULL WHERE end_date = '' OR end_date = '0000-00-00';
UPDATE tbl_league SET regis_start = NULL WHERE regis_start = '' OR regis_start = '0000-00-00';
UPDATE tbl_league SET regis_end = NULL WHERE regis_end = '' OR regis_end = '0000-00-00';

-- Convert columns to DATE type
ALTER TABLE tbl_league 
    MODIFY COLUMN start_date DATE NULL,
    MODIFY COLUMN end_date DATE NULL,
    MODIFY COLUMN regis_start DATE NULL,
    MODIFY COLUMN regis_end DATE NULL;

-- Step 2: Convert varchar foreign key columns to INT
-- tbl_team.league_id references tbl_league.id
UPDATE tbl_team SET league_id = NULL WHERE league_id = '' OR league_id NOT REGEXP '^[0-9]+$';
ALTER TABLE tbl_team MODIFY COLUMN league_id INT NULL;

-- tbl_schedule.league_id references tbl_league.id
UPDATE tbl_schedule SET league_id = NULL WHERE league_id = '' OR league_id NOT REGEXP '^[0-9]+$';
ALTER TABLE tbl_schedule MODIFY COLUMN league_id INT NULL;

-- tbl_player.team_id references tbl_team.id
UPDATE tbl_player SET team_id = NULL WHERE team_id = '' OR team_id NOT REGEXP '^[0-9]+$';
ALTER TABLE tbl_player MODIFY COLUMN team_id INT NULL;

-- tbl_team.user_id references tbl_user.id
UPDATE tbl_team SET user_id = NULL WHERE user_id = '' OR user_id NOT REGEXP '^[0-9]+$';
ALTER TABLE tbl_team MODIFY COLUMN user_id INT NULL;

-- Step 3: Add foreign key constraints
-- Note: MariaDB/MySQL requires InnoDB engine for foreign keys
-- Check if tables are using InnoDB, if not convert them
ALTER TABLE tbl_league ENGINE=InnoDB;
ALTER TABLE tbl_team ENGINE=InnoDB;
ALTER TABLE tbl_player ENGINE=InnoDB;
ALTER TABLE tbl_schedule ENGINE=InnoDB;
ALTER TABLE tbl_user ENGINE=InnoDB;

-- Add foreign keys
ALTER TABLE tbl_team
    ADD CONSTRAINT fk_team_league FOREIGN KEY (league_id) REFERENCES tbl_league(id) ON DELETE CASCADE ON UPDATE CASCADE,
    ADD CONSTRAINT fk_team_user FOREIGN KEY (user_id) REFERENCES tbl_user(id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE tbl_player
    ADD CONSTRAINT fk_player_team FOREIGN KEY (team_id) REFERENCES tbl_team(id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE tbl_schedule
    ADD CONSTRAINT fk_schedule_league FOREIGN KEY (league_id) REFERENCES tbl_league(id) ON DELETE CASCADE ON UPDATE CASCADE;

-- Step 4: Add indexes for better query performance
CREATE INDEX idx_team_league ON tbl_team(league_id);
CREATE INDEX idx_team_user ON tbl_team(user_id);
CREATE INDEX idx_player_team ON tbl_player(team_id);
CREATE INDEX idx_schedule_league ON tbl_schedule(league_id);
CREATE INDEX idx_league_dates ON tbl_league(start_date, end_date);

-- Step 5: Add audit timestamps where missing
-- Add updated_at column to tbl_league if not exists
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_league' AND COLUMN_NAME = 'updated_at');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE tbl_league ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add updated_at column to tbl_team if not exists
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_team' AND COLUMN_NAME = 'updated_at');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE tbl_team ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Migration complete
