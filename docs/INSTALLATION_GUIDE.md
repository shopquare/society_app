# Installation Guide

## Prerequisites
- **Web Server**: Apache 2.4+ with `mod_rewrite` enabled (e.g., XAMPP on Windows).
- **PHP**: PHP 8.2+ with `pdo_mysql`, `openssl`, `curl`, `mbstring`, and `json` extensions enabled.
- **Database**: MySQL 8.0+ or MariaDB 10.4+.
- **Dependency Manager**: Composer 2.x.

## Local Setup (XAMPP Example)

1. **Place Project in Webroot**
   The current `.htaccess` and `routes.php` expect the application to reside at `/society_app/` (e.g., `c:\xampp\htdocs\society_app`).

2. **Install PHP Dependencies**
   ```bash
   composer install
   ```
   This installs `minishlink/web-push` (`^11.0`) and `php-http/guzzle7-adapter` (`^1.1`) into `vendor/`.

3. **Verify Autoloading**
   ```bash
   php test.php
   ```
   Expected output: `Success: minishlink/web-push is installed and loaded properly!`

4. **Database Setup**
   - Create a MySQL database (`utf8mb4_unicode_ci`).
   - Import `config/db.sql` for the initial 4 tables (`sms_roles`, `sms_users`, `sms_user_profiles`, `sms_user_units`).
   - **Important**: `config/db.sql` is incomplete. You must also provision the remaining 7 tables and columns documented in `docs/DATABASE_DOCUMENTATION.md` (`sms_societies`, `sms_notices`, `sms_invoices`, `sms_complaints`, `sms_visitors`, `sms_visitor_passes`, `sms_user_push_subscriptions`, plus `society_id` on `sms_users` and `sms_user_units`) from your live DB export.

5. **Configure Database Connection**
   Update `config/db.php` with your local MySQL host, username, password, and database name (currently ignored in `.gitignore` so local/live credentials are not committed).

6. **Create Uploads Directory**
   Ensure `uploads/visitors/` exists and is writable by the Apache/PHP process.

7. **Start Apache & MySQL**
   Open `http://localhost/society_app/login` in your browser.
