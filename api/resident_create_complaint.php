<?php
// /society_app/api/resident_create_complaint.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['POST']);
requireApiAuth([3]);

$data = getJsonInput();
requireValidCsrfToken($data['csrf_token'] ?? '');

$category = trim((string)($data['category'] ?? ''));
$description = trim((string)($data['description'] ?? ''));
$allowedCategories = ['Plumbing', 'Electrical', 'Housekeeping', 'Security', 'General'];

if ($category === '' || $description === '') {
    jsonError('Category and Description are required.', 400);
}

if (!in_array($category, $allowedCategories, true)) {
    jsonError('Invalid complaint category selected.', 400);
}

try {
    $stmt = $conn->prepare(
        "INSERT INTO sms_complaints (society_id, resident_id, category, description, status, created_at)
         VALUES (:society_id, :resident_id, :category, :description, 'Open', NOW())"
    );
    $stmt->execute([
        ':society_id' => (int)$_SESSION['society_id'],
        ':resident_id' => (int)$_SESSION['user_id'],
        ':category' => $category,
        ':description' => $description,
    ]);

    jsonSuccess(['message' => 'Your complaint has been submitted to the committee.'], 201);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to submit complaint.');
}
