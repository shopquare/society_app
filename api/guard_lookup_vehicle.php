<?php
// api/guard_lookup_vehicle.php
// Instant Guard License Plate Scanner & Parking Allocation Lookup

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/audit_logger.php';

initApi(['POST']);
requireApiAuth([1, 2, 4]); // Admin, Committee, Guard

$guardId = (int)$_SESSION['user_id'];
$societyId = (int)$_SESSION['society_id'];

$input = getJsonInput();
$rawPlate = trim($input['plate_number'] ?? '');
$cleanPlate = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($rawPlate));

if (strlen($cleanPlate) < 3) {
    jsonError('Please enter at least 3 characters of the vehicle plate.', 422);
}

// 1. Search in Registered Resident Vehicles
$stmt = $conn->prepare("
    SELECT v.id, v.unit_number, v.vehicle_type, v.vehicle_number, v.parking_slot, v.created_at,
           CONCAT(p.first_name, ' ', p.last_name) AS resident_name, p.phone_number
    FROM sms_vehicles v
    JOIN sms_users u ON v.resident_id = u.id
    JOIN sms_user_profiles p ON u.id = p.user_id
    WHERE v.society_id = :sid 
      AND REPLACE(REPLACE(UPPER(v.vehicle_number), ' ', ''), '-', '') LIKE :plate
    LIMIT 5
");
$stmt->execute([
    ':sid' => $societyId,
    ':plate' => '%' . $cleanPlate . '%'
]);
$residentVehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($residentVehicles)) {
    // Exact or partial match found in resident registry
    $primary = $residentVehicles[0];
    logAudit($conn, $societyId, $guardId, 'VEHICLE_LOOKUP_HIT', 'sms_vehicles', (int)$primary['id'], [
        'plate_query' => $rawPlate,
        'matched_plate' => $primary['vehicle_number'],
        'unit' => $primary['unit_number']
    ]);

    jsonSuccess([
        'matched' => true,
        'type' => 'resident',
        'count' => count($residentVehicles),
        'results' => $residentVehicles,
        'primary' => [
            'vehicle_number' => $primary['vehicle_number'],
            'vehicle_type' => $primary['vehicle_type'],
            'parking_slot' => $primary['parking_slot'] ?? 'Not Allocated',
            'unit_number' => $primary['unit_number'],
            'resident_name' => $primary['resident_name'],
            'phone_number' => $primary['phone_number']
        ],
        'message' => "Registered Resident Vehicle (Flat {$primary['unit_number']})"
    ]);
}

// 2. Check Recent Visitor Records for this Plate
$visStmt = $conn->prepare("
    SELECT id, visitor_name, phone_number, vehicle_number, purpose, status, entry_time, exit_time
    FROM sms_visitors
    WHERE society_id = :sid 
      AND REPLACE(REPLACE(UPPER(vehicle_number), ' ', ''), '-', '') LIKE :plate
    ORDER BY entry_time DESC
    LIMIT 3
");
$visStmt->execute([
    ':sid' => $societyId,
    ':plate' => '%' . $cleanPlate . '%'
]);
$visitorMatches = $visStmt->fetchAll(PDO::FETCH_ASSOC);

logAudit($conn, $societyId, $guardId, 'VEHICLE_LOOKUP_MISS', 'unknown', null, [
    'plate_query' => $rawPlate,
    'visitor_hits' => count($visitorMatches)
]);

jsonSuccess([
    'matched' => false,
    'type' => 'unregistered',
    'recent_visitor_history' => $visitorMatches,
    'message' => 'Vehicle is not registered in resident directory. Please register entry as visitor.'
]);
