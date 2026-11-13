-- Migration: Add UNIQUE constraint to prevent duplicate leagues
-- Date: 2026-06-03
-- Description: Adds UNIQUE constraint on (league_name, sport, start_date, end_date)
--              to prevent duplicate league creation at database level

-- Drop existing unique key if it exists (for idempotency)
DROP INDEX IF EXISTS uidx_league_name_sport_dates ON tbl_league;

-- Add UNIQUE constraint to prevent duplicates
ALTER TABLE tbl_league
ADD CONSTRAINT uidx_league_name_sport_dates
UNIQUE (league_name, sport, start_date, end_date);
