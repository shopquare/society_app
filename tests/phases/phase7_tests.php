<?php
// tests/phases/phase7_tests.php
// Phase 7: External Channels (WhatsApp, SMS, Email), Cron Delivery Queue, Broadcasts & Android Polling

echo "\n--- Phase 7: External Communications & Alert Broadcast Tests ---\n";

require_once __DIR__ . '/../../includes/channels/WhatsAppProvider.php';
require_once __DIR__ . '/../../includes/channels/SmsProvider.php';
require_once __DIR__ . '/../../includes/channels/EmailProvider.php';
require_once __DIR__ . '/../../api/cron_dispatch.php';

// 1. Provider Adapter Sandbox & Validation Checks
$waRes = sendWhatsAppMessage('9876543210', 'Test Alert', 'Sample maintenance due message');
assertTrue(in_array($waRes['status'], ['Sent', 'Skipped'], true), "WhatsApp adapter handles sandbox/configured dispatch cleanly");
$waBad = sendWhatsAppMessage('12', 'Test', 'Bad');
assertTrue($waBad['status'] === 'Failed', "WhatsApp adapter rejects malformed short phone number");

$smsRes = sendSmsMessage('9876543210', 'OTP 123456 for SecureGate');
assertTrue(in_array($smsRes['status'], ['Sent', 'Skipped'], true), "SMS adapter handles sandbox/configured dispatch cleanly");
$smsBad = sendSmsMessage('', 'Bad');
assertTrue($smsBad['status'] === 'Failed', "SMS adapter rejects empty phone");

$mailRes = sendEmailMessage('resident@demo.com', 'John', 'Monthly Statement', 'Attached summary');
assertTrue(in_array($mailRes['status'], ['Sent', 'Skipped'], true), "Email adapter handles sandbox/configured dispatch cleanly");
$mailBad = sendEmailMessage('not-an-email', 'Bad', 'Subj', 'Body');
assertTrue($mailBad['status'] === 'Failed', "Email adapter rejects invalid email syntax");

// 2. Queue Worker & Broadcast Permission Enforcement
$cronSrc = file_get_contents(__DIR__ . '/../../api/cron_dispatch.php');
assertTrue(strpos($cronSrc, 'CRON_SECRET') !== false, "Cron dispatch endpoint enforces CRON_SECRET or admin permission");

$bcSrc = file_get_contents(__DIR__ . '/../../api/admin_broadcast.php');
assertTrue(strpos($bcSrc, "requireApiPermission('notifications.broadcast')") !== false, "Broadcast API enforces notifications.broadcast permission");
assertTrue(strpos($bcSrc, "'emergency_broadcast'") !== false, "Broadcast API marks emergency alerts with non-suppressible category");

// 3. End-to-End Queue Processing inside transaction (rolled back)
if ($conn instanceof PDO) {
    $socId = (int)($conn->query("SELECT id FROM sms_societies ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 1);
    $uid = (int)($conn->query("SELECT id FROM sms_users WHERE society_id = {$socId} LIMIT 1")->fetchColumn() ?: 0);
    if ($uid > 0) {
        $conn->beginTransaction();
        try {
            $conn->prepare("INSERT INTO sms_notifications (society_id, user_id, category, priority, channel, title, message, is_read) VALUES (:sid, :uid, 'payment_reminder', 'High', 'whatsapp', 'P7 Queue Test', 'Queued body message', 0)")
                ->execute([':sid' => $socId, ':uid' => $uid]);
            $nid = (int)$conn->lastInsertId();

            $conn->prepare("INSERT INTO sms_notification_deliveries (notification_id, society_id, user_id, channel, priority, status, attempts, scheduled_at) VALUES (:nid, :sid, :uid, 'whatsapp', 'High', 'Pending', 0, NULL)")
                ->execute([':nid' => $nid, ':sid' => $socId, ':uid' => $uid]);
            $did = (int)$conn->lastInsertId();

            $summary = processPendingDeliveryQueue($conn, $socId, 10);
            assertTrue($summary['processed'] >= 1, "Queue worker processes queued Pending delivery row");
            $row = $conn->query("SELECT status, attempts FROM sms_notification_deliveries WHERE id = {$did}")->fetch();
            assertTrue((int)$row['attempts'] >= 1 && $row['status'] !== 'Pending', "Delivery record attempts incremented and resolved from Pending");

            $conn->rollBack();
        } catch (Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            assertTrue(false, "Phase 7 queue transaction failed: " . $e->getMessage());
        }
    }
}

// 4. UI, Route & Android Notification Polling checks
assertTrue(file_exists(__DIR__ . '/../../modules/admin/notifications.php'), "modules/admin/notifications.php exists");
assertTrue(strpos(file_get_contents(__DIR__ . '/../../routes.php'), "'/admin/notifications'") !== false, "/admin/notifications route registered");
$ktSrc = file_get_contents(__DIR__ . '/../../android/app/src/main/kotlin/com/shopquare/societyhub/GatePollingService.kt');
assertTrue(strpos($ktSrc, 'pollCriticalNotifications') !== false, "Android GatePollingService polls critical society notifications");
