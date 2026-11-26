# SecureGate v2 Testing & Verification Guide

## Running the Automated Test Suite
Execute the full modular test harness on Windows XAMPP against your isolated `securegate_v2` database:

```powershell
C:\xampp\php\php.exe tests/run_tests.php
```

### Modular Coverage (`tests/phases/`)
- `tests/run_tests.php`: Core 99 assertions (Environment config, role redirects, CSRF tokens, HMAC signatures, rate limiter, UI DOM regressions, database connectivity).
- `phase0_tests.php`: Granular RBAC matrix, anti-escalation, non-suppressible SOS notifications, idempotency dedup, building/flat schema.
- `phase1_tests.php`: Multi-gate authorization checks, visitor gate binding, completed shift handover immutability.
- `phase2_tests.php`: Election governance attestation, `UNIQUE` double-vote constraint, detached anonymous voter IDs, directory rate limiting & PII masking.
- `phase3_tests.php`: Facility `FOR UPDATE` transactional slot locking, blocked maintenance dates, flat quotas & refund cutoffs.
- `phase4_tests.php`: Penalty engine math (grace period, percentage & max cap), pure-PHP PDF generation (`%PDF-1.4`), partial balances, transparency PII isolation.
- `phase5_tests.php`: 17 seeded categories, resident suggestion moderation default, `UNIQUE` duplicate review block.
- `phase6_tests.php`: Reports RBAC, CSV formula-injection sanitization, Tenant KYC columns & staff attendance.
- `phase7_tests.php`: WhatsApp/SMS/Email sandbox & validation adapters, cron queue processing, `CRON_SECRET` guard, Android notification polling.
- `phase8_tests.php`: Private vault `.htaccess` denial, HMAC & `realpath` containment in `api/stream_document.php`, auto-issued NOC PDFs.
- `phase9_tests.php`: Multi-society 5 SaaS plans, non-destructive expired read-only enforcement, platform super admin boundary.
- `phase10_security_perf_tests.php`: Repository-wide static security audit, CSRF & SQL injection checks, index verification & documentation completeness.

## PHP Static Linting
Before any commit on `v_two`, verify zero syntax errors across modified files:
```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { C:\xampp\php\php.exe -l $_.FullName }
```
