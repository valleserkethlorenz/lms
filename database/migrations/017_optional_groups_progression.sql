ALTER TABLE tbl_league ADD COLUMN IF NOT EXISTS grouping_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER format_code;
ALTER TABLE tbl_team
    ADD COLUMN IF NOT EXISTS progression_status ENUM('Undecided','Qualified','Eliminated') NOT NULL DEFAULT 'Undecided',
    ADD COLUMN IF NOT EXISTS progression_decided_by INT NULL,
    ADD COLUMN IF NOT EXISTS progression_decided_at DATETIME NULL;

CREATE TABLE IF NOT EXISTS tbl_league_group (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    league_id INT NOT NULL,
    name VARCHAR(80) NOT NULL,
    display_order INT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_league_group_name (league_id,name),
    CONSTRAINT fk_group_league FOREIGN KEY (league_id) REFERENCES tbl_league(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tbl_league_group_team (
    group_id INT NOT NULL,
    team_id INT NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (team_id),
    KEY idx_group_team_group (group_id),
    CONSTRAINT fk_group_team_group FOREIGN KEY (group_id) REFERENCES tbl_league_group(id) ON DELETE CASCADE,
    CONSTRAINT fk_group_team_team FOREIGN KEY (team_id) REFERENCES tbl_team(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE tbl_schedule ADD COLUMN IF NOT EXISTS league_group_id INT NULL AFTER league_id;
ALTER TABLE tbl_schedule ADD CONSTRAINT fk_schedule_group FOREIGN KEY (league_group_id) REFERENCES tbl_league_group(id) ON DELETE SET NULL;
