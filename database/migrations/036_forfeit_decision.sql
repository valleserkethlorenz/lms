-- ---------------------------------------------------------------------------
-- Forfeit decision record (ANP2026 Volleyball §10 / §7.4)
-- ---------------------------------------------------------------------------
-- A bulk forfeiture voids every match of a team. §10 makes that sweeping
-- sanction "subject to the decision of the Technical Committee", so the
-- decision reference is stamped on every affected match instead of living only
-- in the audit log.
--
-- The same columns are added at runtime by `ensureScheduleTournamentColumns()`
-- (includes/schedulingHelper.php), so an existing installation self-heals
-- without running this file by hand.
-- ---------------------------------------------------------------------------

ALTER TABLE tbl_schedule
    ADD COLUMN IF NOT EXISTS forfeit_decision_ref VARCHAR(120) NULL,
    ADD COLUMN IF NOT EXISTS forfeit_team_id INT NULL,
    ADD COLUMN IF NOT EXISTS forfeit_applied_by INT NULL,
    ADD COLUMN IF NOT EXISTS forfeit_applied_at DATETIME NULL;
