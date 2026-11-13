-- Migration: Remove organization_name and position fields from tbl_user
-- Date: 2026-08-30
-- Description: Removes the organization_name and position columns from tbl_user
--              because the organization is already CSDP and the position is
--              already represented by the user's role (Organizer/Committee).

-- Drop organization_name column if it exists
ALTER TABLE tbl_user
    DROP COLUMN IF EXISTS organization_name;

-- Drop position column if it exists
ALTER TABLE tbl_user
    DROP COLUMN IF EXISTS position;