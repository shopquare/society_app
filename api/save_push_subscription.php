<?php
// /society_app/api/save_push_subscription.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['POST']);
requireApiAuth([3]);

$data = getJsonInput();
requireValidCsrfToken($data['csrf_token'] ?? '');

$subscription = $data['subscription'] ?? null;
if (!is_array($subscription) || empty($subscription['endpoint']) || !filter_var($subscription['endpoint'], FILTER_VALIDATE_URL)) {
    jsonError('Invalid subscription data.', 400);
}

try {
    $userId = (int)$_SESSION['user_id'];
    $subJson = json_encode($subscription);
    if ($subJson === false) {
        jsonError('Invalid subscription payload.', 400);
    }

    $stmt = $conn->prepare(
        'INSERT INTO sms_user_push_subscriptions (user_id, subscription)
         VALUES (:user_id, :subscription)
         ON DUPLICATE KEY UPDATE subscription = :subscription2'
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':subscription' => $subJson,
        ':subscription2' => $subJson,
    ]);

    jsonSuccess(['message' => 'Push subscription saved successfully.']);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to save push subscription.');
}
