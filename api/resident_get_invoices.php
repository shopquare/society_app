<?php
// /society_app/api/resident_get_invoices.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['GET']);
requireApiAuth([3]);

try {
    $stmt = $conn->prepare(
        "SELECT i.id, i.title, i.amount, i.due_date, i.created_at, i.updated_at,
                s.society_name, s.address AS society_address,
                uu.unit_number,
                CONCAT(p.first_name, ' ', p.last_name) AS resident_name,
                CASE
                    WHEN i.status = 'Unpaid' AND i.due_date < CURDATE() THEN 'Overdue'
                    ELSE i.status
                END AS status
         FROM sms_invoices i
         INNER JOIN sms_societies s ON i.society_id = s.id
         INNER JOIN sms_users u ON i.resident_id = u.id
         INNER JOIN sms_user_profiles p ON u.id = p.user_id
         LEFT JOIN sms_user_units uu ON u.id = uu.user_id AND uu.is_current_occupant = 1
         WHERE i.resident_id = :resident_id AND i.society_id = :society_id
         ORDER BY i.status ASC, i.due_date DESC"
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
