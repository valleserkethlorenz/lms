-- Active league names/dates are unique only within an Organizer's records.
-- NULL permits any number of archived historical records under MySQL unique indexes.
ALTER TABLE tbl_league DROP INDEX uidx_league_name_sport_dates;
ALTER TABLE tbl_league
    ADD COLUMN active_unique TINYINT NULL DEFAULT 1 AFTER archived_at;
UPDATE tbl_league SET active_unique = CASE WHEN is_archived=1 THEN NULL ELSE 1 END;
ALTER TABLE tbl_league
    ADD UNIQUE INDEX uidx_organizer_active_league
        (created_by, league_name, sport, start_date, end_date, active_unique);

