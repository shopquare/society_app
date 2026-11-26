# SecureGate v2 API Reference

All API endpoints live under `api/`, initialize via `includes/api_bootstrap.php`, enforce HTTP method whitelists, validate CSRF tokens on state mutations, and return JSON `{status, ...}` responses.

## Authentication, RBAC & Notifications
- `GET/POST /api/admin_manage_roles.php` (`roles.manage`) — Manage society admin roles & granular permission assignments (anti-escalation protected).
- `GET/POST /api/admin_manage_buildings.php` (`buildings.manage`) — Manage towers/wings & flat inventory.
- `GET/POST /api/notifications.php` (Roles 1–4) — Unified notification feed, unread count, mark read, per-category channel preferences (`CRITICAL_SECURITY_CATEGORIES` non-suppressible).

## Security & Gate Operations
- `GET/POST /api/admin_manage_gates.php` (`gates.view` / `gates.manage`) — Manage gates, guard assignments, CSV exports, and missing handover audits.
- `GET/POST /api/guard_handover.php` (Role 4 / Admin) — Start shift handover, accept/acknowledge incoming handover, trigger emergency handover, immutability once acknowledged.
- `GET/POST /api/guard_incidents.php` (Role 4 / Admin) — Log & track unresolved gate incidents across shifts.

## Community & Facilities
- `GET/POST /api/community_polls.php` — Polls & Elections with `sms_poll_voters` duplicate vote prevention & detached anonymous votes.
- `GET/POST /api/community_events.php` — Events, capacity-enforced RSVPs, cancellation & photo gallery uploads.
- `GET/POST /api/community_lost_found.php` — Lost & Found items + claim verification (zero public reporter PII).
- `GET/POST /api/community_suggestions.php` — Anonymous/attributed society suggestions & committee status tracking.
- `GET/POST /api/community_discussions.php` — Threaded forum, comments, likes, pinning, locking, moderation & user restrictions.
- `GET/POST /api/resident_directory.php` — Privacy-respecting resident directory with opt-in contact sharing & rate limiting.
- `GET/POST /api/admin_manage_facilities.php` (`facilities.manage`) & `/api/resident_facility_booking.php` — Transactional `FOR UPDATE` slot locking & cutoff refunds.

## Financials & Services
- `GET/POST /api/admin_financial_v2.php` (`finance.view` / `finance.manage`) — Auto-billing rules, late penalty engine, manual/advance/refund reconciliation, WhatsApp reminders, PDF statements, dues CSV.
- `GET/POST /api/admin_accounting.php` — Society income, expenses with vouchers, vendor contracts & payouts, annual budgets.
- `GET/POST /api/resident_payments.php` — Resident partial/full/advance payments, pure-PHP PDF receipt download (`?action=receipt_pdf`), and aggregate Society Transparency dashboard (`?action=transparency`).
- `GET/POST /api/services_directory.php` & `/api/admin_manage_services.php` — 17-category verified local services, single-review enforcement, favorites & issue reporting.

## Moderation, Comms, Document Vault & SaaS
- `GET/POST /api/admin_moderation_center.php` (`community.moderate`) — Unified Marketplace/Forum/Service report queues & Tenant Move-In/Lease KYC verification.
- `GET/POST /api/admin_manage_staff.php` (`guards.manage`) — Daily help gate passes & guard/staff shift attendance.
- `GET /api/admin_reports.php` (`reports.view`) — Filterable Security, Handovers, Financial, Overdue & Attendance reports in JSON, CSV (formula-safe), and PDF.
- `GET/POST /api/admin_broadcast.php` (`notifications.broadcast`) & `/api/cron_dispatch.php` — Multi-channel emergency broadcasts & queued WhatsApp/SMS/Email dispatcher.
- `GET/POST /api/documents_office.php` & `GET /api/stream_document.php` — HMAC-signed private document vault (`uploads/private_docs/`) & NOC/Address Proof PDF issuance.
- `GET/POST /api/register_society.php` & `/api/platform_admin.php` (`isPlatformSuperAdmin`) — Multi-tenant onboarding, subscriptions, feature quotas & SaaS invoicing.
