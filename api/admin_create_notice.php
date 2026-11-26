<?php
// api/admin_create_notice.php
// Enhanced Notice & Announcement Publisher (Categories, Pinning, Scheduling, Targeting, Attachments, Emergency Broadcast)

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/upload_helper.php';
require_once __DIR__ . '/../includes/audit_logger.php';
require_once __DIR__ . '/../includes/notification_service.php';

initApi(['POST']);
requireApiPermission('notices.manage');

$data = getJsonInput();
requireValidCsrfToken($data['csrf_token'] ?? '');

$societyId = (int)$_SESSION['society_id'];
$userId = (int)$_SESSION['user_id'];
$action = trim((string)($data['action'] ?? 'create'));

try {
    if ($action === 'toggle_pin') {
        $id = (int)($data['id'] ?? 0);
        $conn->prepare("UPDATE sms_notices SET is_pinned = 1 - is_pinned WHERE id = :id AND society_id = :sid")
            ->execute([':id' => $id, ':sid' => $societyId]);
        jsonSuccess(['message' => 'Notice pin status updated.']);
    }

    $title = trim((string)($data['title'] ?? ''));
    $content = trim((string)($data['content'] ?? ''));
    $category = trim((string)($data['category'] ?? 'General'));
    $isImportant = !empty($data['is_important']) ? 1 : 0;
    $isPinned = !empty($data['is_pinned']) ? 1 : 0;
    $targetBuildingId = !empty($data['target_building_id']) ? (int)$data['target_building_id'] : null;
    $scheduledAt = !empty($data['scheduled_at']) ? str_replace('T', ' ', trim((string)$data['scheduled_at'])) : null;
    $expiresAt = !empty($data['expires_at']) ? str_replace('T', ' ', trim((string)$data['expires_at'])) : null;

    if ($title === '' || $content === '') {
        jsonError('Title and content are required.', 400);
    }

    $allowedCats = ['General', 'Maintenance', 'Security', 'Financial', 'Events', 'Emergency', 'Important Updates'];
    if (!in_array($category, $allowedCats, true)) {
        $category = 'General';
    }

    $attachPath = null;
    $attachType = null;
    if (!empty($data['attachment_base64'])) {
        $up = saveBase64Upload(
            (string)$data['attachment_base64'],
            'notices',
            ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
            10485760
        );
        $attachPath = $up['relative_path'];
        $attachType = ($up['mime'] === 'application/pdf') ? 'pdf' : 'image';
    }

    $stmt = $conn->prepare(
        'INSERT INTO sms_notices (society_id, created_by, title, content, category, is_important, is_pinned, target_building_id, scheduled_at, expires_at, attachment_path, attachment_type, created_at)
         VALUES (:society_id, :created_by, :title, :content, :cat, :imp, :pin, :tbid, :sat, :eat, :ap, :at, NOW())'
    );
    $stmt->execute([
        ':society_id' => $societyId,
        ':created_by' => $userId,
        ':title' => $title,
        ':content' => $content,
        ':cat' => $category,
        ':imp' => $isImportant,
        ':pin' => $isPinned,
        ':tbid' => $targetBuildingId,
        ':sat' => $scheduledAt,
        ':eat' => $expiresAt,
        ':ap' => $attachPath,
        ':at' => $attachType
    ]);
    $noticeId = (int)$conn->lastInsertId();

    $isEmergency = ($category === 'Emergency');
    dispatchNotification($conn, [
        'society_id' => $societyId,
        'role_ids' => [3, 4],
        'category' => $isEmergency ? 'emergency_broadcast' : 'society_notice',
        'priority' => $isEmergency ? 'Critical' : ($isImportant ? 'High' : 'Normal'),
        'title' => ($isEmergency ? '🚨 ' : '') . "[{$category}] {$title}",
        'message' => mb_substr($content, 0, 180),
        'action_url' => '/resident/dashboard',
        'reference_type' => 'notice',
        'reference_id' => $noticeId,
        'channels' => ['in_app', 'web_push'],
        'scheduled_at' => $scheduledAt
    ]);

    logAudit($conn, $societyId, $userId, 'CREATE_NOTICE', 'notice', $noticeId, ['category' => $category, 'important' => $isImportant]);
    jsonSuccess(['message' => 'Announcement published successfully.', 'notice_id' => $noticeId], 201);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to publish notice.');
}
