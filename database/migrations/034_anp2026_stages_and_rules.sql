-- ============================================================================
-- ANP2026 Basketball Guidelines support
--   * stage model: Brackets (stage 1) -> Crossover / Pools (stage 2) -> Playoffs
--   * seeded bracket generation (crossover A1vD2 ..., QF 1v4 / 2v3, SF, Final, 3rd place)
--   * configurable standings (win points + tiebreak order)
--   * result types (forfeit / double forfeit / walkover / decision) and penalties
--   * protests + result correction (progression log for safe reversal)
--   * eligibility (birth-year rule, barangay, officials excluded from roster)
-- All statements are idempotent; every column has a safe default so existing
-- leagues keep behaving exactly as before.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. Stage / group model (a team belongs to a group PER STAGE)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tbl_league_stage (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    league_id INT NOT NULL,
    name VARCHAR(80) NOT NULL,
    stage_type ENUM('group','crossover','knockout','placement') NOT NULL DEFAULT 'group',
    display_order INT NOT NULL DEFAULT 1,
    qualifiers_per_group INT NULL,
    carries_previous_results TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_league_stage_name (league_id,name),
    KEY idx_stage_league (league_id,display_order),
    CONSTRAINT fk_stage_league FOREIGN KEY (league_id) REFERENCES tbl_league(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_league_stage_group (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    stage_id INT NOT NULL,
    name VARCHAR(80) NOT NULL,
    display_order INT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_stage_group_name (stage_id,name),
    KEY idx_stage_group_stage (stage_id,display_order),
    CONSTRAINT fk_stage_group_stage FOREIGN KEY (stage_id) REFERENCES tbl_league_stage(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Which earlier groups feed a pool (e.g. Pool 1 sources: Bracket A + Bracket D)
CREATE TABLE IF NOT EXISTS tbl_league_stage_group_source (
    stage_group_id INT NOT NULL,
    source_group_id INT NOT NULL,
    PRIMARY KEY (stage_group_id, source_group_id),
    CONSTRAINT fk_sgs_group FOREIGN KEY (stage_group_id) REFERENCES tbl_league_stage_group(id) ON DELETE CASCADE,
    CONSTRAINT fk_sgs_source FOREIGN KEY (source_group_id) REFERENCES tbl_league_stage_group(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Team membership per stage group (composite PK => same team in multiple stages)
CREATE TABLE IF NOT EXISTS tbl_league_stage_group_team (
    stage_group_id INT NOT NULL,
    team_id INT NOT NULL,
    seed INT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (stage_group_id, team_id),
    KEY idx_sgt_team (team_id),
    CONSTRAINT fk_sgt_group FOREIGN KEY (stage_group_id) REFERENCES tbl_league_stage_group(id) ON DELETE CASCADE,
    CONSTRAINT fk_sgt_team FOREIGN KEY (team_id) REFERENCES tbl_team(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 2. Schedule tagging: stage, stage group and bracket seeds
-- ---------------------------------------------------------------------------
ALTER TABLE tbl_schedule ADD COLUMN IF NOT EXISTS stage_id INT NULL AFTER group_stage;
ALTER TABLE tbl_schedule ADD COLUMN IF NOT EXISTS stage_group_id INT NULL AFTER stage_id;
ALTER TABLE tbl_schedule ADD COLUMN IF NOT EXISTS seed_a VARCHAR(12) NULL AFTER stage_group_id;
ALTER TABLE tbl_schedule ADD COLUMN IF NOT EXISTS seed_b VARCHAR(12) NULL AFTER seed_a;

-- ---------------------------------------------------------------------------
-- 3. Result types, penalties, protest / correction state
-- ---------------------------------------------------------------------------
ALTER TABLE tbl_schedule ADD COLUMN IF NOT EXISTS win_type ENUM('Regular','Forfeit','DoubleForfeit','Walkover','Decision') NOT NULL DEFAULT 'Regular';
ALTER TABLE tbl_schedule ADD COLUMN IF NOT EXISTS result_note VARCHAR(255) NULL;
ALTER TABLE tbl_schedule ADD COLUMN IF NOT EXISTS penalty_a INT NOT NULL DEFAULT 0;
ALTER TABLE tbl_schedule ADD COLUMN IF NOT EXISTS penalty_b INT NOT NULL DEFAULT 0;
ALTER TABLE tbl_schedule ADD COLUMN IF NOT EXISTS result_status ENUM('Final','Protested','UnderReview','Corrected') NOT NULL DEFAULT 'Final';
ALTER TABLE tbl_schedule ADD COLUMN IF NOT EXISTS result_reopened_by INT NULL;
ALTER TABLE tbl_schedule ADD COLUMN IF NOT EXISTS result_reopened_at DATETIME NULL;

CREATE TABLE IF NOT EXISTS tbl_result_protest (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT NOT NULL,
    league_id INT NOT NULL,
    raised_by INT NULL,
    party ENUM('team','committee','organizer') NOT NULL DEFAULT 'team',
    reason TEXT NOT NULL,
    status ENUM('Open','Upheld','Denied') NOT NULL DEFAULT 'Open',
    decided_by INT NULL,
    decided_at DATETIME NULL,
    decision_note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_protest_schedule (schedule_id),
    CONSTRAINT fk_protest_schedule FOREIGN KEY (schedule_id) REFERENCES tbl_schedule(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Every status change / auto-created bracket row is logged so a corrected
-- result can be reversed safely (un-eliminate teams, drop generated matches).
CREATE TABLE IF NOT EXISTS tbl_progression_log (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    league_id INT NOT NULL,
    schedule_id INT NOT NULL,
    action VARCHAR(60) NOT NULL,
    team_id INT NULL,
    before_status VARCHAR(30) NULL,
    after_status VARCHAR(30) NULL,
    created_schedule_ids TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_plog_schedule (schedule_id),
    KEY idx_plog_league (league_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 4. League scoring rules + eligibility settings (ANP2026 defaults)
-- ---------------------------------------------------------------------------
ALTER TABLE tbl_league ADD COLUMN IF NOT EXISTS scoring_win_points INT NOT NULL DEFAULT 3;
ALTER TABLE tbl_league ADD COLUMN IF NOT EXISTS scoring_draw_points INT NOT NULL DEFAULT 1;
ALTER TABLE tbl_league ADD COLUMN IF NOT EXISTS scoring_loss_points INT NOT NULL DEFAULT 0;
ALTER TABLE tbl_league ADD COLUMN IF NOT EXISTS scoring_forfeit_points INT NOT NULL DEFAULT 0;
ALTER TABLE tbl_league ADD COLUMN IF NOT EXISTS tiebreak_order VARCHAR(160) NOT NULL DEFAULT 'head_to_head,quotient,difference,points_for,name';
ALTER TABLE tbl_league ADD COLUMN IF NOT EXISTS birth_year_min SMALLINT NULL;
ALTER TABLE tbl_league ADD COLUMN IF NOT EXISTS birth_year_max SMALLINT NULL;
ALTER TABLE tbl_league ADD COLUMN IF NOT EXISTS require_residency TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE tbl_league ADD COLUMN IF NOT EXISTS one_team_per_barangay TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE tbl_league ADD COLUMN IF NOT EXISTS officials_excluded_from_roster TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE tbl_team ADD COLUMN IF NOT EXISTS barangay VARCHAR(120) NULL;
ALTER TABLE tbl_player ADD COLUMN IF NOT EXISTS barangay VARCHAR(120) NULL;

-- Basketball defaults: win = 2, loss = 1, forfeit = 0, quotient before difference.
UPDATE tbl_league SET scoring_win_points = 2, scoring_loss_points = 1, scoring_draw_points = 1
 WHERE sport = 'Basketball' AND scoring_win_points = 3 AND scoring_loss_points = 0;
UPDATE tbl_league SET tiebreak_order = 'head_to_head,quotient,difference,points_for,name'
 WHERE sport = 'Basketball' AND (tiebreak_order IS NULL OR tiebreak_order = '');

