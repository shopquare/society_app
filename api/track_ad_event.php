<?php
// /society_app/api/track_ad_event.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['GET', 'POST']);

$method = $_SERVER['REQUEST_METHOD'];
$data = ($method === 'POST') ? getJsonInput() : $_GET;

$adId = (int)($data['ad_id'] ?? 0);
$eventType = strtolower(trim((string)($data['event_type'] ?? 'impression')));
$placement = trim((string)($data['placement'] ?? ''));
$doRedirect = !empty($data['redirect']);

if ($adId <= 0) {
    if ($doRedirect) {
        header('Location: ' . appBasePath() . '/resident/dashboard');
        exit;
    }
    jsonError('Valid advertisement ID is required.', 400);
}

if (!in_array($eventType, ['impression', 'click'], true)) {
    $eventType = 'impression';
}

try {
    // 1. Fetch advertisement
    $stmt = $conn->prepare("SELECT id, society_id, target_url FROM sms_advertisements WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $adId]);
    $ad = $stmt->fetch();

    if (!$ad) {
        if ($doRedirect) {
            header('Location: ' . appBasePath() . '/resident/dashboard');
            exit;
        }
        jsonError('Advertisement not found.', 404);
    }

    $societyId = (int)($ad['society_id'] ?? ($_SESSION['society_id'] ?? 1));
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    // 2. Increment counter on sms_advertisements
    if ($eventType === 'click') {
        $up = $conn->prepare("UPDATE sms_advertisements SET clicks_count = clicks_count + 1 WHERE id = :id");
    } else {
        $up = $conn->prepare("UPDATE sms_advertisements SET impressions_count = impressions_count + 1 WHERE id = :id");
    }
    $up->execute([':id' => $adId]);

    // 3. Insert analytics log entry
    $logStmt = $conn->prepare(
        "INSERT INTO sms_advertisement_analytics (advertisement_id, society_id, event_type, placement, user_id, ip_address, user_agent)
         VALUES (:aid, :sid, :etype, :placement, :uid, :ip, :ua)"
    );
    $logStmt->execute([
        ':aid' => $adId,
        ':sid' => $societyId,
        ':etype' => $eventType,
        ':placement' => $placement ?: null,
        ':uid' => $userId,
        ':ip' => $ip,
        ':ua' => $ua
    ]);

    // 4. Handle Click Redirect
    if ($doRedirect && $eventType === 'click') {
        $targetUrl = trim((string)($ad['target_url'] ?? ''));
        if ($targetUrl !== '' && filter_var($targetUrl, FILTER_VALIDATE_URL)) {
            header('Location: ' . $targetUrl, true, 302);
            exit;
        }
        header('Location: ' . appBasePath() . '/resident/dashboard', true, 302);
        exit;
    }

    jsonSuccess([
        'recorded' => true,
        'ad_id' => $adId,
        'event_type' => $eventType
    ]);
} catch (Throwable $e) {
    if ($doRedirect) {
        header('Location: ' . appBasePath() . '/resident/dashboard');
        exit;
    }
    logAndFail($e, 'Unable to track advertisement interaction.');
}
