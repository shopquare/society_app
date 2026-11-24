<?php
// /society_app/api/resident_handle_visitor_approval.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['POST']);
requireApiAuth([3]);

$data = getJsonInput();
requireValidCsrfToken($data['csrf_token'] ?? '');

$visitorId = (int)($data['visitor_id'] ?? 0);
$action = trim((string)($data['action'] ?? ''));

if ($visitorId <= 0 || !in_array($action, ['Approve', 'Decline', 'Deny'], true)) {
    jsonError('Invalid visitor approval request.', 400);
}

$newStatus = ($action === 'Approve') ? 'Entered' : 'Denied';

try {
    $stmt = $conn->prepare(
        "UPDATE sms_visitors
         SET status = :status
         WHERE id = :id
           AND resident_id = :resident_id
           AND society_id = :society_id
           AND status = 'Pending'"
    );
    $stmt->execute([
        ':status' => $newStatus,
        ':id' => $visitorId,
        ':resident_id' => (int)$_SESSION['user_id'],
        ':society_id' => (int)$_SESSION['society_id'],
    ]);

    if ($stmt->rowCount() === 0) {
        jsonError('Visitor request not found or already handled.', 404);
    }

    jsonSuccess(['message' => 'Visitor ' . strtolower($newStatus) . '.']);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to process visitor decision.');
}
