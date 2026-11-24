<?php
// /society_app/api/admin_get_users.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['GET']);
requireApiAuth([1, 2]);

try {
    $query = "SELECT u.id, u.email, u.status, u.created_at,
                     p.first_name, p.last_name, p.phone_number,
                     un.unit_number, un.occupancy_type
              FROM sms_users u
              INNER JOIN sms_user_profiles p ON u.id = p.user_id
              LEFT JOIN sms_user_units un ON u.id = un.user_id AND un.is_current_occupant = 1
              WHERE u.status != 'pending' AND u.role_id = 3 AND u.society_id = :society_id
              ORDER BY u.status ASC, p.first_name ASC";

    $stmt = $conn->prepare($query);
    $stmt->execute([':society_id' => (int)$_SESSION['society_id']]);
    $users = $stmt->fetchAll();

    jsonSuccess(['data' => $users]);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to load resident directory.');
}
