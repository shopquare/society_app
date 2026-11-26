<?php
// api/services_directory.php
// Resident & Society Verified Local Services Directory API

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/audit_logger.php';

initApi(['GET', 'POST']);
requireApiAuth([1, 2, 3]);

$societyId = (int)$_SESSION['society_id'];
$userId = (int)$_SESSION['user_id'];
$isManager = hasPermission('services.manage');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        if (!empty($_GET['provider_id'])) {
            $pid = (int)$_GET['provider_id'];
            $rvStmt = $conn->prepare(
                "SELECT r.*, CONCAT(p.first_name, ' ', LEFT(p.last_name, 1), '.') AS reviewer_name
                 FROM sms_service_reviews r
                 INNER JOIN sms_user_profiles p ON r.resident_id = p.user_id
                 WHERE r.provider_id = :pid AND r.society_id = :sid
                 ORDER BY r.created_at DESC LIMIT 50"
            );
            $rvStmt->execute([':pid' => $pid, ':sid' => $societyId]);
            jsonSuccess(['reviews' => $rvStmt->fetchAll()]);
        }

        $categories = $conn->query("SELECT * FROM sms_service_categories WHERE is_active = 1 ORDER BY display_order ASC, name ASC")->fetchAll();

        $catId = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
        $q = trim((string)($_GET['q'] ?? ''));
        $verifiedOnly = !empty($_GET['verified_only']);
        $favOnly = !empty($_GET['favorites_only']);
        $sort = trim((string)($_GET['sort'] ?? 'verified'));

        $where = ["sp.society_id = :sid", "sp.approval_status = 'Approved'", "sp.is_active = 1"];
        $params = [':sid' => $societyId, ':uid' => $userId];
        if ($catId > 0) { $where[] = "sp.category_id = :cid"; $params[':cid'] = $catId; }
        if ($verifiedOnly) { $where[] = "sp.is_verified = 1"; }
        if ($favOnly) { $where[] = "f.id IS NOT NULL"; }
        if ($q !== '') {
            $where[] = "(sp.business_name LIKE :q1 OR sp.description LIKE :q2 OR sc.name LIKE :q3 OR sp.service_area LIKE :q4)";
            $like = '%' . $q . '%';
            $params[':q1'] = $like; $params[':q2'] = $like; $params[':q3'] = $like; $params[':q4'] = $like;
        }

        $orderSql = match ($sort) {
            'rating' => "sp.avg_rating DESC, sp.review_count DESC, sp.is_verified DESC",
            'newest' => "sp.created_at DESC",
            default => "sp.is_featured DESC, sp.is_verified DESC, sp.avg_rating DESC, sp.review_count DESC"
        };

        $sql = "SELECT sp.*, sc.name AS category_name, sc.icon AS category_icon,
                       CASE WHEN f.id IS NOT NULL THEN 1 ELSE 0 END AS is_favorite
                FROM sms_service_providers sp
                INNER JOIN sms_service_categories sc ON sp.category_id = sc.id
                LEFT JOIN sms_service_favorites f ON sp.id = f.provider_id AND f.resident_id = :uid
                WHERE " . implode(' AND ', $where) . "
                ORDER BY {$orderSql} LIMIT 120";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        $mySub = $conn->prepare(
            "SELECT sp.*, sc.name AS category_name FROM sms_service_providers sp
             INNER JOIN sms_service_categories sc ON sp.category_id = sc.id
             WHERE sp.society_id = :sid AND sp.submitted_by = :uid ORDER BY sp.created_at DESC LIMIT 20"
        );
        $mySub->execute([':sid' => $societyId, ':uid' => $userId]);

        jsonSuccess([
            'categories' => $categories,
            'providers' => $stmt->fetchAll(),
            'my_submissions' => $mySub->fetchAll()
        ]);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to load services directory.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonInput();
    requireValidCsrfToken($data['csrf_token'] ?? '');
    $action = trim((string)($data['action'] ?? ''));

    try {
        if ($action === 'submit_provider') {
            $catId = (int)($data['category_id'] ?? 0);
            $name = trim((string)($data['business_name'] ?? ''));
            $contact = trim((string)($data['contact_person'] ?? ''));
            $phone = normalizeIndianPhoneNumber((string)($data['phone_number'] ?? ''));
            $wa = trim((string)($data['whatsapp_number'] ?? ''));
            $area = trim((string)($data['service_area'] ?? 'Nearby & Society Campus'));
            $desc = trim((string)($data['description'] ?? ''));
            $hours = trim((string)($data['timing_hours'] ?? '8:00 AM - 8:00 PM'));
            $emerg = !empty($data['is_emergency_service']) ? 1 : 0;

            if ($catId <= 0 || $name === '' || $phone === '') {
                jsonError('Category, business name, and valid phone number are required.', 400);
            }

            $status = $isManager ? 'Approved' : 'Pending';
            $verified = $isManager ? 1 : 0;

            $conn->prepare(
                "INSERT INTO sms_service_providers (society_id, category_id, submitted_by, business_name, contact_person, phone_number, whatsapp_number, service_area, description, timing_hours, is_emergency_service, is_verified, approval_status)
                 VALUES (:sid, :cid, :uid, :bn, :cp, :ph, :wa, :sa, :desc, :th, :em, :ver, :st)"
            )->execute([
                ':sid' => $societyId, ':cid' => $catId, ':uid' => $userId, ':bn' => $name,
                ':cp' => $contact ?: null, ':ph' => $phone, ':wa' => $wa ?: $phone,
                ':sa' => $area ?: null, ':desc' => $desc ?: null, ':th' => $hours,
                ':em' => $emerg, ':ver' => $verified, ':st' => $status
            ]);
            jsonSuccess([
                'message' => $status === 'Approved' ? 'Service provider added to directory.' : 'Suggestion submitted for admin verification.'
            ], 201);
        } elseif ($action === 'submit_review') {
            $pid = (int)($data['provider_id'] ?? 0);
            $rating = (int)($data['rating'] ?? 0);
            $reviewText = trim((string)($data['review_text'] ?? ''));
            if ($pid <= 0 || $rating < 1 || $rating > 5) jsonError('Valid provider and star rating (1-5) required.', 400);

            $chk = $conn->prepare("SELECT id FROM sms_service_providers WHERE id = :pid AND society_id = :sid AND approval_status = 'Approved' LIMIT 1");
            $chk->execute([':pid' => $pid, ':sid' => $societyId]);
            if (!$chk->fetch()) jsonError('Approved provider not found.', 404);

            $dup = $conn->prepare("SELECT id FROM sms_service_reviews WHERE provider_id = :pid AND resident_id = :uid LIMIT 1");
            $dup->execute([':pid' => $pid, ':uid' => $userId]);
            if ($dup->fetch()) jsonError('You have already reviewed this service provider.', 409);

            $conn->beginTransaction();
            $conn->prepare(
                "INSERT INTO sms_service_reviews (provider_id, society_id, resident_id, rating, review_text) VALUES (:pid, :sid, :uid, :rat, :txt)"
            )->execute([':pid' => $pid, ':sid' => $societyId, ':uid' => $userId, ':rat' => $rating, ':txt' => $reviewText ?: null]);

            $stats = $conn->query("SELECT AVG(rating) AS avg_r, COUNT(*) AS cnt FROM sms_service_reviews WHERE provider_id = {$pid}")->fetch();
            $conn->prepare("UPDATE sms_service_providers SET avg_rating = :avg, review_count = :cnt WHERE id = :pid")
                ->execute([':avg' => round((float)($stats['avg_r'] ?? $rating), 2), ':cnt' => (int)($stats['cnt'] ?? 1), ':pid' => $pid]);
            $conn->commit();

            jsonSuccess(['message' => 'Thank you! Your review has been published.'], 201);
        } elseif ($action === 'toggle_favorite') {
            $pid = (int)($data['provider_id'] ?? 0);
            if ($pid <= 0) jsonError('Provider ID required.', 400);
            $exists = $conn->prepare("SELECT id FROM sms_service_favorites WHERE provider_id = :pid AND resident_id = :uid");
            $exists->execute([':pid' => $pid, ':uid' => $userId]);
            if ($row = $exists->fetch()) {
                $conn->prepare("DELETE FROM sms_service_favorites WHERE id = :id")->execute([':id' => (int)$row['id']]);
                jsonSuccess(['favorited' => false, 'message' => 'Removed from favorites.']);
            } else {
                $conn->prepare("INSERT INTO sms_service_favorites (provider_id, resident_id) VALUES (:pid, :uid)")
                    ->execute([':pid' => $pid, ':uid' => $userId]);
                jsonSuccess(['favorited' => true, 'message' => 'Saved to favorites.']);
            }
        } elseif ($action === 'report_provider') {
            $pid = (int)($data['provider_id'] ?? 0);
            $reason = trim((string)($data['reason'] ?? ''));
            $details = trim((string)($data['details'] ?? ''));
            if ($pid <= 0 || $reason === '') jsonError('Provider and report reason required.', 400);

            $conn->prepare(
                "INSERT INTO sms_service_reports (provider_id, society_id, reported_by, reason, details) VALUES (:pid, :sid, :uid, :rs, :dt)"
            )->execute([':pid' => $pid, ':sid' => $societyId, ':uid' => $userId, ':rs' => $reason, ':dt' => $details ?: null]);
            jsonSuccess(['message' => 'Report submitted to society moderators.'], 201);
        } else {
            jsonError('Invalid service directory action.', 400);
        }
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        logAndFail($e, 'Unable to complete service directory request.');
    }
}
