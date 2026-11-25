<?php
// /society_app/api/resident_get_pending_visitors.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['GET']);
requireApiAuth([3]);

try {
    $stmt = $conn->prepare(
        "SELECT v.id, v.visitor_name, v.purpose, v.photo_path, v.entry_time, v.phone_number,
                uu.unit_number
         FROM sms_visitors v
         LEFT JOIN sms_user_units uu ON v.resident_id = uu.user_id AND uu.is_current_occupant = 1
         WHERE v.resident_id = :resident_id
           AND v.society_id = :society_id
           AND v.status = 'Pending'
         ORDER BY v.entry_time DESC"
    );
    $stmt->execute([
        ':resident_id' => (int)$_SESSION['user_id'],
        ':society_id' => (int)$_SESSION['society_id'],
    ]);
    $visitors = $stmt->fetchAll();

    foreach ($visitors as &$vis) {
        $vis['photo_url'] = resolveVisitorPhotoUrl($vis['photo_path'], true);
        $vis['action_token'] = signVisitorActionToken((int)$vis['id'], (int)$_SESSION['user_id']);
    }
    unset($vis);

    jsonSuccess(['data' => $visitors]);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to load pending visitors.');
}
