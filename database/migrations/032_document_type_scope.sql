-- Migration: Add applies_to column to tbl_utility for document_type scope control
-- Date: 2026-09-01
-- Description: Moves the per-team/per-player scope decision from league creation
--              to the utility management page. The admin sets the scope once when
--              defining a document type, and it is locked during league creation.

-- Add applies_to column (only meaningful for category='document_type')
ALTER TABLE tbl_utility
    ADD COLUMN IF NOT EXISTS applies_to ENUM('team','player') NULL DEFAULT NULL;

-- Set default scope for existing document types
UPDATE tbl_utility SET applies_to = 'player' WHERE category = 'document_type' AND applies_to IS NULL;
