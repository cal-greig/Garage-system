-- ============================================================
-- Quoting System — run this in phpMyAdmin
-- ============================================================

CREATE TABLE IF NOT EXISTS quotes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    customer_name   VARCHAR(150) NOT NULL,
    customer_phone  VARCHAR(50)  DEFAULT NULL,
    customer_email  VARCHAR(150) DEFAULT NULL,
    contact_source  VARCHAR(150) DEFAULT NULL,
    vehicle         VARCHAR(100) DEFAULT NULL,
    registration    VARCHAR(20)  DEFAULT NULL,
    job_type        VARCHAR(100) DEFAULT NULL,
    description     TEXT         DEFAULT NULL,
    status          ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
    total_amount    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    converted_job_id INT          DEFAULT NULL,
    created_by      VARCHAR(100) DEFAULT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS quote_labour (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    quote_id    INT            NOT NULL,
    hours       DECIMAL(6,2)   NOT NULL,
    description VARCHAR(255)   NOT NULL,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS quote_parts (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    quote_id    INT            NOT NULL,
    part_name   VARCHAR(150)   NOT NULL,
    quantity    INT            NOT NULL DEFAULT 1,
    price       DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS quote_tasks (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    quote_id    INT            NOT NULL,
    task        VARCHAR(255)   NOT NULL,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
);

-- Add soft delete columns to quotes table
-- Run this if you already created the quotes table previously
ALTER TABLE quotes
    ADD COLUMN IF NOT EXISTS deleted      TINYINT(1)   NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS deleted_at   DATETIME     DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS deleted_by   VARCHAR(100) DEFAULT NULL;
