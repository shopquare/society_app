<?php
// /society_app/api/admin_create_notice.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['POST']);
requireApiAuth([1, 2]);

$data = getJsonInput();
requireValidCsrfToken($data['csrf_token'] ?? '');

$title = trim((string)($data['title'] ?? ''));
$content = trim((string)($data['content'] ?? ''));

if ($title === '' || $content === '') {
    jsonError('Title and content are required.', 400);
}

try {
    $stmt = $conn->prepare(
        'INSERT INTO sms_notices (society_id, created_by, title, content, created_at)
         VALUES (:society_id, :created_by, :title, :content, NOW())'
    );
    $stmt->execute([
        ':society_id' => (int)$_SESSION['society_id'],
        ':created_by' => (int)$_SESSION['user_id'],
        ':title' => $title,
        ':content' => $content,
    ]);

    jsonSuccess(['message' => 'Notice published successfully.'], 201);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to publish notice.');
}
