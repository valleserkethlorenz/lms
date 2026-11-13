-- Create announcements table
CREATE TABLE IF NOT EXISTS `tbl_announcement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `league_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `created_by` int(11) NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`league_id`) REFERENCES `tbl_league`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `tbl_user`(`id`) ON DELETE CASCADE,
  INDEX `idx_league_id` (`league_id`),
  INDEX `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
