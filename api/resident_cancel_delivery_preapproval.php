<?php
// /society_app/api/resident_cancel_delivery_preapproval.php
// Cancels an active delivery pre-approval

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['POST']);
requireApiAuth([3]);

$data = getJsonInput();
requireValidCsrfToken($data['csrf_token'] ?? '');

$id = (int)($data['id'] ?? 0);
if ($id <= 0) {
    jsonError('Valid pre-approval ID is required.', 400);
}

try {
    $societyId = (int)$_SESSION['society_id'];
    $residentId = (int)$_SESSION['user_id'];

    $stmt = $conn->prepare(
        "UPDATE sms_delivery_preapprovals
         SET status = 'Cancelled'
         WHERE id = :id AND society_id = :society_id AND resident_id = :resident_id"
    );
    $stmt->execute([
        ':id' => $id,
        ':society_id' => $societyId,
        ':resident_id' => $residentId
    ]);

    if ($stmt->rowCount() > 0) {
        jsonSuccess(['message' => 'Delivery pre-approval cancelled.']);
    } else {
        jsonError('Pre-approval record not found or already cancelled.', 404);
    }
} catch (Throwable $e) {
    logAndFail($e, 'Unable to cancel delivery pre-approval.');
}
