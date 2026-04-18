# Garage Management System

A full-stack garage management web application built with PHP and MySQL. Designed to handle the day-to-day operations of a small-to-medium automotive workshop — from booking jobs and tracking labour, to quoting customers and managing parts inventory.

Built as a solo project to demonstrate full-stack development skills including secure authentication, relational database design, and a clean responsive UI.

---

## Features

- **Dashboard** — Daily job overview, upcoming bookings, revenue summary, and low stock alerts
- **Job Management** — Create, edit, assign, and track jobs through pending → in progress → complete
- **Labour & Parts Tracking** — Log time against jobs and attach parts with quantities and prices
- **Invoicing** — Auto-generated invoices per job with payment status tracking
- **Quoting System** — Build detailed quotes with labour, parts, and tasks; convert accepted quotes directly into jobs
- **Inventory** — Stock management with low-stock threshold warnings and automatic deduction when jobs are completed
- **Customer History** — View all jobs and quotes per customer
- **Analytics** — Revenue charts, busiest days, top job types, and mechanic productivity
- **Calendar** — Monthly view of all booked jobs
- **User Management** — Admin can create mechanic accounts, reset passwords, and manage roles
- **Soft Delete** — Deleted jobs and quotes are archived and restorable rather than permanently removed
- **Search** — Global search across jobs, quotes, customers, and registrations

---

## Tech Stack

| Layer     | Technology                        |
|-----------|-----------------------------------|
| Backend   | PHP 8 (procedural)                |
| Database  | MySQL 8                           |
| Frontend  | HTML5, CSS3, Bootstrap 5.3        |
| Fonts     | Google Fonts (Barlow, DM Mono)    |
| Icons     | Bootstrap Icons                   |
| Hosting   | Designed for shared hosting (e.g. InfinityFree, cPanel) |

---

## Security

- Passwords hashed with `password_hash()` (bcrypt)
- Prepared statements / parameterised queries throughout — no raw user input in SQL
- CSRF tokens on all POST forms
- Session hardening — `HttpOnly`, `Secure`, `SameSite=Strict` cookie flags
- Security headers on every page — `X-Frame-Options`, `X-Content-Type-Options`, `Content-Security-Policy`
- Rate-limited login — account temporarily locked after repeated failed attempts
- Role-based access — mechanics cannot access admin-only pages

---

## Setup

### 1. Database

Run the setup SQL files in this order in phpMyAdmin (or MySQL CLI):

```
inventory_setup.sql
quotes_setup.sql
mechanic_assignment_setup.sql
stock_reduction_setup.sql
add_payment_status.sql
security_setup.sql
setup_users.sql
```

For a demo with pre-populated data, run `demo_data.sql` instead — it creates all tables and fills them with realistic dummy data.

> **Note:** `demo_data.sql` is dummy data only. All names, registrations, phone numbers, and email addresses are entirely fictional and do not correspond to real individuals.

### 2. Configuration

Edit `htdocs/PHP/config.php` and replace the placeholder database credentials:

```php
$host = "your_db_host";
$db   = "your_db_name";
$user = "your_db_user";
$pass = "your_db_password";
```

> **Recommendation:** In a production environment, store credentials in environment variables or a config file outside the web root rather than hardcoding them.

### 3. Deploy

Upload the contents of `htdocs/` to your web server's public directory. The `.htaccess` files handle routing and directory protection.

---

## Demo Login Credentials

These are only valid if you ran `demo_data.sql`.

| Username  | Password    | Role      |
|-----------|-------------|-----------|
| `admin`   | `demo1234`  | Admin     |
| `james.w` | `demo1234`  | Mechanic  |
| `sarah.k` | `demo1234`  | Mechanic  |
| `tom.b`   | `demo1234`  | Mechanic  |

---

## Configuration Variables

These settings are stored in the `settings` database table and can be changed via the **Settings** page (admin only).

| Key           | Default | Description |
|---------------|---------|-------------|
| `hourly_rate` | 55.00   | The labour charge rate in £ per hour. Applied to all job time logs when calculating labour cost on invoices and analytics. |
| `ff_discount` | 10.00   | **Friends & Family discount** — percentage discount applied when selected on a quote or invoice. Stored separately so it can be adjusted without touching code. |

