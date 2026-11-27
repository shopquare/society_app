<?php
// tests/phases/phase5_tests.php
// Phase 5: Local Services & Verified Business Directory Tests

echo "\n--- Phase 5: Local Services Directory Tests ---\n";

if ($conn instanceof PDO) {
    $p5Tables = [
        'sms_service_categories',
        'sms_service_providers',
        'sms_service_reviews',
        'sms_service_favorites',
        'sms_service_reports',
    ];
    foreach ($p5Tables as $tbl) {
        assertTrue((bool)$conn->query("SHOW TABLES LIKE " . $conn->quote($tbl))->fetchColumn(), "Table {$tbl} exists");
    }

    $catCount = (int)$conn->query("SELECT COUNT(*) FROM sms_service_categories WHERE is_active = 1")->fetchColumn();
    assertTrue($catCount >= 17, "Seeded all 17 service directory categories (count: {$catCount})");

    $provCount = (int)$conn->query("SELECT COUNT(*) FROM sms_service_providers WHERE approval_status = 'Approved' AND is_verified = 1")->fetchColumn();
    assertTrue($provCount >= 5, "Seeded verified service providers (count: {$provCount})");
}

// Anti-abuse & resident moderation checks
$svcApiSrc = file_get_contents(__DIR__ . '/../../api/services_directory.php');
assertTrue(strpos($svcApiSrc, "isManager ? 'Approved' : 'Pending'") !== false, "Resident service suggestions default to Pending moderation queue");
assertTrue(strpos($svcApiSrc, 'You have already reviewed this service provider') !== false, "Duplicate resident review is strictly prevented");

// DB UNIQUE constraint check on reviews inside rollback transaction
if ($conn instanceof PDO) {
    $socId = (int)($conn->query("SELECT id FROM sms_societies ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 1);
    $resId = (int)($conn->query("SELECT id FROM sms_users WHERE society_id = {$socId} AND role_id = 3 LIMIT 1")->fetchColumn() ?: 0);
    $provId = (int)($conn->query("SELECT id FROM sms_service_providers WHERE society_id = {$socId} LIMIT 1")->fetchColumn() ?: 0);

    if ($resId > 0 && $provId > 0) {
        $conn->beginTransaction();
        try {
            $conn->prepare("DELETE FROM sms_service_reviews WHERE provider_id = :pid AND resident_id = :rid")->execute([':pid' => $provId, ':rid' => $resId]);
            $conn->prepare("INSERT INTO sms_service_reviews (provider_id, society_id, resident_id, rating, review_text) VALUES (:pid, :sid, :rid, 5, 'Great fast plumbing')")
                ->execute([':pid' => $provId, ':sid' => $socId, ':rid' => $resId]);
            $dupBlocked = false;
            try {
                $conn->prepare("INSERT INTO sms_service_reviews (provider_id, society_id, resident_id, rating, review_text) VALUES (:pid, :sid, :rid, 4, 'Second review')")
                    ->execute([':pid' => $provId, ':sid' => $socId, ':rid' => $resId]);
            } catch (PDOException $ex) {
                $dupBlocked = true;
            }
            assertTrue($dupBlocked, "UNIQUE (provider_id, resident_id) DB constraint blocks duplicate review");
            $conn->rollBack();
        } catch (Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            assertTrue(false, "Phase 5 review DB constraint test failed: " . $e->getMessage());
        }
    }
}

assertTrue(file_exists(__DIR__ . '/../../modules/admin/services.php'), "modules/admin/services.php exists");
assertTrue(file_exists(__DIR__ . '/../../modules/resident/services.php'), "modules/resident/services.php exists");
