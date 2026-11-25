<?php
// /society_app/api/resident_handle_visitor_approval.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['POST']);

$data = getJsonInput();
if (empty($data) && !empty($_POST)) {
    $data = $_POST;
}

$visitorId = (int)($data['visitor_id'] ?? 0);
$action = trim((string)($data['action'] ?? $data['status'] ?? ''));
$actionToken = trim((string)($data['action_token'] ?? ''));

// Normalize action values: 'Entered' -> 'Approve', 'Denied' / 'Decline' -> 'Deny'
if (strcasecmp($action, 'Entered') === 0) {
    $action = 'Approve';
} elseif (strcasecmp($action, 'Decline') === 0 || strcasecmp($action, 'Denied') === 0) {
    $action = 'Deny';
}

if ($visitorId <= 0 || !in_array($action, ['Approve', 'Deny'], true)) {
    jsonError('Invalid visitor approval request.', 400);
}

$newStatus = ($action === 'Approve') ? 'Entered' : 'Denied';

// Authorize via EITHER a valid HMAC action token OR an authenticated resident session
$authorizedResidentId = null;
$societyId = null;

if ($actionToken !== '') {
    $authorizedResidentId = verifyVisitorActionToken($actionToken, $visitorId);
}

if ($authorizedResidentId === null) {
    requireApiAuth([3]);
    $authorizedResidentId = (int)$_SESSION['user_id'];
    $societyId = (int)$_SESSION['society_id'];

    // If not a native app request, enforce CSRF token validation
    $reqWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    if ($reqWith !== 'com.shopquare.societyhub' && empty($data['action_token'])) {
        requireValidCsrfToken($data['csrf_token'] ?? '');
    }
}

try {
    if ($societyId !== null) {
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
            ':resident_id' => $authorizedResidentId,
            ':society_id' => $societyId,
        ]);
    } else {
        $stmt = $conn->prepare(
            "UPDATE sms_visitors
             SET status = :status
             WHERE id = :id
               AND resident_id = :resident_id
               AND status = 'Pending'"
        );
        $stmt->execute([
            ':status' => $newStatus,
            ':id' => $visitorId,
            ':resident_id' => $authorizedResidentId,
        ]);
    }

    if ($stmt->rowCount() === 0) {
        jsonError('Visitor request not found or already handled.', 404);
    }

    jsonSuccess(['message' => 'Visitor ' . strtolower($newStatus) . '.', 'visitor_status' => $newStatus]);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to process visitor decision.');
}
