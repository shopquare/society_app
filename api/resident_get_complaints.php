<?php
// /society_app/api/resident_get_complaints.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['GET']);
requireApiAuth([3]);

try {
    $stmt = $conn->prepare(
        'SELECT id, category, description, status, created_at
         FROM sms_complaints
         WHERE resident_id = :resident_id AND society_id = :society_id
         ORDER BY created_at DESC'
    );
    $stmt->execute([
        ':resident_id' => (int)$_SESSION['user_id'],
        ':society_id' => (int)$_SESSION['society_id'],
    ]);
    $complaints = $stmt->fetchAll();

    jsonSuccess(['data' => $complaints]);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to load complaints.');
}
