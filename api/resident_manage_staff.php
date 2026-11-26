<?php
// /society_app/api/resident_manage_staff.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['GET', 'POST']);
requireApiAuth([3]);

$societyId = (int)$_SESSION['society_id'];
$residentId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Fetch society staff and check if mapped to this resident
        $stmt = $conn->prepare(
            "SELECT s.id, s.staff_name AS name, s.staff_type AS role_type, s.phone_number, s.rating, s.is_verified,
                    IF(m.id IS NOT NULL, 1, 0) AS is_my_staff
             FROM sms_domestic_staff s
             LEFT JOIN sms_resident_staff_mapping m 
                    ON s.id = m.staff_id AND m.resident_id = :resident_id
             WHERE s.society_id = :society_id
             ORDER BY is_my_staff DESC, s.staff_type ASC, s.staff_name ASC"
        );
        $stmt->execute([':society_id' => $societyId, ':resident_id' => $residentId]);
        $staff = $stmt->fetchAll();

        jsonSuccess(['data' => $staff]);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to load domestic staff directory.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonInput();
    requireValidCsrfToken($data['csrf_token'] ?? '');

    $staffId = (int)($data['staff_id'] ?? 0);
    $action = trim((string)($data['action'] ?? 'assign')); // 'assign' or 'remove'

    if ($staffId <= 0) {
        jsonError('Invalid staff ID.', 400);
    }

    try {
        // Verify staff exists in resident's society
        $sCheck = $conn->prepare("SELECT id, staff_name AS name FROM sms_domestic_staff WHERE id = :id AND society_id = :society_id");
        $sCheck->execute([':id' => $staffId, ':society_id' => $societyId]);
        $staff = $sCheck->fetch();
        if (!$staff) {
            jsonError('Domestic staff member not found.', 404);
        }

        if ($action === 'assign') {
            // Get resident's unit number
            $uStmt = $conn->prepare("SELECT unit_number FROM sms_user_units WHERE user_id = :uid AND is_current_occupant = 1 LIMIT 1");
            $uStmt->execute([':uid' => $residentId]);
            $unitRow = $uStmt->fetch();
            $unitNumber = $unitRow ? $unitRow['unit_number'] : 'N/A';

            $insStmt = $conn->prepare(
                "INSERT IGNORE INTO sms_resident_staff_mapping (society_id, resident_id, staff_id, unit_number)
                 VALUES (:society_id, :resident_id, :staff_id, :unit_number)"
            );
            $insStmt->execute([
                ':society_id' => $societyId,
                ':resident_id' => $residentId,
                ':staff_id' => $staffId,
                ':unit_number' => $unitNumber
            ]);
            jsonSuccess(['message' => $staff['name'] . ' added to your daily help roster.']);
        } elseif ($action === 'remove') {
            $delStmt = $conn->prepare(
                "DELETE FROM sms_resident_staff_mapping
                 WHERE society_id = :society_id AND resident_id = :resident_id AND staff_id = :staff_id"
            );
            $delStmt->execute([
                ':society_id' => $societyId,
                ':resident_id' => $residentId,
                ':staff_id' => $staffId
            ]);
            jsonSuccess(['message' => $staff['name'] . ' removed from your roster.']);
        } else {
            jsonError('Invalid action.', 400);
        }
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to update daily help roster.');
    }
}
