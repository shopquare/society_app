<?php
// api/guard_incidents.php
// Security Incident Tracking for Gates (Unresolved incidents stay visible until resolved)

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
        $stmt = $conn->prepare(
            "SELECT gi.*, g.gate_name, CONCAT(gp.first_name, ' ', gp.last_name) AS guard_name
             FROM sms_gate_incidents gi
             INNER JOIN sms_gates g ON gi.gate_id = g.id
             LEFT JOIN sms_user_profiles gp ON gi.reported_by_guard_id = gp.user_id
             WHERE gi.society_id = :sid
             ORDER BY (gi.status != 'Resolved') DESC, gi.created_at DESC
             LIMIT 40"
        );
        $stmt->execute([':sid' => $societyId]);
        $incidents = $stmt->fetchAll();

        jsonSuccess([
            'incidents' => $incidents,
            'unresolved_count' => count(array_filter($incidents, fn($i) => $i['status'] !== 'Resolved'))
        ]);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to load gate incidents.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonInput();
    requireValidCsrfToken($data['csrf_token'] ?? '');
    $action = trim((string)($data['action'] ?? 'report'));

    try {
        if ($action === 'report') {
            $requestedGateId = (int)($data['gate_id'] ?? 0);
            $gate = assertGuardAuthorizedForGate($conn, $societyId, $guardId, $requestedGateId);
            $gateId = (int)$gate['id'];

            $type = trim((string)($data['incident_type'] ?? 'Suspicious Activity'));
            $severity = trim((string)($data['severity'] ?? 'Medium'));
            $title = trim((string)($data['title'] ?? ''));
            $description = trim((string)($data['description'] ?? ''));

            if ($title === '' || !in_array($severity, ['Low', 'Medium', 'High', 'Critical'], true)) {
                jsonError('Incident title and valid severity are required.', 400);
            }

            $ins = $conn->prepare(
                "INSERT INTO sms_gate_incidents (society_id, gate_id, reported_by_guard_id, incident_type, severity, title, description, status, created_at)
                 VALUES (:sid, :gid, :uid, :type, :sev, :title, :desc, 'Open', NOW())"
            );
            $ins->execute([
                ':sid' => $societyId, ':gid' => $gateId, ':uid' => $guardId,
                ':type' => $type, ':sev' => $severity, ':title' => $title, ':desc' => $description ?: null
            ]);
            $incId = (int)$conn->lastInsertId();

            logAudit($conn, $societyId, $guardId, 'REPORT_GATE_INCIDENT', 'gate_incident', $incId, ['gate_id' => $gateId, 'severity' => $severity]);

            dispatchNotification($conn, [
                'society_id' => $societyId,
                'role_ids' => [1, 2],
                'category' => 'security_alert',
                'priority' => in_array($severity, ['High', 'Critical'], true) ? 'Critical' : 'High',
                'title' => "[{$severity}] Gate Incident at {$gate['gate_name']}",
                'message' => "{$title} ({$type}) reported by on-duty security guard.",
                'action_url' => '/admin/gates',
                'reference_type' => 'gate_incident',
                'reference_id' => $incId,
                'channels' => ['in_app']
            ]);

            jsonSuccess(['message' => 'Security incident recorded cleanly.', 'incident_id' => $incId], 201);
        } elseif ($action === 'update_status') {
            $incId = (int)($data['incident_id'] ?? 0);
            $status = trim((string)($data['status'] ?? ''));
            $notes = trim((string)($data['resolution_notes'] ?? ''));
            if ($incId <= 0 || !in_array($status, ['Investigating', 'Resolved'], true)) {
                jsonError('Invalid status update.', 400);
            }

            $up = $conn->prepare(
                "UPDATE sms_gate_incidents
                 SET status = :st,
                     resolution_notes = COALESCE(NULLIF(:notes, ''), resolution_notes),
                     resolved_by = CASE WHEN :st2 = 'Resolved' THEN :uid ELSE resolved_by END,
                     resolved_at = CASE WHEN :st3 = 'Resolved' THEN NOW() ELSE resolved_at END
                 WHERE id = :id AND society_id = :sid"
            );
            $up->execute([
                ':st' => $status, ':notes' => $notes, ':st2' => $status,
                ':uid' => $guardId, ':st3' => $status, ':id' => $incId, ':sid' => $societyId
            ]);
            logAudit($conn, $societyId, $guardId, 'UPDATE_GATE_INCIDENT', 'gate_incident', $incId, ['status' => $status]);
            jsonSuccess(['message' => "Incident status updated to {$status}."]);
        } else {
            jsonError('Unknown incident action.', 400);
        }
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to update gate incident.');
    }
}
