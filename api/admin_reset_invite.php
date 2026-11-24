<?php
// /society_app/api/admin_reset_invite.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['POST']);
requireApiAuth([1, 2]);

$data = getJsonInput();
requireValidCsrfToken($data['csrf_token'] ?? '');

try {
    $newCode = '';
    $checkStmt = $conn->prepare('SELECT id FROM sms_societies WHERE society_code = :code LIMIT 1');

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $candidate = strtoupper(bin2hex(random_bytes(4)));
        $checkStmt->execute([':code' => $candidate]);
        if (!$checkStmt->fetch()) {
            $newCode = $candidate;
            break;
        }
    }

    if ($newCode === '') {
        jsonError('Unable to generate a unique invite code. Please try again.', 500);
    }

    $stmt = $conn->prepare('UPDATE sms_societies SET society_code = :new_code WHERE id = :society_id');
    $stmt->execute([
        ':new_code' => $newCode,
        ':society_id' => (int)$_SESSION['society_id'],
    ]);

    jsonSuccess([
        'message' => 'Invite code regenerated successfully.',
        'new_code' => $newCode,
    ]);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to regenerate invite code.');
}
