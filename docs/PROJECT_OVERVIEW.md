# Project Overview — SecureGate

## Project Name
**SecureGate** (Directory: `society_app`, Package: `com.shopquare.societyhub`)

## Project Purpose & Problem Solved
SecureGate is a modern multi-tenant residential housing society and gated-community security management application. It unites Committee Administrators, Residents, and Gate Security Guards into a seamless digital ecosystem, replacing manual paper visitor logbooks, physical notice boards, and unverified phone calls.

The platform provides a **native Android Application** that wakes up the resident's mobile phone over the lock screen with continuous loud ringers whenever a visitor arrives at the security gate, ensuring deliveries (Swiggy, Zomato, Amazon, Blinkit) and guests are authorized in seconds.

---

## Target Personas & Core Workflows

1. **Super Admin & Society Committee Members (`role_id` 1 & 2)**:
   * **Resident Onboarding**: Review and approve/reject pending resident registrations.
   * **Resident Directory (`/admin/users`)**: Instant search across all active flat occupants and occupancy types (owner/tenant).
   * **Maintenance Billing (`/admin/financials`)**: Generate individual or bulk ("All Active Units") invoices, track collection statuses, auto-calculate overdue penalties, and export CSV ledgers.
   * **Helpdesk & Maintenance Complaints (`/admin/complaints`)**: 3-column Kanban workflow (`Open` → `In Progress` → `Resolved`).
   * **Security Staff Administration (`/admin/guards`)**: Create, list, and provision guard accounts with secure credentials.
   * **Society Settings (`/admin/settings`)**: Configure complex details and manage secret society registration invite codes (e.g. `SUNSHINE2026`).

2. **Residents (`role_id` 3 — Owners & Tenants)**:
   * **Native Mobile Gate Alerts**: Full-screen caller UI on Android over lockscreen with ringtones, vibration, visitor photos, and 1-tap `✓ ALLOW ENTRY` / `✕ DENY`.
   * **Delivery Pre-Approvals**: Create auto-approved gate passes for delivery companies (Swiggy, Zomato, Amazon, Blinkit, Zepto, Flipkart) valid for 2 to 24 hours.
   * **Fast-Track Guest Passes**: Generate 6-digit PIN codes for expected friends and family.
   * **Emergency Panic Alarm**: Red alert trigger instantly alerting the gate terminal and admin console in case of medical or security emergencies.
   * **Notice Board & Billing**: Review society announcements and check maintenance dues.
   * **Parcel Locker**: View deliveries held at the gate with pickup PIN verification.

3. **Security Guards (`role_id` 4)**:
   * **Gatekeeper Terminal (`/guard/dashboard`)**: Camera capture of visitor photos with secured streaming.
   * **Quick Delivery Tags**: 1-tap selection for popular couriers and delivery services.
   * **Live Approval Chimes**: Real-time two-tone Web Audio API chimes and animated popup cards when a resident approves or denies entry.
   * **Overstay Alerts**: Flags visitors inside the premises exceeding 45 minutes.
   * **Visitor Log History**: Fast pagination (20 entries/page) with search by visitor name, flat number, vehicle, or phone.

---

## Technology Stack

* **Backend**: PHP 8.2+ with PDO prepared statements (`utf8mb4`).
* **Database**: MySQL 8.0 / MariaDB with automated schema migrations and auto-seeding (`includes/db_migrator.php`).
* **Web Frontend**: HTML5, Tailwind CSS, Vanilla JavaScript (ES6+), Web Audio API, Service Worker Web Push (`sw.js`).
* **Mobile App**: Native Android App (Kotlin, Jetpack Compose, Material 3, OkHttp, Coil Image Loader, WakeLock).
* **Timezone**: Strict Indian Standard Time (`Asia/Kolkata`, UTC+05:30) synchronized across backend PHP, database sessions, and frontend UI.
* **CI/CD**: GitHub Actions workflow deploying to InfinityFree production FTP (`society.infy.click`).

---

## Current Release Status
**Production Ready (v1.1.0)**. All critical bugs, routing mismatches, timezone offsets, and notification anti-spam requirements have been resolved. The platform passes all 40 automated invariant tests (`tests/run_tests.php`).
