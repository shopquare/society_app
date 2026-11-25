<?php
// api/resident_get_parcels.php
// Resident Parcel Locker Status API

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['GET']);
requireApiAuth([1, 2, 3]);

$userId = (int)$_SESSION['user_id'];
$societyId = (int)$_SESSION['society_id'];

// Get resident unit
$unitStmt = $conn->prepare("
    SELECT unit_number FROM sms_user_units 
    WHERE user_id = :uid AND society_id = :sid AND is_current_occupant = 1 
    LIMIT 1
");
$unitStmt->execute([':uid' => $userId, ':sid' => $societyId]);
$residentUnit = $unitStmt->fetchColumn();

if (!$residentUnit && (int)$_SESSION['role_id'] === 3) {
    jsonSuccess(['parcels' => [], 'count' => 0, 'unit' => null]);
}

$stmt = $conn->prepare("
    SELECT p.id, p.courier_company, p.recipient_name, p.status, p.received_at,
           CONCAT(gprof.first_name, ' ', gprof.last_name) AS guard_name
    FROM sms_gate_parcels p
    JOIN sms_users gu ON p.received_by_guard_id = gu.id
    JOIN sms_user_profiles gprof ON gu.id = gprof.user_id
    WHERE p.society_id = :sid AND p.unit_number = :unit AND p.status = 'At Gate'
    ORDER BY p.received_at DESC
");
$stmt->execute([':sid' => $societyId, ':unit' => $residentUnit]);
$parcels = $stmt->fetchAll(PDO::FETCH_ASSOC);

jsonSuccess([
    'parcels' => $parcels,
    'count' => count($parcels),
    'unit_number' => $residentUnit
]);
