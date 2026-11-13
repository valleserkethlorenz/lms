-- Add eligibility requirement columns to tbl_league table
ALTER TABLE [tbl_league] ADD [min_age] INT DEFAULT 0;
ALTER TABLE [tbl_league] ADD [max_age] INT DEFAULT 100;
ALTER TABLE [tbl_league] ADD [min_male_players] INT DEFAULT 0;
ALTER TABLE [tbl_league] ADD [min_female_players] INT DEFAULT 0;
ALTER TABLE [tbl_league] ADD [registration_window_open] TINYINT DEFAULT 1;
