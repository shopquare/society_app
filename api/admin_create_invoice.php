<?php
// /society_app/api/admin_create_invoice.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['POST']);
requireApiAuth([1, 2]);

$data = getJsonInput();
requireValidCsrfToken($data['csrf_token'] ?? '');

$incomingId = $data['resident_id'] ?? null;
$title = trim((string)($data['title'] ?? ''));
$rawAmount = $data['amount'] ?? null;
$dueDate = trim((string)($data['due_date'] ?? ''));

if ($incomingId === null || $incomingId === '' || $title === '' || $rawAmount === null || $dueDate === '') {
    jsonError('All fields are required. Please ensure a resident is selected.', 400);
}

if (!is_numeric($rawAmount) || (float)$rawAmount <= 0) {
    jsonError('Amount must be a positive number.', 400);
}
$amount = round((float)$rawAmount, 2);

$dt = DateTime::createFromFormat('Y-m-d', $dueDate);
if (!$dt || $dt->format('Y-m-d') !== $dueDate) {
    jsonError('Invalid due date format. Expected YYYY-MM-DD.', 400);
}

try {
    $societyId = (int)$_SESSION['society_id'];

    if ($incomingId === 'ALL') {
        $conn->beginTransaction();

        $userStmt = $conn->prepare(
            "SELECT id FROM sms_users WHERE society_id = :society_id AND role_id = 3 AND status = 'active'"
        );
        $userStmt->execute([':society_id' => $societyId]);
        $users = $userStmt->fetchAll();

        if (count($users) === 0) {
            $conn->rollBack();
            jsonError('No active residents found to bill.', 400);
        }

        $insertStmt = $conn->prepare(
            "INSERT INTO sms_invoices (society_id, resident_id, title, amount, due_date, status, created_at)
             VALUES (:society_id, :resident_id, :title, :amount, :due_date, 'Unpaid', NOW())"
        );

        foreach ($users as $user) {
            $insertStmt->execute([
                ':society_id' => $societyId,
                ':resident_id' => (int)$user['id'],
                ':title' => $title,
                ':amount' => $amount,
                ':due_date' => $dueDate,
            ]);
        }

        $conn->commit();
        jsonSuccess(['message' => count($users) . ' bulk bills generated successfully!'], 201);
    }

    $residentId = (int)$incomingId;
    if ($residentId <= 0) {
        jsonError('Invalid resident selected.', 400);
    }

    $checkStmt = $conn->prepare(
        "SELECT id FROM sms_users WHERE id = :id AND society_id = :society_id AND role_id = 3 AND status = 'active' LIMIT 1"
    );
    $checkStmt->execute([':id' => $residentId, ':society_id' => $societyId]);
    if (!$checkStmt->fetch()) {
        jsonError('Selected resident does not exist or is inactive.', 400);
    }

    $stmt = $conn->prepare(
        "INSERT INTO sms_invoices (society_id, resident_id, title, amount, due_date, status, created_at)
         VALUES (:society_id, :resident_id, :title, :amount, :due_date, 'Unpaid', NOW())"
    );
    $stmt->execute([
        ':society_id' => $societyId,
        ':resident_id' => $residentId,
        ':title' => $title,
        ':amount' => $amount,
        ':due_date' => $dueDate,
    ]);

    jsonSuccess(['message' => 'Maintenance bill generated successfully.'], 201);
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    logAndFail($e, 'Unable to generate maintenance bill.');
}
