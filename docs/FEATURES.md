# Feature Inventory — SecureGate (v1.1.0)

## Role Legend
- **Roles 1 & 2**: Super Admin / Committee Member
- **Role 3**: Resident (Owner / Tenant)
- **Role 4**: Security Guard

---

## Production Feature Matrix

| Feature | Primary Location | Purpose | User Role | Status | Security / Enhancements |
|---|---|---|---|:---:|---|
| **Society Invite Verification** | `api/verify_society.php`, `modules/auth/register.php` | Validates secret invite code before signup | Public | **Implemented** | Rate-limited fixed-window checks |
| **Multi-Step Resident Registration** | `modules/auth/register.php`, `api/register.php` | 3-step signup creating pending resident account | Public | **Implemented** | CSRF-protected transactional insert |
| **User Login & Session Routing** | `modules/auth/login.php`, `api/login.php` | Secure authentication with strict role dashboard routing | All Roles | **Implemented** | `session_regenerate_id()`, `HttpOnly` cookies |
| **User Logout** | `api/logout.php` | Destroys session with token rotation | All Roles | **Implemented** | CSRF-protected POST logout |
| **Pending Approvals Queue** | `modules/admin/dashboard.php`, `api/admin_get_pending.php` | Approve or reject pending residents | Admin (1, 2) | **Implemented** | Filters `WHERE status = 'pending'` |
| **Resident Directory** | `modules/admin/users.php`, `api/admin_get_users.php` | Complete directory of active residents | Admin (1, 2) | **Implemented** | Instant client-side search & filtering |
| **Security Guard Provisioning** | `modules/admin/guards.php`, `api/admin_create_guard.php` | Provision guard terminal accounts | Admin (1, 2) | **Implemented** | Cryptographic passwords, audit logging |
| **Society Notice Board** | `modules/admin/notices.php`, `api/get_notices.php` | Broadcast society announcements | Admin & Residents | **Implemented** | Real-time live notices feed |
| **Admin Maintenance Billing** | `modules/admin/financials.php`, `api/admin_create_invoice.php` | Single and bulk billing + CSV ledger export | Admin (1, 2) | **Implemented** | Automatic overdue calculation |
| **Resident Billing & Invoices** | `modules/resident/financials.php`, `api/resident_get_invoices.php` | View maintenance dues and payment history | Resident (3) | **Implemented** | Clean PDF/print invoice ledger view |
| **Helpdesk & Complaints** | `modules/admin/complaints.php`, `modules/resident/dashboard.php` | Issue tracking with 3-column Kanban board | Admin & Residents | **Implemented** | Category selection, sanitized HTML |
| **Society Settings** | `modules/admin/settings.php`, `api/admin_reset_invite.php` | Complex profile & 8-char invite code generator | Admin (1, 2) | **Implemented** | Cryptographic invite code generation |
| **Guard Visitor Entry & Camera** | `modules/guard/dashboard.php`, `api/guard_log_visitor.php` | Live camera photo capture and entry logging | Guard (4) | **Implemented** | Tokenized photo stream, Indian Standard Time |
| **Live Guard Audio Popups** | `modules/guard/dashboard.php` | Audible chime & popup on resident decision | Guard (4) | **Implemented** | Two-tone Web Audio API (bell vs buzzer) |
| **Fast-Track Delivery Tags** | `modules/guard/dashboard.php` | 1-tap buttons for Swiggy, Zomato, Amazon, Blinkit | Guard (4) | **Implemented** | Quick courier presets |
| **Guard Visitor Pagination** | `modules/guard/dashboard.php` | 20-row pagination with instant search | Guard (4) | **Implemented** | High-performance client-side paging |
| **Resident Full-Screen Gate Alert** | `GateAlertActivity.kt` (Android APK) | Continuous loud ringtone over lockscreen | Resident (3) | **Implemented** | WakeLock, AlarmStream, 1-tap Allow/Deny |
| **Web Push Gate Alerts** | `sw.js`, `api/guard_log_visitor.php` | Browser push notifications with Approve/Deny | Resident (3) | **Implemented** | Anti-spam compliant (Chrome 128+) |
| **Delivery Pre-Approvals** | `modules/resident/dashboard.php`, `api/resident_save_delivery_preapproval.php` | 1-tap pre-cleared passes for couriers | Resident (3) | **Implemented** | 2-24hr expiry tracking |
| **Guest PIN Passes** | `modules/resident/dashboard.php`, `api/resident_generate_pin.php` | 6-digit entry PINs for expected guests | Resident (3) & Guard (4) | **Implemented** | Auto-validation at security terminal |
| **Emergency Panic Alarm** | `modules/resident/dashboard.php`, `api/resident_trigger_emergency.php` | Instant red alert broadcast to gate & admin | Resident (3) | **Implemented** | High-priority security incident log |
| **Gate Parcel Locker** | `modules/guard/dashboard.php`, `api/guard_parcels.php` | Track held parcels with resident PIN pickup | Guard & Resident | **Implemented** | Status tracking with audit log |
| **Resident Vehicle Directory** | `modules/resident/dashboard.php`, `api/resident_vehicles.php` | License plate registration & slot mapping | Resident (3) | **Implemented** | Normalized license plate indexing |
| **Immutable Audit Logs** | `modules/admin/dashboard.php`, `api/admin_get_audit_logs.php` | Security audit trail of all gate & admin actions | Admin (1, 2) | **Implemented** | Actor ID, IP address, timestamp |
| **Cross-Environment Seeding** | `includes/db_migrator.php` | Auto-seed registered units across environments | System | **Implemented** | Pre-configures Units 101, A-101, B-801, A-32, C-H, B-Ty |
