<?php
// /society_app/api/admin_get_guards.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['GET']);
requireApiAuth([1, 2]);

try {
    $query = "SELECT u.id, u.email, u.status, u.created_at,
                     p.first_name, p.last_name, p.phone_number
              FROM sms_users u
              INNER JOIN sms_user_profiles p ON u.id = p.user_id
              WHERE u.society_id = :society_id AND u.role_id = 4
              ORDER BY p.first_name ASC";

    $stmt = $conn->prepare($query);
    $stmt->execute([':society_id' => (int)$_SESSION['society_id']]);
    $guards = $stmt->fetchAll();

    jsonSuccess(['data' => $guards]);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to load security staff.');
}
