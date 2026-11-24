# User Roles & Permissions

## Defined Roles (`sms_roles` / Session `role_id`)

| `role_id` | Role Name | Intended Portal | Access Scope |
|---|---|---|---|
| `1` | Super Admin | `/admin/dashboard` | Full society-scoped admin access (treated identically to Role `2` in current code) |
| `2` | Committee Member | `/admin/dashboard` | Approve residents, manage guards, notices, bills, complaints, and society settings |
| `3` | Resident (Owner / Tenant) | `/resident/dashboard` | Approve/deny own visitors, generate guest PINs, view/pay own bills, raise own complaints, read society notices |
| `4` | Security Guard | `/guard/dashboard` | Verify guest PINs, log visitors with photo, view today's society visitors, mark visitor exit |

> [!NOTE]
> Multi-tenancy is enforced by filtering queries with `$_SESSION['society_id']`. Role `1` and Role `2` have identical privileges in all current checks (`in_array($_SESSION['role_id'], [1, 2])`); there is no cross-society Super Admin console yet.

## Route-Level vs View-Level Authorization Matrix

| Route | `routes.php` Middleware | Target View Check | Status / Gap |
|---|---|---|---|
| `/`, `/login` | None | Redirects if logged in | **Bug**: Sends `role_id == 4` to `/resident/dashboard` |
| `/register` | None | Redirects if logged in | Redirects all logged-in roles to `/resident/dashboard` |
| `/resident/dashboard` | `requireLogin()` | `$_SESSION['logged_in'] === true` | **Gap**: Does not verify `role_id == 3`; `requireLogin()` redirects to missing `login.html` |
| `/resident/financials` | **None** | `$_SESSION['role_id'] != 3` | View check works via router, fails on direct file access if session unstarted |
| `/admin/dashboard` | `requireAdmin()` | `in_array(role_id, [1, 2])` | `requireAdmin()` redirects unauthorized roles to missing `dashboard.php` |
| `/admin/users`, `notices`, `financials`, `guards`, `complaints`, `settings` | **None** | `in_array(role_id, [1, 2])` | Missing router middleware; direct URL `/modules/admin/*.php` skips `session_start()` |
| `/guard/dashboard` | `requireLogin()` + `hasRole(4)` | `role_id != 4` | `hasRole(4)` uses strict `===`; fails if PDO returns `'4'` as string |

## API Authorization & CSRF Enforcement Gaps

| Endpoint | Intended Role | Actual Auth Check | Actual `society_id` / Ownership Scope | CSRF Check | Risk |
|---|---|---|---|---|---|
| `api/update_visitor_status.php` | Resident (`3`) | **NONE** | **NONE** (`WHERE id = ?`) | **NO** | **Critical** unauthenticated IDOR |
| `api/guard_log_visitor.php` | Guard (`4`) | Checks `society_id` & `user_id` only (**no `role_id` check**) | Uses `$_SESSION['society_id']` | **NO** | Any resident/admin can log gate entries |
| `api/resident_handle_visitor_approval.php` | Resident (`3`) | Checks `user_id` only (**no `role_id` check**) | `WHERE id = ? AND resident_id = ?` | **NO** | CSRF vulnerability on approval |
| `api/resident_get_pending_visitors.php` | Resident (`3`) | Checks `user_id` only (**no `role_id` check**) | `WHERE resident_id = ?` | N/A | Low (user-scoped) |
| `api/login.php` / `api/register.php` | Public | Public | Validated by DB / society code | **Ignored** | CSRF token sent by UI is ignored |
| `api/logout.php` | Any logged-in | None | Destroys current session | **NO (GET)** | Logout CSRF via `<img src>` |
| `api/admin_update_status.php` | Admin (`1, 2`) | `in_array(role_id, [1, 2])` | `WHERE id = :user_id AND society_id = :society_id` | Yes | Admin can reject self or other admins |
| `test_push_trigger.php` | Dev/Test only | **NONE** (falls back to `user_id = 1`) | None | N/A | Anonymous push notification spam |
