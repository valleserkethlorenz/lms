-- Migration: Fix NOT NULL columns without defaults in tbl_user
-- Date: 2026-08-31
-- Description: Adds default values to res_id and pass_stat columns
--              so that INSERT statements that omit these columns
--              do not fail under MySQL strict mode (STRICT_TRANS_TABLES).
--
-- Root cause: admin/users.php and login_action.php (signUp handler)
--             did not include res_id / pass_stat in their INSERT lists,
--             causing "Field 'res_id' doesn't have a default value" errors
--             that silently prevented organizer (and TR) accounts from
--             being created.

-- res_id: resident / external reference ID – not used by the application
ALTER TABLE tbl_user ALTER COLUMN res_id SET DEFAULT '';

-- pass_stat: 0 = changed, 1 = not yet changed
ALTER TABLE tbl_user ALTER COLUMN pass_stat SET DEFAULT 1;
