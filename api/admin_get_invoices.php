<?php
// /society_app/api/admin_get_invoices.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['GET']);
requireApiAuth([1, 2]);

try {
    $societyId = (int)$_SESSION['society_id'];

    // Derive 'Overdue' cleanly in SELECT (avoiding write locks on GET) while also matching persisted 'Overdue'
    $query = "SELECT i.id, i.title, i.amount, i.due_date,
                     CASE
                         WHEN i.status = 'Unpaid' AND i.due_date < CURDATE() THEN 'Overdue'
                         ELSE i.status
                     END AS status,
                     p.first_name, p.last_name, uu.unit_number
              FROM sms_invoices i
              INNER JOIN sms_users u ON i.resident_id = u.id
              INNER JOIN sms_user_profiles p ON u.id = p.user_id
              LEFT JOIN sms_user_units uu ON u.id = uu.user_id AND uu.is_current_occupant = 1
              WHERE i.society_id = :society_id
              ORDER BY i.due_date DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute([':society_id' => $societyId]);
    $invoices = $stmt->fetchAll();

    jsonSuccess(['data' => $invoices]);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to load financial ledger.');
}
