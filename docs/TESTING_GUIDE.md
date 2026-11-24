# Testing Guide

## Automated Tests Available
- `tests/run_tests.php`: Non-destructive unit and static security-invariant test suite covering:
  - `.env` parsing and quote stripping (`config/env.php`)
  - Role dashboard routing (`1, 2, 3, 4`) & `hasRole()` string/int handling (`includes/auth_middleware.php`)
  - CSRF token generation, validation, null rejection, and rotation (`includes/csrf.php`)
  - HMAC-SHA256 Service Worker action token signing & verification
  - Static checks ensuring zero hardcoded DB passwords, VAPID private keys, or `display_errors = 1` in application PHP files
- `test.php`: Verifies Composer autoloading for `Minishlink\WebPush\WebPush`.

## Tests Executed (Post-Edit Verification)

| Check | Scope | Result |
|---|---|---|
| `php -l` (PHP 8.5.7 CLI) | All 55 `.php` files outside `vendor/` | **55 Passed / 0 Failed** |
| `php tests/run_tests.php` | 17 unit & static security assertions | **17 Passed / 0 Failed** |
| `php test.php` | Composer autoload & `minishlink/web-push` | **Passed** |

## Tests Not Executed (Environment Limitations)
- **Live MySQL Integration & Browser E2E Tests**: Local `mysqld` and `httpd` services were not running during execution, so live database queries, HTTP sessions, WebRTC camera capture, and browser Service Worker push delivery require verification once Apache and MySQL are started.
