<?php
// api/guard_handover.php
// Security Guard Shift Handover Workflow (Outgoing Start, Incoming Acknowledge/Discrepancy, Immutable Locks)

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/gate_helper.php';
require_once __DIR__ . '/../includes/audit_logger.php';
require_once __DIR__ . '/../includes/notification_service.php';

initApi(['GET', 'POST']);
requireApiAuth([4]);

$societyId = (int)$_SESSION['society_id'];
$guardId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $authorizedGates = getGuardAuthorizedGates($conn, $societyId, $guardId);
        $requestedGateId = (int)($_GET['gate_id'] ?? 0);
        $activeGate = assertGuardAuthorizedForGate($conn, $societyId, $guardId, $requestedGateId);
        $gateId = (int)$activeGate['id'];

        // Snapshots for this gate/society
        $pvStmt = $conn->prepare("SELECT COUNT(*) FROM sms_visitors WHERE society_id = :sid AND (gate_id = :gid OR gate_id IS NULL) AND status IN ('Pending', 'Pending Approval')");
        $pvStmt->execute([':sid' => $societyId, ':gid' => $gateId]);
        $pendingVisitors = (int)$pvStmt->fetchColumn();

        $ppStmt = $conn->prepare("SELECT COUNT(*) FROM sms_gate_parcels WHERE society_id = :sid AND status = 'At Gate'");
        $ppStmt->execute([':sid' => $societyId]);
        $pendingParcels = (int)$ppStmt->fetchColumn();

        $oiStmt = $conn->prepare("SELECT COUNT(*) FROM sms_gate_incidents WHERE society_id = :sid AND status != 'Resolved'");
        $oiStmt->execute([':sid' => $societyId]);
        $openIncidents = (int)$oiStmt->fetchColumn();

        // Pending handover awaiting acknowledgment for this gate
        $pendStmt = $conn->prepare(
            "SELECT h.*, g.gate_name,
                    CONCAT(COALESCE(op.first_name,'Admin'), ' ', COALESCE(op.last_name,'Emergency Override')) AS outgoing_guard_name
             FROM sms_gate_handovers h
             INNER JOIN sms_gates g ON h.gate_id = g.id
             LEFT JOIN sms_user_profiles op ON h.outgoing_guard_id = op.user_id
             WHERE h.society_id = :sid AND h.gate_id = :gid AND h.status = 'Pending_Ack'
             ORDER BY h.started_at DESC LIMIT 5"
        );
        $pendStmt->execute([':sid' => $societyId, ':gid' => $gateId]);
        $pendingAck = $pendStmt->fetchAll();

        // Handover history for authorized gates
        $gateIds = array_map(fn($g) => (int)$g['id'], $authorizedGates);
        $inPlaceholders = implode(',', array_fill(0, count($gateIds), '?'));
        $histStmt = $conn->prepare(
            "SELECT h.*, g.gate_name,
                    CONCAT(COALESCE(op.first_name,'System'), ' ', COALESCE(op.last_name,'')) AS outgoing_guard_name,
                    CONCAT(COALESCE(ip.first_name,'Pending'), ' ', COALESCE(ip.last_name,'')) AS incoming_guard_name
             FROM sms_gate_handovers h
             INNER JOIN sms_gates g ON h.gate_id = g.id
             LEFT JOIN sms_user_profiles op ON h.outgoing_guard_id = op.user_id
             LEFT JOIN sms_user_profiles ip ON h.incoming_guard_id = ip.user_id
             WHERE h.society_id = ? AND h.gate_id IN ({$inPlaceholders})
             ORDER BY h.started_at DESC LIMIT 20"
        );
        $histStmt->execute(array_merge([$societyId], $gateIds));
        $history = $histStmt->fetchAll();

        // Other society guards for optional handover recipient selection
        $oGuards = $conn->prepare(
            "SELECT u.id, p.first_name, p.last_name FROM sms_users u
             INNER JOIN sms_user_profiles p ON u.id = p.user_id
             WHERE u.society_id = :sid AND u.role_id = 4 AND u.status = 'active' AND u.id != :me"
        );
        $oGuards->execute([':sid' => $societyId, ':me' => $guardId]);

        jsonSuccess([
            'authorized_gates' => $authorizedGates,
            'active_gate' => $activeGate,
            'snapshot' => [
                'pending_visitors' => $pendingVisitors,
                'pending_parcels' => $pendingParcels,
                'open_incidents' => $openIncidents
            ],
            'pending_acknowledgments' => $pendingAck,
            'history' => $history,
            'peer_guards' => $oGuards->fetchAll()
        ]);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to load gate handover state.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonInput();
    requireValidCsrfToken($data['csrf_token'] ?? '');
    $action = trim((string)($data['action'] ?? 'start_handover'));

    try {
        if ($action === 'start_handover' || $action === 'emergency_handover') {
            $requestedGateId = (int)($data['gate_id'] ?? 0);
            $gate = assertGuardAuthorizedForGate($conn, $societyId, $guardId, $requestedGateId);
            $gateId = (int)$gate['id'];

            $shiftName = trim((string)($data['shift_name'] ?? ($gate['shift_name'] ?? 'Morning')));
            if (!in_array($shiftName, ['Morning', 'Afternoon', 'Night'], true)) {
                $shiftName = 'Morning';
            }
            $handoverType = ($action === 'emergency_handover') ? 'Emergency' : 'Normal';
            $incomingGuardId = !empty($data['incoming_guard_id']) ? (int)$data['incoming_guard_id'] : null;

            $shiftNotes = trim((string)($data['shift_notes'] ?? ''));
            $suspiciousNotes = trim((string)($data['suspicious_activity_notes'] ?? ''));
            $equipmentIssues = trim((string)($data['equipment_issues'] ?? ''));
            $instructions = trim((string)($data['next_guard_instructions'] ?? ''));

            // Live authoritative snapshots
            $pvStmt = $conn->prepare("SELECT COUNT(*) FROM sms_visitors WHERE society_id = :sid AND (gate_id = :gid OR gate_id IS NULL) AND status IN ('Pending', 'Pending Approval')");
            $pvStmt->execute([':sid' => $societyId, ':gid' => $gateId]);
            $pvCount = (int)$pvStmt->fetchColumn();

            $ppStmt = $conn->prepare("SELECT COUNT(*) FROM sms_gate_parcels WHERE society_id = :sid AND status = 'At Gate'");
            $ppStmt->execute([':sid' => $societyId]);
            $ppCount = (int)$ppStmt->fetchColumn();

            $oiStmt = $conn->prepare("SELECT COUNT(*) FROM sms_gate_incidents WHERE society_id = :sid AND status != 'Resolved'");
            $oiStmt->execute([':sid' => $societyId]);
            $oiCount = (int)$oiStmt->fetchColumn();

            $ins = $conn->prepare(
                "INSERT INTO sms_gate_handovers
                    (society_id, gate_id, outgoing_guard_id, incoming_guard_id, shift_name, handover_type,
                     pending_visitors_count, pending_parcels_count, unresolved_incidents_count,
                     shift_notes, suspicious_activity_notes, equipment_issues, next_guard_instructions, status, started_at)
                 VALUES
                    (:sid, :gid, :out_gid, :in_gid, :shift, :htype, :pv, :pp, :oi, :sn, :susp, :eq, :inst, 'Pending_Ack', NOW())"
            );
            $ins->execute([
                ':sid' => $societyId,
                ':gid' => $gateId,
                ':out_gid' => ($handoverType === 'Emergency') ? null : $guardId,
                ':in_gid' => ($handoverType === 'Emergency') ? $guardId : $incomingGuardId,
                ':shift' => $shiftName,
                ':htype' => $handoverType,
                ':pv' => $pvCount,
                ':pp' => $ppCount,
                ':oi' => $oiCount,
                ':sn' => $shiftNotes ?: null,
                ':susp' => $suspiciousNotes ?: null,
                ':eq' => $equipmentIssues ?: null,
                ':inst' => $instructions ?: null
            ]);
            $handoverId = (int)$conn->lastInsertId();

            logAudit($conn, $societyId, $guardId, 'START_GATE_HANDOVER', 'gate_handover', $handoverId, ['gate_id' => $gateId, 'type' => $handoverType]);

            dispatchNotification($conn, [
                'society_id' => $societyId,
                'role_ids' => [1, 2, 4],
                'category' => 'gate_handover',
                'priority' => $handoverType === 'Emergency' ? 'Critical' : 'High',
                'title' => "Shift Handover Initiated — {$gate['gate_name']}",
                'message' => "{$handoverType} shift handover started ({$shiftName}). Open incidents: {$oiCount}, Pending parcels: {$ppCount}.",
                'action_url' => '/guard/dashboard',
                'reference_type' => 'gate_handover',
                'reference_id' => $handoverId,
                'channels' => ['in_app']
            ]);

            jsonSuccess(['message' => 'Shift handover started and logged cleanly.', 'handover_id' => $handoverId], 201);
        } elseif ($action === 'acknowledge_handover' || $action === 'report_discrepancy') {
            $handoverId = (int)($data['handover_id'] ?? 0);
            $discrepancy = trim((string)($data['discrepancy_notes'] ?? ''));

            if ($handoverId <= 0) {
                jsonError('Invalid handover ID.', 400);
            }
            if ($action === 'report_discrepancy' && $discrepancy === '') {
                jsonError('Please describe the discrepancy before submitting.', 400);
            }

            $conn->beginTransaction();
            $hStmt = $conn->prepare("SELECT * FROM sms_gate_handovers WHERE id = :id AND society_id = :sid FOR UPDATE");
            $hStmt->execute([':id' => $handoverId, ':sid' => $societyId]);
            $handover = $hStmt->fetch();

            if (!$handover) {
                $conn->rollBack();
                jsonError('Handover record not found.', 404);
            }

            assertGuardAuthorizedForGate($conn, $societyId, $guardId, (int)$handover['gate_id']);

            // Immutability check: Completed handovers must never be edited again!
            if ($handover['status'] !== 'Pending_Ack') {
                $conn->rollBack();
                jsonError('Completed handovers cannot be modified (immutable audit record).', 403);
            }

            $newStatus = ($action === 'report_discrepancy') ? 'Discrepancy_Reported' : 'Acknowledged';
            $up = $conn->prepare(
                "UPDATE sms_gate_handovers
                 SET incoming_guard_id = :in_gid, status = :st, discrepancy_notes = :disc, acknowledged_at = NOW()
                 WHERE id = :id"
            );
            $up->execute([
                ':in_gid' => $guardId,
                ':st' => $newStatus,
                ':disc' => ($discrepancy !== '' ? $discrepancy : null),
                ':id' => $handoverId
            ]);
            $conn->commit();

            logAudit($conn, $societyId, $guardId, strtoupper($action), 'gate_handover', $handoverId, ['status' => $newStatus, 'discrepancy' => $discrepancy]);

            if ($newStatus === 'Discrepancy_Reported') {
                dispatchNotification($conn, [
                    'society_id' => $societyId,
                    'role_ids' => [1, 2],
                    'category' => 'gate_handover',
                    'priority' => 'High',
                    'title' => 'Gate Handover Discrepancy Reported',
                    'message' => "Incoming guard reported discrepancy on Handover #{$handoverId}: {$discrepancy}",
                    'action_url' => '/admin/gates',
                    'channels' => ['in_app']
                ]);
            }

            jsonSuccess(['message' => $newStatus === 'Acknowledged' ? 'Handover acknowledged. You are now on duty.' : 'Discrepancy logged and reported to security admin.', 'status' => $newStatus]);
        } else {
            jsonError('Unknown handover action.', 400);
        }
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        logAndFail($e, 'Unable to process gate handover.');
    }
}
