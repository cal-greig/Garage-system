-- ============================================================
-- Mechanic assignment — run in phpMyAdmin
-- ============================================================

-- Add assigned mechanic to jobs table
ALTER TABLE jobs ADD COLUMN IF NOT EXISTS assigned_to INT DEFAULT NULL;

-- Index for fast lookups
ALTER TABLE jobs ADD INDEX IF NOT EXISTS idx_assigned (assigned_to);
