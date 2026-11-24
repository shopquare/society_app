# Project Overview

## Project Name
**SocietyHub / Society Management System** (Directory: `society_app`)

## Project Purpose & Problem Solved
SocietyHub is a multi-tenant residential housing society and gated-community management web application with Progressive Web App (PWA) support. It digitizes day-to-day housing society operations across three distinct personas—Committee Administrators, Residents, and Gate Security Guards—replacing manual visitor registers, paper maintenance billing, physical notice boards, and ad-hoc complaint tracking.

## Target Users
1. **Super Admin / Society Committee Members (`role_id` 1 & 2)**: Manage resident onboarding approvals, publish society notices, generate single or bulk maintenance invoices, track financial collections, provision security guard accounts, resolve helpdesk complaints, and manage society profile/invite codes.
2. **Residents (`role_id` 3 — Owners & Tenants)**: Register using a society invite code, approve or deny walk-in visitors at the gate in real time (via polling and Web Push notifications), generate 6-digit pre-approved guest passes, view maintenance bills, submit payments, and raise maintenance complaints.
3. **Security Guards (`role_id` 4)**: Operate the Gatekeeper Terminal to capture visitor photos via device camera, log walk-in entries, verify 6-digit fast-track guest PINs, track overstaying delivery/cab/service visitors, and mark visitor exits.

## Core Functionality
- **Multi-Tenant Onboarding & Access Control**: Society invite-code verification (`sms_societies`), multi-step resident registration, and committee approval workflow (`pending` → `active` / `rejected`).
- **Gate & Visitor Management**: Guard camera photo capture, real-time resident gate alerts (audio chime + vibration + Web Push notifications with Approve/Deny actions), 6-digit fast-track guest PINs, and overstay alerts (>45 minutes).
- **Maintenance Billing & Financials**: Individual and bulk ("All Active Units") invoice generation, automatic overdue status calculation, status filtering, CSV export, and resident invoice payment flow (currently simulated/direct DB status update).
- **Helpdesk & Complaints**: Resident ticket submission by category and a 3-column Admin Kanban board (`Open`, `In Progress`, `Resolved`).
- **Society Notice Board**: Admin notice publishing (backend/admin side implemented; resident dashboard currently displays a static placeholder).
- **PWA & Web Push**: Service worker (`sw.js`) and Web Push integration via `minishlink/web-push`.

## Technology Stack
- **Backend**: PHP (PDO with `utf8mb4`), Apache `.htaccess` URL rewriting (`routes.php` front controller).
- **Database**: MySQL / MariaDB.
- **Frontend**: HTML5, Tailwind CSS (CDN), Vanilla JavaScript (`fetch`, WebRTC `getUserMedia`, Canvas, Web Audio API, Service Worker / Push API), Google Fonts (`Inter`, `Playfair Display`).
- **Dependencies (Composer)**: `minishlink/web-push` (`^11.0`), `php-http/guzzle7-adapter` (`^1.1`).

## System Requirements
- Apache HTTP Server with `mod_rewrite` enabled (configured for `/society_app/`).
- PHP 8.2+ (matching `composer.lock` dependency requirements such as `brick/math` `^8.2`) with `pdo_mysql`, `openssl`, `curl`, `json`, and `mbstring` extensions.
- MySQL / MariaDB database server.
- Composer (for managing `vendor/`).
- HTTPS (required in production for Camera `getUserMedia` and Service Worker / Web Push APIs; `localhost` works in development).

## Current Development Status
**Active Prototype / Pre-Production MVP.** Core workflows for authentication, admin approvals, billing, complaints, guard visitor logging, and resident push notifications are implemented, but the codebase contains critical bugs (e.g., guard login redirect mismatch, hardcoded resident notice board, status mismatch between Service Worker and Guard exit logic), unused/mock prototype files (`modules/resident/login.php`, `register.php`, `forget-password.php`, `modules/admin/residents.php`), hardcoded secrets, and missing security hardening. It is **not ready for production deployment** without remediation.

---

## Plain-English Explanation (For Non-Technical Stakeholders)

### What Does This Software Do?
SocietyHub is an online portal and mobile-installable web app for residential apartment complexes (housing societies). Think of it as a digital front desk and community office that connects three groups of people:
1. **The Society Committee (Admins)** — manage who lives in the building, send out maintenance bills, post announcements, hire/create accounts for security guards, and track resident repair complaints.
2. **Residents (Owners & Tenants)** — sign up using their building's invite code, get instant ring/pop-up alerts on their phone or computer when a delivery person or guest arrives at the gate, create 6-digit guest entry PINs in advance, view their maintenance bills, and report maintenance problems.
3. **Security Guards** — use a gate tablet or phone to snap a photo of visitors, enter their details, verify guest PINs, see if a delivery or cab has stayed too long (over 45 minutes), and record when visitors leave.

### How It Works End-to-End
1. A new resident signs up with a secret society code (e.g., from the committee) and enters their apartment number.
2. A committee admin reviews the request and clicks **Approve** so the resident can log in.
3. When a visitor arrives at the gate, the guard takes a photo and selects the resident's apartment. The resident's phone/browser rings and shows a pop-up asking them to **Approve** or **Deny** entry.
4. For expected guests, residents can create a 6-digit PIN ahead of time and text it to their guest to show the guard.
5. Admins can issue monthly maintenance bills to everyone at once and manage plumbing/electrical/housekeeping complaints on a task board.

---

## Feature Readiness Breakdown (Code-Verified)
- **Fully Implemented (Working Core)**: Society invite-code verification, 3-step resident registration, admin pending approvals, admin resident directory, guard account creation, admin notice publishing, single/bulk invoice generation + CSV export, helpdesk Kanban board, resident complaint submission, resident gate-alert polling + audio chime, and guest PIN generation.
- **Partially Implemented / Defective**:
  - *Login Redirects*: Works for Admins & Residents; broken for Guards (sent to Resident dashboard).
  - *Guard Visitor Logging*: Logs visitor + photo + push alert, but silently drops `phone_number`, shows Pending/Denied visitors as `"Exited -"`, and does not auto-refresh when residents approve.
  - *Web Push Approve/Deny*: Push sends and SW receives clicks, but the API has no authentication and writes `'Approved'` instead of `'Entered'`.
  - *Fast-Track Guest PIN*: Verifies PIN and prefills form, but burns the PIN immediately and still forces resident re-approval.
  - *Resident Bill Payment*: Works as a simulated DB toggle (`Paid`), with no real payment gateway.
- **Placeholder Features**: Resident Notice Board (`modules/resident/dashboard.php`) is hardcoded static HTML (`Water Supply Interruption`).
- **UI Without Working Backend / Dead**: `modules/auth/login.php` ("Remember me" & "Forgot Password?"), `modules/resident/login.php`, `register.php`, `forget-password.php`, `modules/admin/residents.php`, `modules/auth/logout.php`.
- **Backend / Page Without UI Link**: `modules/resident/financials.php` (`/resident/financials`) and `api/get_notices.php` (for residents) exist and work, but `modules/resident/dashboard.php` has no link or fetch call to them.

