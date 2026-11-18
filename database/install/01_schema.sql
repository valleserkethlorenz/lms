-- ============================================================================
-- LMS  -  Step 01: canonical database schema (structure only, no data)
--
-- Generated with mysqldump from the verified database on 2026-10-22.
-- 2026-11-19: migration 043 reporting indexes folded in.
-- 31 tables. Import AFTER 00_create_database.sql:
--
--     mysql -u root lms < 01_schema.sql        (Windows: C:\xampp\mysql\bin\mysql.exe)
--
-- This file is generated - do not hand-edit. When the schema changes, apply the
-- matching file in ../migrations/ and then regenerate this dump.
-- ============================================================================

USE `lms`;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_announcement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `league_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `created_by` int(11) NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `updated` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_league_id` (`league_id`),
  KEY `idx_created` (`created`),
  CONSTRAINT `tbl_announcement_ibfk_1` FOREIGN KEY (`league_id`) REFERENCES `tbl_league` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tbl_announcement_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `tbl_user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(255) NOT NULL,
  `try_time` bigint(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_audit_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_name` varchar(100) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `user_role` varchar(50) DEFAULT NULL,
  `related_type` varchar(80) DEFAULT NULL,
  `related_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_gallery` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_gallery_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `display_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_post_id` (`post_id`),
  KEY `idx_display_order` (`display_order`),
  KEY `idx_gallery_images_archive` (`post_id`,`is_archived`,`display_order`),
  CONSTRAINT `tbl_gallery_images_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `tbl_gallery_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_gallery_posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_uploaded_by` (`uploaded_by`),
  KEY `idx_gallery_posts_archive` (`is_archived`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_league` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `league_image` varchar(255) NOT NULL,
  `guideline_document` varchar(255) DEFAULT NULL,
  `league_name` varchar(255) NOT NULL,
  `sport` varchar(255) NOT NULL,
  `division` varchar(255) NOT NULL,
  `descrip` varchar(255) NOT NULL,
  `category` varchar(255) NOT NULL,
  `grouping_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `start_date` varchar(255) NOT NULL,
  `end_date` varchar(255) NOT NULL,
  `regis_start` varchar(255) NOT NULL,
  `regis_end` varchar(255) NOT NULL,
  `venue` varchar(255) NOT NULL,
  `max_team` varchar(255) NOT NULL,
  `min_player` int(11) NOT NULL DEFAULT 5,
  `max_player` varchar(255) NOT NULL,
  `min_age` int(11) DEFAULT 0,
  `max_age` int(11) DEFAULT 100,
  `min_male_players` int(11) DEFAULT 0,
  `min_female_players` int(11) DEFAULT 0,
  `registration_window_open` tinyint(1) DEFAULT 1,
  `regis_fee` varchar(255) NOT NULL,
  `add_info` varchar(255) NOT NULL,
  `stat` varchar(255) NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `archived_at` datetime DEFAULT NULL,
  `active_unique` tinyint(4) DEFAULT 1,
  `publication_status` enum('Draft','Published') NOT NULL DEFAULT 'Draft',
  `published_at` datetime DEFAULT NULL,
  `scoring_win_points` int(11) NOT NULL DEFAULT 3,
  `scoring_draw_points` int(11) NOT NULL DEFAULT 1,
  `scoring_loss_points` int(11) NOT NULL DEFAULT 0,
  `scoring_forfeit_points` int(11) NOT NULL DEFAULT 0,
  `tiebreak_order` varchar(160) NOT NULL DEFAULT 'head_to_head,quotient,difference,points_for,name',
  `birth_year_min` smallint(6) DEFAULT NULL,
  `birth_year_max` smallint(6) DEFAULT NULL,
  `require_residency` tinyint(1) NOT NULL DEFAULT 0,
  `one_team_per_barangay` tinyint(1) NOT NULL DEFAULT 0,
  `officials_excluded_from_roster` tinyint(1) NOT NULL DEFAULT 0,
  `finals_pattern` enum('semis_final_bronze','direct_final_bronze') DEFAULT NULL,
  `protest_window_minutes` int(11) DEFAULT NULL,
  `scoring_win_clean_points` int(11) DEFAULT NULL,
  `scoring_win_close_points` int(11) DEFAULT NULL,
  `scoring_loss_close_points` int(11) DEFAULT NULL,
  `tournament_completed_at` datetime DEFAULT NULL,
  `tournament_completed_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uidx_organizer_active_league` (`created_by`,`league_name`,`sport`,`start_date`,`end_date`,`active_unique`) USING HASH,
  KEY `idx_league_report_owner` (`created_by`,`is_archived`,`sport`,`division`,`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_league_document_requirement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `league_id` int(11) NOT NULL,
  `document_type_id` int(11) NOT NULL,
  `applies_to` enum('team','player') NOT NULL DEFAULT 'player',
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `instructions` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_league_document_scope` (`league_id`,`document_type_id`,`applies_to`),
  KEY `idx_requirement_league` (`league_id`),
  KEY `fk_requirement_type` (`document_type_id`),
  CONSTRAINT `fk_requirement_league` FOREIGN KEY (`league_id`) REFERENCES `tbl_league` (`id`),
  CONSTRAINT `fk_requirement_type` FOREIGN KEY (`document_type_id`) REFERENCES `tbl_utility` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_league_group` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `league_id` int(11) NOT NULL,
  `name` varchar(80) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_league_group_name` (`league_id`,`name`),
  CONSTRAINT `fk_group_league` FOREIGN KEY (`league_id`) REFERENCES `tbl_league` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_league_group_team` (
  `group_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`team_id`),
  KEY `idx_group_team_group` (`group_id`),
  CONSTRAINT `fk_group_team_group` FOREIGN KEY (`group_id`) REFERENCES `tbl_league_group` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_group_team_team` FOREIGN KEY (`team_id`) REFERENCES `tbl_team` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_league_guideline_document` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `league_id` int(11) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_guideline_league` (`league_id`,`is_archived`),
  CONSTRAINT `fk_guideline_league` FOREIGN KEY (`league_id`) REFERENCES `tbl_league` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_league_stage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `league_id` int(11) NOT NULL,
  `name` varchar(80) NOT NULL,
  `stage_type` enum('group','crossover','knockout','placement') NOT NULL DEFAULT 'group',
  `display_order` int(11) NOT NULL DEFAULT 1,
  `qualifiers_per_group` int(11) DEFAULT NULL,
  `carries_previous_results` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `best_of` tinyint(4) DEFAULT NULL,
  `generation_pattern` varchar(40) DEFAULT NULL,
  `final_best_of` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_league_stage_name` (`league_id`,`name`),
  KEY `idx_stage_league` (`league_id`,`display_order`),
  CONSTRAINT `fk_stage_league` FOREIGN KEY (`league_id`) REFERENCES `tbl_league` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_league_stage_group` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stage_id` int(11) NOT NULL,
  `name` varchar(80) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stage_group_name` (`stage_id`,`name`),
  KEY `idx_stage_group_stage` (`stage_id`,`display_order`),
  CONSTRAINT `fk_stage_group_stage` FOREIGN KEY (`stage_id`) REFERENCES `tbl_league_stage` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_league_stage_group_source` (
  `stage_group_id` int(11) NOT NULL,
  `source_group_id` int(11) NOT NULL,
  PRIMARY KEY (`stage_group_id`,`source_group_id`),
  KEY `fk_sgs_source` (`source_group_id`),
  CONSTRAINT `fk_sgs_group` FOREIGN KEY (`stage_group_id`) REFERENCES `tbl_league_stage_group` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sgs_source` FOREIGN KEY (`source_group_id`) REFERENCES `tbl_league_stage_group` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_league_stage_group_team` (
  `stage_group_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `seed` int(11) DEFAULT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`stage_group_id`,`team_id`),
  KEY `idx_sgt_team` (`team_id`),
  CONSTRAINT `fk_sgt_group` FOREIGN KEY (`stage_group_id`) REFERENCES `tbl_league_stage_group` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sgt_team` FOREIGN KEY (`team_id`) REFERENCES `tbl_team` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `attempt_time` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts_user` (`user_id`),
  KEY `idx_login_attempts_time` (`attempt_time`),
  KEY `idx_login_attempts_ip` (`ip_address`),
  CONSTRAINT `fk_login_attempts_user` FOREIGN KEY (`user_id`) REFERENCES `tbl_user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_match_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `schedule_id` int(11) NOT NULL,
  `league_id` int(11) NOT NULL,
  `team_a_id` int(11) NOT NULL,
  `team_b_id` int(11) NOT NULL,
  `team_a_score` int(11) DEFAULT 0,
  `team_b_score` int(11) DEFAULT 0,
  `winner_id` int(11) DEFAULT NULL,
  `match_date` date NOT NULL,
  `match_time` time NOT NULL,
  `venue` varchar(255) DEFAULT NULL,
  `status` enum('Completed','Cancelled','Postponed') DEFAULT 'Completed',
  `notes` longtext DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `schedule_id` (`schedule_id`),
  KEY `winner_id` (`winner_id`),
  KEY `idx_league_date` (`league_id`,`match_date`),
  KEY `idx_team_a` (`team_a_id`),
  KEY `idx_team_b` (`team_b_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `tbl_match_history_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `tbl_schedule` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tbl_match_history_ibfk_2` FOREIGN KEY (`league_id`) REFERENCES `tbl_league` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tbl_match_history_ibfk_3` FOREIGN KEY (`team_a_id`) REFERENCES `tbl_team` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tbl_match_history_ibfk_4` FOREIGN KEY (`team_b_id`) REFERENCES `tbl_team` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tbl_match_history_ibfk_5` FOREIGN KEY (`winner_id`) REFERENCES `tbl_team` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_match_set` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `schedule_id` int(11) NOT NULL,
  `league_id` int(11) NOT NULL,
  `set_no` tinyint(4) NOT NULL,
  `a_points` smallint(6) NOT NULL DEFAULT 0,
  `b_points` smallint(6) NOT NULL DEFAULT 0,
  `is_deciding` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_match_set_no` (`schedule_id`,`set_no`),
  KEY `idx_match_set_league` (`league_id`),
  CONSTRAINT `fk_match_set_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `tbl_schedule` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_notification` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_created` (`created`),
  KEY `idx_notif_user_unread` (`user_id`,`is_read`),
  KEY `idx_notif_user_created` (`user_id`,`created`),
  KEY `idx_notif_user_type_related` (`user_id`,`type`,`related_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_password_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_password_history_user` (`user_id`),
  KEY `idx_password_history_date` (`changed_at`),
  CONSTRAINT `fk_password_history_user` FOREIGN KEY (`user_id`) REFERENCES `tbl_user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_password_reset` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(128) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_password_reset_hash` (`token_hash`),
  KEY `idx_password_reset_user` (`user_id`),
  KEY `idx_password_reset_expires` (`expires_at`),
  CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `tbl_user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_email_verification` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `verified_at` datetime DEFAULT NULL,
  `consumed` tinyint(1) NOT NULL DEFAULT 0,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_email_verification_email` (`email`),
  KEY `idx_email_verification_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_player` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `age` varchar(255) NOT NULL,
  `birthdate` date DEFAULT NULL,
  `role` varchar(255) NOT NULL,
  `gender` varchar(255) NOT NULL,
  `jersey_number` varchar(255) NOT NULL,
  `document` varchar(255) DEFAULT NULL,
  `eligibility_status` varchar(30) NOT NULL DEFAULT 'Pending',
  `eligibility_remarks` varchar(500) DEFAULT NULL,
  `eligibility_reviewed_by` int(11) DEFAULT NULL,
  `eligibility_reviewed_at` datetime DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `barangay` varchar(120) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_player_team_eligibility` (`team_id`,`eligibility_status`),
  KEY `idx_player_team_created` (`team_id`,`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_progression_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `league_id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `action` varchar(60) NOT NULL,
  `team_id` int(11) DEFAULT NULL,
  `before_status` varchar(30) DEFAULT NULL,
  `after_status` varchar(30) DEFAULT NULL,
  `created_schedule_ids` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_plog_schedule` (`schedule_id`),
  KEY `idx_plog_league` (`league_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_result_protest` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `schedule_id` int(11) NOT NULL,
  `league_id` int(11) NOT NULL,
  `raised_by` int(11) DEFAULT NULL,
  `party` enum('team','committee','organizer') NOT NULL DEFAULT 'team',
  `reason` text NOT NULL,
  `status` enum('Open','Upheld','Denied') NOT NULL DEFAULT 'Open',
  `decided_by` int(11) DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `decision_note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_protest_schedule` (`schedule_id`),
  CONSTRAINT `fk_protest_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `tbl_schedule` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_schedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `league_id` int(11) NOT NULL,
  `league_group_id` int(11) DEFAULT NULL,
  `team_a` int(11) NOT NULL,
  `a_score` int(11) DEFAULT 0,
  `team_b` int(11) NOT NULL,
  `b_score` int(11) DEFAULT 0,
  `date` date DEFAULT NULL,
  `time` time DEFAULT NULL,
  `venue` varchar(255) DEFAULT NULL,
  `round` int(11) DEFAULT 1,
  `match_number` int(11) DEFAULT NULL,
  `is_automated` tinyint(1) DEFAULT 1,
  `conflict_detected` tinyint(1) DEFAULT 0,
  `conflict_reason` varchar(255) DEFAULT NULL,
  `bracket` varchar(50) DEFAULT NULL,
  `group_stage` int(11) DEFAULT NULL,
  `stage_id` int(11) DEFAULT NULL,
  `stage_group_id` int(11) DEFAULT NULL,
  `seed_a` varchar(12) DEFAULT NULL,
  `seed_b` varchar(12) DEFAULT NULL,
  `stage` varchar(50) DEFAULT NULL,
  `status` varchar(255) NOT NULL,
  `winner` varchar(255) NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `committee_user_id` int(11) DEFAULT NULL,
  `livestream_url` varchar(500) DEFAULT NULL,
  `livestream_status` varchar(50) DEFAULT NULL,
  `result_submitted_by` int(11) DEFAULT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `archived_at` datetime DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `result_breakdown` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`result_breakdown`)),
  `win_type` enum('Regular','Forfeit','DoubleForfeit','Walkover','Decision') NOT NULL DEFAULT 'Regular',
  `result_note` varchar(255) DEFAULT NULL,
  `penalty_a` int(11) NOT NULL DEFAULT 0,
  `penalty_b` int(11) NOT NULL DEFAULT 0,
  `result_status` enum('Final','Protested','UnderReview','Corrected') NOT NULL DEFAULT 'Final',
  `result_reopened_by` int(11) DEFAULT NULL,
  `result_reopened_at` datetime DEFAULT NULL,
  `best_of` tinyint(4) DEFAULT NULL,
  `sets_a` tinyint(4) DEFAULT NULL,
  `sets_b` tinyint(4) DEFAULT NULL,
  `a_points` int(11) DEFAULT NULL,
  `b_points` int(11) DEFAULT NULL,
  `forfeit_decision_ref` varchar(120) DEFAULT NULL,
  `forfeit_team_id` int(11) DEFAULT NULL,
  `forfeit_applied_by` int(11) DEFAULT NULL,
  `forfeit_applied_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_league_date` (`league_id`,`date`),
  KEY `idx_team_a_date` (`team_a`,`date`,`time`),
  KEY `idx_team_b_date` (`team_b`,`date`,`time`),
  KEY `idx_venue_date` (`venue`,`date`,`time`),
  KEY `idx_status` (`status`),
  KEY `idx_conflict` (`conflict_detected`),
  KEY `idx_sched_report_status` (`league_id`,`status`,`published_at`,`is_archived`),
  KEY `fk_schedule_group` (`league_group_id`),
  CONSTRAINT `fk_schedule_group` FOREIGN KEY (`league_group_id`) REFERENCES `tbl_league_group` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_scheduling_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `league_id` int(11) NOT NULL,
  `match_duration_minutes` int(11) DEFAULT 60,
  `rest_period_minutes` int(11) DEFAULT 15,
  `earliest_start_time` time DEFAULT '08:00:00',
  `latest_end_time` time DEFAULT '22:00:00',
  `max_matches_per_day` int(11) DEFAULT 10,
  `auto_schedule_enabled` tinyint(1) DEFAULT 1,
  `conflict_check_enabled` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `league_id` (`league_id`),
  KEY `idx_league` (`league_id`),
  CONSTRAINT `tbl_scheduling_config_ibfk_1` FOREIGN KEY (`league_id`) REFERENCES `tbl_league` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_security_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_type` varchar(50) NOT NULL,
  `severity` enum('INFO','WARNING','CRITICAL','ALERT') NOT NULL DEFAULT 'INFO',
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `description` text NOT NULL,
  `context` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`context`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_security_events_type` (`event_type`),
  KEY `idx_security_events_severity` (`severity`),
  KEY `idx_security_events_date` (`created_at`),
  KEY `idx_security_events_user` (`user_id`),
  CONSTRAINT `fk_security_events_user` FOREIGN KEY (`user_id`) REFERENCES `tbl_user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_submitted_document` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) NOT NULL,
  `player_id` int(11) DEFAULT NULL,
  `requirement_id` int(11) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(120) NOT NULL,
  `quality_status` enum('Pending','Clear','Flagged') NOT NULL DEFAULT 'Pending',
  `quality_metrics` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`quality_metrics`)),
  `review_status` enum('Pending','Accepted','Rejected') NOT NULL DEFAULT 'Pending',
  `review_remarks` varchar(500) DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_submission_team` (`team_id`),
  KEY `idx_submission_player` (`player_id`),
  KEY `idx_submission_requirement` (`requirement_id`),
  CONSTRAINT `fk_submission_player` FOREIGN KEY (`player_id`) REFERENCES `tbl_player` (`id`),
  CONSTRAINT `fk_submission_requirement` FOREIGN KEY (`requirement_id`) REFERENCES `tbl_league_document_requirement` (`id`),
  CONSTRAINT `fk_submission_team` FOREIGN KEY (`team_id`) REFERENCES `tbl_team` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_team` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_name` varchar(255) NOT NULL,
  `team_image` varchar(255) NOT NULL,
  `league_id` varchar(255) NOT NULL,
  `bracket` varchar(255) NOT NULL,
  `team_repre` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `remarks` varchar(500) DEFAULT NULL,
  `correction_due_at` datetime DEFAULT NULL,
  `correction_expired_at` datetime DEFAULT NULL,
  `preliminary_flags` longtext DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `progression_status` enum('Undecided','Qualified','Eliminated') NOT NULL DEFAULT 'Undecided',
  `progression_decided_by` int(11) DEFAULT NULL,
  `progression_decided_at` datetime DEFAULT NULL,
  `active_registration` tinyint(4) GENERATED ALWAYS AS (case when lcase(`status`) in ('rejected','cancelled') then NULL else 1 end) STORED,
  `barangay` varchar(120) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_team_active_registration` (`league_id`,`user_id`,`active_registration`),
  KEY `idx_team_correction_due` (`status`,`correction_due_at`),
  KEY `idx_team_league_status` (`league_id`,`status`),
  KEY `idx_team_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `res_id` varchar(255) NOT NULL DEFAULT '',
  `fname` varchar(255) NOT NULL,
  `lname` varchar(255) NOT NULL,
  `mname` varchar(255) NOT NULL,
  `suffix` varchar(255) NOT NULL,
  `gender` enum('Male','Female','') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `phone` varchar(255) NOT NULL,
  `address` varchar(500) DEFAULT NULL,
  `organization_name` varchar(255) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `years_experience` int(11) DEFAULT NULL,
  `credentials` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `pass` varchar(255) NOT NULL,
  `role` varchar(255) NOT NULL COMMENT '1 = ORGANIZER\r\n2 = TEAM REPRE',
  `stat` varchar(255) NOT NULL DEFAULT '1',
  `pass_stat` int(11) NOT NULL DEFAULT 1 COMMENT '0=change\r\n1 = hindi pa binabago',
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_utility` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category` enum('sport','division','venue','document_type','category') NOT NULL,
  `name` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `min_age` int(11) DEFAULT NULL,
  `max_age` int(11) DEFAULT NULL,
  `default_min_players` int(11) DEFAULT NULL,
  `default_max_players` int(11) DEFAULT NULL,
  `applies_to` enum('team','player') DEFAULT NULL,
  `result_unit` enum('points','sets','games') DEFAULT NULL,
  `scoring_mode` enum('simple','set_line') DEFAULT NULL,
  `best_of` tinyint(4) DEFAULT NULL,
  `set_target` smallint(6) DEFAULT NULL,
  `deciding_target` smallint(6) DEFAULT NULL,
  `win_by` smallint(6) DEFAULT NULL,
  `jersey_min` smallint(6) DEFAULT NULL,
  `jersey_max` smallint(6) DEFAULT NULL,
  `roster_regular` smallint(6) DEFAULT NULL,
  `libero_min` tinyint(4) DEFAULT NULL,
  `libero_max` tinyint(4) DEFAULT NULL,
  `require_official` tinyint(4) DEFAULT NULL,
  `protest_window_minutes` int(11) DEFAULT NULL,
  `allow_draw` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_utility_category_name` (`category`,`name`),
  KEY `idx_utility_active` (`category`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

