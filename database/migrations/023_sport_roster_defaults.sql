-- Optional reusable roster defaults for Sports utility records.
-- League-level min_player/max_player remain the authoritative limits.
ALTER TABLE tbl_utility
    ADD COLUMN IF NOT EXISTS default_min_players INT NULL AFTER format_code,
    ADD COLUMN IF NOT EXISTS default_max_players INT NULL AFTER default_min_players;
