# Deployment Guide

## Current Deployment Readiness
**Status: Not Ready for Production.** Complete Priority 1 items in `docs/IMPROVEMENT_ROADMAP.md` before deploying to a public server.

## Observed Production Indicators
- `config/db.php` references a hosted MySQL database naming pattern, and `api/guard_log_visitor.php` references `https://shopquare.in` in the push notification icon URL, indicating prior or intended shared-hosting deployment under `/society_app/`.

## Pre-Deployment Checklist (Required Remediation)
1. **Remove Test & Legacy Files**: Delete `test.php`, `test_push_trigger.php`, `modules/admin/residents.php`, and `modules/resident/{login,register,forget-password}.php` (or move/block direct access).
2. **Externalize & Rotate Secrets**: Move DB credentials and VAPID keys to `.env` outside webroot; rotate DB password and VAPID keypair.
3. **Fix Critical Auth & Functional Bugs**:
   - Fix `api/update_visitor_status.php` unauthenticated access and `'Approved'` vs `'Entered'` status mismatch.
   - Fix Guard login redirect (`role_id == 4` → `/guard/dashboard`).
   - Fix `s.name` vs `s.society_name` query in `modules/resident/dashboard.php`.
   - Disable `ini_set('display_errors', 1)` in `modules/admin/guards.php` and hide raw `$e->getMessage()` outputs.
4. **Enable HTTPS**: Mandatory for WebRTC camera access (`navigator.mediaDevices.getUserMedia`) on Guard terminals and Web Push / Service Workers (`sw.js`) on Resident devices.
5. **Protect Uploads & Config**: Block HTTP access to `config/`, `includes/`, `modules/`, `vendor/`, and `composer.*`, and move visitor uploads out of public webroot.
6. **Database Provisioning**: Export and version a complete `schema.sql` covering all 11 tables, indexes, and foreign keys before deploying.
7. **Composer Production Install**:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

## Build & CI/CD Pipeline
`Not Applicable` — no build scripts, Dockerfiles, or CI/CD workflows exist in the repository.
