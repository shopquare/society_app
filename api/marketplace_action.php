<?php
// /society_app/api/marketplace_action.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['POST']);
requireApiAuth([1, 2, 3]);

$societyId = (int)$_SESSION['society_id'];
$userId = (int)$_SESSION['user_id'];
$roleId = (int)$_SESSION['role_id'];

$data = getJsonInput();
requireValidCsrfToken($data['csrf_token'] ?? '');

$listingId = (int)($data['listing_id'] ?? 0);
$action = trim((string)($data['action'] ?? ''));

if ($listingId <= 0) {
    jsonError('Invalid listing ID.', 400);
}

try {
    // Verify listing belongs to user's society
    $checkStmt = $conn->prepare("SELECT id, seller_id, status FROM sms_marketplace_listings WHERE id = :id AND society_id = :society_id");
    $checkStmt->execute([':id' => $listingId, ':society_id' => $societyId]);
    $listing = $checkStmt->fetch();

    if (!$listing) {
        jsonError('Marketplace listing not found.', 404);
    }

    if ($action === 'mark_sold') {
        if ($listing['seller_id'] !== $userId && !hasRole([1, 2])) {
            jsonError('Only the seller or an administrator can mark an item as sold.', 403);
        }
        $upStmt = $conn->prepare("UPDATE sms_marketplace_listings SET status = 'Sold' WHERE id = :id");
        $upStmt->execute([':id' => $listingId]);
        jsonSuccess(['message' => 'Item marked as Sold!']);
    } elseif ($action === 'delete') {
        if ($listing['seller_id'] !== $userId && !hasRole([1, 2])) {
            jsonError('Unauthorized to delete this listing.', 403);
        }
        $upStmt = $conn->prepare("UPDATE sms_marketplace_listings SET status = 'Deleted' WHERE id = :id");
        $upStmt->execute([':id' => $listingId]);
        jsonSuccess(['message' => 'Listing removed successfully.']);
    } elseif ($action === 'save') {
        // Toggle saved/favorite
        $favCheck = $conn->prepare("SELECT id FROM sms_marketplace_saved WHERE listing_id = :lid AND user_id = :uid");
        $favCheck->execute([':lid' => $listingId, ':uid' => $userId]);
        $existing = $favCheck->fetch();

        if ($existing) {
            $del = $conn->prepare("DELETE FROM sms_marketplace_saved WHERE id = :id");
            $del->execute([':id' => $existing['id']]);
            jsonSuccess(['saved' => false, 'message' => 'Removed from saved items.']);
        } else {
            $ins = $conn->prepare("INSERT INTO sms_marketplace_saved (listing_id, user_id) VALUES (:lid, :uid)");
            $ins->execute([':lid' => $listingId, ':uid' => $userId]);
            jsonSuccess(['saved' => true, 'message' => 'Saved to your favorites!']);
        }
    } elseif ($action === 'report') {
        $reason = trim((string)($data['reason'] ?? 'Inappropriate Content'));
        $repStmt = $conn->prepare(
            "INSERT INTO sms_marketplace_reports (listing_id, reported_by, reason)
             VALUES (:lid, :uid, :reason)"
        );
        $repStmt->execute([
            ':lid' => $listingId,
            ':uid' => $userId,
            ':reason' => $reason
        ]);
        jsonSuccess(['message' => 'Listing reported to society administration for review.']);
    } else {
        jsonError('Unknown action requested.', 400);
    }
} catch (Throwable $e) {
    logAndFail($e, 'Unable to process marketplace action.');
}
