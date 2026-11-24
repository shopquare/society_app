# Codebase Analysis

## Entry Points & Routing
- **`index.php`**: Initializes `session_start()`, loads `config/db.php`, `includes/auth_middleware.php`, `includes/csrf.php`, and includes `routes.php`.
- **`.htaccess`**: Rewrites non-file/non-directory requests under `/society_app/` to `index.php`. Physical files (all `api/*.php`, `modules/**/*.php`, `test*.php`, `uploads/**`) bypass `index.php` completely.
- **`routes.php`**: Parses `$_SERVER['REQUEST_URI']` via `str_replace('/society_app', '', $request_uri)` and `strtok($path, '?')`. Only 3 of 11 routes enforce middleware inside `routes.php` (`/resident/dashboard`, `/admin/dashboard`, `/guard/dashboard`), leaving 7 admin/resident routes dependent on view-level session checks.

## File-by-File Analysis

### Core Configuration & Helpers
- **`config/db.php`**: Sets `Asia/Kolkata` timezone in PHP and MySQL (`+05:30`), instantiates global PDO `$conn` with `ERRMODE_EXCEPTION` and `FETCH_ASSOC`. **Weakness**: Contains hardcoded database credentials and outputs `$exception->getMessage()` on failure.
- **`config/db.sql`**: Defines only 4 tables (`sms_roles`, `sms_users`, `sms_user_profiles`, `sms_user_units`), omitting 7+ tables queried by the application.
- **`includes/auth_middleware.php`**: Defines `requireLogin()`, `requireAdmin()`, and `hasRole()`. **Weakness**: Redirects to nonexistent `/society_app/login.html` and `/society_app/dashboard.php`; `hasRole()` uses strict `===`, which can fail if `$_SESSION['role_id']` is a string vs integer; omits `logoutUser()` called by `modules/auth/logout.php`.
- **`includes/csrf.php`**: Solid `random_bytes(32)` + `hash_equals()` implementation (`generateCsrfToken()`, `csrfField()`, `verifyCsrfToken()`, `requireValidCsrfToken()`). **Weakness**: Token is never rotated after login or per-request, and several APIs ignore it.
- **`includes/header.php` & `includes/footer.php`**: Legacy shared layout templates referencing nonexistent `/society_app/pwa/sw.js` and `/society_app/assets/images/icon-192.png`. Not used by active modules.

### Authentication & Registration
- **`modules/auth/login.php` & `api/login.php`**: Active login portal. `api/login.php` uses PDO prepared statements and `password_verify()`, checks `'pending'`/`'active'` status, and populates session variables. **Weaknesses**: No CSRF verification despite form sending `csrf_token`; no `session_regenerate_id(true)`; wildcard CORS; redirects role 4 (Guards) to `/resident/dashboard`.
- **`modules/auth/register.php` & `api/register.php`**: Active 3-step registration with society invite-code verification (`api/verify_society.php`). Uses DB transaction across `sms_users`, `sms_user_profiles`, `sms_user_units`. **Weaknesses**: Ignores CSRF token; lacks password length/email validation; `lastName` and `phone` aren't checked with `!empty()` on server.
- **`modules/auth/logout.php`**: Broken wrapper calling undefined `logoutUser()`. Active logout links instead hit `api/logout.php`, which properly clears session and cookie.

### Admin Modules & APIs
- **`modules/admin/navbar.php`**: Shared responsive top navbar + mobile menu toggle.
- **`modules/admin/dashboard.php` + `api/admin_get_pending.php` + `api/admin_update_status.php`**: Pending resident queue and Approve/Reject actions. Clean CSRF enforcement on update.
- **`modules/admin/users.php` + `api/admin_get_users.php`**: Processed resident directory. Uses `INNER JOIN sms_user_units` without filtering `is_current_occupant = 1`.
- **`modules/admin/guards.php` + `api/admin_create_guard.php` + `api/admin_get_guards.php`**: Guard account creation and listing. **Weaknesses**: `ini_set('display_errors', 1)` in view; hardcoded `'guard123'` default password.
- **`modules/admin/financials.php` + `api/admin_create_invoice.php` + `api/admin_get_invoices.php`**: Single/bulk invoice creation (transactional), automatic `Unpaid` → `Overdue` update on read, client-side status filter and CSV export. **Weakness**: CSV export does not sanitize formulas (CSV injection) or escape embedded double quotes.
- **`modules/admin/notices.php` + `api/admin_create_notice.php` + `api/get_notices.php`**: Notice publishing and feed.
- **`modules/admin/complaints.php` + `api/admin_get_complaints.php` + `api/admin_update_complaint.php`**: 3-column Kanban helpdesk board with strict status allowlist (`Open`, `In Progress`, `Resolved`).
- **`modules/admin/settings.php` + `api/admin_get_society.php` + `api/admin_update_society.php` + `api/admin_reset_invite.php`**: Society profile editor and invite-code regenerator.
- **`modules/admin/residents.php`**: Broken/dead legacy MySQLi page referencing nonexistent schema and APIs.

### Resident Modules & APIs
- **`modules/resident/dashboard.php`**: Main resident portal (660 lines). Combines server-side PDO queries (invoices, passes, profile) with client-side polling (`checkPendingVisitors`), Web Audio chime, Web Push subscription (`api/save_push_subscription.php`), guest PIN generation (`api/resident_generate_pin.php`), complaint modal (`api/resident_create_complaint.php`, `api/resident_get_complaints.php`), and a **hardcoded static Notice Board**.
- **`modules/resident/financials.php` + `api/resident_get_invoices.php` + `api/resident_pay_invoice.php`**: Resident billing history and simulated "Pay Now" button. Unlinked from the resident dashboard.
- **`modules/resident/login.php`, `register.php`, `forget-password.php`**: Legacy static prototypes with simulated `setTimeout` flows.

### Guard Modules & APIs
- **`modules/guard/dashboard.php`**: Gatekeeper Terminal with WebRTC camera capture, datalist unit search, fast-track PIN verification (`api/guard_verify_pin.php`), visitor log table with >45-min overstay alerts, and exit marking (`api/guard_visitor_exit.php`).
- **`api/guard_log_visitor.php`**: Decodes Base64 PNG to `uploads/visitors/`, inserts visitor, and sends VAPID Web Push via `minishlink/web-push`. **Weaknesses**: No `role_id == 4` check, no CSRF check, drops `phone_number`, hardcodes VAPID private key and `https://shopquare.in` URL.

### PWA & Service Worker
- **`sw.js`**: Handles `push` and `notificationclick` (`approve`/`deny` calling `api/update_visitor_status.php`).
- **`manifest.json` & `pwa/manifest.json`**: Duplicate, inconsistent manifests pointing to missing/mismatched icons.

## Maintainability Assessment
- **Coupling & Code Duplication**: High duplication across all `modules/` (Tailwind CDN + inline config repeated in almost every view) and `api/` (session check + JSON headers + PDO error handling repeated in ~25 files).
- **Inconsistent Conventions**: `api/guard_log_visitor.php` and `api/resident_*.php` mix `status: 'success'` vs `success: true`, PDO vs `$pdo` fallback, `htmlspecialchars()` at write time vs unescaped `innerHTML` on read.
- **Dead/Orphaned Code**: 5+ legacy files (`modules/admin/residents.php`, `modules/auth/logout.php`, `modules/resident/login.php`, `register.php`, `forget-password.php`, `includes/header.php`, `includes/footer.php`, `pwa/manifest.json`) create confusion and security surface.
