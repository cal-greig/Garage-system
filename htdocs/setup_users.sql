-- Run this to add role and must_change_password to your users table
ALTER TABLE users
ADD COLUMN role ENUM('admin','mechanic') NOT NULL DEFAULT 'mechanic',
ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0,
ADD COLUMN created_at DATETIME DEFAULT NOW();

-- Set your existing admin account's role
-- Replace 'your_admin_username' with your actual username
UPDATE users SET role = 'admin', must_change_password = 0 WHERE username = 'admin';
