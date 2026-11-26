<?php
// api/documents_office.php
// Digital Society Office: Document Vault & NOC/KYC Request Workflow API

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/upload_helper.php';
require_once __DIR__ . '/../includes/pdf_generator.php';
require_once __DIR__ . '/../includes/audit_logger.php';
require_once __DIR__ . '/../includes/notification_service.php';

initApi(['GET', 'POST']);
requireApiAuth([1, 2, 3]);

$societyId = (int)$_SESSION['society_id'];
$userId = (int)$_SESSION['user_id'];
$isDocAdmin = hasPermission('documents.manage');

function buildDocStreamUrl(int $docId, int $userId): string {
    $exp = time() + 1800; // 30-min signed token
    $secret = envValue('APP_SECRET', 'securegate_v2_doc_hmac_key');
    $sig = hash_hmac('sha256', "doc|{$docId}|{$userId}|{$exp}", $secret);
    return appBasePath() . "/api/stream_document.php?id={$docId}&exp={$exp}&sig={$sig}";
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        if ($isDocAdmin) {
            $dStmt = $conn->prepare(
                "SELECT d.*, CONCAT(p.first_name, ' ', p.last_name) AS uploader_name
                 FROM sms_documents d
                 LEFT JOIN sms_user_profiles p ON d.uploaded_by = p.user_id
                 WHERE d.society_id = :sid
                 ORDER BY d.created_at DESC LIMIT 120"
            );
            $dStmt->execute([':sid' => $societyId]);

            $rStmt = $conn->prepare(
                "SELECT r.*, CONCAT(p.first_name, ' ', p.last_name) AS resident_name
                 FROM sms_document_requests r
                 INNER JOIN sms_user_profiles p ON r.resident_id = p.user_id
                 WHERE r.society_id = :sid
                 ORDER BY FIELD(r.status, 'Pending', 'Under_Review', 'Approved', 'Issued', 'Rejected'), r.created_at DESC LIMIT 100"
            );
            $rStmt->execute([':sid' => $societyId]);
        } else {
            $dStmt = $conn->prepare(
                "SELECT d.*, CONCAT(p.first_name, ' ', p.last_name) AS uploader_name
                 FROM sms_documents d
                 LEFT JOIN sms_user_profiles p ON d.uploaded_by = p.user_id
                 WHERE d.society_id = :sid
                   AND (d.visibility = 'Public_Members' OR (d.visibility = 'Resident_Private' AND d.owner_user_id = :uid))
                 ORDER BY d.created_at DESC LIMIT 100"
            );
            $dStmt->execute([':sid' => $societyId, ':uid' => $userId]);

            // Never expose internal_remarks to residents
            $rStmt = $conn->prepare(
                "SELECT id, society_id, resident_id, unit_number, request_type, subject, details,
                        supporting_doc_id, response_doc_id, status, resident_remarks, updated_at, created_at
                 FROM sms_document_requests
                 WHERE society_id = :sid AND resident_id = :uid
                 ORDER BY created_at DESC LIMIT 60"
            );
            $rStmt->execute([':sid' => $societyId, ':uid' => $userId]);
        }

        $docs = array_map(function ($d) use ($userId) {
            $d['stream_url'] = buildDocStreamUrl((int)$d['id'], $userId);
            return $d;
        }, $dStmt->fetchAll());

        $reqs = array_map(function ($r) use ($userId) {
            $r['supporting_url'] = !empty($r['supporting_doc_id']) ? buildDocStreamUrl((int)$r['supporting_doc_id'], $userId) : null;
            $r['response_url'] = !empty($r['response_doc_id']) ? buildDocStreamUrl((int)$r['response_doc_id'], $userId) : null;
            return $r;
        }, $rStmt->fetchAll());

        jsonSuccess(['documents' => $docs, 'requests' => $reqs]);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to load digital document office.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonInput();
    requireValidCsrfToken($data['csrf_token'] ?? '');
    $action = trim((string)($data['action'] ?? ''));

    try {
        if ($action === 'upload_document') {
            $title = trim((string)($data['title'] ?? ''));
            $cat = trim((string)($data['category'] ?? 'Flat_KYC'));
            $vis = trim((string)($data['visibility'] ?? 'Resident_Private'));
            $fileBase64 = (string)($data['file_base64'] ?? '');

            if ($title === '' || $fileBase64 === '') {
                jsonError('Document title and file upload are required.', 400);
            }
            if (!$isDocAdmin) {
                $vis = 'Resident_Private';
                if (!in_array($cat, ['Flat_KYC', 'Lease_Agreement', 'Tax_Receipt'], true)) {
                    $cat = 'Flat_KYC';
                }
            }

            $up = saveBase64Upload($fileBase64, 'vault', ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], 10485760, true);
            $unitNum = (string)($conn->query("SELECT unit_number FROM sms_user_units WHERE user_id = {$userId} AND is_current_occupant = 1 LIMIT 1")->fetchColumn() ?: 'N/A');

            $conn->prepare(
                "INSERT INTO sms_documents (society_id, title, category, visibility, owner_user_id, unit_number, file_path, mime_type, file_size, uploaded_by)
                 VALUES (:sid, :t, :cat, :vis, :own, :un, :fp, :mt, :fs, :by)"
            )->execute([
                ':sid' => $societyId, ':t' => $title, ':cat' => $cat, ':vis' => $vis,
                ':own' => ($vis === 'Resident_Private' ? $userId : null), ':un' => $unitNum,
                ':fp' => $up['relative_path'], ':mt' => $up['mime'], ':fs' => $up['size'], ':by' => $userId
            ]);
            jsonSuccess(['message' => 'Document encrypted & vaulted in private society storage.', 'doc_id' => (int)$conn->lastInsertId()], 201);
        } elseif ($action === 'submit_request') {
            $type = trim((string)($data['request_type'] ?? 'NOC'));
            $subject = trim((string)($data['subject'] ?? ''));
            $details = trim((string)($data['details'] ?? ''));
            $allowedTypes = ['NOC', 'Address_Proof', 'Tenant_Verification', 'Move_In_Out_Approval', 'Parking_Allotment', 'Renovation_Permission'];
            if (!in_array($type, $allowedTypes, true) || $subject === '' || $details === '') {
                jsonError('Request type, subject, and details are required.', 400);
            }

            $unitNum = (string)($conn->query("SELECT unit_number FROM sms_user_units WHERE user_id = {$userId} AND is_current_occupant = 1 LIMIT 1")->fetchColumn() ?: 'N/A');
            $suppDocId = null;
            if (!empty($data['file_base64'])) {
                $up = saveBase64Upload((string)$data['file_base64'], 'requests', ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], 10485760, true);
                $conn->prepare(
                    "INSERT INTO sms_documents (society_id, title, category, visibility, owner_user_id, unit_number, file_path, mime_type, file_size, uploaded_by)
                     VALUES (:sid, :t, 'Flat_KYC', 'Resident_Private', :own, :un, :fp, :mt, :fs, :by)"
                )->execute([
                    ':sid' => $societyId, ':t' => "Supporting: {$subject}", ':own' => $userId,
                    ':un' => $unitNum, ':fp' => $up['relative_path'], ':mt' => $up['mime'], ':fs' => $up['size'], ':by' => $userId
                ]);
                $suppDocId = (int)$conn->lastInsertId();
            }

            $conn->prepare(
                "INSERT INTO sms_document_requests (society_id, resident_id, unit_number, request_type, subject, details, supporting_doc_id, status)
                 VALUES (:sid, :rid, :un, :rt, :sub, :dt, :sdoc, 'Pending')"
            )->execute([
                ':sid' => $societyId, ':rid' => $userId, ':un' => $unitNum,
                ':rt' => $type, ':sub' => $subject, ':dt' => $details, ':sdoc' => $suppDocId
            ]);
            $reqId = (int)$conn->lastInsertId();

            dispatchNotification($conn, [
                'society_id' => $societyId, 'role_ids' => [1, 2], 'category' => 'society_notice', 'priority' => 'Normal',
                'title' => "New {$type} Request: Unit {$unitNum}", 'message' => $subject,
                'action_url' => '/admin/documents', 'reference_type' => 'doc_request', 'reference_id' => $reqId, 'channels' => ['in_app']
            ]);

            jsonSuccess(['message' => 'Office request submitted successfully.', 'request_id' => $reqId], 201);
        } elseif ($action === 'process_request') {
            requireApiPermission('documents.manage');
            $reqId = (int)($data['request_id'] ?? 0);
            $status = trim((string)($data['status'] ?? 'Approved'));
            $internal = trim((string)($data['internal_remarks'] ?? ''));
            $resRem = trim((string)($data['resident_remarks'] ?? ''));
            $autoIssuePdf = !empty($data['auto_issue_pdf']);

            $rStmt = $conn->prepare(
                "SELECT r.*, s.society_name, CONCAT(p.first_name,' ',p.last_name) AS resident_name
                 FROM sms_document_requests r
                 INNER JOIN sms_societies s ON r.society_id = s.id
                 INNER JOIN sms_user_profiles p ON r.resident_id = p.user_id
                 WHERE r.id = :id AND r.society_id = :sid LIMIT 1"
            );
            $rStmt->execute([':id' => $reqId, ':sid' => $societyId]);
            $reqRow = $rStmt->fetch();
            if (!$reqRow) jsonError('Request not found.', 404);

            $respDocId = $reqRow['response_doc_id'] ? (int)$reqRow['response_doc_id'] : null;
            if ($autoIssuePdf || $status === 'Issued') {
                $status = 'Issued';
                $lines = [
                    "Society: {$reqRow['society_name']}",
                    "Date Issued: " . date('d M Y'),
                    "Certificate Ref: CERT-{$reqId}-" . date('Ymd'),
                    "---",
                    "Document Type: " . str_replace('_', ' ', $reqRow['request_type']),
                    "Resident Name: {$reqRow['resident_name']}",
                    "Flat / Unit No: {$reqRow['unit_number']}",
                    "Subject: {$reqRow['subject']}",
                    "---",
                    "OFFICIAL APPROVAL:",
                    "The Managing Committee has verified the unit status and approves this " . str_replace('_', ' ', $reqRow['request_type']) . " request.",
                    $resRem ? "Remarks: {$resRem}" : "Status: Verified & Cleared."
                ];
                $pdfBytes = generateSimplePdf("{$reqRow['society_name']} - Official Certificate", $lines, null);
                $issuedDir = __DIR__ . '/../uploads/private_docs/issued/';
                if (!is_dir($issuedDir)) @mkdir($issuedDir, 0755, true);
                $fname = 'cert_' . $reqId . '_' . bin2hex(random_bytes(4)) . '.pdf';
                file_put_contents($issuedDir . $fname, $pdfBytes);

                $conn->prepare(
                    "INSERT INTO sms_documents (society_id, title, category, visibility, owner_user_id, unit_number, file_path, mime_type, file_size, uploaded_by)
                     VALUES (:sid, :t, 'NOC_Certificate', 'Resident_Private', :own, :un, :fp, 'application/pdf', :fs, :by)"
                )->execute([
                    ':sid' => $societyId, ':t' => "Official {$reqRow['request_type']} - {$reqRow['unit_number']}",
                    ':own' => (int)$reqRow['resident_id'], ':un' => $reqRow['unit_number'],
                    ':fp' => 'private_docs/issued/' . $fname, ':fs' => strlen($pdfBytes), ':by' => $userId
                ]);
                $respDocId = (int)$conn->lastInsertId();
            }

            $conn->prepare(
                "UPDATE sms_document_requests
                 SET status = :st, internal_remarks = :ir, resident_remarks = :rr, response_doc_id = :rdoc, handled_by = :by
                 WHERE id = :id AND society_id = :sid"
            )->execute([
                ':st' => $status, ':ir' => $internal ?: null, ':rr' => $resRem ?: null,
                ':rdoc' => $respDocId, ':by' => $userId, ':id' => $reqId, ':sid' => $societyId
            ]);

            dispatchNotification($conn, [
                'society_id' => $societyId, 'user_ids' => [(int)$reqRow['resident_id']], 'category' => 'society_notice', 'priority' => 'High',
                'title' => "Office Request {$status}: {$reqRow['subject']}",
                'message' => $resRem ?: "Your {$reqRow['request_type']} request status is now {$status}.",
                'action_url' => '/resident/documents', 'channels' => ['in_app', 'email']
            ]);

            logAudit($conn, $societyId, $userId, 'PROCESS_DOC_REQUEST', 'document_request', $reqId, ['status' => $status]);
            jsonSuccess(['message' => "Request updated to {$status}."]);
        } else {
            jsonError('Invalid document office action.', 400);
        }
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to process document office action.');
    }
}
