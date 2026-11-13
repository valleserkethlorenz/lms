-- Migration: Add organizer profile fields to tbl_user
-- Date: 2026-08-29
-- Description: Adds additional profile fields for organizers including
--              gender, date of birth, address, credentials, and profile image

-- Add new columns to tbl_user
ALTER TABLE tbl_user
    ADD COLUMN IF NOT EXISTS gender ENUM('Male','Female','') NULL AFTER suffix,
    ADD COLUMN IF NOT EXISTS date_of_birth DATE NULL AFTER gender,
    ADD COLUMN IF NOT EXISTS address VARCHAR(500) NULL AFTER phone,
    ADD COLUMN IF NOT EXISTS years_experience INT NULL AFTER address,
    ADD COLUMN IF NOT EXISTS credentials TEXT NULL AFTER years_experience,
    ADD COLUMN IF NOT EXISTS profile_image VARCHAR(255) NULL AFTER credentials;
