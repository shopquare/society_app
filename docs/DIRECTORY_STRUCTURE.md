# Directory & File Structure

## Root Directory (`c:\xampp\htdocs\society_app\`)

| File / Folder | Status | Responsibility |
|---|---|---|
| `.htaccess` | Active | Apache `mod_rewrite` rules forwarding non-file/non-dir URLs under `/society_app/` to `index.php`. |
| `.gitignore` | Added in setup | Excludes `.env*`, keys, `config/db.php`, `vendor/`, `uploads/`, logs, cache, IDE/OS files. |
| `index.php` | Active | Front controller: calls `session_start()`, loads `db.php`, `auth_middleware.php`, `csrf.php`, `routes.php`. |
| `routes.php` | Active | Maps clean routes (`/login`, `/register`, `/resident/*`, `/admin/*`, `/guard/dashboard`) to `modules/`. |
| `composer.json` / `composer.lock` | Active | Declares `minishlink/web-push` and `php-http/guzzle7-adapter` (21 locked packages). |
| `manifest.json` | Active (flawed) | Root PWA manifest linked by `modules/auth/login.php` and `modules/resident/dashboard.php`. |
| `sw.js` | Active | Service Worker handling `push` events and `notificationclick` Approve/Deny actions. |
| `test.php` | Diagnostic | Verifies Composer autoloading for `Minishlink\WebPush\WebPush`. Should be removed in production. |
| `test_push_trigger.php` | Diagnostic (**Risk**) | Unauthenticated test script triggering live Web Push to `user_id` 1 with hardcoded VAPID keys. |

## Core Folders

### `config/`
- `config/db.php` *(Active, ignored in Git)* — Sets `Asia/Kolkata` timezone (`+05:30` MySQL) and creates global PDO `$conn` with hardcoded credentials.
- `config/db.sql` *(Incomplete)* — Defines only 4 tables (`sms_roles`, `sms_users`, `sms_user_profiles`, `sms_user_units`), missing `society_id` and 7 other tables used by the app.

### `includes/`
- `includes/auth_middleware.php` *(Partially active)* — Defines `requireLogin()`, `requireAdmin()`, `hasRole()`. Contains broken redirect paths (`/society_app/login.html`, `/society_app/dashboard.php`) and omits `logoutUser()`.
- `includes/csrf.php` *(Active)* — Session CSRF token generator (`generateCsrfToken`, `csrfField`, `verifyCsrfToken`, `requireValidCsrfToken`).
- `includes/header.php` & `includes/footer.php` *(Legacy / Unused)* — Reference nonexistent `/society_app/pwa/sw.js` and `/society_app/assets/images/icon-192.png`; only included by broken `modules/admin/residents.php`.

### `assets/`
- `assets/css/style.css` — Fade animations and custom radio indicator CSS (used only by admin views).
- `assets/js/tailwind-config.js` — Shared Tailwind `brand` color & font configuration (used by admin views).
- `assets/js/app.js` *(Unused)* — Defines `formatDisplayDate()` and console log; only loaded by unused `includes/footer.php`.
- `assets/icon-192.svg`, `assets/icon-512.svg` — SVG PWA icons (mismatched `image/png` MIME type in `manifest.json`).
- `assets/images/` — **Empty** directory (referenced by `pwa/manifest.json` and `includes/header.php`).

### `modules/` (UI Views & Page Controllers)
- **`modules/auth/`**:
  - `login.php` *(Active)* — Portal login UI & JS fetch to `api/login.php`.
  - `register.php` *(Active)* — 3-step society registration UI & JS fetch to `api/verify_society.php` + `api/register.php`.
  - `logout.php` *(Broken / Unused)* — Calls undefined `logoutUser()`.
- **`modules/admin/`**:
  - `navbar.php` *(Active)* — Top navigation + mobile hamburger menu for all active admin pages.
  - `dashboard.php` *(Active)* — Pending resident approval queue.
  - `users.php` *(Active)* — Processed resident directory.
  - `notices.php` *(Active)* — Publish notices & announcement feed.
  - `financials.php` *(Active)* — Single/bulk bill generation, status filter, CSV export.
  - `guards.php` *(Active)* — Provision & list security guards.
  - `complaints.php` *(Active)* — 3-column Kanban helpdesk board.
  - `settings.php` *(Active)* — Edit society profile & regenerate invite code.
  - `residents.php` *(Broken Legacy / Dead Code)* — Uses MySQLi methods, undefined helpers, and nonexistent `users` table / `api/add_user.php`.
- **`modules/resident/`**:
  - `dashboard.php` *(Active)* — Resident portal: gate alert polling/chime, Web Push opt-in, guest PIN generator, invoices table, complaint modal/tickets, and static notice placeholder.
  - `financials.php` *(Active but Unlinked)* — Resident billing history & "Pay Now" UI (no link from `resident/dashboard.php`).
  - `login.php`, `register.php`, `forget-password.php` *(Legacy Prototypes / Unused)* — Hardcoded mock JS pages with `setTimeout` and broken `.html` links.
- **`modules/guard/`**:
  - `dashboard.php` *(Active)* — Gatekeeper Terminal: fast-track PIN verification, WebRTC camera photo capture, visitor entry form, today's visitor log with >45m overstay alerts, and Mark Exit.

### `api/` (30 Procedural Backend Endpoints)
- **Auth & Society**: `login.php`, `logout.php`, `register.php`, `verify_society.php`
- **Admin**: `admin_get_pending.php`, `admin_get_users.php`, `admin_update_status.php`, `admin_get_guards.php`, `admin_create_guard.php`, `admin_create_notice.php`, `admin_get_invoices.php`, `admin_create_invoice.php`, `admin_get_complaints.php`, `admin_update_complaint.php`, `admin_get_society.php`, `admin_update_society.php`, `admin_reset_invite.php`
- **Shared**: `get_notices.php`
- **Resident**: `resident_get_invoices.php`, `resident_pay_invoice.php`, `resident_create_complaint.php`, `resident_get_complaints.php`, `resident_generate_pin.php`, `resident_get_pending_visitors.php`, `resident_handle_visitor_approval.php`, `save_push_subscription.php`, `update_visitor_status.php` *(unauthenticated)*
- **Guard**: `guard_get_visitors.php`, `guard_log_visitor.php`, `guard_verify_pin.php`, `guard_visitor_exit.php`

### `pwa/`, `uploads/`, `vendor/`, `docs/`
- `pwa/manifest.json` *(Legacy / Unused)* — Alternate manifest pointing to missing `assets/images/icon-*.png`.
- `uploads/visitors/` — Contains 44 PNG visitor photos (~32.8 MB), publicly accessible over HTTP.
- `vendor/` — Composer libraries (gitignored).
- `docs/` — Technical documentation created during this audit.
