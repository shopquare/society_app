<?php
// /society_app/api/guard_visitor_exit.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['POST']);
requireApiAuth([4]);

$data = getJsonInput();
requireValidCsrfToken($data['csrf_token'] ?? '');

$visitorId = (int)($data['visitor_id'] ?? 0);
if ($visitorId <= 0) {
    jsonError('Valid visitor ID is required.', 400);
}

try {
    $stmt = $conn->prepare(
        "UPDATE sms_visitors
         SET status = 'Exited', exit_time = NOW()
         WHERE id = :id
           AND society_id = :society_id
           AND status = 'Entered'"
    );
    $stmt->execute([
        ':id' => $visitorId,
        ':society_id' => (int)$_SESSION['society_id'],
    ]);

    if ($stmt->rowCount() > 0) {
        jsonSuccess(['message' => 'Visitor marked as exited.']);
    }

    jsonError('Visitor already exited or not eligible for exit.', 400);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to mark visitor exit.');
}
