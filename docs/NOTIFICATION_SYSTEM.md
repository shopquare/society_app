# Notification System & Multi-Channel Dispatch Engine (v2)

## Overview
SecureGate v2 unifies Web In-App Notifications, Web Push (`minishlink/web-push`), Native Android Alerts (`GatePollingService`), WhatsApp, SMS, and Email behind a single centralized service (`includes/notification_service.php`).

## Core Architecture
1. **Unified Feed & User Preferences (`sms_notifications`, `sms_notification_preferences`, `api/notifications.php`)**
   - Residents, guards, and admins see a shared bell icon (`includes/notification_widget.php`) across every portal.
   - Residents can configure per-category channel preferences (`in_app`, `push`, `email`, `whatsapp`, `sms`).
   - **Critical Security Override (`CRITICAL_SECURITY_CATEGORIES`)**: `emergency_sos`, `security_alert`, `visitor_arrival`, `gate_handover`, and `emergency_broadcast` can NEVER be suppressed by user opt-outs.
2. **Idempotency & Deduplication**
   - `UNIQUE KEY uq_notif_idem (user_id, idempotency_key)` prevents double delivery on webhook retries, cron overlaps, or double-clicks.
3. **External Channel Adapters (`includes/channels/`) & Delivery Queue (`sms_notification_deliveries`)**
   - `WhatsAppProvider.php`, `SmsProvider.php`, `EmailProvider.php` read provider tokens strictly from `.env` (`envValue()`).
   - Unconfigured external channels gracefully record a `Skipped` status with a sandbox dry-run log so local/XAMPP environments run cleanly without external paid API keys.
   - Shared-hosting cron compatibility (`api/cron_dispatch.php`) supports both CLI cron (`php api/cron_dispatch.php`) and `CRON_SECRET`-authenticated web-cron calls plus manual queue flushing on `/admin/notifications`.
4. **Emergency Broadcasts (`api/admin_broadcast.php`, `/admin/notifications`)**
   - Admins send high-priority society broadcasts or non-suppressible Emergency Broadcasts across residents, guards, and committee members, and monitor/retry failed channel deliveries.
5. **Android Companion Polling (`GatePollingService.kt`)**
   - Polls pending gate visitor requests AND critical unread notifications (`/api/notifications.php?limit=5`) to raise native system notifications on Android.
