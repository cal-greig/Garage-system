-- Run this once on your database to add payment tracking
ALTER TABLE jobs
ADD COLUMN payment_status ENUM('unpaid', 'paid') NOT NULL DEFAULT 'unpaid';

-- Run this to create the settings table for storing the hourly rate
CREATE TABLE IF NOT EXISTS settings (
    key_name VARCHAR(50) PRIMARY KEY,
    value VARCHAR(100) NOT NULL
);

-- Insert default hourly rate
INSERT INTO settings (key_name, value) VALUES ('hourly_rate', '50.00')
ON DUPLICATE KEY UPDATE value = value;

-- Run this to add soft delete support to the jobs table
ALTER TABLE jobs
ADD COLUMN deleted TINYINT(1) NOT NULL DEFAULT 0,
ADD COLUMN deleted_at DATETIME NULL,
ADD COLUMN deleted_by VARCHAR(100) NULL;

-- Add friends & family discount setting (default 10%)
INSERT INTO settings (key_name, value) VALUES ('ff_discount', '10.00')
ON DUPLICATE KEY UPDATE value = value;
