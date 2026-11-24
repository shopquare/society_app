# Security Audit

> [!IMPORTANT]
> **Secret Handling Notice**: All discovered credentials and private keys are referenced strictly by file path, line number, and variable/constant name. Actual secret values are omitted from this report.

## Summary of Findings by Severity
- **Critical**: 2
- **High**: 6
- **Medium**: 5
- **Low / Informational**: 3

---

## Critical Findings

### SEC-01 — Hardcoded Database Credentials & VAPID Private Key in Source Files
- **Severity**: Critical
- **Location**:
  - `config/db.php` (lines 8–11): `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`
  - `api/guard_log_visitor.php` (lines 83–89): `VAPID` `subject`, `publicKey`, `privateKey`
  - `test_push_trigger.php` (lines 31–37): `VAPID` `subject`, `publicKey`, `privateKey`
- **Problem**: Live database credentials and the Web Push VAPID private key are hardcoded directly in PHP source files.
- **Why It Matters**: Anyone gaining read access to the source tree or a future Git commit obtains full database access and can forge push notifications to subscribed residents.
- **Exploitation**: Source disclosure, backup exposure, or committing `api/guard_log_visitor.php` / `test_push_trigger.php` / `config/db.php` to a repository exposes credentials immediately.
- **Recommended Fix**: Move all secrets to environment variables (`.env` loaded outside webroot or via `vlucas/phpdotenv`), rotate the database password and VAPID keypair, and delete `test_push_trigger.php` from production.
- **Requires Code Changes**: Yes.

### SEC-02 — Unauthenticated Visitor Status Update Endpoint (IDOR + Broken Access Control)
- **Severity**: Critical
- **Location**: `api/update_visitor_status.php` (lines 1–23)
- **Problem**: The endpoint never calls `session_start()`, never checks authentication or role, never verifies CSRF, never validates `status` against an allowlist, and updates `sms_visitors` by `id` alone (`WHERE id = ?`) without checking `resident_id` or `society_id`.
- **Why It Matters**: Any unauthenticated remote user can approve, deny, or inject arbitrary strings into any visitor record across all societies.
- **Exploitation**: Send `POST /society_app/api/update_visitor_status.php` with `{"visitor_id": 1, "status": "Entered"}` in a loop to mark arbitrary gate entries as approved/entered.
- **Recommended Fix**: Require authenticated session (`$_SESSION['logged_in']` + `role_id == 3`), verify `resident_id = $_SESSION['user_id']`, restrict `status` to `['Entered', 'Denied']`, and replace unauthenticated Service Worker calls with a signed/scoped action token or session-authenticated request.
- **Requires Code Changes**: Yes.

---

## High Findings

### SEC-03 — Stored XSS via Unescaped `innerHTML` Rendering Across Dashboards
- **Severity**: High
- **Location**:
  - `modules/admin/dashboard.php` (lines 103–131)
  - `modules/admin/users.php` (lines 80–104)
  - `modules/admin/guards.php` (lines 128–146)
  - `modules/admin/financials.php` (lines 191–215)
  - `modules/admin/notices.php` (lines 98–112)
  - `modules/admin/complaints.php` (lines 110–134)
  - `modules/guard/dashboard.php` (lines 260–275)
  - `modules/resident/dashboard.php` (lines 494–520, 603–616)
  - `modules/resident/financials.php` (lines 104–113)
  - `modules/auth/register.php` (lines 237, 248)
- **Problem**: `api/register.php` and `api/guard_log_visitor.php` store `firstName`, `lastName`, `phone`, `unit_number`, `visitor_name`, and `purpose` without HTML encoding, and **all** frontend dashboards interpolate API strings directly into `innerHTML`.
- **Why It Matters**: A malicious user registering with `<img src=x onerror=...>` in `firstName` or `unitNumber`, or a guard/attacker logging a malicious `visitor_name`, executes JavaScript in Admin, Guard, and Resident browsers.
- **Exploitation**: Register a resident with an XSS payload in `firstName`/`unitNumber`; when an Admin opens `/admin/dashboard` to review pending requests, the payload executes in the Admin session and can approve users or steal CSRF tokens.
- **Recommended Fix**: Store raw validated values in DB, escape all dynamic text in JS using `textContent` or an `escapeHtml()` helper before DOM insertion, and avoid `innerHTML` for untrusted fields.
- **Requires Code Changes**: Yes.

