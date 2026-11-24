# Configuration Guide

## Current Configuration Files

### 1. Database & Timezone (`config/db.php`)
- **Timezone**: Forces `Asia/Kolkata` in PHP (`date_default_timezone_set('Asia/Kolkata')`) and `+05:30` in MySQL (`SET time_zone = '+05:30'`).
- **Constants**: `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` (currently hardcoded; recommended to migrate to `.env`).

### 2. Apache Routing (`.htaccess`)
- `RewriteBase /society_app/`
- Any request that is not an existing file or directory is forwarded to `index.php`.
- **Deployment Note**: If deploying at domain root (`/`) instead of `/society_app/`, you must update `.htaccess` (`RewriteBase /`), `routes.php` (`$base_path = ''`), `sw.js`, `manifest.json`, and hardcoded `/society_app/...` URLs across `modules/` and `api/`.

### 3. Router (`routes.php`)
- Maps clean paths (`/login`, `/register`, `/resident/dashboard`, `/resident/financials`, `/admin/*`, `/guard/dashboard`) to files in `modules/`.

### 4. Web Push / VAPID Configuration
- **Server-side keys**: Currently hardcoded in `api/guard_log_visitor.php` and `test_push_trigger.php`.
- **Client-side public key**: Currently hardcoded in `modules/resident/dashboard.php` (`applicationServerKey` in `requestPushPermission()`).
- **Notification icon domain**: Currently hardcoded as `https://shopquare.in` in `api/guard_log_visitor.php`.
- **Recommendation**: Centralize `VAPID_SUBJECT`, `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `APP_BASE_PATH`, and `APP_URL` in a `.env` file.

### 5. PWA Manifests & Service Worker
- Active manifest: `manifest.json` (linked by `modules/auth/login.php` and `modules/resident/dashboard.php`).
- Active service worker: `sw.js` (registered with scope `/society_app/`).
- Legacy/unused manifest: `pwa/manifest.json` (linked only by unused `includes/header.php`).

### 6. Build / Environment Configuration
- **Automated Build Pipeline**: `Not Applicable` (no `package.json`, Webpack, Vite, or CI/CD configuration exists).
- **Environment Files (`.env`)**: `Not Applicable` (not yet implemented; strongly recommended).
