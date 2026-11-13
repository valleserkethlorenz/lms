ALTER TABLE tbl_gallery_posts
    ADD COLUMN IF NOT EXISTS is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER updated_at,
    ADD COLUMN IF NOT EXISTS archived_at DATETIME NULL AFTER is_archived,
    ADD INDEX IF NOT EXISTS idx_gallery_posts_archive (is_archived, created_at);

ALTER TABLE tbl_gallery_images
    ADD COLUMN IF NOT EXISTS is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER created_at,
    ADD COLUMN IF NOT EXISTS archived_at DATETIME NULL AFTER is_archived,
    ADD INDEX IF NOT EXISTS idx_gallery_images_archive (post_id, is_archived, display_order);
