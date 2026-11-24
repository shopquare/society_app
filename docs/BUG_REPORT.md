# Bug & Quality Report

All findings below are derived from static inspection of the codebase (`php -l` syntax check passed on all 58 PHP files outside `vendor/`, and `test.php` confirmed Composer autoloading). No live database or browser runtime tests were executed.

---

## Confirmed Bugs

### BUG-01 — Guard Login Redirects to Resident Dashboard (Wrong Portal / Access Loop)
- **Severity**: Critical
- **Location**: `modules/auth/login.php` (lines 3–10) & `api/login.php` (line 66)
- **Details**: `api/login.php` returns `"redirect" => ""`, causing `modules/auth/login.php` JS (`window.location.href = result.data.redirect`) to reload `/login`. On reload, PHP checks:
  ```php
  if (in_array($_SESSION['role_id'], [1, 2])) {
      header("Location: /society_app/admin/dashboard");
  } else {
      header("Location: /society_app/resident/dashboard");
  }
  ```
  Guards (`role_id == 4`) are routed to `/society_app/resident/dashboard` instead of `/society_app/guard/dashboard`.
- **Recommended Fix**: Add `elseif ($_SESSION['role_id'] == 4)` redirect to `/society_app/guard/dashboard` in `modules/auth/login.php` (and return role-appropriate `redirect` from `api/login.php`).

### BUG-02 — Schema Column Mismatch in Resident Dashboard Profile Query
- **Severity**: Critical
- **Location**: `modules/resident/dashboard.php` (line 20) vs `api/verify_society.php` (line 16), `api/admin_get_society.php`, `api/admin_update_society.php` (line 23)
- **Details**: `modules/resident/dashboard.php` selects `s.name as society_name` from `sms_societies`, while every society API uses `society_name`. Because the query is wrapped in `try { ... } catch (PDOException $e) {}`, if `sms_societies` does not have a `name` column, the entire query silently fails and `$residentInfo` remains empty, falling back to `'Society Portal'` and `'Resident'`.
- **Recommended Fix**: Change `s.name` to `s.society_name` (matching the rest of the codebase).

### BUG-03 — Guard Visitor Phone Number Is Never Saved to Database
- **Severity**: High
- **Location**: `modules/guard/dashboard.php` (line 296) vs `api/guard_log_visitor.php` (lines 37–70)
- **Details**: The Guard UI marks `phone_number` as `required` and sends `phone_number` in the JSON payload, and both `api/guard_get_visitors.php` and `api/resident_get_pending_visitors.php` select and display `v.phone_number`. However, `api/guard_log_visitor.php` never reads `$data['phone_number']` and omits `phone_number` from its `INSERT INTO sms_visitors` query.
- **Recommended Fix**: Read, validate, and insert `phone_number` in `api/guard_log_visitor.php`.

### BUG-04 — Push Notification "Approve" Sets `'Approved'`, Breaking Guard Exit & Table Status
- **Severity**: High
- **Location**: `sw.js` (line 49), `api/update_visitor_status.php` (lines 14–15) vs `api/resident_handle_visitor_approval.php` (line 20), `api/guard_visitor_exit.php` (line 24), `modules/guard/dashboard.php` (line 243)
- **Details**: `sw.js` sends `'Approved'` to `api/update_visitor_status.php`. However, `resident_handle_visitor_approval.php` sets `'Entered'`, and `guard_visitor_exit.php` requires `WHERE id = :id AND status = 'Entered'`. Visitors approved via push notification can never be marked exited and display as `"Exited -"` on the Guard dashboard.
- **Recommended Fix**: Unify visitor approval status to `'Entered'` across `sw.js` and the approval API.

### BUG-05 — Guard Dashboard Shows Pending and Denied Visitors as `"Exited -"`
- **Severity**: High
- **Location**: `modules/guard/dashboard.php` (lines 243–258)
- **Details**: The table action column only checks `if (vis.status === 'Entered')` and puts all other statuses (`Pending`, `Denied`, `Approved`, `Exited`) into `else`, rendering `Exited ${formatTime(vis.exit_time)}` (which displays `Exited -` when `exit_time` is null).
- **Recommended Fix**: Handle `'Pending'`, `'Denied'`, `'Entered'`, and `'Exited'` explicitly with distinct badges.

### BUG-06 — Fast-Track PIN Flow Marks PIN Used Early and Still Requires Resident Re-Approval
- **Severity**: High
- **Location**: `api/guard_verify_pin.php` (lines 38–44), `modules/guard/dashboard.php` (lines 138–163, 285–291)
- **Details**:
  1. `guard_verify_pin.php` echoes HTTP 200 JSON *before* running `UPDATE sms_visitor_passes SET status = 'Used'`.
  2. If the guard verifies the PIN and then fails validation (or cancels), the PIN is already burned (`'Used'`).
  3. Submitting the prefilled form calls `guard_log_visitor.php`, which still inserts `status = 'Pending'` and rings the resident again even though the visitor had a pre-approved PIN.
  4. `resident_search` relies on exact datalist string matching (`Unit X - FirstName`); if the unit has no `last_name` or two residents share unit/name formatting, matching fails.
- **Recommended Fix**: Add a dedicated pre-approved entry path (or pass `pin_code` to `guard_log_visitor.php`) that atomically marks the PIN `'Used'` and logs the visitor as `'Entered'`.

