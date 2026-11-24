# Improvement Roadmap

All recommendations below are grounded in actual findings from codebase inspection.

---

## Priority 1 — Critical (Security, Broken Core Flows, Data Exposure)

1. **Migrate Hardcoded Secrets to `.env` & Rotate Credentials**
   - **Reason**: DB credentials (`config/db.php`) and VAPID private keys (`api/guard_log_visitor.php`, `test_push_trigger.php`) are hardcoded.
   - **Expected Benefit**: Prevents credential compromise and enables safe Git commits.
   - **Complexity**: Low | **Dependencies**: `vlucas/phpdotenv` or server env vars | **Effort**: 2–4 hours | **Modifies Existing Code**: Yes.

2. **Secure `api/update_visitor_status.php` & Unify Visitor Status Values**
   - **Reason**: Endpoint has zero authentication/authorization/CSRF checks (`SEC-02`) and writes `'Approved'` instead of `'Entered'`, breaking `guard_visitor_exit.php` (`BUG-04`).
   - **Expected Benefit**: Eliminates critical IDOR vulnerability and makes push notification Approve/Deny work end-to-end.
   - **Complexity**: Medium | **Dependencies**: Signed SW action token or authenticated SW session | **Effort**: 4–6 hours | **Modifies Existing Code**: Yes.

3. **Fix Guard Login Redirect (`modules/auth/login.php` & `api/login.php`)**
   - **Reason**: Guards (`role_id == 4`) are redirected to `/resident/dashboard` (`BUG-01`).
   - **Expected Benefit**: Guards can log into `/guard/dashboard` properly.
   - **Complexity**: Low | **Dependencies**: None | **Effort**: 1 hour | **Modifies Existing Code**: Yes.

4. **Fix Resident Dashboard `sms_societies.name` vs `society_name` Query (`modules/resident/dashboard.php`)**
   - **Reason**: Profile/society query fails silently (`BUG-02`), leaving resident info at fallback values.
   - **Expected Benefit**: Restores accurate resident name, unit number, and society name display.
   - **Complexity**: Low | **Dependencies**: Live DB schema verification | **Effort**: 0.5 hours | **Modifies Existing Code**: Yes.

5. **Eliminate Stored XSS via Safe DOM Escaping**
   - **Reason**: All frontend views interpolate untrusted DB fields into `innerHTML` (`SEC-03`).
   - **Expected Benefit**: Prevents account takeover and Admin CSRF bypass via XSS.
   - **Complexity**: Medium | **Dependencies**: Shared JS `escapeHtml()` helper | **Effort**: 4–6 hours | **Modifies Existing Code**: Yes.

6. **Enforce Auth, Role, CSRF & File-Upload Validation Across All APIs**
   - **Reason**: `guard_log_visitor.php` lacks `role_id == 4` and CSRF checks and writes unvalidated Base64 files into public webroot (`SEC-04`, `SEC-05`); `login.php`/`register.php` ignore CSRF; `api/login.php` lacks `session_regenerate_id(true)`.
   - **Expected Benefit**: Closes privilege escalation, session fixation, and arbitrary file upload risks.
   - **Complexity**: Medium | **Dependencies**: Shared API bootstrap | **Effort**: 6–8 hours | **Modifies Existing Code**: Yes.

7. **Block Direct Access to `modules/` & Remove Exposed Test/Dead Files**
   - **Reason**: `test_push_trigger.php` can spam user 1 anonymously; `modules/admin/residents.php`, `modules/auth/logout.php`, and `modules/resident/{login,register,forget-password}.php` are broken/legacy (`SEC-06`, `BUG-08`, `BUG-09`).
   - **Expected Benefit**: Eliminates dead code confusion and router-bypass exposure.
   - **Complexity**: Low | **Dependencies**: `.htaccess` update | **Effort**: 2–3 hours | **Modifies Existing Code**: Yes.

---

## Priority 2 — High (Core UX, Reliability, Schema & Architecture)

8. **Export Complete `config/db.sql` Schema + Add Indexes**
   - **Reason**: 7 of 11 tables and key `society_id` columns are missing from `config/db.sql`; `sms_users.status` enum mismatches `'rejected'`.
   - **Expected Benefit**: Reproducible local/staging environments and faster queries.
   - **Complexity**: Low | **Dependencies**: Live DB dump (`mysqldump --no-data`) | **Effort**: 2–3 hours | **Modifies Existing Code**: Yes (`config/db.sql`).

9. **Save `phone_number` in `api/guard_log_visitor.php` & Fix Guard Terminal UX**
   - **Reason**: Visitor phone number is collected by UI and queried by APIs but never inserted (`BUG-03`); Pending/Denied visitors show `"Exited -"` (`BUG-05`); guard table has no polling (`BUG-12`).
   - **Expected Benefit**: Complete visitor contact records and real-time gate updates.
   - **Complexity**: Medium | **Dependencies**: None | **Effort**: 4–5 hours | **Modifies Existing Code**: Yes.

