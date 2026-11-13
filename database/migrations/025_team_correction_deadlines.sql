-- Adds a 72-hour correction lifecycle to returned team registrations.
ALTER TABLE `tbl_team`
    ADD COLUMN IF NOT EXISTS `correction_due_at` DATETIME DEFAULT NULL AFTER `remarks`,
    ADD COLUMN IF NOT EXISTS `correction_expired_at` DATETIME DEFAULT NULL AFTER `correction_due_at`,
    ADD INDEX IF NOT EXISTS `idx_team_correction_due` (`status`, `correction_due_at`);

-- Give applications returned before this migration a fair initial grace period.
UPDATE `tbl_team`
SET `correction_due_at` = DATE_ADD(NOW(), INTERVAL 72 HOUR)
WHERE LOWER(`status`) = 'correction requested'
  AND `correction_due_at` IS NULL;
