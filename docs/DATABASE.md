# SecureGate v2 Database Schema & Architecture (`securegate_v2`)

## Overview
SecureGate runs on MySQL 8.0+ / MariaDB 10.4+ with InnoDB (`utf8mb4_unicode_ci`) using native PDO (`ATTR_EMULATE_PREPARES => false`). All multi-tenant tables enforce society isolation via `society_id INT NOT NULL` referencing `sms_societies(id)`.

## Core & v2 Migration Architecture (`includes/migrations/v2_migrations.php`)
Migrations run idempotently through `ensureSchemaUpgrades()` hooked into `config/db.php` / `includes/db_migrator.php`:
1. **Phase 0 — Permissions, Notifications & Buildings**
   - `sms_permissions`, `sms_admin_roles`, `sms_role_permissions`, `sms_user_admin_roles`
   - `sms_notifications`, `sms_notification_preferences`, `sms_notification_deliveries`
   - `sms_buildings`, `sms_units`
2. **Phase 1 — Multi-Gate Security & Shift Handovers**
   - `sms_gates`, `sms_gate_assignments`, `sms_gate_handovers`, `sms_gate_incidents`
   - Added `gate_id` on `sms_visitors`, `sms_gate_parcels`, `sms_emergency_alerts`
3. **Phase 2 — Community & Governance**
   - `sms_polls`, `sms_poll_options`, `sms_poll_voters` (`UNIQUE (poll_id, user_id)`), `sms_poll_votes` (strict `NULL` voter ID when `is_anonymous = 1`)
   - `sms_events`, `sms_event_registrations`, `sms_event_photos`
   - `sms_lost_found`, `sms_lost_found_claims`
   - `sms_notice_views`, `sms_suggestions`
   - `sms_discussions`, `sms_discussion_comments`, `sms_discussion_likes`, `sms_discussion_reports`, `sms_discussion_restrictions`
   - `sms_resident_privacy_settings`
4. **Phase 3 — Facility Booking Engine**
   - `sms_society_facilities` (extended with quotas, slot duration, refund cutoff hours, maintenance mode)
   - `sms_facility_bookings` (locking via `SELECT ... FOR UPDATE`), `sms_facility_blocked_dates`
5. **Phase 4 — Financial Suite & Accounting**
   - `sms_maintenance_rules`, `sms_payments`, `sms_society_income`, `sms_society_expenses`, `sms_vendors`, `sms_vendor_payments`, `sms_budgets`
   - Extended `sms_invoices` (`invoice_number`, `base_amount`, `penalty_amount`, `paid_amount`, `balance_amount`, `rule_id`)
6. **Phase 5 — Local Services Directory**
   - `sms_service_categories` (17 categories seeded), `sms_service_providers`, `sms_service_reviews` (`UNIQUE (provider_id, resident_id)`), `sms_service_favorites`, `sms_service_reports`
7. **Phase 6 — Staff Attendance & Tenant KYC**
   - `sms_staff_attendance`
   - Extended `sms_user_units` (`verification_status`, `lease_end_date`, `kyc_notes`)
8. **Phase 8 — Digital Society Office & Document Vault**
   - `sms_documents` (pointing strictly to `uploads/private_docs/...`), `sms_document_requests`
9. **Phase 9 — Multi-Society SaaS Platform**
   - `sms_subscription_plans` (`free`, `basic`, `standard`, `premium`, `enterprise`)
   - `sms_society_subscriptions`, `sms_saas_invoices`, `sms_platform_tickets`
   - Extended `sms_users.is_platform_admin` and `sms_societies.status`
