-- Migration: Security Enhancements (Steps 13-19)
-- Date: 2026-05-29
-- Description: Adds tables for password history tracking and login attempt monitoring

-- =====================================================
-- Table: tbl_password_history
-- Tracks previous passwords to prevent reuse
-- =====================================================
CREATE TABLE IF NOT EXISTS tbl_password_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_password_history_user (user_id),
    INDEX idx_password_history_date (changed_at),
    CONSTRAINT fk_password_history_user FOREIGN KEY (user_id) REFERENCES tbl_user(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: tbl_login_attempts
-- Tracks failed login attempts for account lockout
-- =====================================================
CREATE TABLE IF NOT EXISTS tbl_login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    attempt_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_attempts_user (user_id),
    INDEX idx_login_attempts_time (attempt_time),
    INDEX idx_login_attempts_ip (ip_address),
    CONSTRAINT fk_login_attempts_user FOREIGN KEY (user_id) REFERENCES tbl_user(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: tbl_security_events
-- Stores security event logs for monitoring
-- =====================================================
CREATE TABLE IF NOT EXISTS tbl_security_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    severity ENUM('INFO', 'WARNING', 'CRITICAL', 'ALERT') NOT NULL DEFAULT 'INFO',
    user_id INT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    description TEXT NOT NULL,
    context JSON DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_security_events_type (event_type),
    INDEX idx_security_events_severity (severity),
    INDEX idx_security_events_date (created_at),
    INDEX idx_security_events_user (user_id),
    CONSTRAINT fk_security_events_user FOREIGN KEY (user_id) REFERENCES tbl_user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Cleanup: Remove old login attempt records (> 30 days)
-- =====================================================
-- Run this as a scheduled task or manually
-- DELETE FROM tbl_login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 30 DAY);
-- DELETE FROM tbl_security_events WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- =====================================================
-- Add session tracking columns to tbl_user (optional)
-- =====================================================
-- ALTER TABLE tbl_user ADD COLUMN last_login_at DATETIME DEFAULT NULL AFTER pass_stat;
-- ALTER TABLE tbl_user ADD COLUMN last_login_ip VARCHAR(45) DEFAULT NULL AFTER last_login_at;
-- ALTER TABLE tbl_user ADD COLUMN failed_login_count INT DEFAULT 0 AFTER last_login_ip;
-- ALTER TABLE tbl_user ADD COLUMN locked_until DATETIME DEFAULT NULL AFTER failed_login_count;
