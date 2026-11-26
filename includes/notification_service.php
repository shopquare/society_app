<?php
// includes/notification_service.php
// Centralized Multi-Channel Notification Dispatcher with Priority & Idempotency Rules

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Categories that must NEVER be suppressed by user marketing opt-outs.
 */
const CRITICAL_SECURITY_CATEGORIES = [
    'emergency_sos',
    'security_alert',
    'visitor_arrival',
    'gate_handover',
    'emergency_broadcast'
];

/**
 * Dispatches a notification to one or more users across requested channels.
 *
 * Supported $payload keys:
 *  - society_id (int)
 *  - user_ids (int[] optional) or role_ids (int[] optional)
 *  - category (string)
 *  - priority ('Critical'|'High'|'Normal'|'Low')
 *  - title (string)
 *  - message (string)
 *  - action_url (string|null)
 *  - reference_type (string|null)
 *  - reference_id (int|null)
 *  - idempotency_key (string|null)
 *  - channels (string[] e.g. ['in_app', 'web_push', 'android', 'email', 'whatsapp', 'sms'])
 *  - scheduled_at (string|null Y-m-d H:i:s)
 */
function dispatchNotification(PDO $conn, array $payload): array {
    $societyId = isset($payload['society_id']) ? (int)$payload['society_id'] : null;
    $category = trim((string)($payload['category'] ?? 'general'));
    $priority = trim((string)($payload['priority'] ?? 'Normal'));
    if (!in_array($priority, ['Critical', 'High', 'Normal', 'Low'], true)) {
        $priority = 'Normal';
    }
    $title = mb_substr(trim((string)($payload['title'] ?? 'SecureGate Alert')), 0, 150);
    $message = trim((string)($payload['message'] ?? ''));
    $actionUrl = isset($payload['action_url']) ? mb_substr((string)$payload['action_url'], 0, 255) : null;
    $refType = isset($payload['reference_type']) ? mb_substr((string)$payload['reference_type'], 0, 50) : null;
    $refId = isset($payload['reference_id']) ? (int)$payload['reference_id'] : null;
    $idempotencyKey = isset($payload['idempotency_key']) && $payload['idempotency_key'] !== ''
        ? mb_substr((string)$payload['idempotency_key'], 0, 100)
        : null;
    $channels = $payload['channels'] ?? ['in_app'];
    $scheduledAt = $payload['scheduled_at'] ?? null;

    if ($message === '') {
        return ['dispatched' => 0, 'skipped' => 0];
    }

    // Resolve recipient user IDs safely scoped by society
    $userIds = [];
    if (!empty($payload['user_ids']) && is_array($payload['user_ids'])) {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $payload['user_ids']), fn($v) => $v > 0)));
    } elseif (!empty($payload['role_ids']) && is_array($payload['role_ids']) && $societyId !== null) {
        $roleList = array_values(array_unique(array_filter(array_map('intval', $payload['role_ids']), fn($v) => $v > 0)));
        if (!empty($roleList)) {
            $placeholders = implode(',', array_fill(0, count($roleList), '?'));
            $uStmt = $conn->prepare("SELECT id FROM sms_users WHERE society_id = ? AND role_id IN ({$placeholders}) AND status = 'active'");
            $uStmt->execute(array_merge([$societyId], $roleList));
            $userIds = array_map('intval', $uStmt->fetchAll(PDO::FETCH_COLUMN));
        }
    }

    if (empty($userIds)) {
        return ['dispatched' => 0, 'skipped' => 0];
    }

    $isNonSuppressible = ($priority === 'Critical') || in_array($category, CRITICAL_SECURITY_CATEGORIES, true);

    $insNotif = $conn->prepare(
        "INSERT INTO sms_notifications (society_id, user_id, category, priority, channel, title, message, action_url, reference_type, reference_id, idempotency_key, is_read, created_at)
         VALUES (:sid, :uid, :cat, :prio, :chan, :title, :msg, :url, :rtype, :rid, :idem, 0, NOW())"
    );
    $chkIdem = $conn->prepare("SELECT id FROM sms_notifications WHERE user_id = :uid AND idempotency_key = :idem LIMIT 1");
    $prefStmt = $conn->prepare("SELECT in_app_enabled, push_enabled, email_enabled, whatsapp_enabled, sms_enabled FROM sms_notification_preferences WHERE user_id = :uid AND category = :cat LIMIT 1");
    $insDeliv = $conn->prepare(
        "INSERT INTO sms_notification_deliveries (notification_id, society_id, user_id, channel, priority, status, attempts, provider_response, scheduled_at, sent_at)
         VALUES (:nid, :sid, :uid, :chan, :prio, :st, :att, :resp, :sched, :sent)"
    );

    $dispatched = 0;
    $skipped = 0;
    $primaryChan = (string)($channels[0] ?? 'in_app');
    $nowStr = date('Y-m-d H:i:s');
    $isScheduledFuture = ($scheduledAt !== null && strtotime($scheduledAt) > time());

    foreach ($userIds as $uid) {
        // Idempotency deduplication check
        if ($idempotencyKey !== null) {
            $chkIdem->execute([':uid' => $uid, ':idem' => $idempotencyKey]);
            if ($chkIdem->fetch()) {
                $skipped++;
                continue;
            }
        }

        // Evaluate preferences unless notification is non-suppressible Emergency / Security
        $prefs = ['in_app_enabled' => 1, 'push_enabled' => 1, 'email_enabled' => 1, 'whatsapp_enabled' => 1, 'sms_enabled' => 0];
        if (!$isNonSuppressible) {
            $prefStmt->execute([':uid' => $uid, ':cat' => $category]);
            $row = $prefStmt->fetch();
            if ($row) {
                $prefs = $row;
            }
        }

        try {
            $insNotif->execute([
                ':sid' => $societyId,
                ':uid' => $uid,
                ':cat' => $category,
                ':prio' => $priority,
                ':chan' => $primaryChan,
                ':title' => $title,
                ':msg' => $message,
                ':url' => $actionUrl,
                ':rtype' => $refType,
                ':rid' => $refId,
                ':idem' => $idempotencyKey
            ]);
            $notifId = (int)$conn->lastInsertId();
            $dispatched++;

            foreach ($channels as $chan) {
                $chanEnabled = true;
                if (!$isNonSuppressible) {
                    if ($chan === 'in_app' && !(int)$prefs['in_app_enabled']) $chanEnabled = false;
                    if (($chan === 'web_push' || $chan === 'android') && !(int)$prefs['push_enabled']) $chanEnabled = false;
                    if ($chan === 'email' && !(int)$prefs['email_enabled']) $chanEnabled = false;
                    if ($chan === 'whatsapp' && !(int)$prefs['whatsapp_enabled']) $chanEnabled = false;
                    if ($chan === 'sms' && !(int)$prefs['sms_enabled']) $chanEnabled = false;
                }

                if (!$chanEnabled) {
                    $insDeliv->execute([
                        ':nid' => $notifId, ':sid' => $societyId, ':uid' => $uid, ':chan' => $chan,
                        ':prio' => $priority, ':st' => 'Skipped', ':att' => 0, ':resp' => 'Opted out by user preference',
                        ':sched' => null, ':sent' => null
                    ]);
                    continue;
                }

                if ($chan === 'in_app' || $chan === 'android') {
                    // Available immediately via notifications feed / polling endpoint
                    $insDeliv->execute([
                        ':nid' => $notifId, ':sid' => $societyId, ':uid' => $uid, ':chan' => $chan,
                        ':prio' => $priority, ':st' => $isScheduledFuture ? 'Pending' : 'Sent',
                        ':att' => 1, ':resp' => 'Stored in unified notification ledger',
                        ':sched' => $scheduledAt, ':sent' => $isScheduledFuture ? null : $nowStr
                    ]);
                } elseif ($chan === 'web_push' && !$isScheduledFuture) {
                    $pushOk = attemptWebPushDispatch($conn, $uid, $title, $message, $actionUrl ?? '/resident/dashboard');
                    $insDeliv->execute([
                        ':nid' => $notifId, ':sid' => $societyId, ':uid' => $uid, ':chan' => 'web_push',
                        ':prio' => $priority, ':st' => $pushOk['status'], ':att' => 1,
                        ':resp' => mb_substr($pushOk['detail'], 0, 250), ':sched' => null,
                        ':sent' => ($pushOk['status'] === 'Sent') ? $nowStr : null
                    ]);
                } else {
                    // Queued for asynchronous Phase 7 provider worker (WhatsApp / SMS / Email / Scheduled)
                    $insDeliv->execute([
                        ':nid' => $notifId, ':sid' => $societyId, ':uid' => $uid, ':chan' => $chan,
                        ':prio' => $priority, ':st' => 'Pending', ':att' => 0,
                        ':resp' => 'Queued for channel dispatch', ':sched' => $scheduledAt ?? $nowStr, ':sent' => null
                    ]);
                }
            }
        } catch (Throwable $e) {
            error_log('dispatchNotification error for user ' . $uid . ': ' . $e->getMessage());
        }
    }

    return ['dispatched' => $dispatched, 'skipped' => $skipped];
}