### BUG-07 — Resident Dashboard Notice Board Is Hardcoded Static HTML
- **Severity**: High
- **Location**: `modules/resident/dashboard.php` (lines 191–202)
- **Details**: The Notice Board card displays hardcoded text ("Water Supply Interruption / Tomorrow from 2:00 PM to 4:00 PM") and never calls `/society_app/api/get_notices.php`.
- **Recommended Fix**: Fetch live notices from `/society_app/api/get_notices.php` on page load.

### BUG-08 — Broken Links, Missing Assets, and Missing Navigation Paths
- **Severity**: High
- **Locations**:
  - `includes/auth_middleware.php` (lines 20, 37): Redirects to nonexistent `/society_app/login.html` and `/society_app/dashboard.php`.
  - `modules/auth/logout.php` (line 3): Calls undefined `logoutUser()` (fatal error).
  - `includes/footer.php` (line 11): Registers nonexistent `/society_app/pwa/sw.js` (actual file is `/society_app/sw.js`).
  - `includes/header.php` (line 23) & `pwa/manifest.json` (lines 11, 16): Reference `/society_app/assets/images/icon-192.png` and `icon-512.png`, but `assets/images/` is empty.
  - `manifest.json` (lines 11–18): References `assets/icon-192.svg` and `assets/icon-512.svg` with `"type": "image/png"` (incorrect MIME type).
  - `sw.js` (lines 8, 24, 64, 97 in `guard_log_visitor.php`): References nonexistent `/society_app/assets/icon.png` and `/society_app/assets/badge.png`.
  - `modules/auth/login.php` (lines 105, 111): "Forgot Password?" links to `#`; "Remember me" checkbox is never read.
  - `modules/resident/dashboard.php` (lines 205–239): No link to `/society_app/resident/financials`, so residents have no UI path to pay bills; `'Overdue'` bills render as `'Unpaid'`.

### BUG-09 — Broken Legacy Admin Page `modules/admin/residents.php`
- **Severity**: High
- **Location**: `modules/admin/residents.php` (lines 1–120)
- **Details**: Calls undefined `requireRole()`, undefined `csrfInput()`, MySQLi method `fetch_all(MYSQLI_ASSOC)` on a PDO statement, queries nonexistent table `users` (`flat_no`, `name`, `phone`), and posts to nonexistent `/society_app/api/add_user.php`.

### BUG-10 — `sms_users.status` Enum Mismatch (`'rejected'`)
- **Severity**: Medium
- **Location**: `config/db.sql` (line 14) vs `api/admin_update_status.php` (line 24)
- **Details**: `db.sql` defines `ENUM('pending', 'active', 'suspended', 'deactivated')`, whereas `admin_update_status.php` sets `'rejected'`.

### BUG-11 — `Notification` API ReferenceError on Unsupported Browsers
- **Severity**: Medium
- **Location**: `modules/resident/dashboard.php` (line 348)
- **Details**: `if (Notification.permission === 'granted')` runs unconditionally without checking `'Notification' in window`, throwing `ReferenceError` on iOS Safari (outside installed PWA) or unsupported browsers and halting `DOMContentLoaded` handlers.

### BUG-12 — Guard Dashboard Has No Polling for Resident Approval Updates
- **Severity**: Medium
- **Location**: `modules/guard/dashboard.php` (line 204)
- **Details**: `fetchVisitors()` runs once on `DOMContentLoaded`. When a resident approves a visitor, the guard does not see the status update or "Mark Exit" button without manually refreshing the browser.

### BUG-13 — Service Worker Always Displays "Visitor was successfully approved" Even on API Failure
- **Severity**: Medium
- **Location**: `sw.js` (lines 53–67)
- **Details**: The `.then()` chain ignores `response.ok` and JSON `success` fields, showing a success notification even if the API returns an error or 500.

### BUG-14 — Full Page Reload Interrupts Resident After Guest Pass Generation
- **Severity**: Low
- **Location**: `modules/resident/dashboard.php` (line 579)
- **Details**: `setTimeout(() => { location.reload(); }, 4000);` forces a full reload after generating a PIN, interrupting active alerts or modal interactions.

### BUG-15 — Routing Edge Cases & Duplicate Query Risks
- **Severity**: Medium
- **Location**: `routes.php` (lines 9, 34–65), `api/admin_get_users.php` (line 23), `api/login.php` (line 25)
- **Details**:
  - `routes.php` fails with 404 on trailing slashes (e.g., `/admin/dashboard/`) and omits route-level auth checks on 7 routes.
  - `api/admin_get_users.php` uses `INNER JOIN sms_user_units` without `is_current_occupant = 1`, risking duplicate user rows or omitting users without unit rows.

---

## Potential Bugs & Unverified Risks
- **Time-Zone / Date Parsing Drift**: `new Date(vis.entry_time)` in `modules/guard/dashboard.php` and `modules/resident/dashboard.php` parses MySQL timestamps (`YYYY-MM-DD HH:MM:SS`) differently across browsers; Safari can fail to parse space-separated timestamps (`Invalid Date`).
- **Undefined `complaints` Status Counter (`NaN`)**: `modules/admin/complaints.php` (line 108) increments `counts[ticket.status]++`; any unexpected DB status value yields `NaN` and omits the card silently.
- **Duplicate Phone Number 500 Error**: `api/register.php` checks email uniqueness before transaction, but `sms_user_profiles.phone_number` is `UNIQUE` in `db.sql`, so duplicate phones trigger an uncaught `PDOException` returning HTTP 500 instead of a friendly 409 validation error.
- **`visitor_name.charAt(0)` Crash**: `modules/resident/dashboard.php` (line 497) throws `TypeError` if `visitor_name` is `null` or empty.
