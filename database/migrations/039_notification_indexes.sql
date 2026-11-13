-- ============================================================================
-- Notification pipeline indexes
--
-- tbl_notification is read far more often than it is written: the sidebar
-- renders an unread count on every page load, every open tab polls the counter
-- once a minute and opening the bell reads the latest 50 rows. All of those
-- filter on user_id first and then on is_read / created, but migration 006
-- created one index per column, so MySQL could only use a single one of them
-- per query.
--
-- Everything here is ADDITIVE and safe to re-run: each index is created only
-- when it does not exist yet (no column, row or behaviour changes).
-- ============================================================================

-- Unread badge / poll: WHERE user_id = ? AND is_read = 0
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_notification'
      AND INDEX_NAME = 'idx_notif_user_unread');
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE tbl_notification ADD INDEX idx_notif_user_unread (user_id, is_read)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Bell / inbox list: WHERE user_id = ? ORDER BY created DESC LIMIT 50
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_notification'
      AND INDEX_NAME = 'idx_notif_user_created');
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE tbl_notification ADD INDEX idx_notif_user_created (user_id, created)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Daily de-duplication lookups ("did this user already get a reminder of this
-- type for this record?") used by upcoming_match, registration_closing and the
-- team_correction_* reminders.
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_notification'
      AND INDEX_NAME = 'idx_notif_user_type_related');
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE tbl_notification ADD INDEX idx_notif_user_type_related (user_id, type, related_id)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Migration complete