10. **Fix Fast-Track Guest Pass PIN Entry Workflow**
    - **Reason**: PINs are marked `'Used'` before entry submission and still trigger resident approval (`BUG-06`).
    - **Expected Benefit**: True frictionless fast-track entry for pre-approved guests.
    - **Complexity**: Medium | **Dependencies**: `guard_verify_pin.php`, `guard_log_visitor.php` | **Effort**: 4 hours | **Modifies Existing Code**: Yes.

11. **Wire Resident Dashboard Notice Board & Financials Navigation**
    - **Reason**: Resident notice board is hardcoded (`BUG-07`), and there is no link to `/resident/financials` (`BUG-08`).
    - **Expected Benefit**: Residents actually receive admin notices and can access bill payments.
    - **Complexity**: Low | **Dependencies**: `api/get_notices.php` | **Effort**: 2–3 hours | **Modifies Existing Code**: Yes.

12. **Compress Guard Visitor Photos & Move Uploads Out of Webroot**
    - **Reason**: Uncompressed PNGs already consume 32.8 MB for 44 files and are publicly readable.
    - **Expected Benefit**: 90%+ storage/bandwidth savings and visitor photo privacy.
    - **Complexity**: Medium | **Dependencies**: Authenticated `api/get_visitor_photo.php` | **Effort**: 4–6 hours | **Modifies Existing Code**: Yes.

13. **Disable Public Error Leakage & Add Security Headers + Rate Limiting**
    - **Reason**: Raw SQL errors (`$e->getMessage()`), `display_errors=1`, wildcard CORS, and missing rate limits (`SEC-08`–`SEC-11`).
    - **Expected Benefit**: Hardens production posture against brute force and info disclosure.
    - **Complexity**: Medium | **Dependencies**: Shared error handler & rate-limit table/cache | **Effort**: 6–8 hours | **Modifies Existing Code**: Yes.

---

## Priority 3 — Medium (Maintainability, Performance, Accessibility, Responsive Polish)

14. **Centralize Shared API Bootstrap & Frontend Layout Partials**
    - **Reason**: Auth checks, JSON headers, CSRF, and Tailwind `<head>` blocks are copy-pasted across ~40 files.
    - **Expected Benefit**: Cuts duplication and prevents inconsistent security checks.
    - **Complexity**: Medium | **Dependencies**: Refactor `includes/` | **Effort**: 6–8 hours | **Modifies Existing Code**: Yes.

15. **Compile Tailwind CSS Locally & Fix PWA Manifest/Icons**
    - **Reason**: Runtime `cdn.tailwindcss.com` slows mobile rendering; missing `icon.png`/`badge.png` and broken `/pwa/sw.js` reference (`BUG-08`).
    - **Expected Benefit**: Reliable offline PWA installation and faster page loads.
    - **Complexity**: Low–Medium | **Dependencies**: Tailwind CLI & PNG icon assets | **Effort**: 3–4 hours | **Modifies Existing Code**: Yes.

16. **Add `is_current_occupant = 1` Filtering, Pagination & Visibility-Aware Polling**
    - **Reason**: Prevent duplicate unit rows (`api/login.php`, `admin_get_users.php`), unbounded table queries, and wasted background polling (`resident/dashboard.php`).
    - **Expected Benefit**: Predictable query performance as society size grows.
    - **Complexity**: Medium | **Dependencies**: None | **Effort**: 5–6 hours | **Modifies Existing Code**: Yes.

17. **Responsive & Accessibility Improvements**
    - **Reason**: Replace `grid-cols-2` cramping on `register.php`, convert mobile tables to card layouts, add `aria-label` attributes, and guard `Notification` API checks (`BUG-11`).
    - **Expected Benefit**: Smooth mobile experience on 320px–420px phones and iOS Safari compatibility.
    - **Complexity**: Medium | **Dependencies**: None | **Effort**: 6–8 hours | **Modifies Existing Code**: Yes.

---

## Priority 4 — Low / Future Enhancements

18. **Real Password Reset & Forced Guard Password Rotation**
    - **Reason**: `forget-password.php` is currently a static mock and guards start with `'guard123'`.
    - **Complexity**: High | **Dependencies**: SMTP/mail provider (`phpmailer` or API) + `password_resets` table | **Effort**: 1–2 days | **Modifies Existing Code**: Yes.

19. **Payment Gateway Integration (Razorpay / Stripe) & Receipts**
    - **Reason**: `resident_pay_invoice.php` currently trusts client clicks with no real payment verification.
    - **Complexity**: High | **Dependencies**: Gateway SDK, webhook endpoint, `sms_transactions` table | **Effort**: 2–4 days | **Modifies Existing Code**: Yes.

20. **Automated PHPUnit & Playwright Test Suite + CI Pipeline**
    - **Reason**: Zero automated tests currently exist.
    - **Complexity**: Medium–High | **Dependencies**: PHPUnit, Playwright, test DB | **Effort**: 2–3 days | **Modifies Existing Code**: Minimal (adds `tests/`).

