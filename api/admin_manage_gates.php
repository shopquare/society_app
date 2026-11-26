<?php
// api/admin_manage_gates.php
// Multi-Gate Configuration, Guard Shift Assignments, Handover Audit & Gate Reporting API

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/audit_logger.php';
require_once __DIR__ . '/../includes/notification_service.php';

initApi(['GET', 'POST']);
requireApiPermission('gates.view');

$societyId = (int)$_SESSION['society_id'];
$actorId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $export = trim((string)($_GET['export'] ?? ''));

    try {
        // Export Handover CSV Report
        if ($export === 'handovers_csv') {
            $stmt = $conn->prepare(
                "SELECT h.id, g.gate_name, h.shift_name, h.handover_type, h.status,
                        CONCAT(COALESCE(op.first_name,'N/A'), ' ', COALESCE(op.last_name,'')) AS outgoing_guard,
                        CONCAT(COALESCE(ip.first_name,'Pending'), ' ', COALESCE(ip.last_name,'')) AS incoming_guard,
                        h.pending_visitors_count, h.pending_parcels_count, h.unresolved_incidents_count,
                        h.shift_notes, h.equipment_issues, h.discrepancy_notes, h.started_at, h.acknowledged_at
                 FROM sms_gate_handovers h
                 INNER JOIN sms_gates g ON h.gate_id = g.id
                 LEFT JOIN sms_user_profiles op ON h.outgoing_guard_id = op.user_id
                 LEFT JOIN sms_user_profiles ip ON h.incoming_guard_id = ip.user_id
                 WHERE h.society_id = :sid
                 ORDER BY h.started_at DESC"
            );
            $stmt->execute([':sid' => $societyId]);
            $rows = $stmt->fetchAll();

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="gate_handovers_' . date('Ymd_His') . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Gate', 'Shift', 'Type', 'Status', 'Outgoing Guard', 'Incoming Guard', 'Pending Visitors', 'Pending Parcels', 'Open Incidents', 'Notes', 'Equipment Issues', 'Discrepancies', 'Started At', 'Acknowledged At']);
            foreach ($rows as $r) {
                fputcsv($out, array_values($r));
            }
            fclose($out);
            exit;
        }

        // Export Gate-Wise Traffic Report
        if ($export === 'gates_csv') {
            $stmt = $conn->prepare(
                "SELECT g.id, g.gate_name, g.gate_type, g.is_active,
                        (SELECT COUNT(*) FROM sms_visitors v WHERE v.society_id = g.society_id AND v.gate_id = g.id) AS total_visitors,
                        (SELECT COUNT(*) FROM sms_visitors v WHERE v.society_id = g.society_id AND v.gate_id = g.id AND v.purpose = 'Delivery') AS total_deliveries,
                        (SELECT COUNT(*) FROM sms_gate_incidents gi WHERE gi.society_id = g.society_id AND gi.gate_id = g.id) AS total_incidents
                 FROM sms_gates g
                 WHERE g.society_id = :sid
                 ORDER BY g.id ASC"
            );
            $stmt->execute([':sid' => $societyId]);
            $rows = $stmt->fetchAll();

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="gate_wise_report_' . date('Ymd_His') . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Gate ID', 'Gate Name', 'Type', 'Active', 'Total Visitors', 'Deliveries', 'Incidents']);
            foreach ($rows as $r) {
                fputcsv($out, array_values($r));
            }
            fclose($out);
            exit;
        }

        // 1. All Gates with Today's Stats
        $todayStart = date('Y-m-d 00:00:00');
        $gStmt = $conn->prepare(
            "SELECT g.*, b.building_name,
                    (SELECT COUNT(*) FROM sms_visitors v WHERE v.society_id = g.society_id AND v.gate_id = g.id AND v.entry_time >= :ts1) AS visitors_today,
                    (SELECT COUNT(*) FROM sms_visitors v WHERE v.society_id = g.society_id AND v.gate_id = g.id AND v.purpose = 'Delivery' AND v.entry_time >= :ts2) AS deliveries_today,
                    (SELECT COUNT(*) FROM sms_gate_incidents gi WHERE gi.society_id = g.society_id AND gi.gate_id = g.id AND gi.status != 'Resolved') AS open_incidents,
                    (SELECT COUNT(*) FROM sms_gate_assignments ga WHERE ga.society_id = g.society_id AND ga.gate_id = g.id AND ga.is_active = 1) AS assigned_guards
             FROM sms_gates g
             LEFT JOIN sms_buildings b ON g.building_id = b.id
             WHERE g.society_id = :sid
             ORDER BY g.id ASC"
        );
        $gStmt->execute([':sid' => $societyId, ':ts1' => $todayStart, ':ts2' => $todayStart]);
        $gates = $gStmt->fetchAll();

        // 2. Active Guards
        $guardsStmt = $conn->prepare(
            "SELECT u.id, u.email, p.first_name, p.last_name, p.phone_number
             FROM sms_users u
             INNER JOIN sms_user_profiles p ON u.id = p.user_id
             WHERE u.society_id = :sid AND u.role_id = 4 AND u.status = 'active'
             ORDER BY p.first_name ASC"
        );
        $guardsStmt->execute([':sid' => $societyId]);
        $guards = $guardsStmt->fetchAll();

        // 3. Guard Assignments & Gate Activity Attendance Snapshot
        $aStmt = $conn->prepare(
            "SELECT a.*, g.gate_name, g.gate_type, p.first_name, p.last_name,
                    (SELECT COUNT(*) FROM sms_visitors v WHERE v.society_id = a.society_id AND v.guard_id = a.guard_id AND v.gate_id = a.gate_id AND v.entry_time >= :ts) AS entries_today
             FROM sms_gate_assignments a
             INNER JOIN sms_gates g ON a.gate_id = g.id
             INNER JOIN sms_user_profiles p ON a.guard_id = p.user_id
             WHERE a.society_id = :sid AND a.is_active = 1
             ORDER BY g.gate_name ASC, a.shift_name ASC"
        );
        $aStmt->execute([':sid' => $societyId, ':ts' => $todayStart]);
        $assignments = $aStmt->fetchAll();

        // 4. Filtered Shift Handovers
        $guardSearch = trim((string)($_GET['guard_search'] ?? ''));
        $filterDate = trim((string)($_GET['date'] ?? ''));
        $filterShift = trim((string)($_GET['shift'] ?? ''));

        $hSql = "SELECT h.*, g.gate_name,
                        CONCAT(COALESCE(op.first_name,'N/A'), ' ', COALESCE(op.last_name,'')) AS outgoing_guard_name,
                        CONCAT(COALESCE(ip.first_name,'Pending'), ' ', COALESCE(ip.last_name,'')) AS incoming_guard_name
                 FROM sms_gate_handovers h
                 INNER JOIN sms_gates g ON h.gate_id = g.id
                 LEFT JOIN sms_user_profiles op ON h.outgoing_guard_id = op.user_id
                 LEFT JOIN sms_user_profiles ip ON h.incoming_guard_id = ip.user_id
                 WHERE h.society_id = :sid";
        $hParams = [':sid' => $societyId];
        if ($guardSearch !== '') {
            $hSql .= " AND (CONCAT(COALESCE(op.first_name,''), ' ', COALESCE(op.last_name,'')) LIKE :gs OR CONCAT(COALESCE(ip.first_name,''), ' ', COALESCE(ip.last_name,'')) LIKE :gs2)";
            $hParams[':gs'] = '%' . $guardSearch . '%';
            $hParams[':gs2'] = '%' . $guardSearch . '%';
        }
        if ($filterDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
            $hSql .= " AND DATE(h.started_at) = :fdate";
            $hParams[':fdate'] = $filterDate;
        }
        if ($filterShift !== '' && in_array($filterShift, ['Morning', 'Afternoon', 'Night'], true)) {
            $hSql .= " AND h.shift_name = :fshift";
            $hParams[':fshift'] = $filterShift;
        }
        $hSql .= " ORDER BY h.started_at DESC LIMIT 60";
        $hStmt = $conn->prepare($hSql);
        $hStmt->execute($hParams);
        $handovers = $hStmt->fetchAll();

        // 5. Missing Handovers Detection (Active gates with entries today or assigned guards but no handover in last 24h)
        $mStmt = $conn->prepare(
            "SELECT g.id, g.gate_name,
                    (SELECT MAX(started_at) FROM sms_gate_handovers h WHERE h.gate_id = g.id AND h.society_id = g.society_id) AS last_handover_at
             FROM sms_gates g
             WHERE g.society_id = :sid AND g.is_active = 1
               AND EXISTS (SELECT 1 FROM sms_gate_assignments ga WHERE ga.gate_id = g.id AND ga.is_active = 1)
               AND NOT EXISTS (SELECT 1 FROM sms_gate_handovers h WHERE h.gate_id = g.id AND h.started_at >= NOW() - INTERVAL 24 HOUR)"
        );
        $mStmt->execute([':sid' => $societyId]);
        $missingHandovers = $mStmt->fetchAll();

        // 6. Unresolved Security Incidents
        $incStmt = $conn->prepare(
            "SELECT gi.*, g.gate_name, CONCAT(gp.first_name, ' ', gp.last_name) AS guard_name
             FROM sms_gate_incidents gi
             INNER JOIN sms_gates g ON gi.gate_id = g.id
             LEFT JOIN sms_user_profiles gp ON gi.reported_by_guard_id = gp.user_id
             WHERE gi.society_id = :sid
             ORDER BY (gi.status != 'Resolved') DESC, gi.created_at DESC
             LIMIT 50"
        );
        $incStmt->execute([':sid' => $societyId]);
        $incidents = $incStmt->fetchAll();

        // Buildings list for tower gates
        $bStmt = $conn->prepare("SELECT id, building_name FROM sms_buildings WHERE society_id = :sid AND is_active = 1 ORDER BY building_name ASC");
        $bStmt->execute([':sid' => $societyId]);
        $buildings = $bStmt->fetchAll();

        jsonSuccess([
            'gates' => $gates,
            'guards' => $guards,
            'assignments' => $assignments,
            'handovers' => $handovers,
            'missing_handovers' => $missingHandovers,
            'incidents' => $incidents,
            'buildings' => $buildings
        ]);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to load gates management dashboard.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonInput();
    requireValidCsrfToken($data['csrf_token'] ?? '');
    $action = trim((string)($data['action'] ?? ''));

    try {
        if ($action === 'save_gate') {
            requireApiPermission('gates.manage');
            $id = (int)($data['id'] ?? 0);
            $name = trim((string)($data['gate_name'] ?? ''));
            $type = trim((string)($data['gate_type'] ?? 'Main'));
            $buildingId = !empty($data['building_id']) ? (int)$data['building_id'] : null;
            $openTime = trim((string)($data['open_time'] ?? '00:00:00'));
            $closeTime = trim((string)($data['close_time'] ?? '23:59:59'));
            $isActive = isset($data['is_active']) ? (!empty($data['is_active']) ? 1 : 0) : 1;

            $allowedTypes = ['Main', 'Back', 'Tower', 'Service', 'Pedestrian', 'Parking', 'Delivery'];
            if ($name === '' || !in_array($type, $allowedTypes, true)) {
                jsonError('Valid gate name and gate type are required.', 400);
            }

            if ($id > 0) {
                $stmt = $conn->prepare(
                    "UPDATE sms_gates SET gate_name = :name, gate_type = :type, building_id = :bid, open_time = :ot, close_time = :ct, is_active = :act
                     WHERE id = :id AND society_id = :sid"
                );
                $stmt->execute([':name' => $name, ':type' => $type, ':bid' => $buildingId, ':ot' => $openTime, ':ct' => $closeTime, ':act' => $isActive, ':id' => $id, ':sid' => $societyId]);
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO sms_gates (society_id, gate_name, gate_type, building_id, open_time, close_time, is_active)
                     VALUES (:sid, :name, :type, :bid, :ot, :ct, :act)"
                );
                $stmt->execute([':sid' => $societyId, ':name' => $name, ':type' => $type, ':bid' => $buildingId, ':ot' => $openTime, ':ct' => $closeTime, ':act' => $isActive]);
                $id = (int)$conn->lastInsertId();
            }

            logAudit($conn, $societyId, $actorId, 'SAVE_GATE', 'gate', $id, ['gate_name' => $name, 'type' => $type]);
            jsonSuccess(['message' => 'Gate configuration saved.', 'gate_id' => $id]);
        } elseif ($action === 'assign_guard') {
            requireApiPermission('gates.manage');
            $gateId = (int)($data['gate_id'] ?? 0);
            $guardId = (int)($data['guard_id'] ?? 0);
            $shift = trim((string)($data['shift_name'] ?? 'Morning'));
            if ($gateId <= 0 || $guardId <= 0 || !in_array($shift, ['Morning', 'Afternoon', 'Night'], true)) {
                jsonError('Gate, guard, and valid shift are required.', 400);
            }

            // Ensure both gate and guard belong to actor's society
            $gChk = $conn->prepare("SELECT id, gate_name FROM sms_gates WHERE id = :gid AND society_id = :sid");
            $gChk->execute([':gid' => $gateId, ':sid' => $societyId]);
            $gateRow = $gChk->fetch();

            $uChk = $conn->prepare("SELECT id FROM sms_users WHERE id = :uid AND society_id = :sid AND role_id = 4 AND status = 'active'");
            $uChk->execute([':uid' => $guardId, ':sid' => $societyId]);
            if (!$gateRow || !$uChk->fetch()) {
                jsonError('Invalid gate or guard for this society.', 404);
            }

            $up = $conn->prepare(
                "INSERT INTO sms_gate_assignments (society_id, gate_id, guard_id, shift_name, is_active, assigned_by)
                 VALUES (:sid, :gate_id, :guard_id, :shift, 1, :by)
                 ON DUPLICATE KEY UPDATE is_active = 1, assigned_by = VALUES(assigned_by)"
            );
            $up->execute([':sid' => $societyId, ':gate_id' => $gateId, ':guard_id' => $guardId, ':shift' => $shift, ':by' => $actorId]);

            dispatchNotification($conn, [
                'society_id' => $societyId,
                'user_ids' => [$guardId],
                'category' => 'gate_handover',
                'priority' => 'High',
                'title' => "Assigned to {$gateRow['gate_name']} ({$shift} Shift)",
                'message' => "Security schedule updated: report to {$gateRow['gate_name']} for {$shift} shift.",
                'action_url' => '/guard/dashboard',
                'channels' => ['in_app']
            ]);

            logAudit($conn, $societyId, $actorId, 'ASSIGN_GUARD_GATE', 'gate', $gateId, ['guard_id' => $guardId, 'shift' => $shift]);
            jsonSuccess(['message' => "Guard assigned to {$gateRow['gate_name']} ({$shift} Shift)."]);
        } elseif ($action === 'remove_assignment') {
            requireApiPermission('gates.manage');
            $assignId = (int)($data['assignment_id'] ?? 0);
            $del = $conn->prepare("UPDATE sms_gate_assignments SET is_active = 0 WHERE id = :id AND society_id = :sid");
            $del->execute([':id' => $assignId, ':sid' => $societyId]);
            jsonSuccess(['message' => 'Guard gate assignment deactivated.']);
        } elseif ($action === 'resolve_incident') {
            requireApiPermission('incidents.manage');
            $incId = (int)($data['incident_id'] ?? 0);
            $notes = trim((string)($data['resolution_notes'] ?? ''));
            $up = $conn->prepare(
                "UPDATE sms_gate_incidents SET status = 'Resolved', resolved_by = :by, resolution_notes = :notes, resolved_at = NOW()
                 WHERE id = :id AND society_id = :sid"
            );
            $up->execute([':by' => $actorId, ':notes' => $notes, ':id' => $incId, ':sid' => $societyId]);
            logAudit($conn, $societyId, $actorId, 'RESOLVE_GATE_INCIDENT', 'gate_incident', $incId, ['notes' => $notes]);
            jsonSuccess(['message' => 'Security incident marked resolved.']);
        } elseif ($action === 'create_emergency_handover') {
            requireApiPermission('handovers.manage');
            $gateId = (int)($data['gate_id'] ?? 0);
            $shift = trim((string)($data['shift_name'] ?? 'Morning'));
            $incomingGuardId = !empty($data['incoming_guard_id']) ? (int)$data['incoming_guard_id'] : null;
            $instructions = trim((string)($data['instructions'] ?? 'Emergency Admin Handover Initiated (Outgoing guard unavailable).'));

            $gChk = $conn->prepare("SELECT id, gate_name FROM sms_gates WHERE id = :id AND society_id = :sid LIMIT 1");
            $gChk->execute([':id' => $gateId, ':sid' => $societyId]);
            $gRow = $gChk->fetch();
            if (!$gRow) jsonError('Gate not found.', 404);

            $ins = $conn->prepare(
                "INSERT INTO sms_gate_handovers (society_id, gate_id, outgoing_guard_id, incoming_guard_id, shift_name, handover_type, next_guard_instructions, status, started_at)
                 VALUES (:sid, :gid, NULL, :inc, :shift, 'Emergency', :inst, 'Pending_Ack', NOW())"
            );
            $ins->execute([':sid' => $societyId, ':gid' => $gateId, ':inc' => $incomingGuardId, ':shift' => $shift, ':inst' => $instructions]);
            $hid = (int)$conn->lastInsertId();

            logAudit($conn, $societyId, $actorId, 'EMERGENCY_GATE_HANDOVER', 'gate_handover', $hid, ['gate_id' => $gateId, 'incoming_guard' => $incomingGuardId]);
            jsonSuccess(['message' => 'Emergency handover logged cleanly.', 'handover_id' => $hid], 201);
        } else {
            jsonError('Unknown gate admin action.', 400);
        }
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to update gate configuration.');
    }
}
