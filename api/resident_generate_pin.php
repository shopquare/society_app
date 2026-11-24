<?php
// /society_app/api/resident_generate_pin.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['POST']);
requireApiAuth([3]);

$data = getJsonInput();
requireValidCsrfToken($data['csrf_token'] ?? '');

$visitorName = trim((string)($data['visitor_name'] ?? ''));
$validDate = trim((string)($data['valid_date'] ?? ''));

if ($visitorName === '' || $validDate === '') {
    jsonError('Visitor name and valid date are required.', 400);
}

$dt = DateTime::createFromFormat('Y-m-d', $validDate);
if (!$dt || $dt->format('Y-m-d') !== $validDate) {
    jsonError('Invalid date format. Expected YYYY-MM-DD.', 400);
}

$today = (new DateTime('today'))->format('Y-m-d');
if ($validDate < $today) {
    jsonError('Valid date cannot be in the past.', 400);
}

try {
    $pinCode = sprintf('%06d', random_int(100000, 999999));

    $stmt = $conn->prepare(
        "INSERT INTO sms_visitor_passes (society_id, resident_id, visitor_name, pin_code, valid_date, status)
         VALUES (:society_id, :resident_id, :visitor_name, :pin_code, :valid_date, 'Active')"
    );
    $stmt->execute([
        ':society_id' => (int)$_SESSION['society_id'],
        ':resident_id' => (int)$_SESSION['user_id'],
        ':visitor_name' => $visitorName,
        ':pin_code' => $pinCode,
        ':valid_date' => $validDate,
    ]);

    jsonSuccess([
        'message' => 'Pass generated successfully.',
        'pin_code' => $pinCode,
        'valid_date' => $validDate,
        'visitor_name' => $visitorName,
    ], 201);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to generate guest pass.');
}