21. **Multi-Society Super Admin Console, Audit Logs & Analytics**
    - **Reason**: Currently no UI exists to create new societies (`sms_societies`) or track admin/guard audit trails.
    - **Complexity**: High | **Dependencies**: New modules & `sms_audit_logs` table | **Effort**: 3–5 days | **Modifies Existing Code**: Yes.

---

## Roadmap Categorized by Engineering Track

1. **Fix Existing Problems (Bugs)**: Items #2 (`update_visitor_status.php` `'Approved'` vs `'Entered'`), #3 (Guard redirect), #4 (`s.society_name`), #9 (`phone_number`, Guard status badges/polling), #10 (Fast-track PIN flow), #11 (Resident Notice Board & `/resident/financials` link), #7 (Remove broken `residents.php`, `auth/logout.php`, mock `resident/` prototypes).
2. **Improve Existing Functionality**: Add search/filter/pagination to Admin Directory (`users.php`), edit/delete/void for notices & invoices, guard deactivation/password reset in `guards.php`, complaint descriptions in `resident/dashboard.php`.
3. **Improve Architecture**: Centralize API auth/CSRF/JSON bootstrap (#14), enforce middleware for all routes in `routes.php`, move `modules/` & `config/` outside webroot, unify JSON response schemas (`status`/`data`), export full `schema.sql` (#8).
4. **Improve UI/UX**: Add Google Fonts to Admin pages, replace native `alert()`/`confirm()` with toast/modals, fix `markExit()` error recovery, convert mobile tables to card views, add `aria-label` & `for` attributes (#17).
5. **Improve Security**: Move/rotate DB & VAPID secrets (#1), fix unauthenticated `update_visitor_status.php` (#2), escape all `innerHTML` outputs (#5), enforce `role_id` & CSRF across all APIs + `session_regenerate_id()` + secure cookies (#6), validate & relocate uploads (#12), add rate limiting & headers (#13).
6. **Improve Performance**: Downscale/compress visitor photos to JPEG (#12), compile Tailwind CSS locally (#15), make `guard_get_visitors.php` date filter sargable & add indexes (#16), pause polling on hidden tabs, move push sending async.
7. **Add New Features**: See *Future Feature Suggestions* below.
8. **Improve Developer Experience**: Add `.env.example`, `config/db.example.php`, Composer scripts (`php -l`, `phpunit`, `phpstan`), and full `docs/` maintenance.
9. **Improve Deployment & Maintenance**: HTTPS enforcement, `robots.txt`, `.htaccess` protection for sensitive dirs, automated DB backups, structured error logging.

---

## Future Feature Suggestions (Product Roadmap)

| Feature Name | Purpose | User Benefit | Business Value | Required Modules | Dependencies | Complexity | Priority |
|---|---|---|---|---|---|---|---|
| **Verified Payment Gateway & PDF Receipts** | Replace simulated "Pay Now" with Razorpay/Stripe checkout + webhook verification | Residents pay maintenance dues online with downloadable receipts | Eliminates manual reconciliation and enables real SaaS monetization | `resident/financials`, `api/payment_*`, `admin/financials` | Payment gateway SDK, `sms_transactions` table | High | High |
| **Token-Based Password Reset & Guard Onboarding** | Email/OTP password recovery + forced password change from `'guard123'` | Eliminates admin intervention for forgotten passwords & secures guard logins | Prevents account lockouts and removes default-password risk | `auth/forgot-password`, `api/password_reset_*` | SMTP / transactional email API, `sms_password_resets` table | Medium–High | High |
| **Daily Help & Frequent Visitor Management** | Register maids, drivers, and cooks with society-wide passcodes & entry/exit alerts | Residents know when domestic staff arrive/leave without gate calls | High daily engagement driver for residential communities | `admin/staff`, `guard/dashboard`, `resident/dashboard` | `sms_domestic_staff` & attendance tables | Medium | Medium |
| **Vehicle & Parking Slot Registry** | Track resident vehicle plates/stickers and visitor parking slots | Guards quickly verify authorized vehicles; admins resolve parking disputes | Improves gate throughput and society compliance | `admin/parking`, `resident/vehicles`, `guard/dashboard` | `sms_vehicles` table | Medium | Medium |
| **Amenity & Clubhouse Booking** | Allow residents to reserve clubhouse, tennis court, or guest rooms with approval/fee rules | Fair, self-service slot booking without committee phone calls | Increases resident adoption and optional booking revenue | `resident/amenities`, `admin/amenities` | `sms_amenities`, `sms_bookings` tables | Medium | Low |
| **Multi-Society Super Admin & Audit Logs** | Platform dashboard to onboard societies, assign initial admins, and audit sensitive actions | Enables SaaS operator to onboard new societies without manual SQL inserts | Unlocks scalable multi-tenant B2B onboarding & compliance | `superadmin/`, `includes/audit_logger.php` | `sms_audit_logs` table, Role `1` separation | High | Medium |

