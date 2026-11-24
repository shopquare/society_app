<?php
// /society_app/api/admin_update_complaint.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['POST']);
requireApiAuth([1, 2]);

$data = getJsonInput();
requireValidCsrfToken($data['csrf_token'] ?? '');

$complaintId = (int)($data['complaint_id'] ?? 0);
$status = trim((string)($data['status'] ?? ''));
$allowedStatuses = ['Open', 'In Progress', 'Resolved'];

if ($complaintId <= 0 || !in_array($status, $allowedStatuses, true)) {
    jsonError('Invalid complaint ID or status update.', 400);
}

try {
    $stmt = $conn->prepare(
        'UPDATE sms_complaints
         SET status = :status
         WHERE id = :id AND society_id = :society_id'
    );
    $stmt->execute([
        ':status' => $status,
        ':id' => $complaintId,
        ':society_id' => (int)$_SESSION['society_id'],
    ]);

    if ($stmt->rowCount() === 0) {
        jsonError('Complaint not found or status unchanged.', 404);
    }

    jsonSuccess(['message' => 'Status updated.']);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to update complaint status.');
}
