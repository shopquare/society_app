<?php
// /society_app/api/guard_get_visitors.php

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/gate_helper.php';

initApi(['GET']);
requireApiAuth([4]);

try {
    $societyId = (int)$_SESSION['society_id'];
    $guardId = (int)$_SESSION['user_id'];
    $requestedGateId = (int)($_GET['gate_id'] ?? 0);

    $authorizedGates = getGuardAuthorizedGates($conn, $societyId, $guardId);
    $authGateIds = array_map(fn($g) => (int)$g['id'], $authorizedGates);

    if ($requestedGateId > 0) {
        assertGuardAuthorizedForGate($conn, $societyId, $guardId, $requestedGateId);
        $filterGateIds = [$requestedGateId];
    } else {
        $filterGateIds = $authGateIds;
    }

    $todayStart = date('Y-m-d 00:00:00');
    $todayEnd = date('Y-m-d 23:59:59');

    $gateClause = '';
    $params = [$societyId, $todayStart, $todayEnd];
    if (!empty($filterGateIds)) {
        $placeholders = implode(',', array_fill(0, count($filterGateIds), '?'));
        $gateClause = " AND (v.gate_id IN ({$placeholders}) OR v.gate_id IS NULL)";
        $params = array_merge($params, $filterGateIds);
    }

    // Sargable IST date range query enables B-tree index usage on (society_id, entry_time)
    $query = "SELECT v.id, v.gate_id, g.gate_name, v.visitor_name, v.visitor_tag, v.phone_number, v.vehicle_number, v.purpose, v.photo_path, v.status, v.entry_time, v.exit_time,
                     p.first_name, p.last_name, uu.unit_number
              FROM sms_visitors v
              INNER JOIN sms_users u ON v.resident_id = u.id
              INNER JOIN sms_user_profiles p ON u.id = p.user_id
              LEFT JOIN sms_user_units uu ON u.id = uu.user_id AND uu.is_current_occupant = 1
              LEFT JOIN sms_gates g ON v.gate_id = g.id
              WHERE v.society_id = ?
                AND v.entry_time >= ?
                AND v.entry_time <= ?
                {$gateClause}
              ORDER BY v.entry_time DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $visitors = $stmt->fetchAll();

    foreach ($visitors as &$vis) {
        $vis['photo_url'] = resolveVisitorPhotoUrl($vis['photo_path'], true);
    }
    unset($vis);

    jsonSuccess(['data' => $visitors, 'authorized_gates' => $authorizedGates]);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to load today\'s visitors.');
}
