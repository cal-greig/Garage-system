-- ============================================================
-- Inventory System — run this in phpMyAdmin
-- ============================================================

CREATE TABLE IF NOT EXISTS inventory (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    part_name           VARCHAR(200)    NOT NULL,
    part_number         VARCHAR(100)    DEFAULT NULL,
    quantity            INT             NOT NULL DEFAULT 0,
    cost_price          DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    low_stock_threshold INT             NOT NULL DEFAULT 2,
    notes               TEXT            DEFAULT NULL,
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
