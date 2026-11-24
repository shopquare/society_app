<?php
// /society_app/api/guard_verify_pin.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['POST']);
requireApiAuth([4]);

$data = getJsonInput();
requireValidCsrfToken($data['csrf_token'] ?? '');

checkRateLimit('verify_pin_' . (int)$_SESSION['user_id'], 20, 300);

$pinCode = trim((string)($data['pin_code'] ?? ''));
if (!preg_match('/^\d{6}$/', $pinCode)) {
    jsonError('Please provide a valid 6-digit PIN code.', 400);
}

try {
    // Verify active pass WITHOUT burning it yet; guard_log_visitor.php marks it 'Used' on entry submission
    $stmt = $conn->prepare(
        "SELECT vp.id, vp.resident_id, vp.visitor_name, vp.pin_code,
                u.first_name, u.last_name, uu.unit_number
         FROM sms_visitor_passes vp
         INNER JOIN sms_user_profiles u ON vp.resident_id = u.user_id
         LEFT JOIN sms_user_units uu ON vp.resident_id = uu.user_id AND uu.is_current_occupant = 1
         WHERE vp.pin_code = :pin_code
           AND vp.society_id = :society_id
           AND vp.status = 'Active'
           AND vp.valid_date >= CURDATE()
         LIMIT 1"
    );
    $stmt->execute([
        ':pin_code' => $pinCode,
        ':society_id' => (int)$_SESSION['society_id'],
    ]);

    $pass = $stmt->fetch();
    if ($pass) {
        jsonSuccess(['data' => $pass]);
    }

    jsonError('Invalid, expired, or already used PIN.', 400);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to verify guest pass PIN.');
}
