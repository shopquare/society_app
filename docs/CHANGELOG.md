# Changelog

All notable changes to the SecureGate (formerly SocietyHub) application are documented in this file.

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
