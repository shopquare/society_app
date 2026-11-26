# Multi-Society SaaS Platform & Subscriptions (v2)

## Overview
SecureGate v2 introduces multi-tenant SaaS subscription tiers, plan quotas, self-service society onboarding, and a strictly isolated Platform Super Admin Command Center (`/platform/dashboard`).

## Strict Tenant vs. Platform Privilege Boundary
- Regular Society Super Admins (`role_id = 1`, `is_platform_admin = 0`) manage **only their own society** and can never view other societies or grant `saas.platform`.
- Only explicitly designated Platform Super Admins (`isPlatformSuperAdmin() === true` + `saas.platform`) can access `/platform/dashboard` and `api/platform_admin.php`.

## Subscription Plans (`sms_subscription_plans`)
| Tier | Monthly (INR) | Max Flats | Max Guards | Max Gates | Key Capabilities |
|---|---:|---:|---:|---:|---|
| Free Community | ₹0 | 25 | 2 | 1 | Core Gate + Community Suite |
| Basic Guard | ₹999 | 75 | 4 | 2 | Multi-Gate + Community Suite |
| Standard Society | ₹2,499 | 250 | 10 | 4 | Full Accounting v2 + Document Vault + WhatsApp |
| Premium Complex | ₹4,999 | 750 | 25 | 10 | All Standard + API Access |
| Enterprise Township | ₹9,999 | 5,000 | 100 | 30 | Full Platform Capabilities |

## Non-Destructive Expiry & Grace Period (`includes/saas_guard.php`)
- Subscriptions transition through `Trial` → `Active` → 7-day `Grace_Period` → `Expired`.
- **Zero Data Loss Guarantee**: When a society subscription expires, existing residents, invoices, audit logs, and vault documents are **never deleted**; writes are paused in read-only mode until renewed.
- Quotas are verified cleanly via `canSocietyAddEntity($conn, $societyId, 'flats'|'guards'|'gates')`.

## Society Onboarding (`/register-society`, `api/register_society.php`)
- New societies self-onboard with a unique society code, starter Tower, Main Entrance Gate, Super Admin credentials, and a 14-day Trial subscription.
