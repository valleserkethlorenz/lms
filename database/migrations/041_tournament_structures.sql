-- ============================================================================
-- Tournament structures renovation (setup + scheduling)
--
-- From now on a stage owns its FORMAT and a knockout match knows its place in
-- the bracket graph:
--
--   Tournament (tbl_league)
--     └── Stage (tbl_league_stage.stage_format)
--           ├── Round robin / Swiss  -> groups + fixtures + standings
--           └── Single / Double elimination -> node graph
--                 winners / losers / grand final, each row pointing at the
--                 match its winner and its loser move to.
--
-- Everything is additive and idempotent: existing leagues keep their stages
-- (stage_format is backfilled from stage_type) and existing matches keep
-- working (node_key stays NULL, so the legacy progression engine still runs).
--
-- Run a full database backup before applying this file.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. Stage format + lifecycle state
--    stage_type stays the coarse bucket used by the existing UI
--    (group / crossover / knockout / placement); stage_format is the exact
--    competition format the new setup wizard and scheduling engine read.
-- ---------------------------------------------------------------------------
ALTER TABLE tbl_league_stage
    ADD COLUMN IF NOT EXISTS stage_format ENUM('round_robin','single_elimination','double_elimination','swiss','placement')
        NOT NULL DEFAULT 'round_robin' AFTER stage_type,
    ADD COLUMN IF NOT EXISTS stage_status ENUM('Pending','Active','Locked','Complete')
        NOT NULL DEFAULT 'Active' AFTER stage_format,
    ADD COLUMN IF NOT EXISTS rounds_planned INT NULL AFTER stage_status,
    ADD COLUMN IF NOT EXISTS settings_json TEXT NULL AFTER rounds_planned,
    ADD COLUMN IF NOT EXISTS activated_at DATETIME NULL AFTER settings_json,
    ADD COLUMN IF NOT EXISTS locked_at DATETIME NULL AFTER activated_at;

-- Backfill only the rows that still carry the column default, so a stage whose
-- format was already chosen by the organizer is never overwritten.
UPDATE tbl_league_stage
   SET stage_format = 'single_elimination'
 WHERE stage_type = 'knockout' AND stage_format = 'round_robin';

UPDATE tbl_league_stage
   SET stage_format = 'placement'
 WHERE stage_type = 'placement' AND stage_format = 'round_robin';

-- ---------------------------------------------------------------------------
-- 2. Bracket graph on the match row
--    A node is one match. next_node_key/next_node_slot is where the WINNER
--    goes, loser_next_node_key/loser_next_node_slot is where the LOSER goes
--    (the losers bracket in double elimination). Node keys are unique per
--    stage (W-R1-M1, L-R2-M3, GF-M1 ...).
-- ---------------------------------------------------------------------------
ALTER TABLE tbl_schedule
    ADD COLUMN IF NOT EXISTS bracket_side ENUM('winners','losers','grand_final','consolation') NULL AFTER seed_b,
    ADD COLUMN IF NOT EXISTS node_key VARCHAR(24) NULL AFTER bracket_side,
    ADD COLUMN IF NOT EXISTS next_node_key VARCHAR(24) NULL AFTER node_key,
    ADD COLUMN IF NOT EXISTS next_node_slot ENUM('a','b') NULL AFTER next_node_key,
    ADD COLUMN IF NOT EXISTS loser_next_node_key VARCHAR(24) NULL AFTER next_node_slot,
    ADD COLUMN IF NOT EXISTS loser_next_node_slot ENUM('a','b') NULL AFTER loser_next_node_key,
    ADD COLUMN IF NOT EXISTS swiss_round INT NULL AFTER loser_next_node_slot,
    ADD COLUMN IF NOT EXISTS locked TINYINT(1) NOT NULL DEFAULT 0 AFTER swiss_round,
    ADD COLUMN IF NOT EXISTS locked_at DATETIME NULL AFTER locked;

ALTER TABLE tbl_schedule
    ADD INDEX IF NOT EXISTS idx_schedule_node (stage_id, node_key),
    ADD INDEX IF NOT EXISTS idx_schedule_swiss (stage_id, swiss_round);

-- ---------------------------------------------------------------------------
-- 3. Stage transitions (cut-off rules)
--    Example: "Top 2 teams from each group of Stage 1 advance to Stage 2" or
--    "Bottom 4 teams drop into the Consolation stage".
--    One inbound rule per target stage.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tbl_league_stage_transition (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    league_id INT NOT NULL,
    from_stage_id INT NULL,
    to_stage_id INT NOT NULL,
    rule ENUM('top_per_group','top_overall','bottom_consolation') NOT NULL DEFAULT 'top_per_group',
    advance_count INT NOT NULL DEFAULT 2,
    settings_json TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_stage_transition_target (to_stage_id),
    KEY idx_stage_transition_league (league_id),
    CONSTRAINT fk_transition_from FOREIGN KEY (from_stage_id) REFERENCES tbl_league_stage(id) ON DELETE CASCADE,
    CONSTRAINT fk_transition_to FOREIGN KEY (to_stage_id) REFERENCES tbl_league_stage(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 4. Stage slots (the right-hand column of the "Advance Teams" screen)
--    The engine fills every slot from the previous stage's locked standings;
--    the organizer may drag teams between slots before activating the stage.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tbl_league_stage_slot (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    stage_id INT NOT NULL,
    slot_code VARCHAR(24) NOT NULL,
    label VARCHAR(80) NOT NULL,
    slot_order INT NOT NULL DEFAULT 1,
    source_stage_id INT NULL,
    source_group_id INT NULL,
    source_rank INT NULL,
    source_kind ENUM('seed','group_rank','group_winner','loser','manual','bye') NOT NULL DEFAULT 'manual',
    team_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_stage_slot (stage_id, slot_code),
    KEY idx_stage_slot_order (stage_id, slot_order),
    CONSTRAINT fk_slot_stage FOREIGN KEY (stage_id) REFERENCES tbl_league_stage(id) ON DELETE CASCADE,
    CONSTRAINT fk_slot_team FOREIGN KEY (team_id) REFERENCES tbl_team(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

