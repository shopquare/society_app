<?php
// tests/phases/phase6_tests.php
// Phase 6: Moderation Center, Tenant Lifecycle KYC, Staff Attendance & Automated Reports

echo "\n--- Phase 6: Moderation, Staff & Compliance Reports Tests ---\n";

if ($conn instanceof PDO) {
    assertTrue((bool)$conn->query("SHOW TABLES LIKE 'sms_staff_attendance'")->fetchColumn(), "Table sms_staff_attendance exists");
    $uuCols = $conn->query("SHOW COLUMNS FROM sms_user_units")->fetchAll(PDO::FETCH_COLUMN);
    foreach (['verification_status', 'lease_end_date', 'kyc_notes'] as $col) {
        assertTrue(in_array($col, $uuCols, true), "sms_user_units has Phase 6 KYC column {$col}");
    }
}

$rptSrc = file_get_contents(__DIR__ . '/../../api/admin_reports.php');
assertTrue(strpos($rptSrc, "requireApiPermission('reports.view')") !== false, "Reports API enforces reports.view permission");
assertTrue(strpos($rptSrc, 'rptCsvSafe') !== false, "Reports API sanitizes CSV formula characters");
assertTrue(strpos($rptSrc, 'generateSimplePdf') !== false, "Reports API generates PDF reports via pure-PHP generator");

$modSrc = file_get_contents(__DIR__ . '/../../api/admin_moderation_center.php');
assertTrue(strpos($modSrc, "requireApiPermission('community.moderate')") !== false, "Moderation API enforces community.moderate");
assertTrue(strpos($modSrc, "requireApiPermission('residents.manage')") !== false, "Tenancy KYC update requires residents.manage");

$routeSrc = file_get_contents(__DIR__ . '/../../routes.php');
foreach (['/admin/moderation', '/admin/staff', '/admin/reports'] as $rt) {
    assertTrue(strpos($routeSrc, $rt) !== false, "Route {$rt} registered");
}
assertTrue(file_exists(__DIR__ . '/../../modules/admin/moderation.php'), "modules/admin/moderation.php exists");
assertTrue(file_exists(__DIR__ . '/../../modules/admin/staff.php'), "modules/admin/staff.php exists");
assertTrue(file_exists(__DIR__ . '/../../modules/admin/reports.php'), "modules/admin/reports.php exists");
