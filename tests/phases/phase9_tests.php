<?php
// tests/phases/phase9_tests.php
// Phase 9: Multi-Society SaaS Platform, Subscriptions, Feature Guard & Onboarding Tests

echo "\n--- Phase 9: Multi-Society SaaS & Subscriptions Tests ---\n";

require_once __DIR__ . '/../../includes/saas_guard.php';

foreach (['sms_subscription_plans', 'sms_society_subscriptions', 'sms_saas_invoices', 'sms_platform_tickets'] as $tbl) {
    assertTrue((bool)$conn->query("SHOW TABLES LIKE " . $conn->quote($tbl))->fetchColumn(), "Table {$tbl} exists");
}
assertTrue((bool)$conn->query("SHOW COLUMNS FROM sms_users LIKE 'is_platform_admin'")->fetch(), "sms_users has is_platform_admin flag");

$planCnt = (int)$conn->query("SELECT COUNT(*) FROM sms_subscription_plans WHERE is_active = 1")->fetchColumn();
assertTrue($planCnt >= 5, "Seeded all 5 SaaS tiers (Free..Enterprise, count: {$planCnt})");

// Verify primary society active features & writable state
assertTrue(hasSocietyFeature($conn, 1, 'document_vault') === true, "Primary flagship society enjoys document_vault capability");
assertTrue(getSocietySubscriptionContext($conn, 1)['is_writable'] === true, "Primary society is writable");

// Non-destructive expiry preservation & read-only enforcement check in transaction
$conn->beginTransaction();
try {
    $conn->exec("INSERT INTO sms_societies (society_name, society_code, address, city, state, zip_code, status) VALUES ('Expired Test Society', 'EXP999', 'Addr', 'Pune', 'MH', '411001', 'Grace_Period')");
    $tmpSid = (int)$conn->lastInsertId();
    $conn->prepare("INSERT INTO sms_society_subscriptions (society_id, plan_id, billing_cycle, status, starts_at, expires_at, grace_until) VALUES (:sid, 1, 'Monthly', 'Expired', DATE_SUB(NOW(), INTERVAL 40 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY))")
        ->execute([':sid' => $tmpSid]);

    $ctx = getSocietySubscriptionContext($conn, $tmpSid);
    assertTrue($ctx['status'] === 'Expired' && $ctx['is_writable'] === false, "Expired society enters non-writable read-only mode cleanly");
    $quotaCheck = canSocietyAddEntity($conn, $tmpSid, 'flats');
    assertTrue($quotaCheck['allowed'] === false, "Expired society blocks adding new entities while preserving existing data");
    $conn->rollBack();
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    assertTrue(false, "Phase 9 expiry test error: " . $e->getMessage());
}

// Verify platform super admin boundary
$platApiSrc = file_get_contents(__DIR__ . '/../../api/platform_admin.php');
assertTrue(strpos($platApiSrc, 'isPlatformSuperAdmin()') !== false, "Platform Admin API strictly enforces isPlatformSuperAdmin()");

$rt = file_get_contents(__DIR__ . '/../../routes.php');
assertTrue(strpos($rt, "'/platform/dashboard'") !== false && strpos($rt, "'/register-society'") !== false, "SaaS platform and society onboarding routes registered");
assertTrue(file_exists(__DIR__ . '/../../modules/platform/dashboard.php'), "modules/platform/dashboard.php exists");
assertTrue(file_exists(__DIR__ . '/../../modules/auth/register_society.php'), "modules/auth/register_society.php exists");
