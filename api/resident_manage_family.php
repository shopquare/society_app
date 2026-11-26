<?php
// /society_app/api/resident_manage_family.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['GET', 'POST', 'DELETE']);
requireApiAuth([3]);

$societyId = (int)$_SESSION['society_id'];
$residentId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $conn->prepare(
            "SELECT id, full_name AS name, relation AS relationship, phone_number, COALESCE(is_emergency_contact, 0) AS is_emergency_contact, created_at
             FROM sms_family_members
             WHERE society_id = :society_id AND resident_id = :resident_id
             ORDER BY is_emergency_contact DESC, full_name ASC"
        );
        $stmt->execute([':society_id' => $societyId, ':resident_id' => $residentId]);
        $family = $stmt->fetchAll();

        jsonSuccess(['data' => $family]);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to load family members.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonInput();
    requireValidCsrfToken($data['csrf_token'] ?? '');

    $name = trim((string)($data['name'] ?? ''));
    $relationship = trim((string)($data['relationship'] ?? ''));
    $phone = trim((string)($data['phone_number'] ?? ''));
    $isEmergency = !empty($data['is_emergency_contact']) ? 1 : 0;

    if ($name === '' || $relationship === '') {
        jsonError('Family member name and relationship are required.', 400);
    }

    $normalizedPhone = null;
    if ($phone !== '') {
        $normalizedPhone = normalizeIndianPhoneNumber($phone);
        if ($normalizedPhone === null) {
            jsonError('Please provide a valid 10-digit Indian phone number.', 400);
        }
    }

    try {
        $unitStmt = $conn->prepare("SELECT unit_number FROM sms_user_units WHERE user_id = :uid AND is_current_occupant = 1 LIMIT 1");
        $unitStmt->execute([':uid' => $residentId]);
        $unitNum = (string)($unitStmt->fetchColumn() ?: 'N/A');

        $stmt = $conn->prepare(
            "INSERT INTO sms_family_members (society_id, resident_id, unit_number, full_name, relation, phone_number, is_emergency_contact)
             VALUES (:society_id, :resident_id, :unit_number, :name, :relationship, :phone_number, :is_emergency)"
        );
        $stmt->execute([
            ':society_id' => $societyId,
            ':resident_id' => $residentId,
            ':unit_number' => $unitNum,
            ':name' => $name,
            ':relationship' => $relationship,
            ':phone_number' => $normalizedPhone,
            ':is_emergency' => $isEmergency
        ]);

        $id = (int)$conn->lastInsertId();
        jsonSuccess(['message' => 'Family member added successfully.', 'id' => $id]);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to add family member.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $data = getJsonInput();
    requireValidCsrfToken($data['csrf_token'] ?? '');

    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) {
        jsonError('Invalid family member ID.', 400);
    }

    try {
        $stmt = $conn->prepare(
            "DELETE FROM sms_family_members
             WHERE id = :id AND resident_id = :resident_id AND society_id = :society_id"
        );
        $stmt->execute([
            ':id' => $id,
            ':resident_id' => $residentId,
            ':society_id' => $societyId
        ]);

        if ($stmt->rowCount() === 0) {
            jsonError('Family member not found or access denied.', 404);
        }

        jsonSuccess(['message' => 'Family member removed successfully.']);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to remove family member.');
    }
}
