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
        "SELECT vp.id, vp.resident_id, vp.visitor_name, vp.pin_code, vp.status, vp.valid_until, vp.valid_date, vp.created_at,
                u.first_name, u.last_name, uu.unit_number
         FROM sms_visitor_passes vp
         INNER JOIN sms_user_profiles u ON vp.resident_id = u.user_id
         LEFT JOIN sms_user_units uu ON vp.resident_id = uu.user_id AND uu.is_current_occupant = 1
         WHERE vp.pin_code = :pin_code
           AND vp.society_id = :society_id
         ORDER BY vp.id DESC
         LIMIT 1"
    );
    $stmt->execute([
        ':pin_code' => $pinCode,
        ':society_id' => (int)$_SESSION['society_id'],
    ]);

    $pass = $stmt->fetch();
    if (!$pass) {
        jsonError('Guest pass PIN not found for this society.', 404);
    }

    // 1. Prevent reuse of already used passes
    if ($pass['status'] === 'Used') {
        jsonError('This guest pass has already been used and cannot be reused.', 400);
    }

    // 2. Enforce 48-hour auto-expiry using server-side IST timestamp
    $nowStr = (new DateTime('now'))->format('Y-m-d H:i:s');
    $isExpired = false;
    if (!empty($pass['valid_until'])) {
        if ($pass['valid_until'] < $nowStr) {
            $isExpired = true;
        }
    } elseif (!empty($pass['valid_date']) && $pass['valid_date'] < (new DateTime('today'))->format('Y-m-d')) {
        $isExpired = true;
    }

    if ($isExpired || $pass['status'] === 'Expired') {
        if ($pass['status'] !== 'Expired') {
            $upd = $conn->prepare("UPDATE sms_visitor_passes SET status = 'Expired' WHERE id = :id");
            $upd->execute([':id' => $pass['id']]);
        }
        $expiryDt = !empty($pass['valid_until']) ? (new DateTime($pass['valid_until']))->format('d M Y, h:i A') : $pass['valid_date'];
        jsonError("This guest pass expired on {$expiryDt} and cannot be accepted at the gate.", 400);
    }

    if ($pass['status'] !== 'Active') {
        jsonError("Guest pass is not active (Status: {$pass['status']}).", 400);
    }

    jsonSuccess(['data' => $pass]);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to verify guest pass PIN.');
}
