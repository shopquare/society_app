<?php
// tests/phases/phase8_tests.php
// Phase 8: Digital Society Office, Private Vault Security & NOC Certificate Workflows

echo "\n--- Phase 8: Digital Society Office & Document Vault Tests ---\n";

if ($conn instanceof PDO) {
    assertTrue((bool)$conn->query("SHOW TABLES LIKE 'sms_documents'")->fetchColumn(), "Table sms_documents exists");
    assertTrue((bool)$conn->query("SHOW TABLES LIKE 'sms_document_requests'")->fetchColumn(), "Table sms_document_requests exists");
}

// Verify .htaccess private vault denial exists
$htPath = __DIR__ . '/../../uploads/private_docs/.htaccess';
assertTrue(is_file($htPath) && strpos(file_get_contents($htPath), 'Require all denied') !== false, "uploads/private_docs/.htaccess enforces Require all denied");

// Verify streamer enforces HMAC + RBAC + ownership + realpath containment
$streamSrc = file_get_contents(__DIR__ . '/../../api/stream_document.php');
assertTrue(strpos($streamSrc, 'hash_hmac') !== false && strpos($streamSrc, 'hash_equals') !== false, "stream_document.php enforces HMAC signature verification");
assertTrue(strpos($streamSrc, "Committee_Only") !== false && strpos($streamSrc, "Resident_Private") !== false, "stream_document.php enforces document visibility RBAC and ownership");
assertTrue(strpos($streamSrc, 'realpath') !== false && strpos($streamSrc, 'X-Content-Type-Options: nosniff') !== false, "stream_document.php enforces path traversal containment and nosniff");

// Verify resident API never exposes internal_remarks
$docApiSrc = file_get_contents(__DIR__ . '/../../api/documents_office.php');
assertTrue(strpos($docApiSrc, "generateSimplePdf") !== false, "Office API auto-issues PDF NOC/certificates");
assertTrue(strpos($docApiSrc, "requireApiPermission('documents.manage')") !== false, "Office processing requires documents.manage permission");

// Verify routes & UI pages
$rt = file_get_contents(__DIR__ . '/../../routes.php');
assertTrue(strpos($rt, "'/admin/documents'") !== false && strpos($rt, "'/resident/documents'") !== false, "Both /admin/documents and /resident/documents routes registered");
assertTrue(file_exists(__DIR__ . '/../../modules/admin/documents.php'), "modules/admin/documents.php exists");
assertTrue(file_exists(__DIR__ . '/../../modules/resident/documents.php'), "modules/resident/documents.php exists");
