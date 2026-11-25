<?php
// api/resident_vehicles.php
// Resident Vehicle & Parking Slot Management API

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/audit_logger.php';

initApi(['GET', 'POST', 'DELETE']);
requireApiAuth([1, 2, 3]);

$userId = (int)$_SESSION['user_id'];
$societyId = (int)$_SESSION['society_id'];

// Get resident unit number
$stmtUnit = $conn->prepare("
    SELECT unit_number FROM sms_user_units 
    WHERE user_id = :uid AND society_id = :sid AND is_current_occupant = 1 
    LIMIT 1
");
$stmtUnit->execute([':uid' => $userId, ':sid' => $societyId]);
$residentUnit = $stmtUnit->fetchColumn();

if (!$residentUnit && (int)$_SESSION['role_id'] === 3) {
    jsonError('No active unit assigned to your account.', 400);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    // List vehicles for this unit (or all vehicles if admin requested with all=1)
    if (in_array((int)$_SESSION['role_id'], [1, 2], true) && !empty($_GET['all'])) {
        $stmt = $conn->prepare("
            SELECT v.id, v.unit_number, v.vehicle_type, v.vehicle_number, v.parking_slot, v.created_at,
                   CONCAT(p.first_name, ' ', p.last_name) AS resident_name, p.phone_number
            FROM sms_vehicles v
            JOIN sms_users u ON v.resident_id = u.id
            JOIN sms_user_profiles p ON u.id = p.user_id
            WHERE v.society_id = :sid
            ORDER BY v.unit_number ASC, v.created_at DESC
        ");
        $stmt->execute([':sid' => $societyId]);
    } else {
        $stmt = $conn->prepare("
            SELECT id, unit_number, vehicle_type, vehicle_number, parking_slot, created_at
            FROM sms_vehicles
            WHERE society_id = :sid AND unit_number = :unit
            ORDER BY created_at DESC
        ");
        $stmt->execute([':sid' => $societyId, ':unit' => $residentUnit]);
    }
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    jsonSuccess(['vehicles' => $vehicles, 'unit_number' => $residentUnit]);
}

if ($method === 'POST') {
    $input = getJsonInput();
    $vehicleType = trim($input['vehicle_type'] ?? '');
    $vehicleNumber = strtoupper(trim($input['vehicle_number'] ?? ''));
    $parkingSlot = trim($input['parking_slot'] ?? '');
    $targetUnit = !empty($input['unit_number']) && in_array((int)$_SESSION['role_id'], [1, 2], true)
        ? trim($input['unit_number'])
        : $residentUnit;

    if (!in_array($vehicleType, ['2_wheeler', '4_wheeler'], true)) {
        jsonError('Vehicle type must be 2_wheeler or 4_wheeler.', 422);
    }
    if (strlen($vehicleNumber) < 4 || strlen($vehicleNumber) > 30) {
        jsonError('Please enter a valid vehicle license plate number.', 422);
    }

    // Check duplicate plate in society
    $cleanPlate = preg_replace('/[^A-Za-z0-9]/', '', $vehicleNumber);
    $checkStmt = $conn->prepare("
        SELECT id, unit_number FROM sms_vehicles 
        WHERE society_id = :sid AND REPLACE(REPLACE(UPPER(vehicle_number), ' ', ''), '-', '') = :plate
        LIMIT 1
    ");
    $checkStmt->execute([':sid' => $societyId, ':plate' => $cleanPlate]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        jsonError("Vehicle {$vehicleNumber} is already registered to Flat {$existing['unit_number']}.", 409);
    }

    $insertStmt = $conn->prepare("
        INSERT INTO sms_vehicles (society_id, resident_id, unit_number, vehicle_type, vehicle_number, parking_slot, created_at)
        VALUES (:sid, :uid, :unit, :vtype, :vnum, :slot, NOW())
    ");
    $insertStmt->execute([
        ':sid' => $societyId,
        ':uid' => $userId,
        ':unit' => $targetUnit,
        ':vtype' => $vehicleType,
        ':vnum' => $vehicleNumber,
        ':slot' => !empty($parkingSlot) ? $parkingSlot : null
    ]);
    $vehicleId = (int)$conn->lastInsertId();

    logAudit($conn, $societyId, $userId, 'VEHICLE_REGISTERED', 'sms_vehicles', $vehicleId, [
        'vehicle_number' => $vehicleNumber,
        'unit_number' => $targetUnit,
        'vehicle_type' => $vehicleType,
        'parking_slot' => $parkingSlot
    ]);

    jsonSuccess([
        'message' => 'Vehicle registered successfully.',
        'vehicle_id' => $vehicleId
    ], 201);
}

if ($method === 'DELETE') {
    $input = getJsonInput();
    $vehicleId = (int)($input['vehicle_id'] ?? $_GET['vehicle_id'] ?? 0);
    if ($vehicleId <= 0) {
        jsonError('Valid vehicle_id is required.', 400);
    }

    // Authorization check
    if (in_array((int)$_SESSION['role_id'], [1, 2], true)) {
        $delStmt = $conn->prepare("DELETE FROM sms_vehicles WHERE id = :id AND society_id = :sid");
        $delStmt->execute([':id' => $vehicleId, ':sid' => $societyId]);
    } else {
        $delStmt = $conn->prepare("DELETE FROM sms_vehicles WHERE id = :id AND society_id = :sid AND unit_number = :unit");
        $delStmt->execute([':id' => $vehicleId, ':sid' => $societyId, ':unit' => $residentUnit]);
    }

    if ($delStmt->rowCount() > 0) {
        logAudit($conn, $societyId, $userId, 'VEHICLE_REMOVED', 'sms_vehicles', $vehicleId, ['vehicle_id' => $vehicleId]);
        jsonSuccess(['message' => 'Vehicle removed successfully.']);
    } else {
        jsonError('Vehicle not found or unauthorized to delete.', 404);
    }
}
