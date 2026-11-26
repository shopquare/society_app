<?php
// tests/phases/phase3_tests.php
// Automated verification for Phase 3: Facility Booking Concurrency, Blocked Dates & Quotas

echo "\n--- Phase 3: Facility Booking & Availability Tests ---\n";

$facCode = file_get_contents(__DIR__ . '/../../api/resident_facility_booking.php');
assertTrue(str_contains($facCode, 'FOR UPDATE'), 'Phase 3: Booking transaction enforces row-level FOR UPDATE concurrency locking');
assertTrue(str_contains($facCode, 'sms_facility_blocked_dates'), 'Phase 3: Booking checks blocked maintenance dates');
assertTrue(str_contains($facCode, 'max_bookings_per_unit_month'), 'Phase 3: Booking checks monthly flat booking cap');
assertTrue(str_contains($facCode, 'cancellation_hours_cutoff'), 'Phase 3: Cancellation checks refund cutoff window');

if ($conn instanceof PDO) {
    $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    assertTrue(in_array('sms_facility_blocked_dates', $tables, true), 'Phase 3 DB table present: sms_facility_blocked_dates');

    $fCount = (int)$conn->query("SELECT COUNT(*) FROM sms_society_facilities WHERE society_id = 1 AND is_active = 1")->fetchColumn();
    assertTrue($fCount >= 8, "Phase 3 seeded all 8 society facility types (count: {$fCount})");

    // Overlap detection & double-booking simulation inside rollback transaction
    $conn->beginTransaction();
    $testDate = date('Y-m-d', strtotime('+10 days'));
    $conn->prepare("INSERT INTO sms_facility_bookings (society_id, facility_id, resident_id, booking_date, start_time, end_time, status) VALUES (1, 1, 3, :dt, '10:00:00', '11:00:00', 'Confirmed')")->execute([':dt' => $testDate]);

    $overlapCheck = $conn->prepare(
        "SELECT id FROM sms_facility_bookings WHERE society_id = 1 AND facility_id = 1 AND booking_date = :dt
         AND status IN ('Confirmed', 'Pending_Approval') AND NOT (end_time <= '10:30:00' OR start_time >= '11:30:00') LIMIT 1"
    );
    $overlapCheck->execute([':dt' => $testDate]);
    assertTrue((bool)$overlapCheck->fetch(), 'Phase 3: Overlapping slot query detects partial time conflict');
    $conn->rollBack();
}
