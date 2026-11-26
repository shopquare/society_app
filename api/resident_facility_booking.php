<?php
// /society_app/api/resident_facility_booking.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['GET', 'POST']);
requireApiAuth([3]);

$societyId = (int)$_SESSION['society_id'];
$residentId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // 1. Fetch available facilities
        $facStmt = $conn->prepare(
            "SELECT id, name, description, capacity, rules, booking_cost_per_slot, is_active
             FROM sms_society_facilities
             WHERE society_id = :society_id AND is_active = 1
             ORDER BY name ASC"
        );
        $facStmt->execute([':society_id' => $societyId]);
        $facilities = $facStmt->fetchAll();

        // 2. Fetch resident's recent and upcoming bookings
        $bookStmt = $conn->prepare(
            "SELECT b.id, b.facility_id, b.booking_date, b.start_time, b.end_time, b.status, b.notes, b.created_at,
                    f.name AS facility_name, f.booking_cost_per_slot
             FROM sms_facility_bookings b
             INNER JOIN sms_society_facilities f ON b.facility_id = f.id
             WHERE b.society_id = :society_id AND b.resident_id = :resident_id
             ORDER BY b.booking_date DESC, b.start_time DESC
             LIMIT 30"
        );
        $bookStmt->execute([':society_id' => $societyId, ':resident_id' => $residentId]);
        $bookings = $bookStmt->fetchAll();

        jsonSuccess([
            'facilities' => $facilities,
            'my_bookings' => $bookings
        ]);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to load society facilities.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonInput();
    requireValidCsrfToken($data['csrf_token'] ?? '');

    $facilityId = (int)($data['facility_id'] ?? 0);
    $bookingDate = trim((string)($data['booking_date'] ?? ''));
    $startTime = trim((string)($data['start_time'] ?? ''));
    $endTime = trim((string)($data['end_time'] ?? ''));
    $notes = trim((string)($data['notes'] ?? ''));

    if ($facilityId <= 0) {
        jsonError('Please select a facility.', 400);
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $bookingDate)) {
        jsonError('Invalid booking date format. Use YYYY-MM-DD.', 400);
    }

    $today = date('Y-m-d');
    if ($bookingDate < $today) {
        jsonError('Booking date cannot be in the past.', 400);
    }

    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $startTime) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $endTime)) {
        jsonError('Invalid start or end time format. Use HH:MM.', 400);
    }

    if ($startTime >= $endTime) {
        jsonError('Start time must be earlier than end time.', 400);
    }

    try {
        // Verify facility belongs to resident's society
        $fCheck = $conn->prepare("SELECT id, name, is_active FROM sms_society_facilities WHERE id = :id AND society_id = :society_id");
        $fCheck->execute([':id' => $facilityId, ':society_id' => $societyId]);
        $facility = $fCheck->fetch();

        if (!$facility || !$facility['is_active']) {
            jsonError('Selected facility is not available for booking.', 400);
        }

        // Check for double booking conflicts on the same date and overlapping slot
        $overlapStmt = $conn->prepare(
            "SELECT id FROM sms_facility_bookings
             WHERE society_id = :society_id
               AND facility_id = :facility_id
               AND booking_date = :booking_date
               AND status IN ('Pending', 'Approved')
               AND NOT (end_time <= :start_time OR start_time >= :end_time)
             LIMIT 1"
        );
        $overlapStmt->execute([
            ':society_id' => $societyId,
            ':facility_id' => $facilityId,
            ':booking_date' => $bookingDate,
            ':start_time' => $startTime,
            ':end_time' => $endTime
        ]);

        if ($overlapStmt->fetch()) {
            jsonError('This time slot is already booked for ' . htmlspecialchars($facility['name'], ENT_QUOTES, 'UTF-8') . '. Please choose another slot.', 409);
        }

        $insStmt = $conn->prepare(
            "INSERT INTO sms_facility_bookings (society_id, facility_id, resident_id, booking_date, start_time, end_time, status, notes)
             VALUES (:society_id, :facility_id, :resident_id, :booking_date, :start_time, :end_time, 'Approved', :notes)"
        );
        $insStmt->execute([
            ':society_id' => $societyId,
            ':facility_id' => $facilityId,
            ':resident_id' => $residentId,
            ':booking_date' => $bookingDate,
            ':start_time' => $startTime,
            ':end_time' => $endTime,
            ':notes' => $notes !== '' ? $notes : null
        ]);

        $bookingId = (int)$conn->lastInsertId();

        jsonSuccess([
            'message' => 'Facility booked successfully for ' . $facility['name'] . '!',
            'booking_id' => $bookingId
        ]);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to complete facility booking.');
    }
}
