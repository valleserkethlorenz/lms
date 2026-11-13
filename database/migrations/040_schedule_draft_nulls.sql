-- Generated stage matches are unscheduled drafts: the organizer chooses the
-- date, time, venue and Committee afterwards.
ALTER TABLE tbl_schedule MODIFY COLUMN `date` DATE NULL DEFAULT NULL;
ALTER TABLE tbl_schedule MODIFY COLUMN `time` TIME NULL DEFAULT NULL;
