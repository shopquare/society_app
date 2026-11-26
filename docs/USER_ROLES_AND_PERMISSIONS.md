# User Roles, Credentials & Permissions — SecureGate

## Defined Roles (`sms_roles` / Session `role_id`)

| `role_id` | Role Name | Primary Portal | Access Scope |
| :---: | :--- | :--- | :--- |
| **`1`** | Super Admin | `/admin/dashboard` | Full society governance, resident approval, billing, financials, settings |
| **`2`** | Committee Member | `/admin/dashboard` | Resident onboarding, guard provisioning, notices, complaints resolution |
| **`3`** | Resident | `/resident/dashboard` | Gate approvals, delivery pre-approvals, guest PIN passes, bills, panic alarm |
| **`4`** | Security Guard | `/guard/dashboard` | Gatekeeper terminal, visitor photo capture, live decision chimes, exit tracking |

---

## Active Pre-Seeded Accounts & Credentials

The following accounts are automatically pre-seeded in the database across both local XAMPP and live production (`https://society.infy.click`) via `includes/db_migrator.php`:

| User ID | Role | Email / Username | Exact Password | Unit Number | Resident Name |
| :---: | :--- | :--- | :--- | :---: | :--- |
| **1** | Super Admin | `admin@demo.com` | `password` | N/A | Super Admin |
| **2** | Committee | `committee@demo.com` | `password` | N/A | Sarah Committee |
| **3** | Resident | `resident@demo.com` | `password` | **101** & **B-205** | John Resident |
| **4** | Guard | `guard@demo.com` | `password` | Gate Terminal | Ramesh Guard |
| **5** | Resident | `yarun520@gmail.com` | `password` | **A-101** | Arun Yadav |
| **6** | Resident | `yarun037@gmail.com` | `Fateh@123` | **B-801** | Fateh Singh |
| **7** | Resident | `bosdikee@gmail.com` | `password` | **A-32** | Bosdiee Kee |
| **8** | Resident | `laundarirani@gmail.com` | `12345` | **C-H** | Laundri Rani |
| **9** | Resident | `ohhyehhbaby@ohh.com` | `12345` | **B-Ty** | BABY |

*Default Society Invite Code*: **`SUNSHINE2026`** (Sunshine Residency, Society ID: `1`)

---

## Route & View Authorization Matrix

Protected routes are enforced at two levels: the front-controller (`routes.php`) and the view file:

| Route Path | Allowed Roles | Enforcement Method | Unauthorized Action |
| :--- | :---: | :--- | :--- |
| `/login` | Public | Redirects if already authenticated | Routed to user's assigned dashboard |
| `/register` | Public | Validates invite code against `sms_societies` | Blocks invalid society codes |
| `/download` | Public | Serves direct APK and PWA install guide | Available to all users |
| `/download/apk` | Public | Binary stream of `apk/SecureGate.apk` | Streams APK with binary headers |
| `/admin/dashboard` | `1, 2` | `requireRole([1, 2])` | Redirects to `/login` |
| `/admin/users` | `1, 2` | `requireRole([1, 2])` | Redirects to `/login` |
| `/admin/notices` | `1, 2` | `requireRole([1, 2])` | Redirects to `/login` |
| `/admin/financials` | `1, 2` | `requireRole([1, 2])` | Redirects to `/login` |
| `/admin/guards` | `1, 2` | `requireRole([1, 2])` | Redirects to `/login` |
| `/admin/complaints` | `1, 2` | `requireRole([1, 2])` | Redirects to `/login` |
| `/admin/settings` | `1, 2` | `requireRole([1, 2])` | Redirects to `/login` |
| `/resident/dashboard` | `3` | `requireRole(3)` | Redirects to `/login` |
| `/resident/financials` | `3` | `requireRole(3)` | Redirects to `/login` |
| `/guard/dashboard` | `4` | `requireRole(4)` | Redirects to `/login` |

---

## API Security & Anti-IDOR Enforcement

All 30+ endpoints under `api/` initialize through `includes/api_bootstrap.php`:

1. **Role Enforcement (`requireApiAuth([roles])`)**:
   Endpoints strictly restrict execution to authorized `role_id`s. Intruders receive a standardized `403 Forbidden` JSON response.
2. **Society Multi-Tenant Isolation**:
   Every database query filters strictly by `society_id = :society_id` from the server-validated PHP session. Cross-society data access is strictly prevented.
3. **Resident Ownership Scope**:
   Resident actions (`resident_handle_visitor_approval.php`, `resident_pay_invoice.php`, `resident_create_complaint.php`) enforce `resident_id = :session_user_id`.
4. **CSRF Tokens**:
   Cryptographic 64-character hex tokens are validated on all mutating `POST` actions (`verifyCsrfToken()`).
5. **HMAC Action Tokens for Service Workers**:
   The Web Push Service Worker (`sw.js`) signs and verifies action tokens (`signVisitorActionToken()`) to ensure 1-tap notifications cannot be forged.
6. **Tokenized Photo Streaming**:
   Visitor camera photos are accessed through `/api/get_visitor_photo.php?file=...&token=...`, protected by signed time-limited tokens to prevent directory traversal.
