-- Migration: Add guideline_document column to tbl_league
-- Purpose: Store organizer-uploaded guideline documents

ALTER TABLE [tbl_league] ADD [guideline_document] VARCHAR(255) NULL;
