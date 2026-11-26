<?php
// /society_app/api/resident_generate_pin.php
// Generates a 6-digit pre-approved guest pass with strict 48-hour auto-expiry in IST

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['POST']);
requireApiAuth([3]);

$data = getJsonInput();
requireValidCsrfToken($data['csrf_token'] ?? '');

$visitorName = trim((string)($data['visitor_name'] ?? ''));

if ($visitorName === '') {
    jsonError('Visitor name is required.', 400);
}

try {
    $societyId = (int)$_SESSION['society_id'];
    $residentId = (int)$_SESSION['user_id'];

    // Strict 48-hour expiration calculated using server-side IST timestamp
    $now = new DateTime('now');
    $createdAt = $now->format('Y-m-d H:i:s');
    $validUntilDt = (clone $now)->modify('+48 hours');
    $validUntil = $validUntilDt->format('Y-m-d H:i:s');
    $validDate = $validUntilDt->format('Y-m-d');
    $formattedExpiry = $validUntilDt->format('d M Y, h:i A');

    // Fetch society name for WhatsApp invite
    $socStmt = $conn->prepare("SELECT society_name FROM sms_societies WHERE id = :society_id LIMIT 1");
    $socStmt->execute([':society_id' => $societyId]);
    $societyRow = $socStmt->fetch();
    $societyName = $societyRow ? $societyRow['society_name'] : 'Sunshine Residency';

    $pinCode = sprintf('%06d', random_int(100000, 999999));

    $stmt = $conn->prepare(
        "INSERT INTO sms_visitor_passes (society_id, resident_id, visitor_name, pin_code, valid_date, valid_until, status, created_at)
         VALUES (:society_id, :resident_id, :visitor_name, :pin_code, :valid_date, :valid_until, 'Active', :created_at)"
    );
    $stmt->execute([
        ':society_id' => $societyId,
        ':resident_id' => $residentId,
        ':visitor_name' => $visitorName,
        ':pin_code' => $pinCode,
        ':valid_date' => $validDate,
        ':valid_until' => $validUntil,
        ':created_at' => $createdAt,
    ]);

    $passId = (int)$conn->lastInsertId();

    // Generate formatted WhatsApp message (No resident private info exposed)
    $waMessage = "Hello! You have been invited to visit {$societyName}.\n\n" .
                 "Guest Pass: {$pinCode}\n\n" .
                 "This guest pass is valid for 48 hours from the time of generation and will automatically expire on {$formattedExpiry}.\n\n" .
                 "Please share this pass with security at the entrance.\n\n" .
                 "Thank you!";

    $waUrl = 'https://api.whatsapp.com/send?text=' . rawurlencode($waMessage);

    jsonSuccess([
        'message' => 'Guest pass generated successfully (valid for 48 hours).',
        'pass_id' => $passId,
        'pin_code' => $pinCode,
        'visitor_name' => $visitorName,
        'created_at' => $createdAt,
        'valid_until' => $validUntil,
        'formatted_expiry' => $formattedExpiry,
        'remaining_hours' => 48,
        'society_name' => $societyName,
        'whatsapp_message' => $waMessage,
        'whatsapp_url' => $waUrl,
    ], 201);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to generate guest pass.');
}
