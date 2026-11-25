<?php
// /society_app/api/resident_save_delivery_preapproval.php
// Creates or updates a delivery pre-approval instruction for the resident's flat

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['POST']);
requireApiAuth([3]);

$data = getJsonInput();
requireValidCsrfToken($data['csrf_token'] ?? '');

$company = trim((string)($data['company'] ?? ''));
$instruction = trim((string)($data['instruction'] ?? 'Leave at Gate / Guard'));
$durationHours = max(1, min(24, (int)($data['duration_hours'] ?? 4)));

$allowedCompanies = ['Swiggy', 'Zomato', 'Amazon', 'Blinkit', 'Zepto', 'Flipkart', 'Uber', 'Other Delivery'];
if (!in_array($company, $allowedCompanies, true)) {
    jsonError('Please select a valid delivery provider.', 400);
}

$allowedInstructions = ['Allow Entry', 'Leave at Gate / Guard', 'Leave at Doorstep'];
if (!in_array($instruction, $allowedInstructions, true)) {
    jsonError('Invalid delivery instruction.', 400);
}

try {
    $societyId = (int)$_SESSION['society_id'];
    $residentId = (int)$_SESSION['user_id'];

    // Retrieve resident unit
    $unitStmt = $conn->prepare("SELECT unit_number FROM sms_user_units WHERE user_id = :user_id AND society_id = :society_id AND is_current_occupant = 1 LIMIT 1");
    $unitStmt->execute([':user_id' => $residentId, ':society_id' => $societyId]);
    $unitRow = $unitStmt->fetch();
    $unitNumber = $unitRow ? $unitRow['unit_number'] : '101';

    $validUntil = (new DateTime("+{$durationHours} hours"))->format('Y-m-d H:i:s');

    // Cancel existing active pre-approvals for the same company and unit
    $cancelStmt = $conn->prepare("UPDATE sms_delivery_preapprovals SET status = 'Cancelled' WHERE society_id = :society_id AND unit_number = :unit_number AND company = :company AND status = 'Active'");
    $cancelStmt->execute([
        ':society_id' => $societyId,
        ':unit_number' => $unitNumber,
        ':company' => $company
    ]);

    // Insert new pre-approval
    $stmt = $conn->prepare(
        "INSERT INTO sms_delivery_preapprovals (society_id, resident_id, unit_number, company, instruction, valid_until, status)
         VALUES (:society_id, :resident_id, :unit_number, :company, :instruction, :valid_until, 'Active')"
    );
    $stmt->execute([
        ':society_id' => $societyId,
        ':resident_id' => $residentId,
        ':unit_number' => $unitNumber,
        ':company' => $company,
        ':instruction' => $instruction,
        ':valid_until' => $validUntil
    ]);

    jsonSuccess([
        'message' => "Delivery pre-approved for {$company}! Instruction: {$instruction}",
        'id' => (int)$conn->lastInsertId(),
        'company' => $company,
        'instruction' => $instruction,
        'valid_until' => $validUntil
    ], 201);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to save delivery pre-approval.');
}
