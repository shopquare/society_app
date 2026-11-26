<?php
// api/admin_manage_facilities.php
// Facility Configuration, Blocked Maintenance Dates, Booking Approvals & Calendar API

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/upload_helper.php';
require_once __DIR__ . '/../includes/audit_logger.php';
require_once __DIR__ . '/../includes/notification_service.php';

initApi(['GET', 'POST']);
requireApiPermission('facilities.manage');

$societyId = (int)$_SESSION['society_id'];
$actorId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $facStmt = $conn->prepare("SELECT * FROM sms_society_facilities WHERE society_id = :sid ORDER BY id ASC");
        $facStmt->execute([':sid' => $societyId]);
        $facilities = $facStmt->fetchAll();

        $blkStmt = $conn->prepare(
            "SELECT bd.*, f.facility_name FROM sms_facility_blocked_dates bd
             INNER JOIN sms_society_facilities f ON bd.facility_id = f.id
             WHERE bd.society_id = :sid AND bd.blocked_date >= CURDATE() ORDER BY bd.blocked_date ASC"
        );
        $blkStmt->execute([':sid' => $societyId]);

        $bkStmt = $conn->prepare(
            "SELECT b.*, f.facility_name, CONCAT(p.first_name, ' ', p.last_name) AS resident_name, uu.unit_number
             FROM sms_facility_bookings b
             INNER JOIN sms_society_facilities f ON b.facility_id = f.id
             INNER JOIN sms_user_profiles p ON b.resident_id = p.user_id
             LEFT JOIN sms_user_units uu ON b.resident_id = uu.user_id AND uu.is_current_occupant = 1
             WHERE b.society_id = :sid
             ORDER BY (b.status = 'Pending_Approval') DESC, b.booking_date DESC, b.start_time ASC LIMIT 80"
        );
        $bkStmt->execute([':sid' => $societyId]);

        jsonSuccess([
            'facilities' => $facilities,
            'blocked_dates' => $blkStmt->fetchAll(),
            'bookings' => $bkStmt->fetchAll()
        ]);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to load facility management console.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonInput();
    requireValidCsrfToken($data['csrf_token'] ?? '');
    $action = trim((string)($data['action'] ?? ''));

    try {
        if ($action === 'save_facility') {
            $id = (int)($data['id'] ?? 0);
            $name = trim((string)($data['facility_name'] ?? ''));
            $type = trim((string)($data['facility_type'] ?? 'Clubhouse'));
            $desc = trim((string)($data['description'] ?? ''));
            $loc = trim((string)($data['location'] ?? ''));
            $cap = max(1, (int)($data['capacity'] ?? 20));
            $fee = max(0, (float)($data['booking_cost_per_slot'] ?? 0));
            $openTime = trim((string)($data['open_time'] ?? '06:00:00'));
            $closeTime = trim((string)($data['close_time'] ?? '22:00:00'));
            $availDays = trim((string)($data['available_days'] ?? 'Mon,Tue,Wed,Thu,Fri,Sat,Sun'));
            $slotDur = max(15, (int)($data['slot_duration_mins'] ?? 60));
            $maxUnitMo = max(1, (int)($data['max_bookings_per_unit_month'] ?? 6));
            $reqAppr = !empty($data['requires_approval']) ? 1 : 0;
            $cutoffHrs = max(0, (int)($data['cancellation_hours_cutoff'] ?? 24));
            $refundPct = max(0, min(100, (int)($data['refund_policy_pct'] ?? 100)));
            $isActive = isset($data['is_active']) ? (!empty($data['is_active']) ? 1 : 0) : 1;
            $rules = trim((string)($data['rules'] ?? ''));

            if ($name === '') jsonError('Facility name is required.', 400);

            $imgPath = null;
            if (!empty($data['image_base64'])) {
                $up = saveBase64Upload((string)$data['image_base64'], 'facilities', ['image/jpeg', 'image/png', 'image/webp'], 5242880);
                $imgPath = $up['relative_path'];
            }

            if ($id > 0) {
                $sql = "UPDATE sms_society_facilities SET facility_name=:fn, facility_type=:ft, description=:d, location=:loc, capacity=:cap, booking_cost_per_slot=:fee, open_time=:ot, close_time=:ct, available_days=:ad, slot_duration_mins=:sd, max_bookings_per_unit_month=:mu, requires_approval=:ra, cancellation_hours_cutoff=:ch, refund_policy_pct=:rp, is_active=:act, rules=:rul"
                     . ($imgPath ? ", image_path=:ip" : "") . " WHERE id=:id AND society_id=:sid";
                $params = [
                    ':fn' => $name, ':ft' => $type, ':d' => $desc ?: null, ':loc' => $loc ?: null, ':cap' => $cap,
                    ':fee' => $fee, ':ot' => $openTime, ':ct' => $closeTime, ':ad' => $availDays, ':sd' => $slotDur,
                    ':mu' => $maxUnitMo, ':ra' => $reqAppr, ':ch' => $cutoffHrs, ':rp' => $refundPct,
                    ':act' => $isActive, ':rul' => $rules ?: null, ':id' => $id, ':sid' => $societyId
                ];
                if ($imgPath) $params[':ip'] = $imgPath;
                $conn->prepare($sql)->execute($params);
            } else {
                $conn->prepare(
                    "INSERT INTO sms_society_facilities (society_id, facility_name, facility_type, description, image_path, location, capacity, booking_cost_per_slot, open_time, close_time, available_days, slot_duration_mins, max_bookings_per_unit_month, requires_approval, cancellation_hours_cutoff, refund_policy_pct, is_active, rules)
                     VALUES (:sid, :fn, :ft, :d, :ip, :loc, :cap, :fee, :ot, :ct, :ad, :sd, :mu, :ra, :ch, :rp, :act, :rul)"
                )->execute([
                    ':sid' => $societyId, ':fn' => $name, ':ft' => $type, ':d' => $desc ?: null, ':ip' => $imgPath,
                    ':loc' => $loc ?: null, ':cap' => $cap, ':fee' => $fee, ':ot' => $openTime, ':ct' => $closeTime,
                    ':ad' => $availDays, ':sd' => $slotDur, ':mu' => $maxUnitMo, ':ra' => $reqAppr, ':ch' => $cutoffHrs,
                    ':rp' => $refundPct, ':act' => $isActive, ':rul' => $rules ?: null
                ]);
                $id = (int)$conn->lastInsertId();
            }

            logAudit($conn, $societyId, $actorId, 'SAVE_FACILITY', 'facility', $id, ['name' => $name]);
            jsonSuccess(['message' => 'Facility configuration saved.', 'facility_id' => $id]);
        } elseif ($action === 'block_date') {
            $fid = (int)($data['facility_id'] ?? 0);
            $bdate = trim((string)($data['blocked_date'] ?? ''));
            $reason = trim((string)($data['reason'] ?? 'Scheduled Maintenance'));
            $conn->prepare("INSERT INTO sms_facility_blocked_dates (society_id, facility_id, blocked_date, reason, blocked_by) VALUES (:sid, :fid, :bd, :rsn, :by)")
                ->execute([':sid' => $societyId, ':fid' => $fid, ':bd' => $bdate, ':rsn' => $reason, ':by' => $actorId]);
            jsonSuccess(['message' => 'Date blocked for maintenance.']);
        } elseif ($action === 'unblock_date') {
            $bid = (int)($data['id'] ?? 0);
            $conn->prepare("DELETE FROM sms_facility_blocked_dates WHERE id = :id AND society_id = :sid")->execute([':id' => $bid, ':sid' => $societyId]);
            jsonSuccess(['message' => 'Date unblocked.']);
        } elseif ($action === 'booking_decision') {
            requireApiPermission('facilities.approve');
            $bookingId = (int)($data['booking_id'] ?? 0);
            $decision = trim((string)($data['decision'] ?? '')); // Confirmed | Rejected
            if (!in_array($decision, ['Confirmed', 'Rejected'], true)) jsonError('Invalid decision.', 400);

            $bStmt = $conn->prepare("SELECT b.*, f.facility_name FROM sms_facility_bookings b INNER JOIN sms_society_facilities f ON b.facility_id = f.id WHERE b.id = :id AND b.society_id = :sid");
            $bStmt->execute([':id' => $bookingId, ':sid' => $societyId]);
            $booking = $bStmt->fetch();
            if (!$booking) jsonError('Booking not found.', 404);

            $refundAmt = ($decision === 'Rejected' && $booking['payment_status'] === 'Paid') ? (float)$booking['booking_fee'] : 0.00;
            $payStat = ($refundAmt > 0) ? 'Refunded' : $booking['payment_status'];

            $conn->prepare("UPDATE sms_facility_bookings SET status = :st, approved_by = :by, refund_amount = :ref, payment_status = :ps WHERE id = :id")
                ->execute([':st' => $decision, ':by' => $actorId, ':ref' => $refundAmt, ':ps' => $payStat, ':id' => $bookingId]);

            dispatchNotification($conn, [
                'society_id' => $societyId, 'user_ids' => [(int)$booking['resident_id']], 'category' => 'facility_booking', 'priority' => 'High',
                'title' => "Facility Booking {$decision}: {$booking['facility_name']}",
                'message' => "Booking for {$booking['booking_date']} ({$booking['start_time']}–{$booking['end_time']}) is now {$decision}.",
                'action_url' => '/resident/facilities', 'channels' => ['in_app', 'web_push']
            ]);
            jsonSuccess(['message' => "Booking marked {$decision}."]);
        } else {
            jsonError('Unknown facility action.', 400);
        }
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to update facility configuration.');
    }
}
