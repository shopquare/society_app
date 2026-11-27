<?php
// includes/gate_helper.php
// Multi-Gate Security & Authorization Enforcement Helper

/**
 * Returns all active gates that the given guard is authorized to operate.
 * Also provisions a fallback assignment to the primary Main Gate if no assignments exist yet for new societies.
 */
function getGuardAuthorizedGates(PDO $conn, int $societyId, int $guardId): array {
    try {
        $stmt = $conn->prepare(
            "SELECT g.id, g.gate_name, g.gate_type, g.open_time, g.close_time, a.shift_name, a.id AS assignment_id
             FROM sms_gate_assignments a
             INNER JOIN sms_gates g ON a.gate_id = g.id
             WHERE a.society_id = :sid AND a.guard_id = :gid AND a.is_active = 1 AND g.is_active = 1
             ORDER BY (g.gate_type = 'Main') DESC, g.id ASC"
        );
        $stmt->execute([':sid' => $societyId, ':gid' => $guardId]);
        $gates = $stmt->fetchAll();

        // Ensure every society has at least one Main Gate fallback if none configured yet
        if (empty($gates)) {
            $chkAny = $conn->prepare("SELECT COUNT(*) FROM sms_gate_assignments WHERE society_id = :sid AND guard_id = :gid");
            $chkAny->execute([':sid' => $societyId, ':gid' => $guardId]);
            if ((int)$chkAny->fetchColumn() === 0) {
                $gStmt = $conn->prepare("SELECT id, gate_name, gate_type, open_time, close_time FROM sms_gates WHERE society_id = :sid AND is_active = 1 ORDER BY id ASC LIMIT 1");
                $gStmt->execute([':sid' => $societyId]);
                $defaultGate = $gStmt->fetch();
                if ($defaultGate) {
                    $conn->prepare(
                        "INSERT IGNORE INTO sms_gate_assignments (society_id, gate_id, guard_id, shift_name, is_active)
                         VALUES (:sid, :gate_id, :gid, 'Morning', 1)"
                    )->execute([':sid' => $societyId, ':gate_id' => (int)$defaultGate['id'], ':gid' => $guardId]);
                    $defaultGate['shift_name'] = 'Morning';
                    return [$defaultGate];
                }
            }
        }

        return $gates;
    } catch (Throwable $e) {
        error_log('getGuardAuthorizedGates notice: ' . $e->getMessage());
        return [];
    }
}

/**
 * Enforces that a guard is explicitly assigned and authorized to operate the requested gate_id.
 * Prevents guards from tampering or accessing unauthorized gates across the same or other societies.
 */
function assertGuardAuthorizedForGate(PDO $conn, int $societyId, int $guardId, int $requestedGateId): array {
    if ($requestedGateId <= 0) {
        $gates = getGuardAuthorizedGates($conn, $societyId, $guardId);
        if (empty($gates)) {
            jsonError('No active gates assigned to your account. Contact Security Manager.', 403);
        }
        return $gates[0];
    }

    $stmt = $conn->prepare(
        "SELECT g.id, g.gate_name, g.gate_type, g.open_time, g.close_time, a.shift_name
         FROM sms_gate_assignments a
         INNER JOIN sms_gates g ON a.gate_id = g.id
         WHERE a.society_id = :sid AND a.guard_id = :gid AND a.gate_id = :gate_id AND a.is_active = 1 AND g.is_active = 1
         LIMIT 1"
    );
    $stmt->execute([':sid' => $societyId, ':gid' => $guardId, ':gate_id' => $requestedGateId]);
    $gate = $stmt->fetch();

    if (!$gate) {
        jsonError('Access denied: you are not assigned or authorized for the selected gate.', 403);
    }

    return $gate;
}