/**
 * Attempts Web Push delivery via minishlink/web-push when user has subscription & VAPID keys exist.
 */
function attemptWebPushDispatch(PDO $conn, int $userId, string $title, string $message, string $url): array {
    $pub = envValue('VAPID_PUBLIC_KEY', '');
    $priv = envValue('VAPID_PRIVATE_KEY', '');
    $subj = envValue('VAPID_SUBJECT', 'mailto:admin@securegate.local');
    if ($pub === '' || $priv === '' || !class_exists('\Minishlink\WebPush\WebPush')) {
        return ['status' => 'Skipped', 'detail' => 'VAPID keys or WebPush library unconfigured'];
    }

    try {
        $stmt = $conn->prepare("SELECT subscription FROM sms_user_push_subscriptions WHERE user_id = :uid LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $subJson = $stmt->fetchColumn();
        if (!$subJson) {
            return ['status' => 'Skipped', 'detail' => 'No active browser push subscription'];
        }
        $subData = json_decode((string)$subJson, true);
        if (!is_array($subData) || empty($subData['endpoint'])) {
            return ['status' => 'Skipped', 'detail' => 'Invalid subscription payload'];
        }

        $auth = ['VAPID' => ['subject' => $subj, 'publicKey' => $pub, 'privateKey' => $priv]];
        $webPush = new \Minishlink\WebPush\WebPush($auth);
        $subscription = \Minishlink\WebPush\Subscription::create($subData);
        $payload = json_encode([
            'title' => $title,
            'body' => mb_substr($message, 0, 180),
            'url' => appBasePath() . $url
        ]);
        $report = $webPush->sendOneNotification($subscription, $payload);
        if ($report->isSuccess()) {
            return ['status' => 'Sent', 'detail' => 'WebPush delivered'];
        }
        return ['status' => 'Failed', 'detail' => 'WebPush failed: ' . $report->getReason()];
    } catch (Throwable $e) {
        return ['status' => 'Failed', 'detail' => $e->getMessage()];
    }
}
