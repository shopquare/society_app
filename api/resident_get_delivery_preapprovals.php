<?php
// /society_app/api/resident_get_delivery_preapprovals.php
// Returns active delivery pre-approvals for the resident

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['GET']);
requireApiAuth([3]);

try {
    $societyId = (int)$_SESSION['society_id'];
    $residentId = (int)$_SESSION['user_id'];

    $now = (new DateTime())->format('Y-m-d H:i:s');

    $stmt = $conn->prepare(
        "SELECT id, company, instruction, valid_until, status, created_at
         FROM sms_delivery_preapprovals
         WHERE society_id = :society_id AND resident_id = :resident_id AND status = 'Active' AND valid_until > :now
         ORDER BY created_at DESC"
    );
    $stmt->execute([
        ':society_id' => $societyId,
        ':resident_id' => $residentId,
        ':now' => $now
    ]);
    $preapprovals = $stmt->fetchAll();

    jsonSuccess([
        'preapprovals' => $preapprovals,
        'count' => count($preapprovals)
    ]);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to retrieve delivery pre-approvals.');
}
