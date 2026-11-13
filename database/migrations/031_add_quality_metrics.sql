-- Migration: Add quality_metrics column to tbl_submitted_document
-- Stores raw analysis metrics (laplacian_std, entropy, detail_ratio, etc.)
-- so organizers can see WHY a document was flagged and thresholds can be
-- recalibrated based on real data later.

ALTER TABLE tbl_submitted_document
    ADD COLUMN IF NOT EXISTS quality_metrics JSON NULL AFTER quality_status;
