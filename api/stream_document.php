<?php
// api/stream_document.php
// Authenticated & HMAC-Tokenized Private Document Vault Streamer

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['GET']);
requireApiAuth([1, 2, 3]);

$docId = (int)($_GET['id'] ?? 0);
$exp = (int)($_GET['exp'] ?? 0);
$sig = trim((string)($_GET['sig'] ?? ''));

$userId = (int)$_SESSION['user_id'];
$societyId = (int)$_SESSION['society_id'];
$isDocAdmin = hasPermission('documents.manage');

if ($docId <= 0 || $exp <= 0 || $sig === '') {
    jsonError('Signed document token required.', 403);
}
if ($exp < time()) {
    jsonError('Document access link expired. Refresh page to issue a new token.', 403);
}

$secret = envValue('APP_SECRET', 'securegate_v2_doc_hmac_key');
$expectedSig = hash_hmac('sha256', "doc|{$docId}|{$userId}|{$exp}", $secret);
if (!hash_equals($expectedSig, $sig)) {
    jsonError('Invalid document access signature.', 403);
}

$stmt = $conn->prepare("SELECT * FROM sms_documents WHERE id = :id AND society_id = :sid LIMIT 1");
$stmt->execute([':id' => $docId, ':sid' => $societyId]);
$doc = $stmt->fetch();

if (!$doc) {
    jsonError('Document not found in your society.', 404);
}

// Strict RBAC & ownership gate
if ($doc['visibility'] === 'Committee_Only' && !$isDocAdmin) {
    jsonError('Restricted to authorized society committee members.', 403);
}
if ($doc['visibility'] === 'Resident_Private' && (int)$doc['owner_user_id'] !== $userId && !$isDocAdmin) {
    jsonError('You do not have permission to view another resident\'s private document.', 403);
}

$uploadsRoot = realpath(__DIR__ . '/../uploads/private_docs');
$candidate = realpath(__DIR__ . '/../uploads/' . ltrim((string)$doc['file_path'], '/'));
if (!$uploadsRoot || !$candidate || strpos($candidate, $uploadsRoot) !== 0 || !is_file($candidate)) {
    jsonError('Protected document binary not found on disk.', 404);
}

$mime = (string)($doc['mime_type'] ?: 'application/pdf');
$safeTitle = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string)$doc['title']);
$ext = str_contains($mime, 'pdf') ? 'pdf' : (str_contains($mime, 'png') ? 'png' : 'jpg');

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
header('Content-Disposition: inline; filename="' . $safeTitle . '.' . $ext . '"');
header('Content-Length: ' . filesize($candidate));
readfile($candidate);
exit;
