<?php
// /society_app/api/get_notices.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['GET']);
requireApiAuth([1, 2, 3, 4]);

try {
    $query = "SELECT n.id, n.title, n.content, n.created_at,
                     p.first_name, p.last_name
              FROM sms_notices n
              INNER JOIN sms_user_profiles p ON n.created_by = p.user_id
              WHERE n.society_id = :society_id
              ORDER BY n.created_at DESC
              LIMIT 50";

    $stmt = $conn->prepare($query);
    $stmt->execute([':society_id' => (int)$_SESSION['society_id']]);
    $notices = $stmt->fetchAll();

    jsonSuccess(['data' => $notices]);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to load notices.');
}
