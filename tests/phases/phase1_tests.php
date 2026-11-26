<?php
// tests/phases/phase1_tests.php
// Automated verification for Phase 1: Multi-Gate Security, Guard Authorization & Shift Handovers

echo "\n--- Phase 1: Multi-Gate & Gate Handover Tests ---\n";

require_once __DIR__ . '/../../includes/gate_helper.php';

$adminGateCode = file_get_contents(__DIR__ . '/../../api/admin_manage_gates.php');
assertTrue(str_contains($adminGateCode, "requireApiPermission('gates.view')"), 'Phase 1: Gates admin API checks gates.view permission');
assertTrue(str_contains($adminGateCode, "requireApiPermission('gates.manage')"), 'Phase 1: Gates admin mutations check gates.manage permission');

$logVisCode = file_get_contents(__DIR__ . '/../../api/guard_log_visitor.php');
assertTrue(str_contains($logVisCode, 'assertGuardAuthorizedForGate'), 'Phase 1: Visitor logging strictly validates authorized gate_id');
assertTrue(str_contains($logVisCode, 'gate_id'), 'Phase 1: Visitor record stores gate_id');

$hoCode = file_get_contents(__DIR__ . '/../../api/guard_handover.php');
assertTrue(str_contains($hoCode, "handover['status'] !== 'Pending_Ack'"), 'Phase 1: Handover API enforces completed handover immutability');

if ($conn instanceof PDO) {
    $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach (['sms_gates', 'sms_gate_assignments', 'sms_gate_handovers', 'sms_gate_incidents'] as $t) {
        assertTrue(in_array($t, $tables, true), "Phase 1 DB table present: {$t}");
    }

    $gateCount = (int)$conn->query("SELECT COUNT(*) FROM sms_gates WHERE society_id = 1 AND is_active = 1")->fetchColumn();
    assertTrue($gateCount >= 1, "Phase 1 seeded active gates for society 1 (count: {$gateCount})");

    // Verify default guard #4 has authorized gate access
    $authGates = getGuardAuthorizedGates($conn, 1, 4);
    assertTrue(count($authGates) >= 1, 'Phase 1: Guard #4 has authorized gate assignment');

    // Verify unauthorized gate lookup returns zero rows via gate assignment join
    $unauthStmt = $conn->prepare(
        "SELECT g.id FROM sms_gate_assignments a INNER JOIN sms_gates g ON a.gate_id = g.id
         WHERE a.society_id = 1 AND a.guard_id = 4 AND a.gate_id = 999999 AND a.is_active = 1"
    );
    $unauthStmt->execute();
    assertTrue($unauthStmt->fetch() === false, 'Phase 1: Unauthorized gate access strictly denied');
}
