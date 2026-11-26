<?php
// api/admin_manage_buildings.php
// Building, Tower & Flat Registry API

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/audit_logger.php';

initApi(['GET', 'POST']);
requireApiPermission('buildings.manage');

$societyId = (int)$_SESSION['society_id'];
$actorId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $bStmt = $conn->prepare(
            "SELECT b.*,
                    (SELECT COUNT(*) FROM sms_units u WHERE u.building_id = b.id AND u.society_id = b.society_id) AS unit_count
             FROM sms_buildings b
             WHERE b.society_id = :sid
             ORDER BY b.building_code ASC, b.building_name ASC"
        );
        $bStmt->execute([':sid' => $societyId]);
        $buildings = $bStmt->fetchAll();

        $uStmt = $conn->prepare(
            "SELECT un.*, b.building_name, b.building_code
             FROM sms_units un
             INNER JOIN sms_buildings b ON un.building_id = b.id
             WHERE un.society_id = :sid
             ORDER BY b.building_code ASC, un.floor_number ASC, un.unit_number ASC"
        );
        $uStmt->execute([':sid' => $societyId]);
        $units = $uStmt->fetchAll();

        jsonSuccess(['buildings' => $buildings, 'units' => $units]);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to load buildings and flats.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonInput();
    requireValidCsrfToken($data['csrf_token'] ?? '');
    $action = trim((string)($data['action'] ?? ''));

    try {
        if ($action === 'save_building') {
            $id = (int)($data['id'] ?? 0);
            $name = trim((string)($data['building_name'] ?? ''));
            $code = strtoupper(trim((string)($data['building_code'] ?? '')));
            $floors = max(1, min(150, (int)($data['total_floors'] ?? 10)));
            $isActive = isset($data['is_active']) ? (!empty($data['is_active']) ? 1 : 0) : 1;

            if ($name === '' || $code === '') {
                jsonError('Building name and code are required.', 400);
            }

            if ($id > 0) {
                $stmt = $conn->prepare(
                    "UPDATE sms_buildings SET building_name = :name, building_code = :code, total_floors = :floors, is_active = :active
                     WHERE id = :id AND society_id = :sid"
                );
                $stmt->execute([':name' => $name, ':code' => $code, ':floors' => $floors, ':active' => $isActive, ':id' => $id, ':sid' => $societyId]);
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO sms_buildings (society_id, building_name, building_code, total_floors, is_active)
                     VALUES (:sid, :name, :code, :floors, :active)"
                );
                $stmt->execute([':sid' => $societyId, ':name' => $name, ':code' => $code, ':floors' => $floors, ':active' => $isActive]);
                $id = (int)$conn->lastInsertId();
            }
            logAudit($conn, $societyId, $actorId, 'SAVE_BUILDING', 'building', $id, ['name' => $name, 'code' => $code]);
            jsonSuccess(['message' => 'Building saved successfully.', 'id' => $id]);
        } elseif ($action === 'save_unit') {
            $id = (int)($data['id'] ?? 0);
            $buildingId = (int)($data['building_id'] ?? 0);
            $unitNumber = trim((string)($data['unit_number'] ?? ''));
            $floorNumber = max(0, min(150, (int)($data['floor_number'] ?? 1)));
            $flatType = trim((string)($data['flat_type'] ?? '2BHK'));
            $areaSqft = max(100, (int)($data['area_sqft'] ?? 1100));
            $status = trim((string)($data['status'] ?? 'Occupied'));

            $allowedTypes = ['1BHK', '2BHK', '3BHK', '4BHK', 'Penthouse', 'Studio', 'Villa', 'Commercial'];
            $allowedStatuses = ['Occupied', 'Vacant', 'Maintenance'];
            if ($buildingId <= 0 || $unitNumber === '' || !in_array($flatType, $allowedTypes, true) || !in_array($status, $allowedStatuses, true)) {
                jsonError('Invalid flat / unit parameters.', 400);
            }

            $bChk = $conn->prepare("SELECT id FROM sms_buildings WHERE id = :bid AND society_id = :sid LIMIT 1");
            $bChk->execute([':bid' => $buildingId, ':sid' => $societyId]);
            if (!$bChk->fetch()) {
                jsonError('Building not found in your society.', 404);
            }

            if ($id > 0) {
                $stmt = $conn->prepare(
                    "UPDATE sms_units SET building_id = :bid, unit_number = :num, floor_number = :flr, flat_type = :ft, area_sqft = :area, status = :st
                     WHERE id = :id AND society_id = :sid"
                );
                $stmt->execute([
                    ':bid' => $buildingId, ':num' => $unitNumber, ':flr' => $floorNumber,
                    ':ft' => $flatType, ':area' => $areaSqft, ':st' => $status, ':id' => $id, ':sid' => $societyId
                ]);
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO sms_units (society_id, building_id, unit_number, floor_number, flat_type, area_sqft, status)
                     VALUES (:sid, :bid, :num, :flr, :ft, :area, :st)"
                );
                $stmt->execute([
                    ':sid' => $societyId, ':bid' => $buildingId, ':num' => $unitNumber,
                    ':flr' => $floorNumber, ':ft' => $flatType, ':area' => $areaSqft, ':st' => $status
                ]);
                $id = (int)$conn->lastInsertId();
            }
            logAudit($conn, $societyId, $actorId, 'SAVE_UNIT', 'unit', $id, ['unit_number' => $unitNumber, 'flat_type' => $flatType]);
            jsonSuccess(['message' => 'Flat / Unit saved successfully.', 'id' => $id]);
        } else {
            jsonError('Unknown building action.', 400);
        }
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to update building registry.');
    }
}