---

## Project Structure

```
htdocs/
├── index.php                  # Entry point — redirects to login or dashboard
├── CSS/
│   └── style.css              # Global styles
├── PHP/
│   ├── config.php             # DB connection, session setup, security headers, CSRF helpers
│   ├── csrf.php               # CSRF token generation and verification
│   ├── navbar.php             # Shared navigation bar (included on every page)
│   ├── login.php              # Login page with rate limiting
│   ├── logout.php             # Session teardown
│   ├── dashboard.php          # Main dashboard
│   ├── add_job.php            # Create a new job
│   ├── edit_job.php           # Edit an existing job
│   ├── view_job.php           # View full job detail — parts, labour, tasks, invoice link
│   ├── delete_job.php         # Soft-delete a job
│   ├── deleted_jobs.php       # Archive of soft-deleted jobs with restore option
│   ├── restore_job.php        # Restore a soft-deleted job
│   ├── add_part.php           # Add a part to a job
│   ├── add_time.php           # Log labour hours against a job
│   ├── add_task.php           # Add a checklist task to a job
│   ├── deduct_stock.php       # Deduct inventory stock when job is completed
│   ├── invoice.php            # Printable invoice for a job
│   ├── add_quote.php          # Create a new customer quote
│   ├── view_quote.php         # View full quote detail
│   ├── print_quote.php        # Printable quote document
│   ├── add_quote_labour.php   # Add labour line to a quote
│   ├── add_quote_part.php     # Add a parts line to a quote
│   ├── add_quote_task.php     # Add a task to a quote
│   ├── quotes.php             # Quotes list with status filtering
│   ├── deleted_quotes.php     # Archived quotes
│   ├── customer.php           # Customer history — all jobs and quotes by name/reg
│   ├── inventory.php          # Inventory list with low-stock highlighting
│   ├── add_inventory.php      # Add a new part to inventory
│   ├── edit_inventory.php     # Edit an inventory item
│   ├── adjust_inventory.php   # Manually adjust stock quantity
│   ├── analytics.php          # Revenue, job type, and mechanic analytics charts
│   ├── calendar.php           # Monthly job calendar
│   ├── search.php             # Global search
│   ├── settings.php           # Admin settings (hourly rate, FF discount)
│   ├── manage_users.php       # Admin — create, edit, delete user accounts
│   ├── change_password.php    # Forced password change on first login
│   ├── get_jobs.php           # AJAX endpoint — returns jobs for calendar
│   ├── get_rate.php           # AJAX endpoint — returns current hourly rate
│   └── form_style.php         # Shared form component styles
├── *.sql                      # Database setup scripts (run once)
└── demo_data.sql              # Demo data for portfolio / testing purposes
```

---

## GDPR & Data Protection

This system collects and stores personal data including customer names, phone numbers, email addresses, and vehicle registrations. Whether it is GDPR compliant **depends entirely on how and where it is deployed.**

**If used to store real customer data, the operator is responsible for:**

- Hosting on a server located within the UK or EEA, or ensuring adequate transfer safeguards if hosted outside (e.g. US-based shared hosting may not meet this requirement without additional measures)
- Informing customers what data is collected and why (a privacy notice)
- Not retaining data longer than necessary
- Ensuring the database is not publicly accessible and is regularly backed up securely
- Providing customers the ability to request access to or deletion of their data

**This application does not include:**
- A privacy notice or consent mechanism
- Data retention/deletion tooling
- An audit log of data access
- Encryption of data at rest

It is provided as a portfolio demonstration only. **It should not be used in a production environment to handle real customer data without a proper compliance review by someone qualified to advise on UK GDPR obligations.**

---

## Known Limitations

- Single-currency (GBP) — no internationalisation
- No email/SMS notifications for customers
- No online booking portal — internal workshop tool only
- Designed for shared hosting; would benefit from environment variable support on a VPS

---

## Motivation

Built to simulate a real-world internal system for a small garage, focusing on practical workflows rather than theoretical features.