### SEC-04 — Missing Role / CSRF Checks on Visitor & Auth Endpoints
- **Severity**: High
- **Location**:
  - `api/guard_log_visitor.php`: No `role_id == 4` check, no CSRF check (`requireValidCsrfToken()` omitted).
  - `api/resident_handle_visitor_approval.php`: No `role_id == 3` check, no CSRF check.
  - `api/resident_get_pending_visitors.php`: Checks `user_id` only, no `role_id == 3` check.
  - `api/login.php` & `api/register.php`: Frontend sends `csrf_token`, but neither endpoint calls `requireValidCsrfToken()`.
- **Problem**: Residents/Admins with an active session can invoke `guard_log_visitor.php`, and visitor approval lacks CSRF protection.
- **Why It Matters**: Cross-Site Request Forgery or horizontal/vertical privilege abuse between authenticated roles.
- **Exploitation**: Trick a logged-in resident into visiting a page that POSTs to `api/resident_handle_visitor_approval.php` (`Content-Type: text/plain` simple request works in browsers since `json_decode(file_get_contents('php://input'))` ignores `Content-Type`!) to silently approve a visitor.
- **Recommended Fix**: Enforce `role_id` checks, `requireValidCsrfToken()`, and `Content-Type: application/json` validation on all state-changing endpoints.
- **Requires Code Changes**: Yes.

### SEC-05 — Unvalidated Base64 File Upload & Public Exposure of Visitor Photos
- **Severity**: High
- **Location**: `api/guard_log_visitor.php` (lines 48–66), `uploads/visitors/`
- **Problem**: `guard_log_visitor.php` decodes arbitrary Base64 data and writes `.png` files directly inside webroot without verifying magic bytes (`finfo`), image validity (`getimagesizefromstring`), or max file size. Additionally, `uploads/visitors/` is directly served over HTTP without authentication or directory-listing protection.
- **Why It Matters**: Potential DoS via oversized uploads, polyglot/HTML-in-image abuse, and privacy exposure of resident visitor photographs.
- **Exploitation**: Send a multi-megabyte Base64 payload repeatedly to exhaust disk space, or enumerate/access `/society_app/uploads/visitors/*.png` publicly.
- **Recommended Fix**: Validate decoded binary with `finfo_buffer` / `getimagesizefromstring`, enforce strict size limits (e.g., <= 2 MB), re-encode via GD/Imagick, store uploads outside webroot, and serve via an authenticated PHP file-streaming endpoint.
- **Requires Code Changes**: Yes.

### SEC-06 — Direct Execution Bypass of `routes.php` & Exposed Test Scripts
- **Severity**: High
- **Location**: `.htaccess` (lines 6–10), `test_push_trigger.php`, `test.php`, `modules/resident/*.php`, `modules/admin/residents.php`
- **Problem**: Because `.htaccess` skips rewriting for existing files (`RewriteCond %{REQUEST_FILENAME} !-f`), visitors can directly access `/society_app/test_push_trigger.php` (which defaults `$test_user_id` to `1` without auth and dispatches a live push notification), `/society_app/modules/resident/forget-password.php`, and `/society_app/modules/admin/*.php` directly.
- **Why It Matters**: Attackers can trigger test endpoints, probe legacy mock pages, and bypass router-level middleware (`routes.php` only checks `requireLogin()`/`requireAdmin()` on 3 of 11 routes anyway).
- **Exploitation**: Request `GET /society_app/test_push_trigger.php` anonymously to spam User ID 1 with push notifications or leak error messages.
- **Recommended Fix**: Move `modules/` and `config/` outside webroot (or block direct HTTP access via `.htaccess`), enforce route-level auth middleware in `routes.php` for all protected routes, and remove `test.php` and `test_push_trigger.php`.
- **Requires Code Changes**: Yes (and Apache config changes).

