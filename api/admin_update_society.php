<?php
// /society_app/api/admin_update_society.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['POST']);
requireApiAuth([1, 2]);

$data = getJsonInput();
requireValidCsrfToken($data['csrf_token'] ?? '');

$societyName = trim((string)($data['society_name'] ?? ''));
$address = trim((string)($data['address'] ?? ''));
$city = trim((string)($data['city'] ?? ''));
$state = trim((string)($data['state'] ?? ''));
$zipCode = trim((string)($data['zip_code'] ?? ''));

if ($societyName === '' || $city === '') {
    jsonError('Society Name and City are required.', 400);
}

try {
    $stmt = $conn->prepare(
        'UPDATE sms_societies
         SET society_name = :society_name,
             address = :address,
             city = :city,
             state = :state,
             zip_code = :zip_code
         WHERE id = :society_id'
    );
    $stmt->execute([
        ':society_name' => $societyName,
        ':address' => $address,
        ':city' => $city,
        ':state' => $state,
        ':zip_code' => $zipCode,
        ':society_id' => (int)$_SESSION['society_id'],
    ]);

    jsonSuccess(['message' => 'Society profile updated successfully.']);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to update society profile.');
}
