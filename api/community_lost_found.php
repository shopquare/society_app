<?php
// api/community_lost_found.php
// Lost & Found Submission, Claim Verification & Privacy-Masked Listing API

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/upload_helper.php';
require_once __DIR__ . '/../includes/audit_logger.php';
require_once __DIR__ . '/../includes/notification_service.php';

initApi(['GET', 'POST']);
requireApiAuth([1, 2, 3, 4]);

$societyId = (int)$_SESSION['society_id'];
$userId = (int)$_SESSION['user_id'];
$isAdmin = hasPermission('community.moderate');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Strictly mask reporter personal phone & email from public listings
        $stmt = $conn->prepare(
            "SELECT lf.id, lf.report_type, lf.item_name, lf.category, lf.description, lf.event_date,
                    lf.location, lf.photo_path, lf.custody_status, lf.contact_preference, lf.status, lf.created_at,
                    (lf.reported_by = :me) AS is_mine,
                    CONCAT(p.first_name, ' ', LEFT(p.last_name, 1), '.') AS reporter_display_name
             FROM sms_lost_found lf
             LEFT JOIN sms_user_profiles p ON lf.reported_by = p.user_id
             WHERE lf.society_id = :sid AND (lf.status != 'Closed' OR :is_adm = 1)
             ORDER BY lf.created_at DESC LIMIT 60"
        );
        $stmt->execute([':me' => $userId, ':sid' => $societyId, ':is_adm' => $isAdmin ? 1 : 0]);
        $items = $stmt->fetchAll();

        $claims = [];
        if ($isAdmin) {
            $cStmt = $conn->prepare(
                "SELECT c.*, lf.item_name, lf.report_type,
                        CONCAT(cp.first_name, ' ', cp.last_name) AS claimant_name, cp.phone_number AS claimant_phone, uu.unit_number
                 FROM sms_lost_found_claims c
                 INNER JOIN sms_lost_found lf ON c.item_id = lf.id
                 INNER JOIN sms_user_profiles cp ON c.claimant_id = cp.user_id
                 LEFT JOIN sms_user_units uu ON c.claimant_id = uu.user_id AND uu.is_current_occupant = 1
                 WHERE c.society_id = :sid
                 ORDER BY (c.status = 'Pending') DESC, c.created_at DESC LIMIT 50"
            );
            $cStmt->execute([':sid' => $societyId]);
            $claims = $cStmt->fetchAll();
        }

        jsonSuccess(['items' => $items, 'claims' => $claims]);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to load Lost & Found board.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonInput();
    requireValidCsrfToken($data['csrf_token'] ?? '');
    $action = trim((string)($data['action'] ?? ''));

    try {
        if ($action === 'report_item') {
            $rtype = trim((string)($data['report_type'] ?? 'Lost'));
            $itemName = trim((string)($data['item_name'] ?? ''));
            $cat = trim((string)($data['category'] ?? 'Personal Item'));
            $desc = trim((string)($data['description'] ?? ''));
            $evDate = trim((string)($data['event_date'] ?? date('Y-m-d')));
            $loc = trim((string)($data['location'] ?? ''));
            $custody = trim((string)($data['custody_status'] ?? ($rtype === 'Found' ? 'With Finder / Security Desk' : 'Missing')));

            if (!in_array($rtype, ['Lost', 'Found'], true) || $itemName === '' || $desc === '' || $loc === '') {
                jsonError('Type, item name, description, and location are required.', 400);
            }

            $photoPath = null;
            if (!empty($data['photo_base64'])) {
                $up = saveBase64Upload((string)$data['photo_base64'], 'lost_found', ['image/jpeg', 'image/png', 'image/webp'], 5242880);
                $photoPath = $up['relative_path'];
            }

            $conn->prepare(
                "INSERT INTO sms_lost_found (society_id, reported_by, report_type, item_name, category, description, event_date, location, photo_path, custody_status, status)
                 VALUES (:sid, :by, :rt, :name, :cat, :desc, :edate, :loc, :pp, :cust, 'New')"
            )->execute([
                ':sid' => $societyId, ':by' => $userId, ':rt' => $rtype, ':name' => $itemName,
                ':cat' => $cat, ':desc' => $desc, ':edate' => $evDate, ':loc' => $loc,
                ':pp' => $photoPath, ':cust' => $custody
            ]);
            $id = (int)$conn->lastInsertId();

            dispatchNotification($conn, [
                'society_id' => $societyId, 'role_ids' => [1, 2], 'category' => 'general', 'priority' => 'Normal',
                'title' => "Lost & Found [{$rtype}]: {$itemName}", 'message' => "Reported near {$loc}. Review on Community Moderation console.",
                'action_url' => '/admin/community', 'channels' => ['in_app']
            ]);
            jsonSuccess(['message' => "{$rtype} item report submitted. Personal contact stays private.", 'item_id' => $id], 201);
        } elseif ($action === 'submit_claim') {
            $itemId = (int)($data['item_id'] ?? 0);
            $proof = trim((string)($data['proof_details'] ?? ''));
            if ($itemId <= 0 || strlen($proof) < 5) {
                jsonError('Please provide identifying proof details to claim this item.', 400);
            }
            $chk = $conn->prepare("SELECT id, item_name FROM sms_lost_found WHERE id = :id AND society_id = :sid AND status IN ('New', 'Under Review')");
            $chk->execute([':id' => $itemId, ':sid' => $societyId]);
            $item = $chk->fetch();
            if (!$item) jsonError('Item unavailable for claim.', 404);

            $conn->prepare("INSERT INTO sms_lost_found_claims (item_id, society_id, claimant_id, proof_details) VALUES (:iid, :sid, :cid, :proof)")
                ->execute([':iid' => $itemId, ':sid' => $societyId, ':cid' => $userId, ':proof' => $proof]);

            $conn->prepare("UPDATE sms_lost_found SET status = 'Under Review' WHERE id = :id AND status = 'New'")->execute([':id' => $itemId]);
            dispatchNotification($conn, [
                'society_id' => $societyId, 'role_ids' => [1, 2], 'category' => 'general', 'priority' => 'High',
                'title' => "Lost & Found Claim: {$item['item_name']}", 'message' => 'A resident submitted ownership proof for verification.',
                'action_url' => '/admin/community', 'channels' => ['in_app']
            ]);
            jsonSuccess(['message' => 'Claim submitted securely for Admin verification.']);
        } elseif ($action === 'admin_verify_claim') {
            requireApiPermission('community.moderate');
            $claimId = (int)($data['claim_id'] ?? 0);
            $decision = trim((string)($data['decision'] ?? '')); // Verified | Rejected
            if (!in_array($decision, ['Verified', 'Rejected'], true)) jsonError('Invalid decision.', 400);

            $cStmt = $conn->prepare("SELECT * FROM sms_lost_found_claims WHERE id = :id AND society_id = :sid");
            $cStmt->execute([':id' => $claimId, ':sid' => $societyId]);
            $claim = $cStmt->fetch();
            if (!$claim) jsonError('Claim not found.', 404);

            $conn->prepare("UPDATE sms_lost_found_claims SET status = :st, verified_by = :by WHERE id = :id")
                ->execute([':st' => $decision, ':by' => $userId, ':id' => $claimId]);
            if ($decision === 'Verified') {
                $conn->prepare("UPDATE sms_lost_found SET status = 'Claimed', reviewed_by = :by WHERE id = :iid")
                    ->execute([':by' => $userId, ':iid' => (int)$claim['item_id']]);
            }
            dispatchNotification($conn, [
                'society_id' => $societyId, 'user_ids' => [(int)$claim['claimant_id']], 'category' => 'general', 'priority' => 'High',
                'title' => "Lost & Found Claim {$decision}", 'message' => $decision === 'Verified' ? 'Your claim was verified. Please collect the item from the security office.' : 'Your Lost & Found claim could not be verified.',
                'action_url' => '/resident/community?tab=lostfound', 'channels' => ['in_app']
            ]);
            jsonSuccess(['message' => "Claim marked {$decision}."]);
        } elseif ($action === 'admin_update_item') {
            requireApiPermission('community.moderate');
            $itemId = (int)($data['item_id'] ?? 0);
            $status = trim((string)($data['status'] ?? ''));
            if (!in_array($status, ['New', 'Under Review', 'Claimed', 'Returned', 'Closed'], true)) jsonError('Invalid status.', 400);

            $conn->prepare("UPDATE sms_lost_found SET status = :st, reviewed_by = :by WHERE id = :id AND society_id = :sid")
                ->execute([':st' => $status, ':by' => $userId, ':id' => $itemId, ':sid' => $societyId]);
            logAudit($conn, $societyId, $userId, 'LOST_FOUND_STATUS', 'lost_found', $itemId, ['status' => $status]);
            jsonSuccess(['message' => "Item status updated to {$status}."]);
        } else {
            jsonError('Unknown Lost & Found action.', 400);
        }
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to update Lost & Found record.');
    }
}
