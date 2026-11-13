-- Add archived_at timestamp to tbl_league for auto-hide after 1 month on public view
ALTER TABLE `tbl_league`
    ADD COLUMN IF NOT EXISTS `is_archived` tinyint(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `archived_at` datetime DEFAULT NULL;
