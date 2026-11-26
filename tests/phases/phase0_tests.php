<?php
// tests/phases/phase0_tests.php
// Automated verification for Phase 0: RBAC Permissions, Notification Infrastructure & Topology

echo "\n--- Phase 0: Foundation, Permissions & Notification Tests ---\n";

// 1. Permission helper & anti-escalation invariants
require_once __DIR__ . '/../../includes/permissions.php';

$_SESSION['logged_in'] = true;
$_SESSION['user_id'] = 999;
$_SESSION['society_id'] = 1;
$_SESSION['role_id'] = 2;
$_SESSION['permissions'] = ['finance.view', 'finance.manage', 'roles.manage'];
$_SESSION['is_platform_admin'] = false;

assertTrue(hasPermission('finance.view'), 'Phase 0: Committee user holds assigned finance.view permission');
assertTrue(!hasPermission('saas.platform'), 'Phase 0: Society committee user denied saas.platform');
assertTrue(!hasPermission('gates.manage'), 'Phase 0: Treasurer-scoped user denied gates.manage');
assertTrue(!canActorGrantPermissions(['finance.view', 'gates.manage']), 'Phase 0: Anti-escalation blocks granting unheld permission');
assertTrue(!canActorGrantPermissions(['saas.platform']), 'Phase 0: Anti-escalation blocks granting platform permission');
assertTrue(canActorGrantPermissions(['finance.view', 'finance.manage']), 'Phase 0: Actor can delegate subset of held permissions');

// 2. Notification infrastructure & safety invariants
require_once __DIR__ . '/../../includes/notification_service.php';
assertTrue(in_array('emergency_sos', CRITICAL_SECURITY_CATEGORIES, true), 'Phase 0: emergency_sos is marked non-suppressible');
assertTrue(in_array('gate_handover', CRITICAL_SECURITY_CATEGORIES, true), 'Phase 0: gate_handover is marked non-suppressible');

if ($conn instanceof PDO) {
    // Check DB schema readiness in securegate_v2
    $p0Tables = ['sms_permissions', 'sms_admin_roles', 'sms_role_permissions', 'sms_user_admin_roles', 'sms_notifications', 'sms_notification_preferences', 'sms_notification_deliveries', 'sms_buildings', 'sms_units'];
    $existingTables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($p0Tables as $t) {
        assertTrue(in_array($t, $existingTables, true), "Phase 0 DB table present: {$t}");
    }

    $permCount = (int)$conn->query("SELECT COUNT(*) FROM sms_permissions")->fetchColumn();
    assertTrue($permCount >= 25, "Phase 0 seeded permission catalog (count: {$permCount})");

    $bCount = (int)$conn->query("SELECT COUNT(*) FROM sms_buildings WHERE society_id = 1")->fetchColumn();
    assertTrue($bCount >= 3, "Phase 0 seeded society buildings (count: {$bCount})");

    // Live dispatch + idempotency + SOS opt-out bypass check against securegate_v2
    $idemKey = 'test_sos_idem_' . bin2hex(random_bytes(4));
    // First simulate user 3 opting out of everything
    $conn->prepare("INSERT INTO sms_notification_preferences (user_id, category, in_app_enabled, push_enabled, email_enabled, whatsapp_enabled, sms_enabled)
                    VALUES (3, 'emergency_sos', 0, 0, 0, 0, 0)
                    ON DUPLICATE KEY UPDATE in_app_enabled=0, push_enabled=0")->execute();

    $res1 = dispatchNotification($conn, [
        'society_id' => 1,
        'user_ids' => [3],
        'category' => 'emergency_sos',
        'priority' => 'Critical',
        'title' => 'Test Critical SOS Alert',
        'message' => 'Automated safety bypass test notification.',
        'channels' => ['in_app'],
        'idempotency_key' => $idemKey
    ]);
    assertSame(1, $res1['dispatched'], 'Phase 0: Critical SOS notification delivered despite user opt-out');

    $res2 = dispatchNotification($conn, [
        'society_id' => 1,
        'user_ids' => [3],
        'category' => 'emergency_sos',
        'priority' => 'Critical',
        'title' => 'Duplicate SOS Alert',
        'message' => 'Should be deduplicated.',
        'channels' => ['in_app'],
        'idempotency_key' => $idemKey
    ]);
    assertSame(0, $res2['dispatched'], 'Phase 0: Idempotency key prevents duplicate notification dispatch');
    assertSame(1, $res2['skipped'], 'Phase 0: Duplicate notification recorded as skipped');

    // Clean up test row cleanly
    $conn->prepare("DELETE FROM sms_notifications WHERE idempotency_key = :k")->execute([':k' => $idemKey]);
    $conn->prepare("DELETE FROM sms_notification_preferences WHERE user_id = 3 AND category = 'emergency_sos'")->execute();
}

// 3. Static security checks on Phase 0 files
$rolesApi = file_get_contents(__DIR__ . '/../../api/admin_manage_roles.php');
assertTrue(str_contains($rolesApi, "requireApiPermission('roles.manage')"), 'Phase 0: Roles API enforces granular roles.manage permission');
assertTrue(str_contains($rolesApi, 'canActorGrantPermissions'), 'Phase 0: Roles API checks privilege escalation');
$bldgApi = file_get_contents(__DIR__ . '/../../api/admin_manage_buildings.php');
assertTrue(str_contains($bldgApi, "requireApiPermission('buildings.manage')"), 'Phase 0: Buildings API enforces granular buildings.manage permission');
