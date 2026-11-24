<?php
// /society_app/api/admin_get_complaints.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['GET']);
requireApiAuth([1, 2]);

try {
    $query = "SELECT c.id, c.category, c.description, c.status, c.created_at,
                     p.first_name, p.last_name, p.phone_number
              FROM sms_complaints c
              INNER JOIN sms_user_profiles p ON c.resident_id = p.user_id
              WHERE c.society_id = :society_id
              ORDER BY c.created_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute([':society_id' => (int)$_SESSION['society_id']]);
    $complaints = $stmt->fetchAll();

    jsonSuccess(['data' => $complaints]);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to load complaints.');
}
