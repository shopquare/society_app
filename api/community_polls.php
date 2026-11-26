<?php
// api/community_polls.php
// Polls & Election Voting System with Confidentiality & Duplicate Prevention

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/audit_logger.php';
require_once __DIR__ . '/../includes/notification_service.php';

initApi(['GET', 'POST']);
requireApiAuth([1, 2, 3]);

$societyId = (int)$_SESSION['society_id'];
$userId = (int)$_SESSION['user_id'];
$isAdmin = hasPermission('polls.manage');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $export = trim((string)($_GET['export'] ?? ''));
    $pollId = (int)($_GET['poll_id'] ?? 0);

    try {
        // Admin CSV Results Export
        if ($export === 'csv' && $pollId > 0) {
            requireApiPermission('polls.manage');
            $pChk = $conn->prepare("SELECT title, poll_type, is_anonymous FROM sms_polls WHERE id = :id AND society_id = :sid");
            $pChk->execute([':id' => $pollId, ':sid' => $societyId]);
            $poll = $pChk->fetch();
            if (!$poll) jsonError('Poll not found.', 404);

            $oStmt = $conn->prepare(
                "SELECT o.option_text, COUNT(v.id) AS votes
                 FROM sms_poll_options o
                 LEFT JOIN sms_poll_votes v ON o.id = v.option_id AND v.poll_id = o.poll_id
                 WHERE o.poll_id = :pid
                 GROUP BY o.id ORDER BY o.sort_order ASC, o.id ASC"
            );
            $oStmt->execute([':pid' => $pollId]);

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="poll_results_' . $pollId . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Poll Title', 'Type', 'Anonymous', 'Option', 'Votes']);
            foreach ($oStmt->fetchAll() as $row) {
                fputcsv($out, [$poll['title'], $poll['poll_type'], $poll['is_anonymous'] ? 'Yes' : 'No', $row['option_text'], $row['votes']]);
            }
            fclose($out);
            exit;
        }

        // Resident occupancy type for eligibility check
        $occStmt = $conn->prepare("SELECT occupancy_type FROM sms_user_units WHERE user_id = :uid AND society_id = :sid AND is_current_occupant = 1 LIMIT 1");
        $occStmt->execute([':uid' => $userId, ':sid' => $societyId]);
        $occupancy = (string)($occStmt->fetchColumn() ?: 'owner');

        $pStmt = $conn->prepare(
            "SELECT p.*,
                    EXISTS(SELECT 1 FROM sms_poll_voters pv WHERE pv.poll_id = p.id AND pv.user_id = :uid) AS has_voted,
                    (SELECT COUNT(*) FROM sms_poll_voters pv2 WHERE pv2.poll_id = p.id) AS total_votes
             FROM sms_polls p
             WHERE p.society_id = :sid
             ORDER BY (p.status = 'Active') DESC, p.end_at DESC"
        );
        $pStmt->execute([':uid' => $userId, ':sid' => $societyId]);
        $polls = $pStmt->fetchAll();

        $optStmt = $conn->prepare(
            "SELECT o.id, o.option_text, COUNT(v.id) AS vote_count
             FROM sms_poll_options o
             LEFT JOIN sms_poll_votes v ON o.id = v.option_id AND v.poll_id = o.poll_id
             WHERE o.poll_id = :pid
             GROUP BY o.id ORDER BY o.sort_order ASC, o.id ASC"
        );

        $now = date('Y-m-d H:i:s');
        foreach ($polls as &$poll) {
            if ($poll['status'] === 'Active' && $poll['end_at'] < $now) {
                $conn->prepare("UPDATE sms_polls SET status = 'Closed' WHERE id = :id")->execute([':id' => $poll['id']]);
                $poll['status'] = 'Closed';
            }

            $optStmt->execute([':pid' => (int)$poll['id']]);
            $options = $optStmt->fetchAll();

            // Strict result visibility control for elections & confidential polls
            $canSeeResults = $isAdmin
                || $poll['results_visibility'] === 'Immediate'
                || ($poll['results_visibility'] === 'After_Close' && $poll['status'] === 'Closed');

            $poll['can_view_results'] = $canSeeResults;
            $poll['is_eligible'] = ($poll['eligible_voters'] === 'all')
                || ($poll['eligible_voters'] === 'owners' && $occupancy === 'owner')
                || ($poll['eligible_voters'] === 'tenants' && $occupancy === 'tenant');

            if (!$canSeeResults) {
                foreach ($options as &$o) {
                    $o['vote_count'] = null;
                }
                unset($o);
            }
            $poll['options'] = $options;
        }
        unset($poll);

        jsonSuccess(['polls' => $polls]);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to load polls.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonInput();
    requireValidCsrfToken($data['csrf_token'] ?? '');
    $action = trim((string)($data['action'] ?? ''));

    try {
        if ($action === 'create_poll') {
            requireApiPermission('polls.manage');
            $title = trim((string)($data['title'] ?? ''));
            $description = trim((string)($data['description'] ?? ''));
            $pollType = trim((string)($data['poll_type'] ?? 'General Survey'));
            $eligible = trim((string)($data['eligible_voters'] ?? 'all'));
            $isAnon = !empty($data['is_anonymous']) ? 1 : 0;
            $visibility = trim((string)($data['results_visibility'] ?? 'Immediate'));
            $certified = !empty($data['is_governance_certified']) ? 1 : 0;
            $startAt = trim((string)($data['start_at'] ?? date('Y-m-d H:i:s')));
            $endAt = trim((string)($data['end_at'] ?? date('Y-m-d H:i:s', strtotime('+7 days'))));
            $options = array_values(array_filter(array_map('trim', (array)($data['options'] ?? [])), fn($v) => $v !== ''));

            $allowedTypes = ['General Survey', 'Society Improvement', 'Facility Preference', 'Festival Planning', 'Society Election', 'Committee Decision'];
            if ($title === '' || count($options) < 2 || !in_array($pollType, $allowedTypes, true) || !in_array($eligible, ['all', 'owners', 'tenants'], true)) {
                jsonError('Poll title, valid type, and at least 2 options are required.', 400);
            }
            if ($pollType === 'Society Election' && $certified !== 1) {
                jsonError('Formal Society Elections require governance compliance attestation confirmation.', 400);
            }
            if (!in_array($visibility, ['Immediate', 'After_Close', 'Admin_Only'], true)) {
                $visibility = 'Immediate';
            }

            $conn->beginTransaction();
            $ins = $conn->prepare(
                "INSERT INTO sms_polls (society_id, created_by, title, description, poll_type, eligible_voters, is_anonymous, results_visibility, is_governance_certified, start_at, end_at, status)
                 VALUES (:sid, :by, :title, :desc, :ptype, :elig, :anon, :vis, :cert, :sat, :eat, 'Active')"
            );
            $ins->execute([
                ':sid' => $societyId, ':by' => $userId, ':title' => $title, ':desc' => $description ?: null,
                ':ptype' => $pollType, ':elig' => $eligible, ':anon' => $isAnon, ':vis' => $visibility,
                ':cert' => $certified, ':sat' => str_replace('T', ' ', $startAt), ':eat' => str_replace('T', ' ', $endAt)
            ]);
            $pollId = (int)$conn->lastInsertId();

            $oIns = $conn->prepare("INSERT INTO sms_poll_options (poll_id, option_text, sort_order) VALUES (:pid, :txt, :ord)");
            foreach ($options as $idx => $optText) {
                $oIns->execute([':pid' => $pollId, ':txt' => $optText, ':ord' => $idx]);
            }
            $conn->commit();

            logAudit($conn, $societyId, $userId, 'CREATE_POLL', 'poll', $pollId, ['title' => $title, 'type' => $pollType, 'anonymous' => $isAnon]);
            dispatchNotification($conn, [
                'society_id' => $societyId, 'role_ids' => [3], 'category' => 'poll_reminder', 'priority' => 'Normal',
                'title' => "New {$pollType}: {$title}", 'message' => 'Cast your vote before ' . date('d M Y', strtotime($endAt)) . '.',
                'action_url' => '/resident/community?tab=polls', 'reference_type' => 'poll', 'reference_id' => $pollId, 'channels' => ['in_app']
            ]);

            jsonSuccess(['message' => 'Poll published successfully.', 'poll_id' => $pollId], 201);
        } elseif ($action === 'close_poll') {
            requireApiPermission('polls.manage');
            $pollId = (int)($data['poll_id'] ?? 0);
            $up = $conn->prepare("UPDATE sms_polls SET status = 'Closed', end_at = LEAST(end_at, NOW()) WHERE id = :id AND society_id = :sid");
            $up->execute([':id' => $pollId, ':sid' => $societyId]);
            logAudit($conn, $societyId, $userId, 'CLOSE_POLL', 'poll', $pollId, []);
            jsonSuccess(['message' => 'Poll closed and results finalized.']);
        } elseif ($action === 'send_reminder') {
            requireApiPermission('polls.manage');
            $pollId = (int)($data['poll_id'] ?? 0);
            $pRow = $conn->prepare("SELECT title FROM sms_polls WHERE id = :id AND society_id = :sid LIMIT 1");
            $pRow->execute([':id' => $pollId, ':sid' => $societyId]);
            $title = (string)$pRow->fetchColumn();
            if (!$title) jsonError('Poll not found.', 404);

            dispatchNotification($conn, [
                'society_id' => $societyId, 'role_ids' => [3], 'category' => 'poll_reminder', 'priority' => 'High',
                'title' => "Voting Reminder: {$title}", 'message' => 'Your society poll closes soon. Please cast your vote now.',
                'action_url' => '/resident/community?tab=polls', 'channels' => ['in_app', 'web_push']
            ]);
            jsonSuccess(['message' => 'Voting reminder sent to eligible residents.']);
        } elseif ($action === 'cast_vote') {
            $pollId = (int)($data['poll_id'] ?? 0);
            $optionId = (int)($data['option_id'] ?? 0);
            if ($pollId <= 0 || $optionId <= 0) {
                jsonError('Poll and voting option are required.', 400);
            }

            $conn->beginTransaction();
            $pStmt = $conn->prepare("SELECT * FROM sms_polls WHERE id = :id AND society_id = :sid FOR UPDATE");
            $pStmt->execute([':id' => $pollId, ':sid' => $societyId]);
            $poll = $pStmt->fetch();
            $now = date('Y-m-d H:i:s');

            if (!$poll || $poll['status'] !== 'Active' || $now < $poll['start_at'] || $now > $poll['end_at']) {
                $conn->rollBack();
                jsonError('This poll is currently closed or outside its voting window.', 400);
            }

            // Eligibility check
            if ($poll['eligible_voters'] !== 'all') {
                $occStmt = $conn->prepare("SELECT occupancy_type FROM sms_user_units WHERE user_id = :uid AND society_id = :sid AND is_current_occupant = 1 LIMIT 1");
                $occStmt->execute([':uid' => $userId, ':sid' => $societyId]);
                $occ = (string)($occStmt->fetchColumn() ?: '');
                if (($poll['eligible_voters'] === 'owners' && $occ !== 'owner') || ($poll['eligible_voters'] === 'tenants' && $occ !== 'tenant')) {
                    $conn->rollBack();
                    jsonError('You are not in the eligible voter group (' . $poll['eligible_voters'] . ') for this poll.', 403);
                }
            }

            // Validate option belongs to this poll
            $oChk = $conn->prepare("SELECT id FROM sms_poll_options WHERE id = :oid AND poll_id = :pid LIMIT 1");
            $oChk->execute([':oid' => $optionId, ':pid' => $pollId]);
            if (!$oChk->fetch()) {
                $conn->rollBack();
                jsonError('Invalid voting option.', 400);
            }

            // Prevent duplicate votes atomically
            $dup = $conn->prepare("SELECT id FROM sms_poll_voters WHERE poll_id = :pid AND user_id = :uid LIMIT 1 FOR UPDATE");
            $dup->execute([':pid' => $pollId, ':uid' => $userId]);
            if ($dup->fetch()) {
                $conn->rollBack();
                jsonError('Duplicate voting denied: you have already cast your vote in this poll.', 409);
            }

            $conn->prepare("INSERT INTO sms_poll_voters (poll_id, society_id, user_id, voted_at) VALUES (:pid, :sid, :uid, :at)")
                ->execute([':pid' => $pollId, ':sid' => $societyId, ':uid' => $userId, ':at' => $now]);

            // Confidentiality invariant: store user_id = NULL on anonymous ballots
            $storedVoterId = ((int)$poll['is_anonymous'] === 1) ? null : $userId;
            $conn->prepare("INSERT INTO sms_poll_votes (poll_id, option_id, user_id, created_at) VALUES (:pid, :oid, :uid, :at)")
                ->execute([':pid' => $pollId, ':oid' => $optionId, ':uid' => $storedVoterId, ':at' => $now]);

            $conn->commit();
            logAudit($conn, $societyId, $userId, 'CAST_VOTE', 'poll', $pollId, ['anonymous' => (int)$poll['is_anonymous']]);
            jsonSuccess(['message' => 'Your vote has been securely recorded.']);
        } else {
            jsonError('Unknown poll action.', 400);
        }
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        logAndFail($e, 'Unable to process poll operation.');
    }
}
