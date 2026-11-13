-- ============================================================================
-- ANP2026 Volleyball support  (V1 schema + seeds)
--
-- Adds the sport-profile columns, the set-based match model and the
-- finals-pattern selector.  Everything is additive and idempotent and NO
-- existing sport/league row is rewritten: leagues keep today's behaviour
-- (points, single game, 20-0 forfeit, 24-hour protest window) until a sport
-- profile explicitly says otherwise.
--
-- Manual: Volleyball Guidelines ANP2026
--   * 4.1/4.2  BO3 from the Elimination Round to the Semifinals, BO5 Final
--   * 4.3/4.4  sets to 25 (win by 2); deciding set to 15
--   * 6.1      Single Round Robin, Pool A + Pool B -> Finals / Bronze
--   * 6.2      Win 2-0 = 3 pts, Win 2-1 = 2 pts, Loss 1-2 = 1 pt,
--              Loss 0-2 = 0 pts, Forfeit/Default/Withdrawal = 0 pts
--   * 6.3      tie in points -> number of matches won (descending)
--   * 10       BO3 forfeit = 0-2 (0-25, 0-25); BO5 forfeit = 0-3 (0-25 x3)
--   * 7.2      written protest within 30 minutes
--   * Team composition: 14 players incl. 2 Liberos + officials
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. Sport profiles live where the other sport defaults already live
--    (tbl_utility category = 'sport'), so the Admin -> Utilities screen can
--    manage them without code changes.
-- ---------------------------------------------------------------------------
ALTER TABLE tbl_utility
    ADD COLUMN IF NOT EXISTS result_unit ENUM('points','sets') NULL,
    ADD COLUMN IF NOT EXISTS scoring_mode ENUM('simple','set_line') NULL,
    ADD COLUMN IF NOT EXISTS best_of TINYINT NULL,
    ADD COLUMN IF NOT EXISTS set_target SMALLINT NULL,
    ADD COLUMN IF NOT EXISTS deciding_target SMALLINT NULL,
    ADD COLUMN IF NOT EXISTS win_by SMALLINT NULL,
    ADD COLUMN IF NOT EXISTS jersey_min SMALLINT NULL,
    ADD COLUMN IF NOT EXISTS jersey_max SMALLINT NULL,
    ADD COLUMN IF NOT EXISTS roster_regular SMALLINT NULL,
    ADD COLUMN IF NOT EXISTS libero_min TINYINT NULL,
    ADD COLUMN IF NOT EXISTS libero_max TINYINT NULL,
    ADD COLUMN IF NOT EXISTS require_official TINYINT NULL,
    ADD COLUMN IF NOT EXISTS protest_window_minutes INT NULL,
    ADD COLUMN IF NOT EXISTS allow_draw TINYINT NULL;

-- ---------------------------------------------------------------------------
-- 2. League overrides
-- ---------------------------------------------------------------------------
ALTER TABLE tbl_league
    ADD COLUMN IF NOT EXISTS finals_pattern ENUM('semis_final_bronze','direct_final_bronze') NULL,
    ADD COLUMN IF NOT EXISTS protest_window_minutes INT NULL,
    ADD COLUMN IF NOT EXISTS scoring_win_clean_points INT NULL,
    ADD COLUMN IF NOT EXISTS scoring_win_close_points INT NULL,
    ADD COLUMN IF NOT EXISTS scoring_loss_close_points INT NULL;

-- ---------------------------------------------------------------------------
-- 3. Stage + match level: best-of and the generation pattern
-- ---------------------------------------------------------------------------
ALTER TABLE tbl_league_stage
    ADD COLUMN IF NOT EXISTS best_of TINYINT NULL,
    ADD COLUMN IF NOT EXISTS generation_pattern VARCHAR(40) NULL;

ALTER TABLE tbl_schedule
    ADD COLUMN IF NOT EXISTS best_of TINYINT NULL,
    ADD COLUMN IF NOT EXISTS sets_a TINYINT NULL,
    ADD COLUMN IF NOT EXISTS sets_b TINYINT NULL,
    ADD COLUMN IF NOT EXISTS a_points INT NULL,
    ADD COLUMN IF NOT EXISTS b_points INT NULL;

-- ---------------------------------------------------------------------------
-- 4. Per-set scores (a_score/b_score keep meaning "the unit that decides the
--    match": sets for volleyball, points for every other sport).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tbl_match_set (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT NOT NULL,
    league_id INT NOT NULL,
    set_no TINYINT NOT NULL,
    a_points SMALLINT NOT NULL DEFAULT 0,
    b_points SMALLINT NOT NULL DEFAULT 0,
    is_deciding TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_match_set_no (schedule_id, set_no),
    KEY idx_match_set_league (league_id),
    CONSTRAINT fk_match_set_schedule FOREIGN KEY (schedule_id) REFERENCES tbl_schedule(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 5. Seed the Volleyball profile (idempotent, Volleyball row only)
-- ---------------------------------------------------------------------------
UPDATE tbl_utility SET
    result_unit             = 'sets',
    scoring_mode            = 'set_line',
    best_of                 = 3,
    set_target              = 25,
    deciding_target         = 15,
    win_by                  = 2,
    jersey_min              = 1,
    jersey_max              = 99,
    roster_regular          = 12,
    libero_min              = 1,
    libero_max              = 2,
    require_official        = 1,
    protest_window_minutes  = 30,
    allow_draw              = 0,
    default_min_players     = 12,
    default_max_players     = 14
WHERE category = 'sport' AND name = 'Volleyball';

INSERT INTO tbl_utility (category, name, is_active, result_unit, scoring_mode, best_of,
    set_target, deciding_target, win_by, jersey_min, jersey_max, roster_regular,
    libero_min, libero_max, require_official, protest_window_minutes, allow_draw,
    default_min_players, default_max_players)
SELECT 'sport', 'Volleyball', 1, 'sets', 'set_line', 3, 25, 15, 2, 1, 99, 12, 1, 2, 1, 30, 0, 12, 14
WHERE NOT EXISTS (SELECT 1 FROM tbl_utility WHERE category = 'sport' AND name = 'Volleyball');

-- Existing volleyball leagues default to F1 (semifinals -> championship + bronze).
UPDATE tbl_league SET finals_pattern = 'semis_final_bronze'
 WHERE sport = 'Volleyball' AND (finals_pattern IS NULL OR finals_pattern = '');