### SEC-07 — Session Fixation & Insecure Session Cookie Settings
- **Severity**: High
- **Location**: `api/login.php` (lines 51–58), `index.php` (line 5)
- **Problem**: `api/login.php` does not call `session_regenerate_id(true)` after successful authentication, and the application never sets `session_set_cookie_params()` for `httponly`, `secure`, or `samesite`.
- **Why It Matters**: Pre-existing session IDs remain valid across privilege escalation (anon → authenticated Admin/Resident/Guard), and cookies lack explicit `SameSite` and `HttpOnly` hardening.
- **Exploitation**: Session fixation or cookie interception over non-HTTPS connections.
- **Recommended Fix**: Call `session_regenerate_id(true)` upon login and configure `session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => true])` in production.
- **Requires Code Changes**: Yes.

### SEC-08 — Weak Password Policy, Hardcoded Guard Password & Absent Brute-Force Protection
- **Severity**: High
- **Location**: `api/register.php` (lines 16–40), `api/admin_create_guard.php` (line 34), `api/login.php`, `api/verify_society.php`, `api/guard_verify_pin.php`
- **Problem**:
  - `api/register.php` accepts any non-empty password (even 1 character) with no complexity or minimum length check.
  - `api/admin_create_guard.php` assigns every new guard the hardcoded password `'guard123'` with no forced reset flow (and password reset is not implemented).
  - No rate limiting or lockout exists on login, invite-code guessing, or 6-digit guest PIN verification.
- **Why It Matters**: Newly created guard accounts have trivially guessable credentials; login and 6-digit PINs/invite codes can be brute-forced.
- **Exploitation**: Attempt `'guard123'` against guard emails, or brute-force `api/login.php` and `api/verify_society.php` at high request rates.
- **Recommended Fix**: Enforce `>= 8` char password policy server-side, generate random one-time guard passwords or invite-based password setup, implement a real token-based password reset flow, and add IP/account rate limiting.
- **Requires Code Changes**: Yes.

---

## Medium Findings

### SEC-09 — Verbose Database Error Disclosure & `display_errors = 1`
- **Severity**: Medium
- **Location**: Nearly all `api/*.php` files (`"Database error: " . $e->getMessage()`), `config/db.php` (line 33), `modules/admin/guards.php` (lines 4–6: `ini_set('display_errors', 1)`).
- **Problem**: Raw SQL/PDO exception messages and PHP startup/runtime errors are returned to clients.
- **Why It Matters**: Leaks database schema names, column names, SQL syntax, and server file paths.
- **Exploitation**: Trigger constraint errors (e.g., duplicate phone number in `api/register.php`) to inspect table/constraint names.
- **Recommended Fix**: Log full exceptions server-side with `error_log()`, set `display_errors = 0`, and return generic error messages to clients.
- **Requires Code Changes**: Yes.

### SEC-10 — Wildcard CORS Policy (`Access-Control-Allow-Origin: *`)
- **Severity**: Medium
- **Location**: `api/login.php` (line 6), `api/register.php` (line 3), `api/verify_society.php` (line 3)
- **Problem**: Endpoints return `Access-Control-Allow-Origin: *` while also declaring `Access-Control-Allow-Headers: Authorization`.
- **Why It Matters**: Allows any third-party origin to read API responses from cross-origin requests.
- **Exploitation**: Malicious site probes invite codes or login error responses from a victim's browser.
- **Recommended Fix**: Restrict CORS to the exact application origin (or omit CORS headers for same-origin web endpoints).
- **Requires Code Changes**: Yes.

