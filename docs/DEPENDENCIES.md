# Dependencies & Third-Party Integrations

## 1. PHP Composer Dependencies (`composer.json` & `composer.lock`)

**Direct Requirements (`composer.json`)**:
- `minishlink/web-push` (`^11.0` → locked at `v11.0.0`, MIT): Server-side Web Push notifications with VAPID encryption. Requires `php >= 8.2`.
- `php-http/guzzle7-adapter` (`^1.1` → locked at `1.1.0`, MIT): HTTPlug adapter for Guzzle 7.

**All 21 Locked Packages (`composer.lock`)**:
| Package | Locked Version | License | PHP Constraint |
|---|---|---|---|
| `minishlink/web-push` | `v11.0.0` | MIT | `>=8.2` |
| `web-token/jwt-library` | `4.1.7` | MIT | `>=8.2` |
| `brick/math` | `0.17.2` | MIT | `^8.2` |
| `guzzlehttp/guzzle` | `7.15.3` | MIT | `^7.2.5 \|\| ^8.0` |
| `guzzlehttp/promises` | `2.5.2` | MIT | `^7.2.5 \|\| ^8.0` |
| `guzzlehttp/psr7` | `2.13.0` | MIT | `^7.2.5 \|\| ^8.0` |
| `php-http/guzzle7-adapter` | `1.1.0` | MIT | `^7.3 \| ^8.0` |
| `php-http/discovery` | `1.20.0` | MIT | `^7.1 \|\| ^8.0` |
| `php-http/httplug` | `2.4.1` | MIT | `^7.1 \|\| ^8.0` |
| `php-http/promise` | `1.3.1` | MIT | `^7.1 \|\| ^8.0` |
| `psr/clock` | `1.0.0` | MIT | `^7.0 \|\| ^8.0` |
| `psr/http-client` | `1.0.3` | MIT | `^7.0 \|\| ^8.0` |
| `psr/http-factory` | `1.1.0` | MIT | `>=7.1` |
| `psr/http-message` | `2.0` | MIT | `^7.2 \|\| ^8.0` |
| `psr/log` | `3.0.2` | MIT | `>=8.0.0` |
| `ralouphie/getallheaders` | `3.0.3` | MIT | `>=5.6` |
| `spomky-labs/base64url` | `v2.0.4` | MIT | `>=7.1` |
| `spomky-labs/pki-framework` | `1.6.0` | MIT | `>=8.1` |
| `symfony/deprecation-contracts` | `v3.7.1` | MIT | `>=8.1` |
| `symfony/polyfill-php80` | `v1.37.0` | MIT | `>=7.2` |
| `symfony/polyfill-php83` | `v1.41.0` | MIT | `>=7.2` |

- **Licensing**: All 21 packages use permissive **MIT** licenses.
- **Vulnerability Status**: `composer` CLI is not installed in the system `PATH`, so `composer audit` could not be executed. Check advisories before production deployment.
- **Runtime Requirement**: Minimum PHP **8.2** (enforced by `minishlink/web-push`, `brick/math`, and `web-token/jwt-library`).

## 2. Frontend CDN Dependencies
| Library / Asset | Source | Used In | Risk |
|---|---|---|---|
| Tailwind CSS Play CDN | `https://cdn.tailwindcss.com` | All active & legacy views | Runtime JIT compiler slows mobile load; breaks styling offline or if CDN is blocked; no SRI hash |
| Google Fonts (`Inter`, `Playfair Display`) | `https://fonts.googleapis.com` | Auth, Guard, Resident views (Admin config references them without `<link>`!) | Admin pages define `font-serif`/`font-sans` in `tailwind-config.js` without loading Google Fonts `<link>` |
| SweetAlert2 (`sweetalert2@11`) | `cdn.jsdelivr.net` | `includes/header.php` only (unused) | Unused dependency |
| Chart.js | `cdn.jsdelivr.net` | `includes/header.php` only (unused) | Unused dependency |

## 3. External Services & Browser APIs
- **Browser Push Services (FCM / Mozilla / Apple Push)**: Used via `minishlink/web-push` in `api/guard_log_visitor.php` and `test_push_trigger.php`. Reliability risk: synchronous HTTP push call inside `guard_log_visitor.php` has no explicit timeout or retry queue, and stale `410 Gone` subscriptions are never purged.
- **Browser APIs**:
  - `navigator.mediaDevices.getUserMedia` (Guard camera — requires HTTPS outside `localhost`).
  - `ServiceWorker` & `PushManager` & `Notification` (Resident alerts — requires HTTPS outside `localhost`; `Notification` throws if unsupported).
  - `AudioContext` / `navigator.vibrate` (Resident chime — browser autoplay policies may block `AudioContext` until first user tap).
- **Payment Gateways / Email / SMS / Storage Services**: **None integrated.** (`resident_pay_invoice.php` is a direct DB status toggle; `forget-password.php` is a static prototype).
