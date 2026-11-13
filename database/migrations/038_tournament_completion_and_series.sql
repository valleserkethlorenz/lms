-- ============================================================================
-- Tournament completion state + series (best-of) support for game-based sports
--
-- Everything here is ADDITIVE and idempotent. No existing row is deleted and no
-- existing behaviour changes:
--
--   * Leagues keep whatever they do today. tbl_league.tournament_completed_at
--     stays NULL, so "is this tournament finished?" keeps answering exactly the
--     way it did before this migration.
--   * tbl_league_stage.best_of / generation_pattern already exist (migration
--     035) but were never written. best_of is now written by the Setup page;
--     final_best_of is the NEW round-specific override for the last round.
--   * tbl_utility.result_unit gains 'games'. 'sets' (Volleyball) and 'points'
--     (every other sport) are untouched, so sportProfileUsesSets() keeps
--     returning the same answer it does today.
--
-- Why not tbl_league.stat?
--   config/leagueStatus.php recomputes `stat` from start_date / end_date on every
--   render (Upcoming / Open / Ongoing / Completed), and the scheduling guards
--   read it ("League must be Ongoing"). Writing a completion flag there would be
--   overwritten on the next page load, so completion needs its own columns.
--
-- Why no new tables?
--   A series is already one tbl_schedule row (best_of, sets_a, sets_b, winner)
--   and a game inside it is already one tbl_match_set row (set_no, a_points,
--   b_points). That is the Tournament > Stage > Round > Series > Game model, so
--   no relational extension is required.
--
-- A full database backup is required before running this file.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. Explicit tournament completion (an organizer decision, not a date guess)
-- ---------------------------------------------------------------------------
ALTER TABLE tbl_league
    ADD COLUMN IF NOT EXISTS tournament_completed_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS tournament_completed_by INT NULL;

-- ---------------------------------------------------------------------------
-- 2. Round-specific series length.
--    tbl_league_stage.best_of  = default series length for the stage.
--    tbl_league_stage.final_best_of = override applied to the LAST round only
--                                     (e.g. Quarterfinals BO3, Grand Final BO5).
--    Matches are stamped with the resolved value at generation time, so
--    tbl_schedule.best_of (already present) stays the single source of truth for
--    a played series and matchBestOfForSchedule() prefers it.
-- ---------------------------------------------------------------------------
ALTER TABLE tbl_league_stage
    ADD COLUMN IF NOT EXISTS final_best_of TINYINT NULL;

-- ---------------------------------------------------------------------------
-- 3. result_unit gains 'games' (esports series: BO1/BO3/BO5/BO7 of games).
--    'sets' and 'points' keep their current meaning.
-- ---------------------------------------------------------------------------
ALTER TABLE tbl_utility
    MODIFY COLUMN result_unit ENUM('points','sets','games') NULL;

-- ---------------------------------------------------------------------------
-- 4. Existing sports stay exactly as they are. Only the Mobile Legends row is
--    filled in, and only where it is still empty, so an organizer who already
--    customised it from Admin > Utilities is never overwritten.
-- ---------------------------------------------------------------------------
UPDATE tbl_utility
   SET result_unit     = 'games',
       scoring_mode    = 'set_line',
       best_of         = 3,
       allow_draw      = 0,
       forfeit_sets    = 2,
       forfeit_set_points = 0,
       forfeit_points  = 0
 WHERE category = 'sport'
   AND LOWER(name) = 'mobile legends'
   AND result_unit IS NULL;
