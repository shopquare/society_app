# Changelog

## [CI/CD & Root-Domain Portability] — 2026-11-24
### Added
- `.github/workflows/ci-cd.yml`: GitHub Actions CI (`php -l`, `composer validate/install/audit`, `tests/run_tests.php`, `test.php`) and CD deployment to InfinityFree FTP (`https://society.infy.click/`) with secret-injected `.env` and `vendor/` production build.
- `docs/CI_CD_GUIDE.md`: Setup guide for GitHub Secrets/Variables and InfinityFree hosting.

### Changed
- Made `.htaccess`, `manifest.json`, `sw.js`, `assets/js/app.js` (`appPath()`), and all `modules/` base-path portable for both `/society_app` (XAMPP) and `/` (`https://society.infy.click/`).
- Configured local Git remote `origin` to `https://github.com/shopquare/society_app.git`.
- Updated `tests/run_tests.php` to verify both subpath and root `getRoleDashboardPath()` behavior (19 assertions).

## [Hardening & Bug-Fix Release] — 2026-11-24
### Added
- `config/env.php`, `.env.example`, and gitignored `.env` for externalized DB, VAPID, and `APP_KEY` configuration.
- `includes/api_bootstrap.php` providing unified JSON headers, HTTP method checks, role authorization, error logging, fixed-window rate limiting, and HMAC-SHA256 Service Worker visitor action tokens.
- `api/get_visitor_photo.php` authenticated society-scoped photo streaming endpoint.
- `robots.txt` disallowing search indexing of private portal and internal routes.
- `tests/run_tests.php` non-destructive unit & static security invariant suite (17/17 passing).

### Changed
- `config/db.php` now loads credentials from `.env` and logs errors without exposing PDO exception messages.
- `config/db.sql` expanded to define all 11 tables, role seeds, foreign keys, and composite indexes.
- `.htaccess` blocks direct HTTP access to `config/`, `includes/`, `modules/`, `docs/`, `vendor/`, `tests/`, `uploads/`, `.env*`, and `composer.*`, and adds security headers.
- `routes.php` normalizes trailing slashes and enforces role middleware across all protected admin, resident, and guard routes.
- `includes/auth_middleware.php` & `includes/csrf.php` configure `HttpOnly`/`SameSite=Lax` session cookies, fix redirect targets (`/login`, role dashboards), handle int/string `role_id` uniformly, add `rotateCsrfToken()`, and implement `logoutUser()`.
- All `api/*.php` endpoints migrated to `api_bootstrap.php` with role checks, CSRF verification, input validation, `session_regenerate_id(true)` on login, random temporary guard passwords, sargable date queries, and JPEG photo compression/validation in `guard_log_visitor.php`.
- `api/update_visitor_status.php` & `sw.js` secured with HMAC action tokens / session auth and aligned on `'Entered'` / `'Denied'` visitor status values.
- All active views in `modules/auth/`, `modules/admin/`, `modules/guard/`, and `modules/resident/` updated to escape dynamic DOM output (`escapeHtml()`), use CSRF-protected POST logout forms, fix Guard login routing, fix `sms_societies.society_name` lookup, wire Resident Notice Board to `api/get_notices.php`, link `/resident/financials`, support real-time Guard visitor polling & Fast-Track PIN auto-entry, guard `Notification` API usage, and add non-blocking `showToast()` feedback.
- `manifest.json` updated to `image/svg+xml` icon types and `/society_app/login` start URL.

### Removed
- Removed broken/legacy/exposed files: `test_push_trigger.php`, `modules/admin/residents.php`, `modules/resident/login.php`, `modules/resident/register.php`, `modules/resident/forget-password.php`, `includes/header.php`, `includes/footer.php`, and `pwa/manifest.json`.

## [Baseline Audit] — 2026-11-24
### Added
- Initialized local Git repository (`.git/`) and `.gitignore`.
- Created technical documentation suite under `docs/`.
