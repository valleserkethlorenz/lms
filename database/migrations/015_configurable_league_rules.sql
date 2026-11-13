ALTER TABLE tbl_utility
    MODIFY category ENUM('sport','division','venue','document_type','category','league_format') NOT NULL,
    ADD COLUMN IF NOT EXISTS min_age INT NULL,
    ADD COLUMN IF NOT EXISTS max_age INT NULL,
    ADD COLUMN IF NOT EXISTS format_code VARCHAR(50) NULL;

ALTER TABLE tbl_league ADD COLUMN IF NOT EXISTS format_code VARCHAR(50) NULL AFTER format;
UPDATE tbl_league SET format_code=CASE format
    WHEN 'Round Robin' THEN 'round_robin'
    WHEN 'Single Elimination' THEN 'single_elimination'
    WHEN 'Double Elimination' THEN 'double_elimination'
    WHEN 'Swiss System' THEN 'swiss_system'
    WHEN 'Group Stage + Knockout' THEN 'group_knockout'
    ELSE format_code END
WHERE format_code IS NULL;

UPDATE tbl_utility SET min_age=0,max_age=60 WHERE category='division' AND name='Open';
UPDATE tbl_utility SET min_age=0,max_age=13 WHERE category='division' AND name='Under - 13';
UPDATE tbl_utility SET min_age=0,max_age=18 WHERE category='division' AND name='Under - 18';
UPDATE tbl_utility SET min_age=0,max_age=21 WHERE category='division' AND name='Under - 21';
ALTER TABLE tbl_utility ADD UNIQUE INDEX IF NOT EXISTS uq_utility_format_code (format_code);

INSERT IGNORE INTO tbl_utility(category,name,is_active) VALUES
('category','Male',1),('category','Female',1),('category','Mixed',1);
INSERT IGNORE INTO tbl_utility(category,name,format_code,is_active) VALUES
('league_format','Round Robin','round_robin',1),
('league_format','Single Elimination','single_elimination',1),
('league_format','Double Elimination','double_elimination',0),
('league_format','Swiss System','swiss_system',0),
('league_format','Group Stage + Knockout','group_knockout',0);

CREATE TABLE IF NOT EXISTS tbl_league_document_requirement (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    league_id INT NOT NULL,
    document_type_id INT NOT NULL,
    applies_to ENUM('team','player') NOT NULL DEFAULT 'player',
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    instructions VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_league_document_scope (league_id,document_type_id,applies_to),
    KEY idx_requirement_league (league_id),
    CONSTRAINT fk_requirement_league FOREIGN KEY (league_id) REFERENCES tbl_league(id),
    CONSTRAINT fk_requirement_type FOREIGN KEY (document_type_id) REFERENCES tbl_utility(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_submitted_document (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    player_id INT NULL,
    requirement_id INT NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    quality_status ENUM('Pending','Clear','Flagged') NOT NULL DEFAULT 'Pending',
    review_status ENUM('Pending','Accepted','Rejected') NOT NULL DEFAULT 'Pending',
    review_remarks VARCHAR(500) NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_submission_team (team_id),
    KEY idx_submission_player (player_id),
    KEY idx_submission_requirement (requirement_id),
    CONSTRAINT fk_submission_team FOREIGN KEY (team_id) REFERENCES tbl_team(id),
    CONSTRAINT fk_submission_player FOREIGN KEY (player_id) REFERENCES tbl_player(id),
    CONSTRAINT fk_submission_requirement FOREIGN KEY (requirement_id) REFERENCES tbl_league_document_requirement(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
