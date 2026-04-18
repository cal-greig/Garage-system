-- ============================================================
-- Stock reduction feature — run in phpMyAdmin
-- ============================================================

-- Track whether stock has been deducted for a job
ALTER TABLE jobs ADD COLUMN IF NOT EXISTS stock_deducted TINYINT(1) DEFAULT 0;

-- Link job_parts to inventory items (nullable — not all parts are in inventory)
ALTER TABLE job_parts ADD COLUMN IF NOT EXISTS inventory_id INT DEFAULT NULL;

-- Add logged_by to job_time so hours can be attributed to mechanics
ALTER TABLE job_time ADD COLUMN IF NOT EXISTS logged_by INT DEFAULT NULL;
