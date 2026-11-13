CREATE TABLE IF NOT EXISTS tbl_league_guideline_document (
    id INT AUTO_INCREMENT PRIMARY KEY,
    league_id INT NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    uploaded_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_archived TINYINT(1) NOT NULL DEFAULT 0,
    archived_at DATETIME NULL,
    INDEX idx_guideline_league (league_id, is_archived),
    CONSTRAINT fk_guideline_league FOREIGN KEY (league_id) REFERENCES tbl_league(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO tbl_league_guideline_document (league_id, stored_name, original_name, uploaded_by)
SELECT l.id, l.guideline_document, l.guideline_document, l.created_by
FROM tbl_league l
WHERE l.guideline_document IS NOT NULL
  AND l.guideline_document <> ''
  AND NOT EXISTS (
      SELECT 1 FROM tbl_league_guideline_document d
      WHERE d.league_id=l.id AND d.stored_name=l.guideline_document
  );
