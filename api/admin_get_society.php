<?php
// /society_app/api/admin_get_society.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['GET']);
requireApiAuth([1, 2]);

try {
    $stmt = $conn->prepare(
        'SELECT id, society_name, society_code, address, city, state, zip_code
         FROM sms_societies
         WHERE id = :society_id
         LIMIT 1'
    );
    $stmt->execute([':society_id' => (int)$_SESSION['society_id']]);
    $society = $stmt->fetch();

    if ($society) {
        jsonSuccess(['data' => $society]);
    }

    jsonError('Society record not found.', 404);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to load society profile.');
}
