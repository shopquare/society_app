# Changelog

All notable changes to the SecureGate (formerly SocietyHub) application are documented in this file.

## [v1.3.0] — 2026-11-26
### Added
- **Admin-Managed Third-Party Company Advertisements (MyGate-Style)**:
  - `modules/admin/advertisements.php`: Dedicated company advertisement management terminal at `/admin/advertisements` featuring KPI summary counters (Total, Active, Scheduled, Expired, Total Impressions, Clicks, CTR %, Revenue).
  - `api/admin_manage_company_ads.php`: Full multi-tenant CRUD API supporting logo and banner image uploads, start and end date scheduling, priority weighting (1-10), target audience ('all', 'owners', 'tenants'), placement selectors, pause/resume status toggling, and audit logging.
  - `api/track_ad_event.php`: High-performance impression and click tracking engine storing granular event telemetry in `sms_advertisement_analytics` and updating live engagement counters on `sms_advertisements`.
  - `modules/resident/dashboard.php`:
    - **Quick Brand Carousel**: Horizontal scrollable company strip featuring pre-seeded verified brands (Zomato, Swiggy, Blinkit, Zepto, Amazon, Local Restaurant).
    - **Hero Promotional Banner**: Top in-feed sponsor banner with discount tags and 1-tap call-to-action buttons.
    - **Dedicated Offers & Deals Hub**: Interactive modal with category filter pills (Food & Dining, Quick Grocery, Shopping, Healthcare, Home Services) displaying promotional banners, offer details, validity countdowns, and direct "Call Advertiser" / "Order Now" buttons.
  - `config/db.sql` & `includes/db_migrator.php`:
    - Added columns to `sms_advertisements`: `company_name`, `company_logo_path`, `category`, `contact_phone`, `offer_badge`, `cta_text`, `target_audience`, `priority`.
    - Created `sms_advertisement_analytics` table for tracking daily and campaign-wise impressions and clicks.
    - Pre-seeded realistic partner campaigns for Zomato, Swiggy, Blinkit, Zepto, Amazon, and The Royal Spice Restaurant.
  - `tests/run_tests.php`: Expanded test suite from 84 to 99 automated assertions covering company ad CRUD, safety exclusions, URL validation, multi-tenancy, analytics logging, and UI components. All 99 tests passing.

---

## [v1.2.0] — 2026-11-26
### Added
- **Guest Gate Pass 48-Hour Auto-Expiry & WhatsApp Sharing**:
  - `api/resident_generate_pin.php`: Strict 48-hour calculation in IST (`valid_until`), formatted expiry dates, and pre-formatted click-to-chat WhatsApp URLs.
  - `api/guard_verify_pin.php` & `api/guard_log_visitor.php`: Enforces 48-hour expiration; auto-updates status to `Expired`; prevents reuse of `Used` passes.
  - `modules/resident/dashboard.php`: Pass cards display Guest Name, PIN, Generated At, Expiry Date & Time, Remaining Validity countdown badge, Status, and 1-tap WhatsApp sharing button.
- **Real-Time SOS Emergency Response System**:
  - `api/resident_trigger_emergency.php`: Rate-limited emergency dispatch (max 3/60s), 6 categories (Medical, Fire, Threat, Accident, Theft, Other), geolocation capture, and audit logging.
  - `api/guard_acknowledge_emergency.php` & `api/guard_get_emergency_alerts.php`: Multi-stage response workflow (`Active`, `Acknowledged`, `Responding`, `Resolved`, `Cancelled`) with audio siren and Google Maps navigation.
  - `api/resident_get_emergency_status.php`: Live polling of ongoing emergency resolution.
- **WebRTC In-App Camera for Guard Terminal**:
  - `modules/guard/dashboard.php`: Replaced external camera intent / file picker with integrated WebRTC `getUserMedia()` video stream, front/rear camera flip toggle, and Canvas API frame grabbing.
- **Visitor Tag vs Visitor Name Separation**:
  - `sms_visitors`: Added `visitor_tag` column.
  - Separated `visitor_name` (text input) from `visitor_tag` (dropdown + quick category tag buttons). Quick company tag buttons no longer overwrite the visitor's actual name.
- **10-Digit Indian Phone Number Normalization**:
  - Centralized `normalizeIndianPhoneNumber()` helper in `includes/api_bootstrap.php` enforcing 10-digit Indian mobile regex (`^[6-9]\d{9}$`) with support for `+91`, `91`, and `0` prefixes.
- **10 MB Visitor Photo Upload Enforced**:
  - Upload limit raised to 10 MB with server-side binary MIME inspection (`image/jpeg`, `image/png`, `image/webp`).
- **Society Management Modules (Facilities, Family, Daily Help, Rules)**:
  - `api/resident_facility_booking.php`: Multi-tenant amenity reservations (Clubhouse, Swimming pool, Gym, Tennis court) with double-booking prevention.
  - `api/resident_manage_family.php`: Family member roster with emergency contact flag.
  - `api/resident_manage_staff.php`: Verified domestic daily help directory (Maid, Driver, Cook, Cleaner) with 1-tap unit assignment.
  - Society Rules & Guidelines official handbook modal.
- **Resident Dashboard Responsive Navigation**:
  - Collapsible desktop sidebar navigation + mobile off-canvas drawer with hamburger menu button.
- **Society Marketplace (Buy & Sell)**:
  - `api/marketplace_get_listings.php`, `api/marketplace_create_listing.php`, `api/marketplace_action.php`: Strict intra-society multi-tenant isolation, multi-image upload (up to 5 photos), condition badges, WhatsApp chat, and admin moderation.
