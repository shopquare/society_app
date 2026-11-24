<?php
// /society_app/api/register.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['POST']);

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
checkRateLimit('register_' . $ip, 10, 600);

$data = getJsonInput();
requireValidCsrfToken($data['csrf_token'] ?? '');

$societyId = (int)($data['societyId'] ?? 0);
$email = trim((string)($data['email'] ?? ''));
$password = (string)($data['password'] ?? '');
$firstName = trim((string)($data['firstName'] ?? ''));
$lastName = trim((string)($data['lastName'] ?? ''));
$phone = trim((string)($data['phone'] ?? ''));
$occupancyType = trim((string)($data['occupancyType'] ?? ''));
$block = trim((string)($data['block'] ?? ''));
$unitNumber = trim((string)($data['unitNumber'] ?? ''));
$leaseExpiry = trim((string)($data['leaseExpiry'] ?? ''));

if (
    $societyId <= 0 ||
    $email === '' ||
    $password === '' ||
    $firstName === '' ||
    $lastName === '' ||
    $phone === '' ||
    $block === '' ||
    $unitNumber === ''
) {
    jsonError('Incomplete data provided. Please ensure all required fields are filled out.', 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonError('Please enter a valid email address.', 400);
}

if (strlen($password) < 8) {
    jsonError('Password must be at least 8 characters long.', 400);
}

if (!in_array($occupancyType, ['owner', 'tenant'], true)) {
    jsonError('Invalid occupancy type selected.', 400);
}

$moveOutDate = null;
if ($occupancyType === 'tenant' && $leaseExpiry !== '') {
    $dt = DateTime::createFromFormat('Y-m-d', $leaseExpiry);
    if (!$dt || $dt->format('Y-m-d') !== $leaseExpiry) {
        jsonError('Invalid lease expiry date format.', 400);
    }
    $moveOutDate = $leaseExpiry;
}

try {
    // Verify referenced society exists
    $socStmt = $conn->prepare('SELECT id FROM sms_societies WHERE id = :id LIMIT 1');
    $socStmt->execute([':id' => $societyId]);
    if (!$socStmt->fetch()) {
        jsonError('Invalid society selected.', 400);
    }

    // Prevent duplicate emails
    $checkStmt = $conn->prepare('SELECT id FROM sms_users WHERE email = :email LIMIT 1');
    $checkStmt->execute([':email' => $email]);
    if ($checkStmt->fetch()) {
        jsonError('This email address is already registered.', 409);
    }

    // Prevent duplicate phone numbers cleanly instead of 500 constraint error
    $phoneStmt = $conn->prepare('SELECT user_id FROM sms_user_profiles WHERE phone_number = :phone LIMIT 1');
    $phoneStmt->execute([':phone' => $phone]);
    if ($phoneStmt->fetch()) {
        jsonError('This phone number is already registered.', 409);
    }

    $conn->beginTransaction();

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $roleId = 3; // Standard resident

    $stmtUser = $conn->prepare(
        "INSERT INTO sms_users (society_id, email, password_hash, role_id, status)
         VALUES (:society_id, :email, :password_hash, :role_id, 'pending')"
    );
    $stmtUser->execute([
        ':society_id' => $societyId,
        ':email' => $email,
        ':password_hash' => $passwordHash,
        ':role_id' => $roleId,
    ]);

    $userId = (int)$conn->lastInsertId();

    $stmtProfile = $conn->prepare(
        'INSERT INTO sms_user_profiles (user_id, first_name, last_name, phone_number)
         VALUES (:user_id, :first_name, :last_name, :phone_number)'
    );
    $stmtProfile->execute([
        ':user_id' => $userId,
        ':first_name' => $firstName,
        ':last_name' => $lastName,
        ':phone_number' => $phone,
    ]);

    $formattedUnit = $block . '-' . $unitNumber;

    $stmtUnit = $conn->prepare(
        'INSERT INTO sms_user_units (society_id, user_id, unit_number, occupancy_type, move_in_date, move_out_date)
         VALUES (:society_id, :user_id, :unit_number, :occupancy_type, CURDATE(), :move_out_date)'
    );
    $stmtUnit->execute([
        ':society_id' => $societyId,
        ':user_id' => $userId,
        ':unit_number' => $formattedUnit,
        ':occupancy_type' => $occupancyType,
        ':move_out_date' => $moveOutDate,
    ]);

    $conn->commit();

    jsonSuccess(['message' => 'Registration successful. Waiting for committee approval.'], 201);
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    logAndFail($e, 'Registration could not be completed.');
}
