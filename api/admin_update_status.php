<?php
// /society_app/api/admin_update_status.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['POST']);
requireApiAuth([1, 2]);

$data = getJsonInput();
requireValidCsrfToken($data['csrf_token'] ?? '');

$targetUserId = (int)($data['user_id'] ?? 0);
$newStatus = trim((string)($data['status'] ?? ''));

if ($targetUserId <= 0 || !in_array($newStatus, ['active', 'rejected'], true)) {
    jsonError('Invalid user ID or status provided.', 400);
}

if ($targetUserId === (int)$_SESSION['user_id']) {
    jsonError('You cannot modify your own account status.', 400);
}

try {
    // Restrict updates to resident accounts within the admin's society
    $stmt = $conn->prepare(
        "UPDATE sms_users
         SET status = :status
         WHERE id = :user_id AND society_id = :society_id AND role_id = 3"
    );
    $stmt->execute([
        ':status' => $newStatus,
        ':user_id' => $targetUserId,
        ':society_id' => (int)$_SESSION['society_id'],
    ]);

    if ($stmt->rowCount() > 0) {
        jsonSuccess(['message' => 'User status updated successfully.']);
    }

    jsonError('User not found or already updated.', 404);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to update user status.');
}
