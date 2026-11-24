<?php
// /society_app/api/verify_society.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['POST']);

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
checkRateLimit('verify_society_' . $ip, 20, 300);

$data = getJsonInput();
$rawCode = trim((string)($data['society_code'] ?? ''));

if ($rawCode === '') {
    jsonError('Please enter a society code.', 400);
}

try {
    $code = strtoupper($rawCode);
    $stmt = $conn->prepare('SELECT id, society_name, city FROM sms_societies WHERE society_code = :code LIMIT 1');
    $stmt->execute([':code' => $code]);
    $society = $stmt->fetch();

    if ($society) {
        jsonSuccess([
            'message' => 'Society verified successfully.',
            'data' => [
                'society_id' => (int)$society['id'],
                'society_name' => $society['society_name'],
                'city' => $society['city'],
            ],
        ]);
    }

    jsonError('Invalid society invite code. Please check with your committee.', 404);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to verify society code.');
}
