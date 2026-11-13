-- Preserve existing schedules as already published; new schedules remain Draft until explicitly published.
UPDATE tbl_schedule SET published_at=COALESCE(published_at,created)
WHERE is_archived=0 OR is_archived IS NULL;

