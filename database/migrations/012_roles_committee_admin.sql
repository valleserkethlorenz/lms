-- ============================================================
-- Migration: Add Admin (3), Committee (4) roles + new columns
-- Run this once against the `lms` database
-- ============================================================

-- 1. tbl_user: add is_active alias (stat already exists; add role comment clarity)
--    role: 1=Organizer, 2=Team Representative, 3=Admin, 4=Committee
--    No structural change needed for tbl_user.role (already varchar)

-- 2. tbl_audit_log: add user_role column
ALTER TABLE `tbl_audit_log`
    ADD COLUMN IF NOT EXISTS `user_role` VARCHAR(50) DEFAULT NULL AFTER `user_id`;

-- 3. tbl_team: add remarks column (used in reject action but missing from schema)
ALTER TABLE `tbl_team`
    ADD COLUMN IF NOT EXISTS `remarks` TEXT DEFAULT NULL AFTER `status`;

-- 4. tbl_schedule: add committee assignment, livestream, result tracking
ALTER TABLE `tbl_schedule`
    ADD COLUMN IF NOT EXISTS `committee_user_id` INT DEFAULT NULL AFTER `status`,
    ADD COLUMN IF NOT EXISTS `livestream_url` VARCHAR(500) DEFAULT NULL AFTER `committee_user_id`,
    ADD COLUMN IF NOT EXISTS `livestream_status` VARCHAR(50) DEFAULT NULL AFTER `livestream_url`,
    ADD COLUMN IF NOT EXISTS `result_submitted_by` INT DEFAULT NULL AFTER `livestream_status`;

-- 5. tbl_utility: new table for Admin-managed reference data
CREATE TABLE IF NOT EXISTS `tbl_utility` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category` ENUM('sport','division','venue','document_type') NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_by` INT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_category` (`category`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6. Seed default sports into tbl_utility
INSERT IGNORE INTO `tbl_utility` (`category`, `name`, `is_active`) VALUES
('sport', 'Basketball', 1),
('sport', 'Volleyball', 1),
('sport', 'Football', 1),
('sport', 'Badminton', 1),
('sport', 'Tennis', 1),
('division', 'Open', 1),
('division', 'Under - 13', 1),
('division', 'Under - 18', 1),
('division', 'Under - 21', 1),
('document_type', 'Birth Certificate', 1),
('document_type', 'Medical Certificate', 1),
('document_type', 'School ID', 1);

-- 7. Create default Admin account (password: Admin@1234)
--    Only insert if no role=3 user exists
INSERT INTO `tbl_user` (`fname`, `lname`, `mname`, `suffix`, `phone`, `email`, `pass`, `role`, `stat`, `pass_stat`)
SELECT 'System', 'Admin', '', '', '00000000000', 'admin@lms.local',
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC//wjAWEYoziSCIPnWm', '3', '1', 0
WHERE NOT EXISTS (SELECT 1 FROM `tbl_user` WHERE `role` = '3');
-- Note: default password is "password" — change immediately after first login
