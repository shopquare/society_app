<?php
// api/get_notices.php
// Enhanced Announcement Reader with Category Search, Pin Priority, Building Filtering & View Stats

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['GET', 'POST']);
requireApiAuth([1, 2, 3, 4]);

$societyId = (int)$_SESSION['society_id'];
$userId = (int)$_SESSION['user_id'];
$isAdmin = hasAnyPermission(['notices.manage', 'reports.view']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonInput();
    $noticeId = (int)($data['notice_id'] ?? 0);
    if ($noticeId > 0) {
        try {
            $conn->prepare("INSERT IGNORE INTO sms_notice_views (notice_id, user_id) VALUES (:nid, :uid)")
                ->execute([':nid' => $noticeId, ':uid' => $userId]);
        } catch (Throwable $_) {}
    }
    jsonSuccess(['recorded' => true]);
}

try {
    $category = trim((string)($_GET['category'] ?? ''));
    $search = trim((string)($_GET['q'] ?? ''));
    $now = date('Y-m-d H:i:s');

    $where = "n.society_id = :society_id";
    $params = [':society_id' => $societyId];

    if (!$isAdmin) {
        $where .= " AND (n.scheduled_at IS NULL OR n.scheduled_at <= :now1) AND (n.expires_at IS NULL OR n.expires_at >= :now2)";
        $params[':now1'] = $now;
        $params[':now2'] = $now;
    }
    if ($category !== '' && $category !== 'all') {
        $where .= " AND n.category = :cat";
        $params[':cat'] = $category;
    }
    if ($search !== '') {
        $where .= " AND (n.title LIKE :q1 OR n.content LIKE :q2)";
        $params[':q1'] = '%' . $search . '%';
        $params[':q2'] = '%' . $search . '%';
    }

    $query = "SELECT n.id, n.title, n.content, COALESCE(n.category, 'General') AS category,
                     COALESCE(n.is_important, 0) AS is_important, COALESCE(n.is_pinned, 0) AS is_pinned,
                     n.target_building_id, b.building_name AS target_building_name,
                     n.scheduled_at, n.expires_at, n.attachment_path, n.attachment_type, n.created_at,
                     p.first_name, p.last_name,
                     (SELECT COUNT(*) FROM sms_notice_views nv WHERE nv.notice_id = n.id) AS views_count
              FROM sms_notices n
              INNER JOIN sms_user_profiles p ON n.created_by = p.user_id
              LEFT JOIN sms_buildings b ON n.target_building_id = b.id
              WHERE {$where}
              ORDER BY COALESCE(n.is_pinned, 0) DESC, n.created_at DESC
              LIMIT 60";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $notices = $stmt->fetchAll();

    jsonSuccess(['data' => $notices]);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to load notices.');
}
