<?php
// api/admin_get_audit_logs.php
// Enterprise Audit Trail & Compliance Log Viewer

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['GET']);
requireApiAuth([1, 2]); // Super Admin, Committee

$societyId = (int)$_SESSION['society_id'];
$limit = min(max((int)($_GET['limit'] ?? 50), 10), 200);
$offset = max((int)($_GET['offset'] ?? 0), 0);
$actionFilter = trim($_GET['action'] ?? '');

$sql = "
    SELECT a.id, a.user_id, a.action, a.entity_type, a.entity_id, a.details, a.ip_address, a.created_at,
           CONCAT(p.first_name, ' ', p.last_name) AS user_name, r.role_name
    FROM sms_audit_logs a
    LEFT JOIN sms_users u ON a.user_id = u.id
    LEFT JOIN sms_user_profiles p ON u.id = p.user_id
    LEFT JOIN sms_roles r ON u.role_id = r.id
    WHERE a.society_id = :sid
";

$params = [':sid' => $societyId];

if ($actionFilter !== '') {
    $sql .= " AND a.action = :act";
    $params[':act'] = $actionFilter;
}

$sql .= " ORDER BY a.created_at DESC LIMIT :limit OFFSET :offset";

$stmt = $conn->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count total
$countSql = "SELECT COUNT(*) FROM sms_audit_logs WHERE society_id = :sid";
if ($actionFilter !== '') {
    $countSql .= " AND action = :act";
}
$countStmt = $conn->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

jsonSuccess([
    'logs' => $logs,
    'total' => $total,
    'limit' => $limit,
    'offset' => $offset
]);
