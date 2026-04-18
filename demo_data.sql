-- ============================================================
--  Garage System — Demo Data
--  Run this in phpMyAdmin (or via MySQL CLI) on a fresh DB.
--  It creates all tables then populates them with realistic
--  dummy data so the system looks lived-in for a demo.
-- ============================================================

-- ────────────────────────────────────────────────────────────
--  TABLES
-- ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS users (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    username             VARCHAR(100) NOT NULL UNIQUE,
    password_hash        VARCHAR(255) NOT NULL,
    role                 ENUM('admin','mechanic') NOT NULL DEFAULT 'mechanic',
    must_change_password TINYINT(1)  NOT NULL DEFAULT 0,
    created_at           DATETIME    DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS jobs (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    job_date       DATE         NOT NULL,
    customer_name  VARCHAR(150) NOT NULL,
    vehicle        VARCHAR(100) DEFAULT NULL,
    registration   VARCHAR(20)  DEFAULT NULL,
    job_type       VARCHAR(100) DEFAULT NULL,
    description    TEXT         DEFAULT NULL,
    status         ENUM('pending','in_progress','complete') NOT NULL DEFAULT 'pending',
    assigned_to    INT          DEFAULT NULL,
    payment_status ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid',
    stock_deducted TINYINT(1)  DEFAULT 0,
    deleted        TINYINT(1)  NOT NULL DEFAULT 0,
    deleted_at     DATETIME     DEFAULT NULL,
    deleted_by     VARCHAR(100) DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS job_parts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    job_id       INT            NOT NULL,
    part_name    VARCHAR(150)   NOT NULL,
    quantity     INT            NOT NULL DEFAULT 1,
    price        DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    inventory_id INT            DEFAULT NULL,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS job_time (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    job_id      INT           NOT NULL,
    hours       DECIMAL(6,2)  NOT NULL,
    description VARCHAR(255)  DEFAULT NULL,
    logged_by   INT           DEFAULT NULL,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS job_tasks (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    job_id  INT          NOT NULL,
    task    VARCHAR(255) NOT NULL,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS inventory (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    part_name           VARCHAR(200)  NOT NULL,
    part_number         VARCHAR(100)  DEFAULT NULL,
    quantity            INT           NOT NULL DEFAULT 0,
    cost_price          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    low_stock_threshold INT           NOT NULL DEFAULT 2,
    notes               TEXT          DEFAULT NULL,
    created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS settings (
    key_name VARCHAR(50) PRIMARY KEY,
    value    VARCHAR(100) NOT NULL
);

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
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted         TINYINT(1)   NOT NULL DEFAULT 0,
    deleted_at      DATETIME     DEFAULT NULL,
    deleted_by      VARCHAR(100) DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS quote_labour (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    quote_id    INT           NOT NULL,
    hours       DECIMAL(6,2)  NOT NULL,
    description VARCHAR(255)  NOT NULL,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS quote_parts (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    quote_id  INT           NOT NULL,
    part_name VARCHAR(150)  NOT NULL,
    quantity  INT           NOT NULL DEFAULT 1,
    price     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS quote_tasks (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    quote_id INT          NOT NULL,
    task     VARCHAR(255) NOT NULL,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
);


-- ────────────────────────────────────────────────────────────
--  SETTINGS
-- ────────────────────────────────────────────────────────────

INSERT INTO settings (key_name, value) VALUES
    ('hourly_rate', '55.00'),
    ('ff_discount',  '10.00')
ON DUPLICATE KEY UPDATE value = value;


-- ────────────────────────────────────────────────────────────
--  USERS
--  Passwords are bcrypt hashes.
--  admin    → password: demo1234
--  mechanic accounts → password: demo1234
-- ────────────────────────────────────────────────────────────

INSERT INTO users (username, password_hash, role, must_change_password) VALUES
('admin',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin',    0),
('james.w', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mechanic', 0),
('sarah.k', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mechanic', 0),
('tom.b',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mechanic', 0);


-- ────────────────────────────────────────────────────────────
--  INVENTORY  (30 common parts, a few at low stock)
-- ────────────────────────────────────────────────────────────

INSERT INTO inventory (part_name, part_number, quantity, cost_price, low_stock_threshold, notes) VALUES
('Oil Filter — Standard',        'OF-1042',   12,  4.50,  3, NULL),
('Oil Filter — Premium',         'OF-2200',    8,  7.20,  3, NULL),
('Air Filter',                   'AF-7731',    6,  9.80,  2, NULL),
('Cabin Filter',                 'CF-3390',    4, 11.50,  2, NULL),
('Brake Pads — Front (Set)',      'BP-F901',    9, 18.00,  3, 'Fits most Ford/Vauxhall'),
('Brake Pads — Rear (Set)',       'BP-R902',    7, 15.50,  3, NULL),
('Brake Discs — Front (Pair)',    'BD-F450',    4, 42.00,  2, NULL),
('Brake Discs — Rear (Pair)',     'BD-R451',    3, 38.00,  2, NULL),
('Spark Plugs (x4)',              'SP-NGK4',   14,  8.40,  4, 'NGK standard'),
('Spark Plugs — Iridium (x4)',    'SP-IRD4',    6, 22.00,  2, NULL),
('Engine Oil 5W-30 (5L)',         'EO-5W30',   20,  14.99, 5, 'Fully synthetic'),
('Engine Oil 10W-40 (5L)',        'EO-10W40',  11,  11.50, 4, NULL),
('Coolant Antifreeze (5L)',       'CL-AFX5',    8,  9.00,  2, NULL),
('Windscreen Washer Fluid (5L)',  'WW-FL5',    15,  2.80,  4, NULL),
('Timing Belt Kit',               'TB-KIT1',    2, 65.00,  2, 'Includes tensioner & idler'),
('Serpentine Belt',               'SB-7652',    5, 19.00,  2, NULL),
('Alternator Belt',               'AB-3310',    4, 14.00,  2, NULL),
('Wiper Blades — 24"',            'WB-24IN',    8,  6.50,  2, NULL),
('Wiper Blades — 21"',            'WB-21IN',    6,  5.80,  2, NULL),
('Battery 063 (60Ah)',            'BAT-063',    3, 72.00,  2, NULL),
('Battery 096 (70Ah)',            'BAT-096',    2, 89.00,  2, NULL),
('Wheel Bearing — Front',         'WB-FR12',    4, 28.00,  2, NULL),
('Wheel Bearing — Rear',          'WB-RR12',    3, 26.00,  2, NULL),
('CV Boot Kit',                   'CV-BK55',    5, 12.00,  2, NULL),
('Brake Fluid DOT4 (500ml)',      'BF-DOT4',   10,  4.20,  3, NULL),
('Power Steering Fluid (1L)',     'PS-FL1',     7,  5.50,  2, NULL),
('Exhaust Clamp 50mm',            'EX-CL50',    1,  3.80,  3, 'LOW — reorder needed'),
('Lambda Sensor — Front',         'LS-FR01',    1, 45.00,  2, 'LOW — reorder needed'),
('Gearbox Oil 75W-90 (1L)',       'GB-7590',    5, 12.00,  2, NULL),
('Clutch Kit (3-piece)',          'CK-3PC',     2, 95.00,  2, NULL);


-- ────────────────────────────────────────────────────────────
--  JOBS  (spread across last ~8 weeks for good analytics)
-- ────────────────────────────────────────────────────────────

INSERT INTO jobs (job_date, customer_name, vehicle, registration, job_type, description, status, assigned_to, payment_status, stock_deducted, deleted) VALUES
-- Completed & paid (older)
(DATE_SUB(CURDATE(), INTERVAL 54 DAY), 'Oliver Bennett',   'Ford Focus 1.6',        'AB12 CDE', 'Full Service',       'Annual full service including oil, filters, plugs.',        'complete', 2, 'paid',   1, 0),
(DATE_SUB(CURDATE(), INTERVAL 51 DAY), 'Priya Sharma',     'Vauxhall Astra 1.4',   'FG34 HIJ', 'Brake Service',      'Front brake pads and discs replaced.',                      'complete', 3, 'paid',   1, 0),
(DATE_SUB(CURDATE(), INTERVAL 49 DAY), 'Daniel Hughes',    'BMW 3 Series 2.0d',    'KL56 MNO', 'Diagnostics',        'EML on — diagnosed and cleared faulty MAF sensor.',          'complete', 2, 'paid',   0, 0),
(DATE_SUB(CURDATE(), INTERVAL 47 DAY), 'Emily Carter',     'Toyota Yaris 1.0',     'PQ78 RST', 'MOT Prep',           'Pre-MOT inspection and minor advisory work.',                'complete', 4, 'paid',   1, 0),
(DATE_SUB(CURDATE(), INTERVAL 44 DAY), 'Marcus Webb',      'Honda Civic 1.8',      'UV90 WXY', 'Full Service',       'Oil and filter change, air and cabin filters replaced.',      'complete', 3, 'paid',   1, 0),
(DATE_SUB(CURDATE(), INTERVAL 42 DAY), 'Sophie Allen',     'Nissan Micra 1.2',     'ZA11 BCD', 'Tyre Replacement',   'Two front tyres replaced, tracking checked.',                'complete', 2, 'paid',   0, 0),
(DATE_SUB(CURDATE(), INTERVAL 40 DAY), 'Liam Foster',      'Audi A3 2.0 TDI',      'EF22 GHI', 'Cambelt & Water Pump','Cambelt and water pump replacement at 90k miles.',          'complete', 4, 'paid',   1, 0),
(DATE_SUB(CURDATE(), INTERVAL 38 DAY), 'Hannah Brooks',    'Renault Clio 1.2',     'JK33 LMN', 'Clutch Replacement', 'Full clutch kit replaced, flywheel inspected.',              'complete', 2, 'paid',   1, 0),
(DATE_SUB(CURDATE(), INTERVAL 35 DAY), 'Noah Edwards',     'VW Golf 1.6 TDI',      'OP44 QRS', 'Full Service',       'Full service with DPF inspection.',                          'complete', 3, 'paid',   1, 0),
(DATE_SUB(CURDATE(), INTERVAL 33 DAY), 'Isla Patel',       'Peugeot 208 1.2',      'TU55 VWX', 'Battery Replacement', 'Battery failed — replaced with 063 unit.',                 'complete', 4, 'paid',   1, 0),
(DATE_SUB(CURDATE(), INTERVAL 31 DAY), 'Connor Walsh',     'Ford Mondeo 2.0 TDCi', 'YZ66 ABC', 'Exhaust Repair',     'Mid-section exhaust replaced, clamps fitted.',               'complete', 2, 'paid',   1, 0),
(DATE_SUB(CURDATE(), INTERVAL 29 DAY), 'Grace Mitchell',   'Mercedes A-Class 1.5', 'DE77 FGH', 'Diagnostics',        'Gearbox warning light — software update resolved.',          'complete', 3, 'paid',   0, 0),
(DATE_SUB(CURDATE(), INTERVAL 27 DAY), 'Ryan Thomas',      'Seat Leon 1.4 TSI',    'IJ88 KLM', 'Brake Service',      'Rear brake pads and discs replaced, fluid changed.',         'complete', 4, 'paid',   1, 0),
(DATE_SUB(CURDATE(), INTERVAL 25 DAY), 'Amelia Scott',     'Hyundai i20 1.2',      'NO99 PQR', 'Oil Change',         'Engine oil and filter change only.',                         'complete', 2, 'paid',   1, 0),
(DATE_SUB(CURDATE(), INTERVAL 23 DAY), 'Jack Robinson',    'Kia Sportage 1.7 CRDi','ST00 UVW', 'Full Service',       'Full service including gearbox oil check.',                  'complete', 3, 'paid',   1, 0),
(DATE_SUB(CURDATE(), INTERVAL 21 DAY), 'Charlotte Evans',  'Ford Fiesta 1.0 Eco',  'XY11 ZAB', 'Suspension',         'Front wishbone arm and ball joint replaced.',                'complete', 4, 'paid',   1, 0),
(DATE_SUB(CURDATE(), INTERVAL 19 DAY), 'Ben Clarke',       'Toyota Corolla 1.8 HV','CD22 EFG', 'MOT Prep',           'MOT prep — rear wiper blade, horn fuse fixed.',              'complete', 2, 'paid',   0, 0),
(DATE_SUB(CURDATE(), INTERVAL 17 DAY), 'Zara Khan',        'Vauxhall Corsa 1.4',   'HI33 JKL', 'Air Con Regas',      'Air conditioning regassed and leak-tested.',                 'complete', 3, 'paid',   0, 0),
(DATE_SUB(CURDATE(), INTERVAL 15 DAY), 'Ethan Price',      'Skoda Octavia 2.0 TDI','MN44 OPQ', 'Full Service',       'Full service + EGR valve cleaned.',                          'complete', 4, 'paid',   1, 0),
(DATE_SUB(CURDATE(), INTERVAL 13 DAY), 'Mia Turner',       'Ford Ka 1.2',          'RS55 TUV', 'Wiper Replacement',  'Front wipers and rear wiper blade replaced.',                'complete', 2, 'paid',   1, 0),
-- Completed but unpaid
(DATE_SUB(CURDATE(), INTERVAL 5 DAY),  'George Harris',    'BMW 5 Series 3.0d',    'WX66 YZA', 'Cambelt & Water Pump','Major cambelt service with water pump at 80k.',             'complete', 3, 'unpaid', 1, 0),
(DATE_SUB(CURDATE(), INTERVAL 3 DAY),  'Poppy Wilson',     'Mini Cooper S 2.0',    'BC77 DEF', 'Full Service',       'Full service including spark plugs.',                         'complete', 4, 'unpaid', 1, 0),
-- In progress
(DATE_SUB(CURDATE(), INTERVAL 2 DAY),  'Freddie Green',    'Renault Megane 1.5 dCi','GH88 IJK','Diagnostics',        'Intermittent misfire — running diagnostics.',                'in_progress', 2, 'unpaid', 0, 0),
(DATE_SUB(CURDATE(), INTERVAL 1 DAY),  'Chloe Martin',     'Ford Transit 2.2 TDCi','LM99 NOP', 'Brake Service',      'Full brake overhaul front and rear.',                        'in_progress', 3, 'unpaid', 0, 0),
(CURDATE(),                            'Harry Johnson',     'Volkswagen Polo 1.2',  'QR00 STU', 'Full Service',       'Annual service — booked in this morning.',                   'in_progress', 4, 'unpaid', 0, 0),
-- Pending (upcoming / just booked)
(DATE_ADD(CURDATE(), INTERVAL 1 DAY),  'Lily Thompson',    'Citroen C3 1.2 PureTech','VW11 XYZ','MOT Prep',          'Pre-MOT check — customer flagged rear light issue.',         'pending', 2, 'unpaid', 0, 0),
(DATE_ADD(CURDATE(), INTERVAL 2 DAY),  'Oscar Davies',     'Honda Jazz 1.3',       'AB22 CDE', 'Oil Change',         'Interim oil change.',                                        'pending', 3, 'unpaid', 0, 0),
(DATE_ADD(CURDATE(), INTERVAL 3 DAY),  'Scarlett Moore',   'Mazda CX-5 2.0',       'FG33 HIJ', 'Air Con Regas',      'Air con not cold — regas and check.',                        'pending', 4, 'unpaid', 0, 0),
(DATE_ADD(CURDATE(), INTERVAL 5 DAY),  'Archie Taylor',    'Audi A4 2.0 TDI',      'KL44 MNO', 'Full Service',       'Full service with four-wheel alignment.',                    'pending', 2, 'unpaid', 0, 0),
(DATE_ADD(CURDATE(), INTERVAL 7 DAY),  'Daisy White',      'Hyundai Tucson 1.6',   'PQ55 RST', 'Suspension',         'Knocking from front — suspected top mount.',                 'pending', 3, 'unpaid', 0, 0);


-- ────────────────────────────────────────────────────────────
--  JOB PARTS  (linked to completed & in-progress jobs)
-- ────────────────────────────────────────────────────────────

INSERT INTO job_parts (job_id, part_name, quantity, price, inventory_id) VALUES
-- Job 1 — Full Service
(1, 'Oil Filter — Standard',       1,  6.00,  1),
(1, 'Engine Oil 5W-30 (5L)',        1, 22.00, 11),
(1, 'Air Filter',                   1, 14.00,  3),
(1, 'Spark Plugs (x4)',             1, 16.00,  9),
-- Job 2 — Brake Service
(2, 'Brake Pads — Front (Set)',     1, 32.00,  5),
(2, 'Brake Discs — Front (Pair)',   1, 75.00,  7),
-- Job 4 — MOT Prep
(4, 'Wiper Blades — 21"',           2, 10.00, 19),
-- Job 5 — Full Service
(5, 'Oil Filter — Premium',         1,  9.00,  2),
(5, 'Engine Oil 5W-30 (5L)',        1, 22.00, 11),
(5, 'Air Filter',                   1, 14.00,  3),
(5, 'Cabin Filter',                 1, 18.00,  4),
-- Job 7 — Cambelt
(7, 'Timing Belt Kit',              1, 95.00, 15),
(7, 'Coolant Antifreeze (5L)',      1, 14.00, 13),
-- Job 8 — Clutch
(8, 'Clutch Kit (3-piece)',         1,140.00, 30),
-- Job 9 — Full Service
(9, 'Oil Filter — Standard',        1,  6.00,  1),
(9, 'Engine Oil 10W-40 (5L)',       1, 18.00, 12),
(9, 'Air Filter',                   1, 14.00,  3),
-- Job 10 — Battery
(10,'Battery 063 (60Ah)',           1,100.00, 20),
-- Job 11 — Exhaust
(11,'Exhaust Clamp 50mm',           2,  6.50, 27),
-- Job 13 — Brake Service
(13,'Brake Pads — Rear (Set)',      1, 28.00,  6),
(13,'Brake Discs — Rear (Pair)',    1, 65.00,  8),
(13,'Brake Fluid DOT4 (500ml)',     1,  7.00, 25),
-- Job 14 — Oil Change
(14,'Oil Filter — Standard',        1,  6.00,  1),
(14,'Engine Oil 5W-30 (5L)',        1, 22.00, 11),
-- Job 15 — Full Service
(15,'Oil Filter — Premium',         1,  9.00,  2),
(15,'Engine Oil 5W-30 (5L)',        1, 22.00, 11),
(15,'Gearbox Oil 75W-90 (1L)',      1, 18.00, 29),
-- Job 16 — Suspension
(16,'Wheel Bearing — Front',        1, 48.00, 22),
-- Job 20 — Wipers
(20,'Wiper Blades — 24"',           1,  9.00, 18),
(20,'Wiper Blades — 21"',           1,  8.00, 19),
-- Job 21 — Cambelt
(21,'Timing Belt Kit',              1, 95.00, 15),
(21,'Coolant Antifreeze (5L)',      1, 14.00, 13),
-- Job 22 — Full Service
(22,'Oil Filter — Premium',         1,  9.00,  2),
(22,'Engine Oil 5W-30 (5L)',        1, 22.00, 11),
(22,'Spark Plugs — Iridium (x4)',   1, 32.00, 10),
-- Job 24 — Brake Service (in progress)
(24,'Brake Pads — Front (Set)',     1, 32.00,  5),
(24,'Brake Pads — Rear (Set)',      1, 28.00,  6),
(24,'Brake Fluid DOT4 (500ml)',     1,  7.00, 25),
-- Job 25 — Full Service (today)
(25,'Oil Filter — Standard',        1,  6.00,  1),
(25,'Engine Oil 5W-30 (5L)',        1, 22.00, 11),
(25,'Air Filter',                   1, 14.00,  3);


-- ────────────────────────────────────────────────────────────
--  JOB TIME  (labour hours)
-- ────────────────────────────────────────────────────────────

INSERT INTO job_time (job_id, hours, description, logged_by) VALUES
(1,  2.0, 'Full service completed',           2),
(2,  1.5, 'Front brakes replaced',            3),
(3,  1.0, 'Diagnostics and MAF sensor reset', 2),
(4,  1.0, 'Pre-MOT inspection',               4),
(5,  2.0, 'Full service completed',           3),
(6,  0.5, 'Tyre swap and tracking',           2),
(7,  3.5, 'Cambelt and water pump',           4),
(8,  4.0, 'Clutch replacement',               2),
(9,  2.0, 'Full service with DPF check',      3),
(10, 0.5, 'Battery swap',                     4),
(11, 1.0, 'Exhaust mid-section replaced',     2),
(12, 0.5, 'Diagnostics — software update',    3),
(13, 1.5, 'Rear brakes and fluid',            4),
(14, 0.5, 'Oil and filter',                   2),
(15, 2.0, 'Full service',                     3),
(16, 2.0, 'Wishbone and ball joint',          4),
(17, 0.5, 'Wiper blade and horn fuse',        2),
(18, 0.5, 'Air con regas',                    3),
(19, 2.5, 'Full service and EGR clean',       4),
(20, 0.5, 'Wiper blades all round',           2),
(21, 3.5, 'Cambelt service',                  3),
(22, 2.0, 'Full service',                     4),
(23, 1.0, 'Initial diagnostics',              2),
(24, 1.5, 'Brake work so far',                3),
(25, 0.5, 'Oil drain and filter',             4);


-- ────────────────────────────────────────────────────────────
--  JOB TASKS  (checklist items on selected jobs)
-- ────────────────────────────────────────────────────────────

INSERT INTO job_tasks (job_id, task) VALUES
(1, 'Check tyre pressures and condition'),
(1, 'Top up screenwash'),
(1, 'Inspect brake pads — advisory'),
(5, 'Check tyre pressures and condition'),
(5, 'Test battery voltage'),
(7, 'Inspect water pump for leaks post-fit'),
(7, 'Reset service interval'),
(9, 'Check DPF regeneration cycle'),
(9, 'Inspect air intake for cracks'),
(15,'Check gearbox oil level'),
(19,'Inspect EGR valve operation post-clean'),
(21,'Pressure-test cooling system'),
(25,'Check all fluid levels'),
(25,'Inspect brake pad thickness');


-- ────────────────────────────────────────────────────────────
--  QUOTES
-- ────────────────────────────────────────────────────────────

INSERT INTO quotes (customer_name, customer_phone, customer_email, contact_source, vehicle, registration, job_type, description, status, total_amount, created_by, created_at) VALUES
('James Holloway',  '07700 900111', 'james.holloway@example.com',  'Phone',   'Ford Focus 2.0 TDCi',   'UV12 WXY', 'Clutch Replacement', 'Clutch slipping, wants full kit replacement.',          'pending',  420.00, 'admin', DATE_SUB(NOW(), INTERVAL 3 DAY)),
('Nina Kapoor',     '07700 900222', 'nina.kapoor@example.com',     'Walk-in', 'Vauxhall Corsa 1.2',    'ZA34 BCD', 'Full Service',       'First service since purchase, 12k miles.',              'pending',  185.00, 'admin', DATE_SUB(NOW(), INTERVAL 2 DAY)),
('Tom Griffiths',   '07700 900333', NULL,                          'Website', 'Audi Q5 2.0 TDI',       'EF56 GHI', 'Diagnostics',        'DSG warning light, occasional judder on take-off.',     'pending',  120.00, 'admin', DATE_SUB(NOW(), INTERVAL 1 DAY)),
('Rachel Simmons',  '07700 900444', 'rachel.s@example.com',        'Referral','BMW 1 Series 2.0d',     'JK78 LMN', 'Brake Service',      'Full brake overhaul all round.',                        'accepted', 310.00, 'admin', DATE_SUB(NOW(), INTERVAL 7 DAY)),
('Pete Gallagher',  '07700 900555', NULL,                          'Phone',   'Mercedes Sprinter 2.1', 'OP90 QRS', 'Cambelt',            'Cambelt due at 100k — timing kit and water pump.',      'accepted', 480.00, 'admin', DATE_SUB(NOW(), INTERVAL 10 DAY)),
('Yusra Ahmed',     '07700 900666', 'yusra.ahmed@example.com',     'Website', 'Toyota RAV4 2.5 HV',   'TU01 VWX', 'Air Con Regas',      'Air con blowing warm only.',                            'declined', 85.00,  'admin', DATE_SUB(NOW(), INTERVAL 14 DAY));


-- ────────────────────────────────────────────────────────────
--  QUOTE LABOUR
-- ────────────────────────────────────────────────────────────

INSERT INTO quote_labour (quote_id, hours, description) VALUES
(1, 4.0, 'Clutch removal, replacement and refitting'),
(2, 2.0, 'Full service labour'),
(3, 1.0, 'Diagnostic scan and road test'),
(4, 2.5, 'Brake pads, discs and fluid all round'),
(5, 3.5, 'Cambelt, water pump, coolant flush'),
(6, 0.5, 'Air con regas and leak test');


-- ────────────────────────────────────────────────────────────
--  QUOTE PARTS
-- ────────────────────────────────────────────────────────────

INSERT INTO quote_parts (quote_id, part_name, quantity, price) VALUES
(1, 'Clutch Kit (3-piece)',          1, 140.00),
(2, 'Engine Oil 5W-30 (5L)',         1,  22.00),
(2, 'Oil Filter — Premium',          1,   9.00),
(2, 'Air Filter',                    1,  14.00),
(4, 'Brake Pads — Front (Set)',      1,  32.00),
(4, 'Brake Pads — Rear (Set)',       1,  28.00),
(4, 'Brake Discs — Front (Pair)',    1,  75.00),
(4, 'Brake Discs — Rear (Pair)',     1,  65.00),
(4, 'Brake Fluid DOT4 (500ml)',      1,   7.00),
(5, 'Timing Belt Kit',               1,  95.00),
(5, 'Coolant Antifreeze (5L)',        1,  14.00);


-- ────────────────────────────────────────────────────────────
--  QUOTE TASKS
-- ────────────────────────────────────────────────────────────

INSERT INTO quote_tasks (quote_id, task) VALUES
(1, 'Road test after fitment'),
(1, 'Check flywheel for hot spots'),
(2, 'Check tyre pressures'),
(4, 'Check handbrake travel after rear brakes'),
(5, 'Pressure-test cooling system post-service'),
(5, 'Reset timing service interval');


-- ────────────────────────────────────────────────────────────
--  Done!
--  Login credentials for demo:
--    admin    / demo1234  (full access)
--    james.w  / demo1234  (mechanic)
--    sarah.k  / demo1234  (mechanic)
--    tom.b    / demo1234  (mechanic)
-- ────────────────────────────────────────────────────────────
