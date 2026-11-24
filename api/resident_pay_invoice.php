<?php
// /society_app/api/resident_pay_invoice.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['POST']);
requireApiAuth([3]);

$data = getJsonInput();
requireValidCsrfToken($data['csrf_token'] ?? '');

$invoiceId = (int)($data['invoice_id'] ?? 0);
if ($invoiceId <= 0) {
    jsonError('Invalid invoice ID.', 400);
}

try {
    $stmt = $conn->prepare(
        "UPDATE sms_invoices
         SET status = 'Paid', updated_at = NOW()
         WHERE id = :invoice_id
           AND resident_id = :resident_id
           AND society_id = :society_id
           AND status != 'Paid'"
    );
    $stmt->execute([
        ':invoice_id' => $invoiceId,
        ':resident_id' => (int)$_SESSION['user_id'],
        ':society_id' => (int)$_SESSION['society_id'],
    ]);

    if ($stmt->rowCount() > 0) {
        jsonSuccess(['message' => 'Payment recorded successfully. Thank you!']);
    }

    jsonError('Invalid invoice or already paid.', 400);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to process invoice payment.');
}
