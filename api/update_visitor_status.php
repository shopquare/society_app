<?php
// /society_app/api/update_visitor_status.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['POST']);

$data = getJsonInput();
$visitorId = (int)($data['visitor_id'] ?? 0);
$rawStatus = trim((string)($data['status'] ?? ''));
$actionToken = (string)($data['action_token'] ?? '');

// Normalize 'Approved' from legacy SW payloads to 'Entered'
$normalizedStatus = $rawStatus === 'Approved' ? 'Entered' : $rawStatus;
if ($visitorId <= 0 || !in_array($normalizedStatus, ['Entered', 'Denied'], true)) {
    jsonError('Invalid visitor ID or status.', 400);
}

// Authorize via EITHER a valid resident session (+ CSRF if provided) OR a valid HMAC action token
$authorizedResidentId = null;
if ($actionToken !== '') {
    $authorizedResidentId = verifyVisitorActionToken($actionToken, $visitorId);
}

if ($authorizedResidentId === null) {
    requireApiAuth([3]);
    requireValidCsrfToken($data['csrf_token'] ?? '');
    $authorizedResidentId = (int)$_SESSION['user_id'];
}

try {
    $stmt = $conn->prepare(
        "UPDATE sms_visitors
         SET status = :status
         WHERE id = :id AND resident_id = :resident_id AND status = 'Pending'"
    );
    $stmt->execute([
        ':status' => $normalizedStatus,
        ':id' => $visitorId,
        ':resident_id' => $authorizedResidentId,
    ]);

    if ($stmt->rowCount() === 0) {
        jsonError('Visitor entry not found or already processed.', 404);
    }

    echo json_encode(['status' => 'success', 'success' => true, 'visitor_status' => $normalizedStatus]);
    exit();
} catch (Throwable $e) {
    logAndFail($e, 'Unable to update visitor status.');
}
