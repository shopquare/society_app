<?php
// api/resident_directory.php
// Privacy-Enforced, Anti-Scraping Resident Directory API

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['GET', 'POST']);
requireApiAuth([1, 2, 3]);

$societyId = (int)$_SESSION['society_id'];
$userId = (int)$_SESSION['user_id'];
$isAdmin = hasPermission('residents.manage');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = trim((string)($_GET['action'] ?? 'list'));
    try {
        if ($action === 'my_privacy') {
            $pStmt = $conn->prepare("SELECT show_in_directory, show_phone, show_email, show_family FROM sms_resident_privacy_settings WHERE user_id = :uid LIMIT 1");
            $pStmt->execute([':uid' => $userId]);
            $row = $pStmt->fetch() ?: ['show_in_directory' => 1, 'show_phone' => 0, 'show_email' => 0, 'show_family' => 0];
            jsonSuccess(['privacy' => array_map('intval', $row)]);
        }

        // Prevent automated bulk scraping
        checkRateLimit('directory_lookup_' . $userId, 40, 60);

        $search = trim((string)($_GET['q'] ?? ''));
        $building = trim((string)($_GET['building'] ?? ''));
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20; // Hard cap per page to protect against bulk extraction
        $offset = ($page - 1) * $limit;

        $where = "u.society_id = :sid AND u.role_id = 3 AND u.status = 'active'";
        $params = [':sid' => $societyId];
        if (!$isAdmin) {
            $where .= " AND COALESCE(rps.show_in_directory, 1) = 1";
        }
        if ($search !== '') {
            $where .= " AND (p.first_name LIKE :q1 OR p.last_name LIKE :q2 OR uu.unit_number LIKE :q3)";
            $params[':q1'] = '%' . $search . '%';
            $params[':q2'] = '%' . $search . '%';
            $params[':q3'] = '%' . $search . '%';
        }
        if ($building !== '' && $building !== 'all') {
            $where .= " AND (uu.unit_number LIKE :b1 OR un.building_id = :b2)";
            $params[':b1'] = $building . '%';
            $params[':b2'] = (int)$building;
        }

        $sql = "SELECT u.id, p.first_name, p.last_name,
                       uu.unit_number, uu.occupancy_type,
                       b.building_name, un.flat_type,
                       COALESCE(rps.show_phone, 0) AS show_phone,
                       COALESCE(rps.show_email, 0) AS show_email,
                       COALESCE(rps.show_family, 0) AS show_family,
                       CASE WHEN COALESCE(rps.show_phone, 0) = 1 OR :adm1 = 1 THEN p.phone_number ELSE NULL END AS phone_number,
                       CASE WHEN COALESCE(rps.show_email, 0) = 1 OR :adm2 = 1 THEN u.email ELSE NULL END AS email
                FROM sms_users u
                INNER JOIN sms_user_profiles p ON u.id = p.user_id
                LEFT JOIN sms_user_units uu ON u.id = uu.user_id AND uu.is_current_occupant = 1
                LEFT JOIN sms_units un ON uu.society_id = un.society_id AND uu.unit_number = un.unit_number
                LEFT JOIN sms_buildings b ON un.building_id = b.id
                LEFT JOIN sms_resident_privacy_settings rps ON u.id = rps.user_id
                WHERE {$where}
                ORDER BY uu.unit_number ASC, p.first_name ASC
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $conn->prepare($sql);
        $params[':adm1'] = $isAdmin ? 1 : 0;
        $params[':adm2'] = $isAdmin ? 1 : 0;
        $stmt->execute($params);
        $residents = $stmt->fetchAll();

        $famStmt = $conn->prepare("SELECT full_name, relation FROM sms_family_members WHERE resident_id = :rid AND society_id = :sid");
        foreach ($residents as &$res) {
            if ((int)$res['show_family'] === 1 || $isAdmin) {
                $famStmt->execute([':rid' => (int)$res['id'], ':sid' => $societyId]);
                $res['family_members'] = $famStmt->fetchAll();
            } else {
                $res['family_members'] = [];
            }
        }
        unset($res);

        $bStmt = $conn->prepare("SELECT id, building_name, building_code FROM sms_buildings WHERE society_id = :sid AND is_active = 1 ORDER BY building_code ASC");
        $bStmt->execute([':sid' => $societyId]);

        jsonSuccess(['residents' => $residents, 'buildings' => $bStmt->fetchAll(), 'page' => $page]);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to load resident directory.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonInput();
    requireValidCsrfToken($data['csrf_token'] ?? '');

    try {
        $showDir = !empty($data['show_in_directory']) ? 1 : 0;
        $showPhone = !empty($data['show_phone']) ? 1 : 0;
        $showEmail = !empty($data['show_email']) ? 1 : 0;
        $showFamily = !empty($data['show_family']) ? 1 : 0;

        $conn->prepare(
            "INSERT INTO sms_resident_privacy_settings (user_id, society_id, show_in_directory, show_phone, show_email, show_family)
             VALUES (:uid, :sid, :sd, :sp, :se, :sf)
             ON DUPLICATE KEY UPDATE
             show_in_directory = VALUES(show_in_directory), show_phone = VALUES(show_phone),
             show_email = VALUES(show_email), show_family = VALUES(show_family)"
        )->execute([':uid' => $userId, ':sid' => $societyId, ':sd' => $showDir, ':sp' => $showPhone, ':se' => $showEmail, ':sf' => $showFamily]);

        jsonSuccess(['message' => 'Directory privacy preferences updated.']);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to save privacy preferences.');
    }
}
