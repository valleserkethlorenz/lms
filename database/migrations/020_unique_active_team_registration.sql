-- Preserve rejected/cancelled history while allowing only one active application
-- per Team Representative in each league.
ALTER TABLE tbl_team
    ADD COLUMN active_registration TINYINT
        GENERATED ALWAYS AS (
            CASE
                WHEN LOWER(status) IN ('rejected', 'cancelled') THEN NULL
                ELSE 1
            END
        ) STORED,
    ADD UNIQUE KEY uq_team_active_registration (league_id, user_id, active_registration);
