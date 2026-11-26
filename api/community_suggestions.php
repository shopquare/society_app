<?php
// api/community_suggestions.php
// Resident Suggestions Box & Admin Triage Workflow API

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/upload_helper.php';
require_once __DIR__ . '/../includes/audit_logger.php';
require_once __DIR__ . '/../includes/notification_service.php';

initApi(['GET', 'POST']);
requireApiAuth([1, 2, 3]);

$societyId = (int)$_SESSION['society_id'];
$userId = (int)$_SESSION['user_id'];
$isAdmin = hasPermission('community.moderate');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        if ($isAdmin) {
            $stmt = $conn->prepare(
                "SELECT s.*,
                        CASE WHEN s.is_anonymous = 1 THEN 'Anonymous Resident' ELSE CONCAT(p.first_name, ' ', p.last_name) END AS resident_name,
                        CASE WHEN s.is_anonymous = 1 THEN 'Hidden' ELSE uu.unit_number END AS unit_number,
                        CONCAT(ap.first_name, ' ', ap.last_name) AS assigned_name
                 FROM sms_suggestions s
                 LEFT JOIN sms_user_profiles p ON s.resident_id = p.user_id
                 LEFT JOIN sms_user_units uu ON s.resident_id = uu.user_id AND uu.is_current_occupant = 1
                 LEFT JOIN sms_user_profiles ap ON s.assigned_to = ap.user_id
                 WHERE s.society_id = :sid
                 ORDER BY s.created_at DESC LIMIT 80"
            );
            $stmt->execute([':sid' => $societyId]);
            jsonSuccess(['suggestions' => $stmt->fetchAll()]);
        }

        // Residents only see their own suggestions & public admin response (NEVER internal_notes!)
        $stmt = $conn->prepare(
            "SELECT id, category, title, description, attachment_path, is_anonymous, admin_response, status, created_at, updated_at
             FROM sms_suggestions
             WHERE society_id = :sid AND resident_id = :uid
             ORDER BY created_at DESC LIMIT 40"
        );
        $stmt->execute([':sid' => $societyId, ':uid' => $userId]);
        jsonSuccess(['suggestions' => $stmt->fetchAll()]);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to load suggestions.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonInput();
    requireValidCsrfToken($data['csrf_token'] ?? '');
    $action = trim((string)($data['action'] ?? 'submit'));

    try {
        if ($action === 'submit') {
            $cat = trim((string)($data['category'] ?? 'Society Improvement'));
            $title = trim((string)($data['title'] ?? ''));
            $desc = trim((string)($data['description'] ?? ''));
            $isAnon = !empty($data['is_anonymous']) ? 1 : 0;

            if ($title === '' || $desc === '') {
                jsonError('Suggestion title and description are required.', 400);
            }
            $attachPath = null;
            if (!empty($data['image_base64'])) {
                $up = saveBase64Upload((string)$data['image_base64'], 'suggestions', ['image/jpeg', 'image/png', 'image/webp'], 5242880);
                $attachPath = $up['relative_path'];
            }

            $conn->prepare(
                "INSERT INTO sms_suggestions (society_id, resident_id, category, title, description, attachment_path, is_anonymous, status)
                 VALUES (:sid, :uid, :cat, :title, :desc, :ap, :anon, 'Submitted')"
            )->execute([
                ':sid' => $societyId, ':uid' => $userId, ':cat' => $cat, ':title' => $title,
                ':desc' => $desc, ':ap' => $attachPath, ':anon' => $isAnon
            ]);
            $sid = (int)$conn->lastInsertId();
            jsonSuccess(['message' => 'Suggestion submitted cleanly! Administration will review it shortly.', 'id' => $sid], 201);
        } elseif ($action === 'admin_update') {
            requireApiPermission('community.moderate');
            $id = (int)($data['id'] ?? 0);
            $status = trim((string)($data['status'] ?? 'Under Review'));
            $notes = trim((string)($data['internal_notes'] ?? ''));
            $response = trim((string)($data['admin_response'] ?? ''));
            $assignedTo = !empty($data['assigned_to']) ? (int)$data['assigned_to'] : null;

            $allowed = ['Submitted', 'Under Review', 'Approved', 'In Progress', 'Implemented', 'Rejected'];
            if ($id <= 0 || !in_array($status, $allowed, true)) jsonError('Invalid status update.', 400);

            $chk = $conn->prepare("SELECT resident_id, title FROM sms_suggestions WHERE id = :id AND society_id = :sid");
            $chk->execute([':id' => $id, ':sid' => $societyId]);
            $row = $chk->fetch();
            if (!$row) jsonError('Suggestion not found.', 404);

            $conn->prepare(
                "UPDATE sms_suggestions SET status = :st, internal_notes = :inotes, admin_response = :resp, assigned_to = :ato
                 WHERE id = :id AND society_id = :sid"
            )->execute([
                ':st' => $status, ':inotes' => $notes ?: null, ':resp' => $response ?: null,
                ':ato' => $assignedTo, ':id' => $id, ':sid' => $societyId
            ]);

            dispatchNotification($conn, [
                'society_id' => $societyId, 'user_ids' => [(int)$row['resident_id']], 'category' => 'complaint_update', 'priority' => 'Normal',
                'title' => "Suggestion Update ({$status}): {$row['title']}",
                'message' => $response !== '' ? $response : "Your suggestion status changed to {$status}.",
                'action_url' => '/resident/community?tab=suggestions', 'channels' => ['in_app']
            ]);
            logAudit($conn, $societyId, $userId, 'UPDATE_SUGGESTION', 'suggestion', $id, ['status' => $status]);
            jsonSuccess(['message' => "Suggestion updated to {$status}."]);
        } else {
            jsonError('Unknown suggestion action.', 400);
        }
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to process suggestion.');
    }
}
