-- Migration: Password Reset System
-- Date: 2026-09-15
-- Description: Creates the tbl_password_reset table for forgot password functionality

-- =====================================================
-- Table: tbl_password_reset
-- Stores password reset tokens with expiration
-- =====================================================
CREATE TABLE IF NOT EXISTS tbl_password_reset (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(128) NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_password_reset_hash (token_hash),
    INDEX idx_password_reset_user (user_id),
    INDEX idx_password_reset_expires (expires_at),
    CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES tbl_user(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