### SEC-11 — Missing HTTP Security Headers
- **Severity**: Medium
- **Location**: Global (`.htaccess`, `index.php`, `api/*.php`)
- **Problem**: No `Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, or `Strict-Transport-Security` headers are emitted.
- **Why It Matters**: Increases impact of XSS, clickjacking, and MIME-sniffing attacks.
- **Exploitation**: Embed login or admin approval pages in an attacker iframe (clickjacking).
- **Recommended Fix**: Add security headers via `.htaccess` (`mod_headers`) or a shared PHP bootstrap.
- **Requires Code Changes**: Yes.

### SEC-12 — Simulated Payment Endpoint & Missing Admin Self-Protection
- **Severity**: Medium
- **Location**: `api/resident_pay_invoice.php` (lines 22–24), `api/admin_update_status.php` (lines 32–34)
- **Problem**:
  - `resident_pay_invoice.php` marks an invoice `'Paid'` solely upon receiving `invoice_id` with no payment gateway verification, webhook signature, or transaction ledger.
  - `admin_update_status.php` allows a committee member to set any user in the society (including themselves or another admin, or a guard) to `'rejected'`.
- **Why It Matters**: Residents can mark bills paid without paying; committee members can accidentally or maliciously lock out admin accounts.
- **Exploitation**: Click "Pay Now" (or POST `invoice_id`) to clear dues without transferring funds.
- **Recommended Fix**: Integrate a verified payment gateway with server-side signature verification and prevent `admin_update_status.php` from modifying `role_id IN (1,2)` or `$_SESSION['user_id']`.
- **Requires Code Changes**: Yes.

### SEC-13 — GET-Based Logout CSRF & CSV Formula Injection
- **Severity**: Medium
- **Location**: `api/logout.php` (lines 1–24), `modules/admin/financials.php` (lines 219–261)
- **Problem**:
  - Logout is performed via unauthenticated GET (`<a href="/society_app/api/logout.php">`), allowing any external `<img src=".../api/logout.php">` to log users out.
  - `exportToCSV()` in `modules/admin/financials.php` writes `inv.title`, `first_name`, `last_name`, `unit_number` unescaped; values starting with `=`, `+`, `-`, `@` execute as formulas in Excel/Sheets.
- **Why It Matters**: Forced logout annoyance and spreadsheet formula execution on admin machines.
- **Exploitation**: Resident sets bill/name value starting with `=HYPERLINK(...)` or `=CMD|...`; admin exports CSV and opens in Excel.
- **Recommended Fix**: Make logout a CSRF-protected POST form, and prefix dangerous CSV cell prefixes with `'`.
- **Requires Code Changes**: Yes.

---

## Low / Informational Findings

### SEC-14 — Non-Cryptographic PRNG for Guest Pass PINs
- **Severity**: Low
- **Location**: `api/resident_generate_pin.php` (line 23)
- **Problem**: Uses `mt_rand(100000, 999999)` instead of `random_int(100000, 999999)`.
- **Recommended Fix**: Replace `mt_rand()` with `random_int()` and validate `valid_date >= CURDATE()`.
- **Requires Code Changes**: Yes.

### SEC-15 — Missing Input Bounds on Invoice Amounts & Dates
- **Severity**: Low
- **Location**: `api/admin_create_invoice.php` (lines 25–26)
- **Problem**: `floatval($data->amount)` is not checked to be `> 0`, and `$data->due_date` is not validated as `YYYY-MM-DD`.
- **Recommended Fix**: Validate `amount > 0` and strict `DateTime::createFromFormat('Y-m-d', $due_date)`.
- **Requires Code Changes**: Yes.

### SEC-16 — Hardcoded External Production Domain in Push Payload
- **Severity**: Informational
- **Location**: `api/guard_log_visitor.php` (line 97)
- **Problem**: Hardcodes `'https://shopquare.in' . $photo_path` as the push notification icon URL.
- **Recommended Fix**: Build the URL dynamically from environment configuration (`APP_URL`).
- **Requires Code Changes**: Yes.
