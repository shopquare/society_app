<?php
// api/guard_parcels.php
// Gate Parcel Locker & Delivery Handover API

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/audit_logger.php';

initApi(['GET', 'POST', 'PUT']);
requireApiAuth([1, 2, 4]); // Guard, Admin, Committee

$guardId = (int)$_SESSION['user_id'];
$societyId = (int)$_SESSION['society_id'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    // List all active parcels at the gate
    $filter = trim($_GET['status'] ?? 'At Gate');
    $sql = "
        SELECT p.id, p.unit_number, p.courier_company, p.recipient_name, p.status, p.received_at, p.collected_at,
               CONCAT(prof.first_name, ' ', prof.last_name) AS resident_name, prof.phone_number,
               CONCAT(gprof.first_name, ' ', gprof.last_name) AS guard_name
        FROM sms_gate_parcels p
        JOIN sms_users u ON p.resident_id = u.id
        JOIN sms_user_profiles prof ON u.id = prof.user_id
        JOIN sms_users gu ON p.received_by_guard_id = gu.id
        JOIN sms_user_profiles gprof ON gu.id = gprof.user_id
        WHERE p.society_id = :sid
    ";
    if ($filter === 'At Gate') {
        $sql .= " AND p.status = 'At Gate' ORDER BY p.received_at DESC";
    } else {
        $sql .= " ORDER BY p.received_at DESC LIMIT 50";
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute([':sid' => $societyId]);
    $parcels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonSuccess(['parcels' => $parcels, 'total_active' => count($parcels)]);
}

if ($method === 'POST') {
    // Log new parcel arriving at gate
    $input = getJsonInput();
    $unitNumber = trim($input['unit_number'] ?? '');
    $courier = trim($input['courier_company'] ?? '');
    $recipient = trim($input['recipient_name'] ?? '');

    if ($unitNumber === '') {
        jsonError('Unit / Flat number is required.', 422);
    }
    if ($courier === '') {
        jsonError('Courier or delivery company name is required.', 422);
    }

    // Find resident for this unit
    $resStmt = $conn->prepare("
        SELECT u.id, CONCAT(p.first_name, ' ', p.last_name) AS full_name 
        FROM sms_user_units uu
        JOIN sms_users u ON uu.user_id = u.id
        JOIN sms_user_profiles p ON u.id = p.user_id
        WHERE uu.society_id = :sid AND uu.unit_number = :unit AND uu.is_current_occupant = 1
        LIMIT 1
    ");
    $resStmt->execute([':sid' => $societyId, ':unit' => $unitNumber]);
    $resident = $resStmt->fetch(PDO::FETCH_ASSOC);

    if (!$resident) {
        jsonError("No registered resident found for Flat {$unitNumber}.", 404);
    }
    $residentId = (int)$resident['id'];

    $insertStmt = $conn->prepare("
        INSERT INTO sms_gate_parcels (society_id, resident_id, unit_number, courier_company, recipient_name, received_by_guard_id, status, received_at)
        VALUES (:sid, :rid, :unit, :courier, :recip, :gid, 'At Gate', NOW())
    ");
    $insertStmt->execute([
        ':sid' => $societyId,
        ':rid' => $residentId,
        ':unit' => $unitNumber,
        ':courier' => $courier,
        ':recip' => !empty($recipient) ? $recipient : $resident['full_name'],
        ':gid' => $guardId
    ]);
    $parcelId = (int)$conn->lastInsertId();

    // Create resident in-app notification
    try {
        $notifStmt = $conn->prepare("
            INSERT INTO sms_notifications (user_id, society_id, title, message, type, created_at)
            VALUES (:uid, :sid, :title, :msg, 'delivery', NOW())
        ");
        $notifStmt->execute([
            ':uid' => $residentId,
            ':sid' => $societyId,
            ':title' => '📦 Parcel Arrived at Security Gate',
            ':msg' => "A parcel from {$courier} for Flat {$unitNumber} has been received by security and is safely held at the gate."
        ]);
    } catch (Throwable $ignore) {}

    logAudit($conn, $societyId, $guardId, 'PARCEL_LOGGED_AT_GATE', 'sms_gate_parcels', $parcelId, [
        'unit_number' => $unitNumber,
        'courier' => $courier,
        'recipient' => $recipient
    ]);

    jsonSuccess([
        'message' => "Parcel from {$courier} logged successfully for Flat {$unitNumber}.",
        'parcel_id' => $parcelId
    ], 201);
}

if ($method === 'PUT') {
    // Mark parcel as collected by resident
    $input = getJsonInput();
    $parcelId = (int)($input['parcel_id'] ?? 0);
    if ($parcelId <= 0) {
        jsonError('Valid parcel_id is required.', 422);
    }

    $updateStmt = $conn->prepare("
        UPDATE sms_gate_parcels 
        SET status = 'Collected', collected_at = NOW()
        WHERE id = :id AND society_id = :sid AND status = 'At Gate'
    ");
    $updateStmt->execute([':id' => $parcelId, ':sid' => $societyId]);

    if ($updateStmt->rowCount() > 0) {
        logAudit($conn, $societyId, $guardId, 'PARCEL_HANDED_OVER', 'sms_gate_parcels', $parcelId, ['parcel_id' => $parcelId]);
        jsonSuccess(['message' => 'Parcel marked as collected by resident.']);
    } else {
        jsonError('Parcel not found or already collected.', 404);
    }
}
