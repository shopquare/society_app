<?php
// api/community_events.php
// Society Events & Celebrations, Monthly Calendar, Registration & Gallery API

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/upload_helper.php';
require_once __DIR__ . '/../includes/audit_logger.php';
require_once __DIR__ . '/../includes/notification_service.php';

initApi(['GET', 'POST']);
requireApiAuth([1, 2, 3]);

$societyId = (int)$_SESSION['society_id'];
$userId = (int)$_SESSION['user_id'];
$isAdmin = hasPermission('events.manage');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $conn->prepare(
            "SELECT e.*,
                    (SELECT COALESCE(SUM(guests_count), 0) FROM sms_event_registrations r WHERE r.event_id = e.id AND r.status = 'Registered') AS registered_count,
                    (SELECT status FROM sms_event_registrations r2 WHERE r2.event_id = e.id AND r2.user_id = :uid LIMIT 1) AS my_rsvp_status
             FROM sms_events e
             WHERE e.society_id = :sid
             ORDER BY (e.status = 'Upcoming') DESC, e.start_datetime ASC"
        );
        $stmt->execute([':uid' => $userId, ':sid' => $societyId]);
        $events = $stmt->fetchAll();

        $pStmt = $conn->prepare("SELECT * FROM sms_event_photos WHERE event_id = :eid AND society_id = :sid ORDER BY created_at DESC LIMIT 20");
        $rStmt = $conn->prepare(
            "SELECT r.*, CONCAT(p.first_name, ' ', p.last_name) AS resident_name, uu.unit_number
             FROM sms_event_registrations r
             INNER JOIN sms_user_profiles p ON r.user_id = p.user_id
             LEFT JOIN sms_user_units uu ON r.user_id = uu.user_id AND uu.is_current_occupant = 1
             WHERE r.event_id = :eid AND r.society_id = :sid ORDER BY r.created_at DESC"
        );

        foreach ($events as &$ev) {
            $pStmt->execute([':eid' => (int)$ev['id'], ':sid' => $societyId]);
            $ev['photos'] = $pStmt->fetchAll();
            if ($isAdmin) {
                $rStmt->execute([':eid' => (int)$ev['id'], ':sid' => $societyId]);
                $ev['registrations'] = $rStmt->fetchAll();
            }
        }
        unset($ev);

        jsonSuccess(['events' => $events]);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to load society events.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonInput();
    requireValidCsrfToken($data['csrf_token'] ?? '');
    $action = trim((string)($data['action'] ?? ''));

    try {
        if ($action === 'save_event') {
            requireApiPermission('events.manage');
            $id = (int)($data['id'] ?? 0);
            $title = trim((string)($data['title'] ?? ''));
            $category = trim((string)($data['category'] ?? 'Community Gatherings'));
            $desc = trim((string)($data['description'] ?? ''));
            $loc = trim((string)($data['event_location'] ?? 'Clubhouse'));
            $start = str_replace('T', ' ', trim((string)($data['start_datetime'] ?? '')));
            $end = str_replace('T', ' ', trim((string)($data['end_datetime'] ?? '')));
            $regReq = isset($data['registration_required']) ? (!empty($data['registration_required']) ? 1 : 0) : 1;
            $maxPart = max(1, (int)($data['max_participants'] ?? 100));
            $isPaid = !empty($data['is_paid']) ? 1 : 0;
            $fee = $isPaid ? max(0, (float)($data['registration_fee'] ?? 0)) : 0.00;
            $status = trim((string)($data['status'] ?? 'Upcoming'));
            $posterPath = null;

            if ($title === '' || $start === '' || $end === '') {
                jsonError('Event title, start date/time, and end date/time are required.', 400);
            }
            if (!empty($data['poster_base64'])) {
                $up = saveBase64Upload((string)$data['poster_base64'], 'events', ['image/jpeg', 'image/png', 'image/webp'], 5242880);
                $posterPath = $up['relative_path'];
            }

            if ($id > 0) {
                $sql = "UPDATE sms_events SET title=:t, category=:c, description=:d, event_location=:l, start_datetime=:sd, end_datetime=:ed, registration_required=:rr, max_participants=:mp, is_paid=:ip, registration_fee=:fee, status=:st"
                     . ($posterPath ? ", poster_path=:pp" : "") . " WHERE id=:id AND society_id=:sid";
                $params = [
                    ':t' => $title, ':c' => $category, ':d' => $desc ?: null, ':l' => $loc, ':sd' => $start, ':ed' => $end,
                    ':rr' => $regReq, ':mp' => $maxPart, ':ip' => $isPaid, ':fee' => $fee, ':st' => $status, ':id' => $id, ':sid' => $societyId
                ];
                if ($posterPath) $params[':pp'] = $posterPath;
                $conn->prepare($sql)->execute($params);
            } else {
                $conn->prepare(
                    "INSERT INTO sms_events (society_id, created_by, title, category, description, poster_path, event_location, start_datetime, end_datetime, registration_required, max_participants, is_paid, registration_fee, status)
                     VALUES (:sid, :by, :t, :c, :d, :pp, :l, :sd, :ed, :rr, :mp, :ip, :fee, 'Upcoming')"
                )->execute([
                    ':sid' => $societyId, ':by' => $userId, ':t' => $title, ':c' => $category, ':d' => $desc ?: null,
                    ':pp' => $posterPath, ':l' => $loc, ':sd' => $start, ':ed' => $end, ':rr' => $regReq,
                    ':mp' => $maxPart, ':ip' => $isPaid, ':fee' => $fee
                ]);
                $id = (int)$conn->lastInsertId();

                dispatchNotification($conn, [
                    'society_id' => $societyId, 'role_ids' => [3], 'category' => 'event_announcement', 'priority' => 'Normal',
                    'title' => "Upcoming Event: {$title}", 'message' => "{$category} on " . date('d M, h:i A', strtotime($start)) . " at {$loc}.",
                    'action_url' => '/resident/community?tab=events', 'reference_type' => 'event', 'reference_id' => $id, 'channels' => ['in_app']
                ]);
            }
            logAudit($conn, $societyId, $userId, 'SAVE_EVENT', 'event', $id, ['title' => $title, 'status' => $status]);
            jsonSuccess(['message' => 'Event saved successfully.', 'event_id' => $id]);
        } elseif ($action === 'upload_photo') {
            requireApiPermission('events.manage');
            $eventId = (int)($data['event_id'] ?? 0);
            $caption = trim((string)($data['caption'] ?? ''));
            $eChk = $conn->prepare("SELECT id FROM sms_events WHERE id = :id AND society_id = :sid");
            $eChk->execute([':id' => $eventId, ':sid' => $societyId]);
            if (!$eChk->fetch()) jsonError('Event not found.', 404);

            $up = saveBase64Upload((string)($data['photo_base64'] ?? ''), 'events', ['image/jpeg', 'image/png', 'image/webp'], 8388608);
            $conn->prepare("INSERT INTO sms_event_photos (event_id, society_id, image_path, caption, uploaded_by) VALUES (:eid, :sid, :ip, :cap, :uid)")
                ->execute([':eid' => $eventId, ':sid' => $societyId, ':ip' => $up['relative_path'], ':cap' => $caption ?: null, ':uid' => $userId]);
            jsonSuccess(['message' => 'Event photo uploaded cleanly.']);
        } elseif ($action === 'register') {
            $eventId = (int)($data['event_id'] ?? 0);
            $guests = max(1, min(10, (int)($data['guests_count'] ?? 1)));
            $payRef = trim((string)($data['payment_ref'] ?? ''));

            $conn->beginTransaction();
            $eStmt = $conn->prepare("SELECT * FROM sms_events WHERE id = :id AND society_id = :sid FOR UPDATE");
            $eStmt->execute([':id' => $eventId, ':sid' => $societyId]);
            $ev = $eStmt->fetch();
            if (!$ev || $ev['status'] !== 'Upcoming') {
                $conn->rollBack();
                jsonError('Event is not open for registration.', 400);
            }

            $cntStmt = $conn->prepare("SELECT COALESCE(SUM(guests_count), 0) FROM sms_event_registrations WHERE event_id = :eid AND status = 'Registered'");
            $cntStmt->execute([':eid' => $eventId]);
            $currentCount = (int)$cntStmt->fetchColumn();
            if ($currentCount + $guests > (int)$ev['max_participants']) {
                $conn->rollBack();
                jsonError('Event participant capacity reached.', 409);
            }

            $payStatus = ((int)$ev['is_paid'] === 1) ? ($payRef !== '' ? 'Paid' : 'Pending') : 'Free';
            $conn->prepare(
                "INSERT INTO sms_event_registrations (event_id, society_id, user_id, guests_count, payment_status, payment_ref, status)
                 VALUES (:eid, :sid, :uid, :gc, :ps, :pref, 'Registered')
                 ON DUPLICATE KEY UPDATE guests_count = VALUES(guests_count), status = 'Registered', payment_status = VALUES(payment_status), payment_ref = VALUES(payment_ref)"
            )->execute([
                ':eid' => $eventId, ':sid' => $societyId, ':uid' => $userId,
                ':gc' => $guests, ':ps' => $payStatus, ':pref' => $payRef ?: null
            ]);
            $conn->commit();

            dispatchNotification($conn, [
                'society_id' => $societyId, 'user_ids' => [$userId], 'category' => 'event_announcement', 'priority' => 'Normal',
                'title' => "Event Registration Confirmed: {$ev['title']}", 'message' => "Registered ({$guests} seat(s)) for {$ev['title']}.",
                'action_url' => '/resident/community?tab=events', 'channels' => ['in_app']
            ]);
            jsonSuccess(['message' => 'Registered for event successfully!']);
        } elseif ($action === 'cancel_registration') {
            $eventId = (int)($data['event_id'] ?? 0);
            $conn->prepare("UPDATE sms_event_registrations SET status = 'Cancelled' WHERE event_id = :eid AND user_id = :uid AND society_id = :sid")
                ->execute([':eid' => $eventId, ':uid' => $userId, ':sid' => $societyId]);
            jsonSuccess(['message' => 'Event registration cancelled.']);
        } else {
            jsonError('Unknown event action.', 400);
        }
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        logAndFail($e, 'Unable to update event.');
    }
}
