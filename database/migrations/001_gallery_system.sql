-- Gallery System Database Migration
-- This creates the gallery table structure for storing gallery posts with multiple images

-- Create gallery posts table (one post = one title + description + multiple images)
CREATE TABLE IF NOT EXISTS `tbl_gallery_posts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` LONGTEXT,
  `uploaded_by` INT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create gallery images table (multiple images per post)
CREATE TABLE IF NOT EXISTS `tbl_gallery_images` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `post_id` INT NOT NULL,
  `filename` VARCHAR(255) NOT NULL,
  `display_order` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`post_id`) REFERENCES `tbl_gallery_posts`(`id`) ON DELETE CASCADE,
  INDEX `idx_post_id` (`post_id`),
  INDEX `idx_display_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
