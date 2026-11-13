-- Add created_by column to tbl_league to track organizer
ALTER TABLE `tbl_league` ADD COLUMN `created_by` int(11) DEFAULT NULL AFTER `created`;
ALTER TABLE `tbl_league` ADD FOREIGN KEY (`created_by`) REFERENCES `tbl_user`(`id`) ON DELETE SET NULL;
