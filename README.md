# SecureGate — Residential Society & Gate Security Management System

[![Build & Deploy](https://github.com/shopquare/society_app/actions/workflows/ci-cd.yml/badge.svg)](https://github.com/shopquare/society_app/actions/workflows/ci-cd.yml)
[![Tests Passing](https://img.shields.io/badge/Tests-99%2F99%20Passing-success)](tests/run_tests.php)
[![Version](https://img.shields.io/badge/Release-v1.3.0-blue)](apk/SecureGate.apk)
[![Android APK](https://img.shields.io/badge/Android-APK%20Ready-emerald)](download.php)
[![Timezone](https://img.shields.io/badge/Timezone-IST%20(UTC%2B05:30)-orange)](config/db.php)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

**SecureGate** is an enterprise-grade, multi-tenant residential gated community and security terminal platform built with PHP 8.2+, MySQL, Tailwind CSS, Web Push PWA, and a companion **native Android App** (Kotlin + Jetpack Compose). 

Designed for Indian residential housing societies, it digitizes gate security, resident visitor authorization, delivery pre-approvals, vehicle logs, maintenance billing, and incident response.

---

## 📱 Mobile App (Android APK)

Residents can receive continuous loud incoming gate calls that wake their phone screen over the lock screen and allow instant 1-tap approvals:

* **Direct Download**: [Download SecureGate APK (v1.3.0)](https://society.infy.click/download/apk)
* **Version**: `v1.3.0` (Build 13)
* **Architecture**: Kotlin, Jetpack Compose, OkHttp, Coil image loader, Foreground WakeLock Service, AlarmStream Audio.
* **Compatibility**: Android 7.0 (API 24) to Android 15 (API 36).

---

## 🌟 Key Features

### 1. Gatekeeper Terminal (`/guard/dashboard`)
* **Integrated In-App WebRTC Camera**: Front & rear camera switcher directly embedded in the browser/terminal. Zero native camera or gallery redirects.
* **Separated Visitor Name & Category Tags**: Visitor Name and quick company tags (Swiggy, Zomato, Amazon, Blinkit, Zepto, Flipkart, Cab, Guest, Service) are independent fields.
* **10MB Photo Upload & 10-Digit Mobile Validation**: Server-side binary MIME validation supporting up to 10MB JPEG/PNG/WebP and Indian phone normalization (`^[6-9]\d{9}$`).
* **Emergency SOS Response Hub**: Prominent audio siren banner with 1-tap resident calling, Google Maps navigation, and multi-stage status workflow (`Active`, `Acknowledged`, `Responding`, `Resolved`, `Cancelled`).
* **Real-Time Resident Decision Popups**: Live audio chime feedback on the terminal (ascending chime on approval, buzzer on denial) without page reloads.
* **Overstay Alerts**: Automatically flags service personnel and delivery agents remaining in the complex for >45 minutes.

### 2. Resident Experience (`/resident/dashboard`)
* **Third-Party Brand Deals & Offers Hub (MyGate-Style)**: Dedicated Offers & Deals modal with category filtering (Food, Grocery, Shopping, Healthcare, Services), featuring brand promotions (Zomato, Swiggy, Blinkit, Zepto, Amazon, local merchants) with 1-tap ordering and direct advertiser calling.
* **Sponsored Brand Carousel**: Sleek horizontal brand strip on the dashboard highlighting active discount tags and partner offers.
* **Guest Gate Pass with 48-Hour Auto-Expiry & WhatsApp Sharing**: Resident-generated 6-digit passes auto-expire in exactly 48 hours (server-side IST). 1-tap WhatsApp click-to-chat sharing URL with sanitized formatting.
* **Enhanced SOS Panic Response**: 6 incident categories (Medical, Fire, Security, Accident, Theft, Other) with confirmation modal, optional notes, and real-time GPS coordinate logging.
* **Facilities & Amenities Booking**: Reserve Clubhouse, Swimming Pool, Tennis Court, Banquet Hall, and Gym with double-booking collision prevention.
* **Domestic Help & Staff Directory**: Verified Maid, Driver, Cook, Cleaner directory with 1-tap mapping and gate status.
* **Family Members Roster & Society Handbook**: Add co-residents with emergency contact flags and read society rules.
* **Society Marketplace (Buy & Sell)**: Intra-society classifieds with multi-photo uploads (up to 5), condition badges, and direct WhatsApp seller chat.
* **Responsive Navigation**: Collapsible desktop sidebar and mobile off-canvas drawer with smooth quick-jump transitions.

### 3. Committee & Super Admin Portal (`/admin/dashboard`)
* **Company Advertisement Management (`/admin/advertisements`)**: Complete multi-tenant advertising portal to create, schedule, edit, pause, and preview brand campaigns with logo & banner uploads, target audience filtering, placement selection, and live mobile preview.
* **Campaign Analytics & Revenue**: Real-time KPI counters tracking impressions, clicks, CTR %, and advertising revenue with campaign-by-campaign engagement breakdowns.
* **Google AdSense & Society Monetization**: Native in-feed community sponsor ad banner settings and resident self-serve ad submissions with manual UPI/bank payment verification. Ads strictly excluded from gate terminals, emergency alerts, and auth screens.
* **Pending Approvals Queue**: Review, verify, and approve new resident registrations.
* **Processed Resident Directory (`/admin/users`)**: Searchable directory of all active flat occupants and historical records.
* **Automated Billing & Collections (`/admin/financials`)**: Single and bulk ("All Active Flats") invoice generation with CSV ledger export.
* **Helpdesk Kanban (`/admin/complaints`)**: Drag-and-drop 3-column ticket tracker (Open, In Progress, Resolved).
* **Security Staff Provisioning (`/admin/guards`)**: Generate and manage guard accounts with encrypted credentials.
* **Society Configuration (`/admin/settings`)**: Manage complex details, Google AdSense slots, and regenerate society invite codes.

---

## 👥 Default Roles & Credentials

All test accounts are pre-seeded in the database across both local and live deployments:

| Role | Email / Username | Password | Notes / Assigned Flat |
| :--- | :--- | :--- | :--- |
| **Super Admin** | `admin@demo.com` | `password` | Complete society governance & financials |
| **Committee** | `committee@demo.com` | `password` | Resident onboarding & helpdesk resolution |
| **Security Guard** | `guard@demo.com` | `password` | Gatekeeper Terminal (`Ramesh on Duty`) |
| **Resident 1** | `resident@demo.com` | `password` | Unit **101** & Unit **B-205** (John Resident) |
| **Resident 2** | `yarun520@gmail.com` | `password` | Unit **A-101** (Arun Yadav) |
| **Resident 3** | `yarun037@gmail.com` | `Fateh@123` | Unit **B-801** (Fateh Singh) |
| **Resident 4** | `bosdikee@gmail.com` | `password` | Unit **A-32** (Bosdiee Kee) |
| **Resident 5** | `laundarirani@gmail.com` | `12345` | Unit **C-H** (Laundri Rani) |
| **Resident 6** | `ohhyehhbaby@ohh.com` | `12345` | Unit **B-Ty** (BABY) |

*Default Society Invite Code for new registrations*: **`SUNSHINE2026`**

---

## 🏗️ Architecture & Technology Stack

```
                        SecureGate System Architecture
 ┌────────────────┐    ┌─────────────────┐    ┌──────────────────────────┐
 │  Android App   │    │  Mobile Chrome  │    │ Desktop Web / Gate Tablet│
 │ (Kotlin/Compose│    │  (Web Push PWA) │    │      (Tailwind CSS)      │
 └───────┬────────┘    └────────┬────────┘    └────────────┬─────────────┘
         │                      │                          │
         └───────────────┬──────┴──────────────────────────┘
                         ▼
        ┌──────────────────────────────────┐
        │       Front Controller           │
        │   routes.php & .htaccess         │
        └────────────────┬─────────────────┘
                         ▼
        ┌──────────────────────────────────┐
        │       Security Middleware        │
        │ api_bootstrap.php & csrf.php     │
        │ (Session, HMAC Tokens, Auth, IST)│
        └────────────────┬─────────────────┘
                         ▼
        ┌──────────────────────────────────┐
        │  PHP 8.2+ Backend (PDO MySQL)    │
        │  Modules: Guard, Resident, Admin │
        └────────────────┬─────────────────┘
                         ▼
        ┌──────────────────────────────────┐
        │  MySQL Database (17 Tables)      │
        │  Auto-Migrator & Auto-Seeder     │
        └──────────────────────────────────┘
```

* **Backend**: PHP 8.2+ with PDO `utf8mb4` prepared statements.
* **Frontend**: Vanilla JavaScript (ES6+), Web Audio API, Tailwind CSS, Google Fonts (`Inter`, `Playfair Display`).
* **Database**: MySQL 8.0 / MariaDB with automatic schema migrations (`includes/db_migrator.php`).
* **Timezone**: Strict **Indian Standard Time** (`Asia/Kolkata`, UTC+05:30) synchronized across backend PHP, MySQL session, and client JS.
* **CI/CD**: Automated GitHub Actions testing (`tests/run_tests.php`) and FTP deployment to production (`society.infy.click`).

---

## 🚀 Quick Setup & Installation

### Local Development (XAMPP on Windows)

1. **Clone the repository**:
   ```bash
   git clone https://github.com/shopquare/society_app.git c:/xampp/htdocs/society_app
   cd c:/xampp/htdocs/society_app
   ```

2. **Configure Environment**:
   Copy `.env.example` to `.env`:
   ```bash
   copy .env.example .env
   ```
   Set your local database credentials:
   ```env
   APP_ENV=local
   APP_DEBUG=true
   APP_TIMEZONE=Asia/Kolkata
   APP_BASE_PATH=/society_app
   APP_URL=http://localhost/society_app
   DB_HOST=localhost
   DB_NAME=u480171677_demosocietyapp
   DB_USER=root
   DB_PASS=
   ```

3. **Install Dependencies & Seed Database**:
   ```bash
   composer install
   ```
   The database tables (17 tables) and all resident accounts are **automatically created and seeded** on the very first page visit via `includes/db_migrator.php`.

4. **Run Invariant Tests**:
   ```bash
   php tests/run_tests.php
   ```
   *Expected result: `40 passed, 0 failed`.*

5. **Access the Portal**:
   Open [http://localhost/society_app/login](http://localhost/society_app/login) in your browser.

---

## 📚 Complete Technical Documentation

Comprehensive architectural guides and operational references are available under [`docs/`](docs/):

* [**Documentation Index**](docs/README.md) — Master index and guide directory.
* [**System Architecture**](docs/ARCHITECTURE.md) — Front-controller design and system components.
* [**Feature Inventory**](docs/FEATURES.md) — Exhaustive feature matrix and status tracking.
* [**API Documentation**](docs/API_DOCUMENTATION.md) — Reference for all JSON endpoints under `api/`.
* [**Database Schema**](docs/DATABASE_DOCUMENTATION.md) — 17 tables, foreign keys, indexes, and ER diagram.
* [**User Roles & Security Matrix**](docs/USER_ROLES_AND_PERMISSIONS.md) — Permissions, route guards, and credentials.
* [**Workflows & Sequence Diagrams**](docs/WORKFLOWS.md) — Mermaid sequence diagrams for visitor approvals and PIN flows.
* [**CI/CD & Deployment Guide**](docs/CI_CD_GUIDE.md) — GitHub Actions to InfinityFree FTP deployment.
* [**Installation Guide**](docs/INSTALLATION_GUIDE.md) — Step-by-step setup for XAMPP and Linux LAMP.
* [**Testing & QA Guide**](docs/TESTING_GUIDE.md) — Invariant test suite and validation instructions.
* [**Changelog**](docs/CHANGELOG.md) — Historical record of versions from v1.0.0 to v1.1.0.

---

## 🔒 Security & Privacy Features

* **No Hardcoded Credentials**: Externalized configuration via `.env` with `.htaccess` directory blocks.
* **CSRF Protection**: Cryptographically secure 64-character tokens validated on all state-changing mutations.
* **Tokenized Visitor Photos**: Direct file traversal blocked; photos streamed via HMAC-signed time-limited tokens.
* **Strict Session Management**: `HttpOnly`, `SameSite=Lax`, and `session_regenerate_id()` on role transitions.
* **Anti-Spam Push Compliance**: Web push payloads conform to Android Chrome 128+ anti-abuse specifications.

---

## 📄 License
This project is licensed under the MIT License — see the [LICENSE](LICENSE) file for details.
