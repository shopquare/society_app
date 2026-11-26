<?php
// api/community_discussions.php
// Society Community Forum, Threaded Comments, Likes, Reporting & Moderation API

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/audit_logger.php';
require_once __DIR__ . '/../includes/notification_service.php';

initApi(['GET', 'POST']);
requireApiAuth([1, 2, 3]);

$societyId = (int)$_SESSION['society_id'];
$userId = (int)$_SESSION['user_id'];
$isAdmin = hasPermission('community.moderate');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $cat = trim((string)($_GET['category'] ?? ''));
    $search = trim((string)($_GET['q'] ?? ''));
    $discId = (int)($_GET['discussion_id'] ?? 0);

    try {
        if ($discId > 0) {
            $cStmt = $conn->prepare(
                "SELECT c.*, CONCAT(p.first_name, ' ', p.last_name) AS author_name, uu.unit_number
                 FROM sms_discussion_comments c
                 INNER JOIN sms_user_profiles p ON c.author_id = p.user_id
                 LEFT JOIN sms_user_units uu ON c.author_id = uu.user_id AND uu.is_current_occupant = 1
                 WHERE c.discussion_id = :did AND c.society_id = :sid AND c.status = 'Active'
                 ORDER BY c.created_at ASC"
            );
            $cStmt->execute([':did' => $discId, ':sid' => $societyId]);
            jsonSuccess(['comments' => $cStmt->fetchAll()]);
        }

        $sql = "SELECT d.*, CONCAT(p.first_name, ' ', p.last_name) AS author_name, uu.unit_number,
                       (SELECT COUNT(*) FROM sms_discussion_comments c WHERE c.discussion_id = d.id AND c.status = 'Active') AS comments_count,
                       EXISTS(SELECT 1 FROM sms_discussion_likes dl WHERE dl.discussion_id = d.id AND dl.user_id = :uid) AS liked_by_me
                FROM sms_discussions d
                INNER JOIN sms_user_profiles p ON d.author_id = p.user_id
                LEFT JOIN sms_user_units uu ON d.author_id = uu.user_id AND uu.is_current_occupant = 1
                WHERE d.society_id = :sid AND d.status = 'Active'";
        $params = [':uid' => $userId, ':sid' => $societyId];
        if ($cat !== '' && $cat !== 'all') {
            $sql .= " AND d.category = :cat";
            $params[':cat'] = $cat;
        }
        if ($search !== '') {
            $sql .= " AND (d.title LIKE :q1 OR d.content LIKE :q2)";
            $params[':q1'] = '%' . $search . '%';
            $params[':q2'] = '%' . $search . '%';
        }
        $sql .= " ORDER BY d.is_pinned DESC, d.created_at DESC LIMIT 50";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $posts = $stmt->fetchAll();

        $reports = [];
        if ($isAdmin) {
            $rStmt = $conn->prepare(
                "SELECT r.*, CONCAT(rp.first_name, ' ', rp.last_name) AS reporter_name
                 FROM sms_discussion_reports r
                 INNER JOIN sms_user_profiles rp ON r.reporter_id = rp.user_id
                 WHERE r.society_id = :sid ORDER BY (r.status = 'Pending') DESC, r.created_at DESC LIMIT 40"
            );
            $rStmt->execute([':sid' => $societyId]);
            $reports = $rStmt->fetchAll();
        }

        jsonSuccess(['posts' => $posts, 'reports' => $reports]);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to load community discussions.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonInput();
    requireValidCsrfToken($data['csrf_token'] ?? '');
    $action = trim((string)($data['action'] ?? ''));

    try {
        if (in_array($action, ['create_post', 'add_comment'], true)) {
            $banChk = $conn->prepare("SELECT reason, restricted_until FROM sms_discussion_restrictions WHERE society_id = :sid AND user_id = :uid AND (restricted_until IS NULL OR restricted_until > NOW()) LIMIT 1");
            $banChk->execute([':sid' => $societyId, ':uid' => $userId]);
            if ($banRow = $banChk->fetch()) {
                jsonError('Your forum posting privileges are restricted: ' . ($banRow['reason'] ?: 'Moderation policy'), 403);
            }
            checkRateLimit('forum_post_' . $userId, 12, 300);
        }

        if ($action === 'create_post') {
            $cat = trim((string)($data['category'] ?? 'General Discussion'));
            $title = trim((string)($data['title'] ?? ''));
            $content = trim((string)($data['content'] ?? ''));
            if ($title === '' || $content === '') jsonError('Title and discussion text are required.', 400);

            $conn->prepare(
                "INSERT INTO sms_discussions (society_id, author_id, category, title, content) VALUES (:sid, :aid, :cat, :title, :cont)"
            )->execute([':sid' => $societyId, ':aid' => $userId, ':cat' => $cat, ':title' => $title, ':cont' => $content]);
            jsonSuccess(['message' => 'Discussion topic posted!', 'id' => (int)$conn->lastInsertId()], 201);
        } elseif ($action === 'add_comment') {
            $discId = (int)($data['discussion_id'] ?? 0);
            $parentId = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
            $text = trim((string)($data['comment_text'] ?? ''));
            if ($discId <= 0 || $text === '') jsonError('Comment cannot be empty.', 400);

            $dChk = $conn->prepare("SELECT id, author_id, is_locked, title FROM sms_discussions WHERE id = :id AND society_id = :sid AND status = 'Active'");
            $dChk->execute([':id' => $discId, ':sid' => $societyId]);
            $disc = $dChk->fetch();
            if (!$disc) jsonError('Discussion not found.', 404);
            if ((int)$disc['is_locked'] === 1 && !$isAdmin) jsonError('This discussion is locked by moderators.', 403);

            $conn->prepare(
                "INSERT INTO sms_discussion_comments (discussion_id, society_id, author_id, parent_id, comment_text)
                 VALUES (:did, :sid, :aid, :pid, :txt)"
            )->execute([':did' => $discId, ':sid' => $societyId, ':aid' => $userId, ':pid' => $parentId, ':txt' => $text]);

            if ((int)$disc['author_id'] !== $userId) {
                dispatchNotification($conn, [
                    'society_id' => $societyId, 'user_ids' => [(int)$disc['author_id']], 'category' => 'general', 'priority' => 'Normal',
                    'title' => "New Reply on: {$disc['title']}", 'message' => mb_substr($text, 0, 140),
                    'action_url' => '/resident/community?tab=discussions', 'channels' => ['in_app']
                ]);
            }
            jsonSuccess(['message' => 'Reply added.'], 201);
        } elseif ($action === 'toggle_like') {
            $discId = (int)($data['discussion_id'] ?? 0);
            $dChk = $conn->prepare("SELECT id FROM sms_discussions WHERE id = :id AND society_id = :sid AND status = 'Active'");
            $dChk->execute([':id' => $discId, ':sid' => $societyId]);
            if (!$dChk->fetch()) jsonError('Discussion not found.', 404);

            $chk = $conn->prepare("SELECT id FROM sms_discussion_likes WHERE discussion_id = :did AND user_id = :uid");
            $chk->execute([':did' => $discId, ':uid' => $userId]);
            if ($chk->fetch()) {
                $conn->prepare("DELETE FROM sms_discussion_likes WHERE discussion_id = :did AND user_id = :uid")->execute([':did' => $discId, ':uid' => $userId]);
                $conn->prepare("UPDATE sms_discussions SET likes_count = GREATEST(0, likes_count - 1) WHERE id = :id")->execute([':id' => $discId]);
                jsonSuccess(['liked' => false]);
            } else {
                $conn->prepare("INSERT INTO sms_discussion_likes (discussion_id, user_id) VALUES (:did, :uid)")->execute([':did' => $discId, ':uid' => $userId]);
                $conn->prepare("UPDATE sms_discussions SET likes_count = likes_count + 1 WHERE id = :id")->execute([':id' => $discId]);
                jsonSuccess(['liked' => true]);
            }
        } elseif ($action === 'report') {
            $tType = ($data['target_type'] ?? 'discussion') === 'comment' ? 'comment' : 'discussion';
            $tId = (int)($data['target_id'] ?? 0);
            $reason = trim((string)($data['reason'] ?? 'Inappropriate content'));
            $conn->prepare("INSERT INTO sms_discussion_reports (society_id, target_type, target_id, reporter_id, reason) VALUES (:sid, :tt, :tid, :rid, :rsn)")
                ->execute([':sid' => $societyId, ':tt' => $tType, ':tid' => $tId, ':rid' => $userId, ':rsn' => $reason]);
            jsonSuccess(['message' => 'Content reported to moderators.']);
        } elseif ($action === 'moderate') {
            requireApiPermission('community.moderate');
            $modAction = trim((string)($data['mod_action'] ?? ''));
            $targetId = (int)($data['target_id'] ?? 0);
            if ($modAction === 'pin_post') {
                $conn->prepare("UPDATE sms_discussions SET is_pinned = 1 - is_pinned WHERE id = :id AND society_id = :sid")->execute([':id' => $targetId, ':sid' => $societyId]);
            } elseif ($modAction === 'remove_post') {
                $conn->prepare("UPDATE sms_discussions SET status = 'Removed' WHERE id = :id AND society_id = :sid")->execute([':id' => $targetId, ':sid' => $societyId]);
                $conn->prepare("UPDATE sms_discussion_reports SET status = 'Action_Taken', reviewed_by = :by WHERE target_type = 'discussion' AND target_id = :id AND society_id = :sid")->execute([':by' => $userId, ':id' => $targetId, ':sid' => $societyId]);
            } elseif ($modAction === 'dismiss_report') {
                $conn->prepare("UPDATE sms_discussion_reports SET status = 'Dismissed', reviewed_by = :by WHERE id = :id AND society_id = :sid")->execute([':by' => $userId, ':id' => $targetId, ':sid' => $societyId]);
            } elseif ($modAction === 'restrict_user') {
                $banUid = (int)($data['user_id'] ?? 0);
                $reason = trim((string)($data['reason'] ?? 'Violation of community guidelines'));
                $conn->prepare("INSERT INTO sms_discussion_restrictions (society_id, user_id, reason, created_by) VALUES (:sid, :uid, :rsn, :by) ON DUPLICATE KEY UPDATE reason = VALUES(reason)")
                    ->execute([':sid' => $societyId, ':uid' => $banUid, ':rsn' => $reason, ':by' => $userId]);
            }
            logAudit($conn, $societyId, $userId, 'MODERATE_FORUM', 'discussion', $targetId, ['mod_action' => $modAction]);
            jsonSuccess(['message' => 'Moderation action completed.']);
        } else {
            jsonError('Unknown discussion action.', 400);
        }
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to process discussion request.');
    }
}
