-- Final role/responsibility foundation. Review the live schema before running.
ALTER TABLE tbl_league
    ADD COLUMN IF NOT EXISTS publication_status ENUM('Draft','Published') NOT NULL DEFAULT 'Draft',
    ADD COLUMN IF NOT EXISTS published_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS active_unique TINYINT NULL DEFAULT 1;

UPDATE tbl_league SET active_unique = CASE WHEN is_archived=1 THEN NULL ELSE 1 END;

UPDATE tbl_league SET publication_status='Published', published_at=COALESCE(published_at, created)
WHERE publication_status='Draft' AND created_by IS NOT NULL;

ALTER TABLE tbl_schedule
    ADD COLUMN IF NOT EXISTS committee_user_id INT NULL,
    ADD COLUMN IF NOT EXISTS livestream_url VARCHAR(500) NULL,
    ADD COLUMN IF NOT EXISTS livestream_status VARCHAR(50) NULL,
    ADD COLUMN IF NOT EXISTS result_submitted_by INT NULL,
    ADD COLUMN IF NOT EXISTS is_archived TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS archived_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS published_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS result_breakdown LONGTEXT NULL;

ALTER TABLE tbl_team
    ADD COLUMN IF NOT EXISTS preliminary_flags LONGTEXT NULL,
    ADD COLUMN IF NOT EXISTS reviewed_by INT NULL,
    ADD COLUMN IF NOT EXISTS reviewed_at DATETIME NULL;

ALTER TABLE tbl_audit_log
    ADD COLUMN IF NOT EXISTS user_role VARCHAR(50) NULL,
    ADD COLUMN IF NOT EXISTS related_type VARCHAR(80) NULL,
    ADD COLUMN IF NOT EXISTS related_id INT NULL;

CREATE TABLE IF NOT EXISTS tbl_utility (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    category ENUM('sport','division','venue','document_type') NOT NULL,
    name VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_utility_category_name (category, name),
    KEY idx_utility_active (category, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO tbl_utility (category, name, is_active) VALUES
('sport','Basketball',1), ('sport','Volleyball',1), ('sport','Football',1),
('sport','Badminton',1), ('sport','Tennis',1), ('division','Open',1),
('division','Under - 13',1), ('division','Under - 18',1),
('division','Under - 21',1), ('document_type','Birth Certificate',1),
('document_type','Medical Certificate',1), ('document_type','School ID',1);
