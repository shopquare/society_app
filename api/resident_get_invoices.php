<?php
// /society_app/api/resident_get_invoices.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['GET']);
requireApiAuth([3]);

try {
    $stmt = $conn->prepare(
        "SELECT id, title, amount, due_date,
                CASE
                    WHEN status = 'Unpaid' AND due_date < CURDATE() THEN 'Overdue'
                    ELSE status
                END AS status
         FROM sms_invoices
         WHERE resident_id = :resident_id AND society_id = :society_id
         ORDER BY status ASC, due_date DESC"
    );
    $stmt->execute([
        ':resident_id' => (int)$_SESSION['user_id'],
        ':society_id' => (int)$_SESSION['society_id'],
    ]);
    $invoices = $stmt->fetchAll();

    jsonSuccess(['data' => $invoices]);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to load invoices.');
}
