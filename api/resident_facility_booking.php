<?php
// api/resident_facility_booking.php
// Transactional Facility Availability, Booking & Cancellation Engine with Concurrency Locks

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/notification_service.php';
require_once __DIR__ . '/../includes/audit_logger.php';

initApi(['GET', 'POST']);
requireApiAuth([1, 2, 3]);

$societyId = (int)$_SESSION['society_id'];
$residentId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = trim((string)($_GET['action'] ?? 'list'));

    try {
        if ($action === 'availability') {
            $facilityId = (int)($_GET['facility_id'] ?? 0);
            $date = trim((string)($_GET['date'] ?? date('Y-m-d')));

            $fStmt = $conn->prepare("SELECT * FROM sms_society_facilities WHERE id = :id AND society_id = :sid AND is_active = 1 LIMIT 1");
            $fStmt->execute([':id' => $facilityId, ':sid' => $societyId]);
            $fac = $fStmt->fetch();
            if (!$fac) jsonError('Facility not found or disabled.', 404);

            // Check blocked dates
            $blkStmt = $conn->prepare("SELECT reason, start_time, end_time FROM sms_facility_blocked_dates WHERE society_id = :sid AND facility_id = :fid AND blocked_date = :dt");
            $blkStmt->execute([':sid' => $societyId, ':fid' => $facilityId, ':dt' => $date]);
            $blockedRows = $blkStmt->fetchAll();

            // Check day of week availability
            $dayAbbr = date('D', strtotime($date));
            $allowedDays = array_map('trim', explode(',', (string)$fac['available_days']));
            $isDayOpen = in_array($dayAbbr, $allowedDays, true);

            // Active overlapping bookings on date
            $bkStmt = $conn->prepare(
                "SELECT start_time, end_time FROM sms_facility_bookings
                 WHERE society_id = :sid AND facility_id = :fid AND booking_date = :dt AND status IN ('Confirmed', 'Pending_Approval')"
            );
            $bkStmt->execute([':sid' => $societyId, ':fid' => $facilityId, ':dt' => $date]);
            $bookedSlots = $bkStmt->fetchAll();

            // Build time slots
            $durMins = max(30, (int)($fac['slot_duration_mins'] ?? 60));
            $curTs = strtotime($date . ' ' . $fac['open_time']);
            $endTs = strtotime($date . ' ' . $fac['close_time']);
            $slots = [];

            while ($curTs + ($durMins * 60) <= $endTs + 60) {
                $sTime = date('H:i:s', $curTs);
                $eTs = min($endTs, $curTs + ($durMins * 60));
                if ($eTs <= $curTs) break;
                $eTime = date('H:i:s', $eTs);

                $state = 'available';
                $reason = '';
                if (!$isDayOpen) {
                    $state = 'closed';
                    $reason = 'Closed on ' . $dayAbbr;
                } else {
                    foreach ($blockedRows as $blk) {
                        if ($blk['start_time'] === null || !($blk['end_time'] <= $sTime || $blk['start_time'] >= $eTime)) {
                            $state = 'blocked';
                            $reason = $blk['reason'];
                            break;
                        }
                    }
                    if ($state === 'available') {
                        foreach ($bookedSlots as $bk) {
                            if (!($bk['end_time'] <= $sTime || $bk['start_time'] >= $eTime)) {
                                $state = 'booked';
                                $reason = 'Already Reserved';
                                break;
                            }
                        }
                    }
                }

                $slots[] = [
                    'start_time' => substr($sTime, 0, 5),
                    'end_time' => substr($eTime, 0, 5),
                    'status' => $state,
                    'reason' => $reason
                ];
                $curTs += ($durMins * 60);
            }

            jsonSuccess(['facility' => $fac, 'date' => $date, 'slots' => $slots]);
        }

        // Standard GET: list all active facilities + user's bookings
        $facStmt = $conn->prepare(
            "SELECT id, facility_name AS name, facility_name, facility_type, description, image_path, location, capacity,
                    open_time, close_time, available_days, slot_duration_mins, max_bookings_per_unit_month, requires_approval,
                    cancellation_hours_cutoff, refund_policy_pct, rules, COALESCE(booking_cost_per_slot, 0) AS booking_cost_per_slot, is_active
             FROM sms_society_facilities
             WHERE society_id = :society_id AND is_active = 1
             ORDER BY id ASC"
        );
        $facStmt->execute([':society_id' => $societyId]);
        $facilities = $facStmt->fetchAll();

        $bookStmt = $conn->prepare(
            "SELECT b.*, f.facility_name AS facility_name, COALESCE(f.booking_cost_per_slot, 0) AS booking_cost_per_slot
             FROM sms_facility_bookings b
             INNER JOIN sms_society_facilities f ON b.facility_id = f.id
             WHERE b.society_id = :society_id AND b.resident_id = :resident_id
             ORDER BY b.booking_date DESC, b.start_time DESC LIMIT 40"
        );
        $bookStmt->execute([':society_id' => $societyId, ':resident_id' => $residentId]);

        jsonSuccess(['facilities' => $facilities, 'my_bookings' => $bookStmt->fetchAll()]);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to load society facilities.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonInput();
    requireValidCsrfToken($data['csrf_token'] ?? '');
    $action = trim((string)($data['action'] ?? 'book'));

    try {
        if ($action === 'cancel') {
            $bookingId = (int)($data['booking_id'] ?? 0);
            $reason = trim((string)($data['reason'] ?? 'Resident cancelled'));

            $conn->beginTransaction();
            $bStmt = $conn->prepare(
                "SELECT b.*, f.facility_name, f.cancellation_hours_cutoff, f.refund_policy_pct
                 FROM sms_facility_bookings b
                 INNER JOIN sms_society_facilities f ON b.facility_id = f.id
                 WHERE b.id = :id AND b.resident_id = :rid AND b.society_id = :sid FOR UPDATE"
            );
            $bStmt->execute([':id' => $bookingId, ':rid' => $residentId, ':sid' => $societyId]);
            $booking = $bStmt->fetch();
            if (!$booking || !in_array($booking['status'], ['Confirmed', 'Pending_Approval'], true)) {
                $conn->rollBack();
                jsonError('Booking cannot be cancelled.', 400);
            }

            $hoursBefore = (strtotime($booking['booking_date'] . ' ' . $booking['start_time']) - time()) / 3600;
            $eligibleRefund = ($hoursBefore >= (int)$booking['cancellation_hours_cutoff']) && $booking['payment_status'] === 'Paid';
            $refundAmt = $eligibleRefund ? round((float)$booking['booking_fee'] * ((int)$booking['refund_policy_pct'] / 100), 2) : 0.00;
            $payStat = ($refundAmt > 0) ? 'Refunded' : $booking['payment_status'];

            $conn->prepare("UPDATE sms_facility_bookings SET status = 'Cancelled', cancellation_reason = :rsn, cancelled_at = NOW(), refund_amount = :ra, payment_status = :ps WHERE id = :id")
                ->execute([':rsn' => $reason, ':ra' => $refundAmt, ':ps' => $payStat, ':id' => $bookingId]);
            $conn->commit();

            jsonSuccess(['message' => 'Booking cancelled.' . ($refundAmt > 0 ? " Refund of ₹{$refundAmt} initiated." : ''), 'refund_amount' => $refundAmt]);
        }

        // CREATE TRANSACTIONAL BOOKING WITH CONCURRENCY LOCK
        $facilityId = (int)($data['facility_id'] ?? 0);
        $bookingDate = trim((string)($data['booking_date'] ?? ''));
        $startTime = trim((string)($data['start_time'] ?? ''));
        $endTime = trim((string)($data['end_time'] ?? ''));
        $notes = trim((string)($data['notes'] ?? ''));
        $paymentRef = trim((string)($data['payment_ref'] ?? ''));

        if (strlen($startTime) === 5) $startTime .= ':00';
        if (strlen($endTime) === 5) $endTime .= ':00';

        if ($facilityId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $bookingDate) || $bookingDate < date('Y-m-d')) {
            jsonError('Valid facility and future/today booking date are required.', 400);
        }
        if ($startTime >= $endTime) {
            jsonError('Start time must be earlier than end time.', 400);
        }

        $conn->beginTransaction();

        // Row-level FOR UPDATE lock prevents concurrent double booking race condition
        $fStmt = $conn->prepare("SELECT * FROM sms_society_facilities WHERE id = :id AND society_id = :sid FOR UPDATE");
        $fStmt->execute([':id' => $facilityId, ':sid' => $societyId]);
        $facility = $fStmt->fetch();

        if (!$facility || !(int)$facility['is_active']) {
            $conn->rollBack();
            jsonError('Selected facility is unavailable.', 400);
        }

        // Day & Hours validation
        $dayAbbr = date('D', strtotime($bookingDate));
        if (!in_array($dayAbbr, array_map('trim', explode(',', (string)$facility['available_days'])), true)) {
            $conn->rollBack();
            jsonError("{$facility['facility_name']} is closed on {$dayAbbr}.", 400);
        }
        if ($startTime < $facility['open_time'] || $endTime > $facility['close_time']) {
            $conn->rollBack();
            jsonError("Booking must be within facility hours ({$facility['open_time']} - {$facility['close_time']}).", 400);
        }

        // Blocked / maintenance check
        $blkStmt = $conn->prepare(
            "SELECT reason FROM sms_facility_blocked_dates
             WHERE society_id = :sid AND facility_id = :fid AND blocked_date = :dt
               AND (start_time IS NULL OR NOT (end_time <= :st OR start_time >= :et)) LIMIT 1"
        );
        $blkStmt->execute([':sid' => $societyId, ':fid' => $facilityId, ':dt' => $bookingDate, ':st' => $startTime, ':et' => $endTime]);
        if ($blkRow = $blkStmt->fetch()) {
            $conn->rollBack();
            jsonError('Facility blocked on this date: ' . $blkRow['reason'], 409);
        }

        // Monthly flat limit check
        $monthStart = date('Y-m-01', strtotime($bookingDate));
        $monthEnd = date('Y-m-t', strtotime($bookingDate));
        $capStmt = $conn->prepare(
            "SELECT COUNT(*) FROM sms_facility_bookings
             WHERE society_id = :sid AND facility_id = :fid AND resident_id = :rid
               AND booking_date BETWEEN :ms AND :me AND status IN ('Confirmed', 'Pending_Approval')"
        );
        $capStmt->execute([':sid' => $societyId, ':fid' => $facilityId, ':rid' => $residentId, ':ms' => $monthStart, ':me' => $monthEnd]);
        if ((int)$capStmt->fetchColumn() >= (int)($facility['max_bookings_per_unit_month'] ?? 6)) {
            $conn->rollBack();
            jsonError('Monthly booking limit reached for your unit on this facility.', 409);
        }

        // Overlap lock check
        $overlapStmt = $conn->prepare(
            "SELECT id FROM sms_facility_bookings
             WHERE society_id = :sid AND facility_id = :fid AND booking_date = :dt
               AND status IN ('Confirmed', 'Pending_Approval')
               AND NOT (end_time <= :st OR start_time >= :et)
             LIMIT 1 FOR UPDATE"
        );
        $overlapStmt->execute([':sid' => $societyId, ':fid' => $facilityId, ':dt' => $bookingDate, ':st' => $startTime, ':et' => $endTime]);
        if ($overlapStmt->fetch()) {
            $conn->rollBack();
            jsonError("This time slot is already booked for {$facility['facility_name']}. Please select another slot.", 409);
        }

        $fee = (float)($facility['booking_cost_per_slot'] ?? 0);
        $payStatus = $fee > 0 ? ($paymentRef !== '' ? 'Paid' : 'Pending') : 'Free';
        $bookingStatus = ((int)$facility['requires_approval'] === 1) ? 'Pending_Approval' : 'Confirmed';

        $conn->prepare(
            "INSERT INTO sms_facility_bookings (society_id, facility_id, resident_id, booking_date, start_time, end_time, status, notes, booking_fee, payment_status, payment_ref)
             VALUES (:sid, :fid, :rid, :dt, :st, :et, :bst, :notes, :fee, :ps, :pref)"
        )->execute([
            ':sid' => $societyId, ':fid' => $facilityId, ':rid' => $residentId, ':dt' => $bookingDate,
            ':st' => $startTime, ':et' => $endTime, ':bst' => $bookingStatus, ':notes' => $notes ?: null,
            ':fee' => $fee, ':ps' => $payStatus, ':pref' => $paymentRef ?: null
        ]);
        $bookingId = (int)$conn->lastInsertId();
        $conn->commit();

        dispatchNotification($conn, [
            'society_id' => $societyId, 'user_ids' => [$residentId], 'category' => 'facility_booking', 'priority' => 'Normal',
            'title' => "Facility {$bookingStatus}: {$facility['facility_name']}",
            'message' => "Reserved for {$bookingDate} ({$startTime}–{$endTime}).",
            'action_url' => '/resident/facilities', 'channels' => ['in_app']
        ]);

        jsonSuccess([
            'message' => $bookingStatus === 'Pending_Approval' ? 'Booking submitted for Facility Manager approval.' : "Facility booked successfully for {$facility['facility_name']}!",
            'booking_id' => $bookingId,
            'booking_status' => $bookingStatus
        ], 201);
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        logAndFail($e, 'Unable to complete facility booking.');
    }
}
