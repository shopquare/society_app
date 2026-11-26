<?php
// tests/phases/phase10_security_perf_tests.php
// Phase 10: Final Security Audit, Performance Indexes & Release Readiness Assertions

echo "\n--- Phase 10: Security Hardening, Performance & Release Audit ---\n";

// 1. Verify all 20 required v2 documentation deliverables exist & are non-empty
$requiredDocs = [
    'GATE_HANDOVER.md', 'MULTIPLE_GATE_MANAGEMENT.md', 'POLLS_AND_VOTING.md', 'EVENT_MANAGEMENT.md',
    'LOST_AND_FOUND.md', 'ANNOUNCEMENTS.md', 'SUGGESTIONS.md', 'SOCIETY_DISCUSSIONS.md',
    'RESIDENT_DIRECTORY.md', 'FACILITY_BOOKING.md', 'FINANCIAL_MANAGEMENT.md', 'VENDOR_MANAGEMENT.md',
    'SERVICE_DIRECTORY.md', 'ADMIN_PERMISSIONS.md', 'NOTIFICATION_SYSTEM.md', 'DOCUMENT_MANAGEMENT.md',
    'SAAS_SUBSCRIPTIONS.md', 'DATABASE.md', 'API_REFERENCE.md', 'TESTING.md'
];
foreach ($requiredDocs as $docFile) {
    $full = __DIR__ . '/../../docs/' . $docFile;
    assertTrue(is_file($full) && filesize($full) > 100, "Documentation file docs/{$docFile} exists cleanly");
}

// 2. Security Static Scan across all Phase 0-9 API endpoints
$v2Apis = [
    'admin_manage_roles.php', 'admin_manage_buildings.php', 'notifications.php',
    'admin_manage_gates.php', 'guard_handover.php', 'guard_incidents.php',
    'community_polls.php', 'community_events.php', 'community_lost_found.php',
    'community_suggestions.php', 'community_discussions.php', 'resident_directory.php',
    'admin_manage_facilities.php', 'resident_facility_booking.php',
    'admin_financial_v2.php', 'admin_accounting.php', 'resident_payments.php',
    'services_directory.php', 'admin_manage_services.php',
    'admin_moderation_center.php', 'admin_manage_staff.php', 'admin_reports.php',
    'admin_broadcast.php', 'documents_office.php', 'stream_document.php',
    'register_society.php', 'platform_admin.php'
];
foreach ($v2Apis as $apiName) {
    $src = file_get_contents(__DIR__ . '/../../api/' . $apiName);
    assertTrue(strpos($src, 'initApi(') !== false, "API {$apiName} initializes via api_bootstrap.php");
    $isMutating = strpos($src, "'POST'") !== false;
    if ($isMutating) {
        assertTrue(strpos($src, 'requireValidCsrfToken') !== false, "Mutating API {$apiName} validates CSRF token");
    }
    $hasDirectRawSql = preg_match('/\$conn->query\([^;]*\$_(GET|POST|REQUEST)/', $src) === 1;
    assertTrue(!$hasDirectRawSql, "API {$apiName} has zero raw \$_GET/\$_POST SQL interpolation");
}

// 3. Verify critical performance indexes exist on high-traffic v2 tables
$indexedTables = [
    'sms_notifications',
    'sms_gate_handovers',
    'sms_facility_bookings',
    'sms_invoices',
    'sms_service_providers',
    'sms_documents',
];
foreach ($indexedTables as $tbl) {
    $indexes = $conn->query("SHOW INDEX FROM `{$tbl}`")->fetchAll();
    assertTrue(count($indexes) >= 2, "High-traffic table {$tbl} has secondary performance index(es)");
}

// 4. Verify Android build version bumped to 2.0.0 on v_two
$gradle = file_get_contents(__DIR__ . '/../../android/app/build.gradle.kts');
assertTrue(strpos($gradle, 'versionCode = 20') !== false && strpos($gradle, 'versionName = "2.0.0"') !== false, "Android Gradle configured for SecureGate v2.0.0 (versionCode 20)");
