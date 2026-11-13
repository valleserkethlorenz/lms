-- Enhanced Schedule Table with Conflict Detection and Match Tracking
-- This migration upgrades the tbl_schedule table to support automated scheduling

ALTER TABLE `tbl_schedule` MODIFY `league_id` INT NOT NULL;
ALTER TABLE `tbl_schedule` MODIFY `team_a` INT NOT NULL;
ALTER TABLE `tbl_schedule` MODIFY `team_b` INT NOT NULL;
ALTER TABLE `tbl_schedule` MODIFY `date` DATE NOT NULL;
ALTER TABLE `tbl_schedule` MODIFY `time` TIME NOT NULL;
ALTER TABLE `tbl_schedule` MODIFY `a_score` INT DEFAULT 0;
ALTER TABLE `tbl_schedule` MODIFY `b_score` INT DEFAULT 0;
ALTER TABLE `tbl_schedule` MODIFY `winner` INT DEFAULT NULL;

-- Add new columns for enhanced scheduling
ALTER TABLE `tbl_schedule` ADD COLUMN `venue` VARCHAR(255) DEFAULT NULL AFTER `time`;
ALTER TABLE `tbl_schedule` ADD COLUMN `round` INT DEFAULT 1 AFTER `venue`;
ALTER TABLE `tbl_schedule` ADD COLUMN `match_number` INT DEFAULT NULL AFTER `round`;
ALTER TABLE `tbl_schedule` ADD COLUMN `is_automated` TINYINT(1) DEFAULT 1 AFTER `match_number`;
ALTER TABLE `tbl_schedule` ADD COLUMN `conflict_detected` TINYINT(1) DEFAULT 0 AFTER `is_automated`;
ALTER TABLE `tbl_schedule` ADD COLUMN `conflict_reason` VARCHAR(255) DEFAULT NULL AFTER `conflict_detected`;
ALTER TABLE `tbl_schedule` ADD COLUMN `bracket` VARCHAR(50) DEFAULT NULL AFTER `conflict_reason`;
ALTER TABLE `tbl_schedule` ADD COLUMN `group_stage` INT DEFAULT NULL AFTER `bracket`;
ALTER TABLE `tbl_schedule` ADD COLUMN `stage` VARCHAR(50) DEFAULT NULL AFTER `group_stage`;
ALTER TABLE `tbl_schedule` ADD COLUMN `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created`;

-- Add indexes for better query performance
ALTER TABLE `tbl_schedule` ADD INDEX `idx_league_date` (`league_id`, `date`);
ALTER TABLE `tbl_schedule` ADD INDEX `idx_team_a_date` (`team_a`, `date`, `time`);
ALTER TABLE `tbl_schedule` ADD INDEX `idx_team_b_date` (`team_b`, `date`, `time`);
ALTER TABLE `tbl_schedule` ADD INDEX `idx_venue_date` (`venue`, `date`, `time`);
ALTER TABLE `tbl_schedule` ADD INDEX `idx_status` (`status`);
ALTER TABLE `tbl_schedule` ADD INDEX `idx_conflict` (`conflict_detected`);

-- Create Match History Table for tracking completed matches
CREATE TABLE IF NOT EXISTS `tbl_match_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `schedule_id` INT NOT NULL,
  `league_id` INT NOT NULL,
  `team_a_id` INT NOT NULL,
  `team_b_id` INT NOT NULL,
  `team_a_score` INT DEFAULT 0,
  `team_b_score` INT DEFAULT 0,
  `winner_id` INT DEFAULT NULL,
  `match_date` DATE NOT NULL,
  `match_time` TIME NOT NULL,
  `venue` VARCHAR(255),
  `status` ENUM('Completed', 'Cancelled', 'Postponed') DEFAULT 'Completed',
  `notes` LONGTEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`schedule_id`) REFERENCES `tbl_schedule`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`league_id`) REFERENCES `tbl_league`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`team_a_id`) REFERENCES `tbl_team`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`team_b_id`) REFERENCES `tbl_team`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`winner_id`) REFERENCES `tbl_team`(`id`) ON DELETE SET NULL,
  INDEX `idx_league_date` (`league_id`, `match_date`),
  INDEX `idx_team_a` (`team_a_id`),
  INDEX `idx_team_b` (`team_b_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create Scheduling Configuration Table
CREATE TABLE IF NOT EXISTS `tbl_scheduling_config` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `league_id` INT NOT NULL UNIQUE,
  `match_duration_minutes` INT DEFAULT 60,
  `rest_period_minutes` INT DEFAULT 15,
  `earliest_start_time` TIME DEFAULT '08:00:00',
  `latest_end_time` TIME DEFAULT '22:00:00',
  `max_matches_per_day` INT DEFAULT 10,
  `auto_schedule_enabled` TINYINT(1) DEFAULT 1,
  `conflict_check_enabled` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`league_id`) REFERENCES `tbl_league`(`id`) ON DELETE CASCADE,
  INDEX `idx_league` (`league_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
