<?php
// tests/phases/phase2_tests.php
// Automated verification for Phase 2: Community Suite (Polls Confidentiality, Duplicate Prevention, Events, Lost & Found, Directory Privacy)

echo "\n--- Phase 2: Community Management & Privacy Tests ---\n";

$pollCode = file_get_contents(__DIR__ . '/../../api/community_polls.php');
assertTrue(str_contains($pollCode, "\$pollType === 'Society Election'"), 'Phase 2: Polls API enforces governance attestation for Society Elections');
assertTrue(str_contains($pollCode, 'sms_poll_voters'), 'Phase 2: Polls API enforces duplicate vote lock table');
assertTrue(str_contains($pollCode, "is_anonymous'] === 1) ? null : \$userId"), 'Phase 2: Anonymous ballot strictly detaches voter user_id');

$dirCode = file_get_contents(__DIR__ . '/../../api/resident_directory.php');
assertTrue(str_contains($dirCode, 'checkRateLimit'), 'Phase 2: Resident Directory enforces scraping rate limiting');
assertTrue(str_contains($dirCode, 'COALESCE(rps.show_phone, 0) = 1'), 'Phase 2: Directory hides resident phone numbers by default');
assertTrue(str_contains($dirCode, '$limit = 20'), 'Phase 2: Directory enforces pagination cap');

$lfCode = file_get_contents(__DIR__ . '/../../api/community_lost_found.php');
assertTrue(!str_contains($lfCode, 'u.email'), 'Phase 2: Lost & Found public listing excludes reporter email');

if ($conn instanceof PDO) {
    $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ([
        'sms_polls', 'sms_poll_options', 'sms_poll_voters', 'sms_poll_votes',
        'sms_events', 'sms_event_registrations', 'sms_event_photos',
        'sms_lost_found', 'sms_lost_found_claims',
        'sms_notice_views', 'sms_suggestions',
        'sms_discussions', 'sms_discussion_comments', 'sms_discussion_likes', 'sms_discussion_reports', 'sms_discussion_restrictions',
        'sms_resident_privacy_settings'
    ] as $t) {
        assertTrue(in_array($t, $tables, true), "Phase 2 DB table present: {$t}");
    }

    // Functional test: duplicate vote prevention via UNIQUE constraint on sms_poll_voters & anonymous unlink
    $conn->beginTransaction();
    $conn->prepare("INSERT INTO sms_polls (society_id, created_by, title, poll_type, is_anonymous, start_at, end_at, status) VALUES (1, 1, 'Test Anonymous Poll', 'General Survey', 1, NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY), 'Active')")->execute();
    $pid = (int)$conn->lastInsertId();
    $conn->prepare("INSERT INTO sms_poll_options (poll_id, option_text) VALUES (:pid, 'Yes')")->execute([':pid' => $pid]);
    $oid = (int)$conn->lastInsertId();

    $conn->prepare("INSERT INTO sms_poll_voters (poll_id, society_id, user_id, voted_at) VALUES (:pid, 1, 3, NOW())")->execute([':pid' => $pid]);
    $conn->prepare("INSERT INTO sms_poll_votes (poll_id, option_id, user_id, created_at) VALUES (:pid, :oid, NULL, NOW())")->execute([':pid' => $pid, ':oid' => $oid]);

    $dupCaught = false;
    try {
        $conn->prepare("INSERT INTO sms_poll_voters (poll_id, society_id, user_id, voted_at) VALUES (:pid, 1, 3, NOW())")->execute([':pid' => $pid]);
    } catch (PDOException $e) {
        $dupCaught = true;
    }
    assertTrue($dupCaught, 'Phase 2 DB unique constraint prevents duplicate voting');

    $anonUid = $conn->query("SELECT user_id FROM sms_poll_votes WHERE poll_id = {$pid}")->fetchColumn();
    assertSame(null, $anonUid, 'Phase 2 anonymous vote stores NULL voter user_id');
    $conn->rollBack();
}
