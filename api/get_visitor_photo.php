<?php
// /society_app/api/get_visitor_photo.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    jsonError('Method not allowed.', 405);
}

startSecureSession();
requireApiAuth([1, 2, 3, 4]);

$rawFile = (string)($_GET['file'] ?? '');
$fileName = basename($rawFile);

if ($fileName === '' || !preg_match('/^[A-Za-z0-9_.-]+\.(png|jpg|jpeg|webp)$/i', $fileName)) {
    http_response_code(400);
    exit('Invalid photo reference.');
}

try {
    // Verify requester is authorized to view this visitor photo within their society (and own unit for residents)
    $societyId = (int)$_SESSION['society_id'];
    $likePattern = '%' . $fileName;

    if ((int)$_SESSION['role_id'] === 3) {
        $stmt = $conn->prepare(
            'SELECT id FROM sms_visitors WHERE society_id = :society_id AND resident_id = :resident_id AND photo_path LIKE :photo LIMIT 1'
        );
        $stmt->execute([
            ':society_id' => $societyId,
            ':resident_id' => (int)$_SESSION['user_id'],
            ':photo' => $likePattern,
        ]);
    } else {
        $stmt = $conn->prepare(
            'SELECT id FROM sms_visitors WHERE society_id = :society_id AND photo_path LIKE :photo LIMIT 1'
        );
        $stmt->execute([
            ':society_id' => $societyId,
            ':photo' => $likePattern,
        ]);
    }

    if (!$stmt->fetch()) {
        http_response_code(403);
        exit('Access denied.');
    }

    $uploadDir = realpath(dirname(__DIR__) . '/uploads/visitors');
    if ($uploadDir === false) {
        http_response_code(404);
        exit('Photo not found.');
    }

    $fullPath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
    if (!is_file($fullPath)) {
        http_response_code(404);
        exit('Photo not found.');
    }

    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $mimeMap = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
    ];
    $mime = $mimeMap[$ext] ?? 'application/octet-stream';

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($fullPath));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=3600');
    readfile($fullPath);
    exit();
} catch (Throwable $e) {
    error_log('Visitor photo fetch failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to load photo.');
}
