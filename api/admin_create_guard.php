<?php
// /society_app/api/admin_create_guard.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['POST']);
requireApiAuth([1, 2]);

$data = getJsonInput();
requireValidCsrfToken($data['csrf_token'] ?? '');

$email = trim((string)($data['email'] ?? ''));
$firstName = trim((string)($data['first_name'] ?? ''));
$lastName = trim((string)($data['last_name'] ?? ''));
$phoneNumber = trim((string)($data['phone_number'] ?? ''));

if ($email === '' || $firstName === '' || $lastName === '' || $phoneNumber === '') {
    jsonError('All fields are required.', 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonError('Please provide a valid email address.', 400);
}

try {
    $checkStmt = $conn->prepare('SELECT id FROM sms_users WHERE email = :email LIMIT 1');
    $checkStmt->execute([':email' => $email]);
    if ($checkStmt->fetch()) {
        jsonError('An account with this email already exists.', 409);
    }

    $phoneStmt = $conn->prepare('SELECT user_id FROM sms_user_profiles WHERE phone_number = :phone LIMIT 1');
    $phoneStmt->execute([':phone' => $phoneNumber]);
    if ($phoneStmt->fetch()) {
        jsonError('An account with this phone number already exists.', 409);
    }

    // Generate a secure random temporary password instead of a hardcoded string
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#';
    $tempPassword = '';
    for ($i = 0; $i < 10; $i++) {
        $tempPassword .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);

    $conn->beginTransaction();

    $userStmt = $conn->prepare(
        "INSERT INTO sms_users (society_id, email, password_hash, role_id, status, created_at)
         VALUES (:society_id, :email, :password_hash, 4, 'active', NOW())"
    );
    $userStmt->execute([
        ':society_id' => (int)$_SESSION['society_id'],
        ':email' => $email,
        ':password_hash' => $passwordHash,
    ]);

    $userId = (int)$conn->lastInsertId();

    $profileStmt = $conn->prepare(
        'INSERT INTO sms_user_profiles (user_id, first_name, last_name, phone_number)
         VALUES (:user_id, :first_name, :last_name, :phone_number)'
    );
    $profileStmt->execute([
        ':user_id' => $userId,
        ':first_name' => $firstName,
        ':last_name' => $lastName,
        ':phone_number' => $phoneNumber,
    ]);

    $conn->commit();

    jsonSuccess([
        'message' => 'Security guard account created successfully.',
        'temporary_password' => $tempPassword,
    ], 201);
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    logAndFail($e, 'Unable to create guard account.');
}
