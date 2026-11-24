# Git Setup & Version Control Guide

## Current Git State
- **Git Installation**: `git version 2.45.2.windows.1`
- **Repository Status**: Local Git initialized in `c:\xampp\htdocs\society_app` (0 commits, no remote configured).
- **Secrets Remediation Status**: **Ready for Initial Commit.** Hardcoded database credentials and VAPID keys were migrated out of `config/db.php` and `api/guard_log_visitor.php` into `.env` (which is ignored by `.gitignore`), and `test_push_trigger.php` was removed. Static test verification (`tests/run_tests.php`) confirms 0 hardcoded DB passwords or VAPID private keys remain in PHP files.

## What `.gitignore` Excludes
- `.env`, `.env.*` (keeping `!.env.example`), `*.pem`, `*.key`, `*.crt`, `*.p12`
- `/vendor/`, `composer.phar`, `/node_modules/`
- `/uploads/` contents (user-uploaded visitor photos)
- Database dumps (`*.sql.gz`, `*.dump`, `*.bak`, `*.backup`)
- Logs, cache, temp files, `.vscode/`, `.idea/`, `Thumbs.db`, `.DS_Store`

## Recommended Git Workflow
1. Verify repository status and confirm `.env`, `vendor/`, and `uploads/` do not appear:
   ```bash
   git status
   ```
2. Stage safe project files:
   ```bash
   git add .
   ```
3. Create the initial baseline commit:
   ```bash
   git commit -m "Initial hardened baseline"
   ```
4. Check branch and remote configuration:
   ```bash
   git branch
   git remote -v
   ```
