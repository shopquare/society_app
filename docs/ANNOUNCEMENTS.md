# Announcements & Notice Management — SecureGate v2

## Overview
Upgrades the society notice board (`/admin/notices`, `api/admin_create_notice.php`, `api/get_notices.php`) with 7 structured categories (`General`, `Maintenance`, `Security`, `Financial`, `Events`, `Emergency`, `Important Updates`), tower targeting, pinning, scheduling, expiry, and PDF/image attachments.

## Features
- Attachments validated via binary magic-byte inspection (`image/jpeg`, `image/png`, `image/webp`, `application/pdf`).
- `Emergency` category automatically triggers non-suppressible `Critical` multi-channel notifications via `dispatchNotification()`.
- Read telemetry tracked uniquely per user in `sms_notice_views`.
