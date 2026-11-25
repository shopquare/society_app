<?php
// /society_app/api/resident_get_visitor_history.php
// Returns past and recent visitor records for the authenticated resident

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['GET']);
requireApiAuth([3]);

$search = trim((string)($_GET['search'] ?? ''));
$limit = min(50, max(1, (int)($_GET['limit'] ?? 5)));

try {
    $societyId = (int)$_SESSION['society_id'];
    $residentId = (int)$_SESSION['user_id'];

    $sql = "SELECT id, visitor_name, phone_number, vehicle_number, purpose, photo_path, status, entry_time, exit_time
            FROM sms_visitors
            WHERE society_id = :society_id AND resident_id = :resident_id";
    $params = [
        ':society_id' => $societyId,
        ':resident_id' => $residentId,
    ];

    if ($search !== '') {
        $sql .= " AND (visitor_name LIKE :search OR purpose LIKE :search OR vehicle_number LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    $sql .= " ORDER BY entry_time DESC LIMIT " . (int)$limit;

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $visitors = $stmt->fetchAll();

    foreach ($visitors as &$vis) {
        $vis['photo_url'] = resolveVisitorPhotoUrl($vis['photo_path'], true);
    }
    unset($vis);

    jsonSuccess([
        'visitors' => $visitors,
        'count' => count($visitors),
    ]);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to retrieve visitor history.');
}
