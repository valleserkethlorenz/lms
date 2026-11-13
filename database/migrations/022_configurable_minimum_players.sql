-- Configurable minimum roster size per league.
-- Existing leagues retain the former five-player requirement.
ALTER TABLE tbl_league
    ADD COLUMN IF NOT EXISTS min_player INT NOT NULL DEFAULT 5 AFTER max_team;

UPDATE tbl_league
SET min_player = 5
WHERE min_player IS NULL OR min_player < 1;