- **Monetization & Advertisement Management**:
  - `api/get_advertisements.php`: Ad display endpoint with **strict exclusion invariant** from emergency, auth, and guard security terminals.
  - `api/admin_manage_ads.php`: Google AdSense settings (Publisher ID, Slot ID) and resident paid campaign manual payment verification.
  - `api/resident_submit_ad.php`: Resident advertising submission with manual UPI / bank payment verification workflow.
- **Automated Regression Test Suite**:
  - `tests/run_tests.php`: Expanded to 84 automated assertions covering phone normalization, 48-hr expiry, ad safety exclusions, camera components, tag separation, and multi-tenant isolation. All 84 tests pass.

---

## [v1.1.0] — 2026-11-25
### Added
- `includes/db_migrator.php`: Added `ensureRegisteredResidentsSeeded()` to automatically seed and activate registered resident units (`A-101`, `B-801`, `A-32`, `C-H`, `B-Ty`) across both local XAMPP and live production InfinityFree environments.
- `config/db.sql`: Seed definitions expanded to include all 6 registered resident accounts and unit mappings.
- `sw.js`: Added `timestamp: Date.now()`, `requireInteraction: false`, and metadata to adhere to Google Chrome 128+ anti-spam notification heuristics.
- `GateAlertActivity.kt`: Added explicit photo status indicator below avatar (`No photo taken by gate guard` / `Photo unavailable`).

### Changed
- `GateAlertActivity.kt`: Fixed `✓ ALLOW ENTRY` and `✕ DENY` button text wrapping on narrow mobile screens (adjusted padding to `10.dp`, font to `12.5.sp`, and enforced `maxLines = 1`, `softWrap = false`).
- `GateAlertActivity.kt`: Handled `"null"` string photo URLs cleanly so non-existent photos do not trigger 404 network attempts.
- `download.php`: Updated direct download links and version badges to `v1.1.0`.
- Android `versionCode` bumped to `11` and `versionName` to `1.1.0`; release APK rebuilt and v2+v3 signed at `apk/SecureGate.apk`.

---

## [v1.0.9] — 2026-11-25
### Added
- `modules/guard/dashboard.php`: Implemented live state tracking with animated floating decision cards (`decision-popup-enter`) and two-tone Web Audio API chimes (ascending bell chime for resident approvals, warning buzzer for denials).
- `modules/guard/dashboard.php`: Added client-side 20-row pagination with `Previous` / `Next` controls and "Showing X to Y of Z visitors" indicator.

### Changed
- `modules/resident/dashboard.php`: Limited visitor log display to the 5 most recent visitors for a cleaner, high-focus mobile feed.
- `modules/auth/login.php` & `modules/resident/dashboard.php`: Removed redundant "Get Mobile App" and download banners from the login view.

---

## [v1.0.8] — 2026-11-25
### Added
- Official Rebranding to **SecureGate**:
  - Designed new SVG & high-resolution PNG shield logos (`assets/icon-512.svg`, `assets/icon-192.svg`, `assets/icon-512.png`, `assets/icon-192.png`).
  - Generated full Android launcher mipmap WebP suites (square & round) across `mdpi`, `hdpi`, `xhdpi`, `xxhdpi`, and `xxxhdpi`.
  - Created standalone direct download page at `/download` (`download.php`) with 3-step installation guide.
  - Added `/download/apk` binary streaming route in `routes.php`.

### Changed
- Renamed application string to `SecureGate` across `android/app/src/main/res/values/strings.xml`, `android/settings.gradle.kts`, `manifest.json`, `routes.php`, and `sw.js`.
- Configured Coil image loader in Kotlin to load visitor photos using HMAC signed tokens and cookies.
- Built release APK `apk/SecureGate.apk` (and backwards-compatible `apk/SocietyHub.apk`).

---

## [v1.0.7] — 2026-11-25
### Changed
- Strictly enforced **Indian Standard Time** (`Asia/Kolkata`, UTC+05:30) system-wide:
  - Backend: `date_default_timezone_set('Asia/Kolkata')` forced in `config/db.php`.
  - Database: Executed `SET time_zone = '+05:30'` on every MySQL PDO session.
  - Frontend: `assets/js/app.js` date formatter anchored to `Asia/Kolkata` with explicit +05:30 ISO string conversion.

---

## [v1.0.6] — 2026-11-24
### Added
- `.github/workflows/ci-cd.yml`: GitHub Actions CI (`php -l`, `composer validate/install/audit`, `tests/run_tests.php`, `test.php`) and CD deployment to InfinityFree FTP (`https://society.infy.click/`) with secret-injected `.env` and `vendor/` production build.
- `docs/CI_CD_GUIDE.md`: Setup guide for GitHub Secrets/Variables and InfinityFree hosting.

### Changed
- Made `.htaccess`, `manifest.json`, `sw.js`, `assets/js/app.js` (`appPath()`), and all `modules/` base-path portable for both `/society_app` (XAMPP) and `/` (`https://society.infy.click/`).
- Configured local Git remote `origin` to `https://github.com/shopquare/society_app.git`.
- Expanded `tests/run_tests.php` to verify both subpath and root `getRoleDashboardPath()` behavior.

---

## [v1.0.0] — 2026-11-24
### Added
- Core platform architecture with 17 MySQL tables, multi-tenant society isolation, session management, CSRF tokens, HMAC visitor action tokens, rate limiting, and 40 automated test assertions.
- Modules for Guard Terminal (`/guard/dashboard`), Resident Portal (`/resident/dashboard`), and Committee Admin Console (`/admin/dashboard`).
- Technical documentation suite under `docs/`.
