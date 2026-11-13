ALTER TABLE tbl_player
    ADD COLUMN eligibility_status VARCHAR(30) NOT NULL DEFAULT 'Pending' AFTER document,
    ADD COLUMN eligibility_remarks VARCHAR(500) NULL AFTER eligibility_status,
    ADD COLUMN eligibility_reviewed_by INT NULL AFTER eligibility_remarks,
    ADD COLUMN eligibility_reviewed_at DATETIME NULL AFTER eligibility_reviewed_by,
    ADD INDEX idx_player_team_eligibility (team_id, eligibility_status);

-- Preserve existing approved teams: their current official rosters were already accepted.
UPDATE tbl_player p
JOIN tbl_team t ON t.id = CAST(p.team_id AS UNSIGNED)
SET p.eligibility_status='Eligible', p.eligibility_reviewed_at=COALESCE(t.reviewed_at,NOW())
WHERE LOWER(t.status)='approved';
