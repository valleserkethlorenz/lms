-- Create notifications table
CREATE TABLE IF NOT EXISTS `tbl_notification` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL COMMENT 'team_registration, schedule_update, team_approved, team_rejected, announcement',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `related_id` int(11) DEFAULT NULL COMMENT 'team_id, schedule_id, league_id, etc',
  `is_read` tinyint(1) DEFAULT 0,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `tbl_user`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_is_read` (`is_read`),
  INDEX `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
