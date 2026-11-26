# Testing & QA Guide — SecureGate

SecureGate includes an automated invariant test suite in `tests/run_tests.php` designed to run in both CI/CD and local environments without destroying database state.

---

## 🧪 Automated Test Suite (`tests/run_tests.php`)

Run the test suite from your terminal:

```bash
php tests/run_tests.php
```

### 40 Test Assertions Covered:

1. **Environment Configuration (`config/env.php`)**:
   * Loads unquoted keys cleanly.
   * Strips surrounding single and double quotes.
2. **Role Dashboard Routing (`includes/auth_middleware.php`)**:
   * Role `1` (Super Admin) maps to `/admin/dashboard` (subpath and root).
   * Role `2` (Committee) maps to `/admin/dashboard` (subpath and root).
   * Role `3` (Resident) maps to `/resident/dashboard` (subpath and root).
   * Role `4` (Guard) maps to `/guard/dashboard` (subpath and root).
   * `hasRole()` handles integer and string `role_id`s uniformly.
   * `hasRole()` checks array matches and rejects unauthorized roles.
3. **CSRF Protection (`includes/csrf.php`)**:
   * Generates 64-character cryptographic hex tokens.
   * Validates matching tokens and rejects mismatched/null tokens.
   * `rotateCsrfToken()` safely invalidates old tokens.
4. **HMAC Action Tokens for Service Workers**:
   * Validates bound `visitor_id` and `resident_id` signatures.
   * Rejects forged or mismatched visitor tokens.
5. **License Plate Normalization (`api/resident_vehicles.php`)**:
   * Standardizes spacing, hyphens, and casing (e.g. `'mh 12 ab 1234'` → `'MH12AB1234'`).
6. **Database Migrator Table Checks (`includes/db_migrator.php`)**:
   * Verifies `sms_vehicles`, `sms_gate_parcels`, and `sms_audit_logs`.
   * Verifies `logAudit()` audit logging helper existence.
7. **Visitor Photo Path Security**:
   * Safely extracts filenames from queries, relative paths, and double-encoded strings.
   * Validates HMAC photo tokens for authorized visitor images and rejects mismatches.
8. **Binary Distribution**:
   * Verifies `download.php` exists and is readable.
   * Verifies `apk/SecureGate.apk` exists, is signed, and exceeds 1 MB in size.
   * Validates `/download` and `/download/apk` routing rules in `routes.php`.
9. **Timezone Synchronization**:
   * Asserts default PHP timezone is strictly `Asia/Kolkata` (IST).
   * Asserts `assets/js/app.js` enforces `Asia/Kolkata` and anchors ISO strings to `+05:30`.
10. **Static Security Invariants**:
    * Asserts **zero hardcoded DB passwords** or VAPID private keys across all PHP files.
    * Asserts **zero `display_errors = 1`** left in production files.

---

## 🔄 CI/CD Integration (`.github/workflows/ci-cd.yml`)

Every git push to `main` executes:
1. `find . -name '*.php' | xargs -n1 php -l` (PHP Syntax Lint across all files).
2. `php tests/run_tests.php` (40 Invariant Unit Tests).
3. `php test.php` (Composer Autoloader Verification).
4. Automated FTP deployment to InfinityFree hosting (`https://society.infy.click/`).
