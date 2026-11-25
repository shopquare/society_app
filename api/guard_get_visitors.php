<?php
// /society_app/api/guard_get_visitors.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['GET']);
requireApiAuth([4]);

try {
    // Sargable date range query enables B-tree index usage on (society_id, entry_time)
    $query = "SELECT v.id, v.visitor_name, v.phone_number, v.vehicle_number, v.purpose, v.photo_path, v.status, v.entry_time, v.exit_time,
                     p.first_name, p.last_name, uu.unit_number
              FROM sms_visitors v
              INNER JOIN sms_users u ON v.resident_id = u.id
              INNER JOIN sms_user_profiles p ON u.id = p.user_id
              LEFT JOIN sms_user_units uu ON u.id = uu.user_id AND uu.is_current_occupant = 1
              WHERE v.society_id = :society_id
                AND v.entry_time >= CURDATE()
                AND v.entry_time < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
              ORDER BY v.entry_time DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute([':society_id' => (int)$_SESSION['society_id']]);
    $visitors = $stmt->fetchAll();

    foreach ($visitors as &$vis) {
        $vis['photo_url'] = resolveVisitorPhotoUrl($vis['photo_path'], true);
    }
    unset($vis);

    jsonSuccess(['data' => $visitors]);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to load today\'s visitors.');
}
