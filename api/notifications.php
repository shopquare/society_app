<?php
// api/notifications.php
// Centralized Notification Feed, Unread Count, Read State, and Preferences API

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/notification_service.php';

initApi(['GET', 'POST']);
requireApiAuth([1, 2, 3, 4]);

$userId = (int)$_SESSION['user_id'];
$societyId = (int)$_SESSION['society_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = trim((string)($_GET['action'] ?? 'list'));

    try {
        if ($action === 'preferences') {
            $stmt = $conn->prepare(
                "SELECT category, in_app_enabled, push_enabled, email_enabled, whatsapp_enabled, sms_enabled
                 FROM sms_notification_preferences
                 WHERE user_id = :uid"
            );
            $stmt->execute([':uid' => $userId]);
            $rows = $stmt->fetchAll();

            $categories = [
                'visitor_arrival' => 'Visitor Arrivals (Non-Suppressible)',
                'emergency_sos' => 'Emergency SOS (Always Active)',
                'payment_reminder' => 'Payment Reminders & Receipts',
                'complaint_update' => 'Helpdesk Complaint Updates',
                'facility_booking' => 'Facility Bookings & Reminders',
                'event_announcement' => 'Society Events & Celebrations',
                'society_notice' => 'Announcements & Circulars',
                'poll_reminder' => 'Polls & Society Elections',
                'marketplace_alert' => 'Marketplace Activity',
                'gate_handover' => 'Gate Shift Handover Alerts'
            ];

            $map = [];
            foreach ($rows as $r) {
                $map[$r['category']] = $r;
            }

            $list = [];
            foreach ($categories as $catKey => $label) {
                $pref = $map[$catKey] ?? [
                    'in_app_enabled' => 1, 'push_enabled' => 1, 'email_enabled' => 1,
                    'whatsapp_enabled' => 1, 'sms_enabled' => 0
                ];
                $list[] = [
                    'category' => $catKey,
                    'label' => $label,
                    'locked' => in_array($catKey, CRITICAL_SECURITY_CATEGORIES, true),
                    'in_app_enabled' => (int)$pref['in_app_enabled'],
                    'push_enabled' => (int)$pref['push_enabled'],
                    'email_enabled' => (int)$pref['email_enabled'],
                    'whatsapp_enabled' => (int)$pref['whatsapp_enabled'],
                    'sms_enabled' => (int)$pref['sms_enabled']
                ];
            }

            jsonSuccess(['preferences' => $list]);
        }

        $limit = min(50, max(5, (int)($_GET['limit'] ?? 25)));
        $category = trim((string)($_GET['category'] ?? ''));

        $where = "user_id = :uid";
        $params = [':uid' => $userId];
        if ($category !== '' && $category !== 'all') {
            $where .= " AND category = :cat";
            $params[':cat'] = $category;
        }

        $stmt = $conn->prepare(
            "SELECT id, category, priority, channel, title, message, action_url, reference_type, reference_id, is_read, created_at
             FROM sms_notifications
             WHERE {$where}
             ORDER BY is_read ASC, (priority = 'Critical') DESC, created_at DESC
             LIMIT {$limit}"
        );
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        $cntStmt = $conn->prepare("SELECT COUNT(*) FROM sms_notifications WHERE user_id = :uid AND is_read = 0");
        $cntStmt->execute([':uid' => $userId]);
        $unreadCount = (int)$cntStmt->fetchColumn();

        jsonSuccess([
            'notifications' => $items,
            'unread_count' => $unreadCount
        ]);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to load notifications.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonInput();
    requireValidCsrfToken($data['csrf_token'] ?? '');
    $action = trim((string)($data['action'] ?? ''));

    try {
        if ($action === 'mark_read') {
            $notifId = (int)($data['id'] ?? 0);
            if ($notifId <= 0) {
                jsonError('Invalid notification ID.', 400);
            }
            $up = $conn->prepare("UPDATE sms_notifications SET is_read = 1 WHERE id = :id AND user_id = :uid");
            $up->execute([':id' => $notifId, ':uid' => $userId]);
            jsonSuccess(['message' => 'Notification marked as read.']);
        } elseif ($action === 'mark_all_read') {
            $up = $conn->prepare("UPDATE sms_notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0");
            $up->execute([':uid' => $userId]);
            jsonSuccess(['message' => 'All notifications marked as read.']);
        } elseif ($action === 'save_preference') {
            $category = trim((string)($data['category'] ?? ''));
            if ($category === '') {
                jsonError('Category is required.', 400);
            }
            $isCritical = in_array($category, CRITICAL_SECURITY_CATEGORIES, true);
            $inApp = $isCritical ? 1 : (!empty($data['in_app_enabled']) ? 1 : 0);
            $push = $isCritical ? 1 : (!empty($data['push_enabled']) ? 1 : 0);
            $email = !empty($data['email_enabled']) ? 1 : 0;
            $wa = !empty($data['whatsapp_enabled']) ? 1 : 0;
            $sms = !empty($data['sms_enabled']) ? 1 : 0;

            $ins = $conn->prepare(
                "INSERT INTO sms_notification_preferences (user_id, category, in_app_enabled, push_enabled, email_enabled, whatsapp_enabled, sms_enabled)
                 VALUES (:uid, :cat, :inapp, :push, :email, :wa, :sms)
                 ON DUPLICATE KEY UPDATE
                 in_app_enabled = VALUES(in_app_enabled),
                 push_enabled = VALUES(push_enabled),
                 email_enabled = VALUES(email_enabled),
                 whatsapp_enabled = VALUES(whatsapp_enabled),
                 sms_enabled = VALUES(sms_enabled)"
            );
            $ins->execute([
                ':uid' => $userId, ':cat' => $category, ':inapp' => $inApp,
                ':push' => $push, ':email' => $email, ':wa' => $wa, ':sms' => $sms
            ]);

            jsonSuccess(['message' => 'Notification preferences updated.']);
        } else {
            jsonError('Unknown notification action.', 400);
        }
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to update notifications.');
    }
}
