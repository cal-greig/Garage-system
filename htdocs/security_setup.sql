-- ============================================================
-- Security hardening — run this in phpMyAdmin
-- ============================================================

-- Ensure all passwords are properly hashed (should already be the case)
-- This is a reminder only — no data changes needed if setup_users.sql was run

-- Optional: if you want server-side login attempt tracking across devices
-- (the default session-based approach already works without this table)
-- CREATE TABLE IF NOT EXISTS login_attempts (
--     id         INT AUTO_INCREMENT PRIMARY KEY,
--     username   VARCHAR(100) NOT NULL,
--     ip_address VARCHAR(45)  NOT NULL,
--     attempted_at DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
--     INDEX idx_username (username),
--     INDEX idx_ip (ip_address)
-- );
