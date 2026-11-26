# Facility Booking System — SecureGate v2

## Overview
Provides real-time amenity discovery, slot-level availability inspection, transactional double-booking prevention, approval workflows, maintenance date blocking, and cancellation refund handling (`/admin/facilities`, `/resident/facilities`, `api/resident_facility_booking.php`, `api/admin_manage_facilities.php`).

## Supported Amenities
Clubhouse, Gym, Swimming Pool, Banquet Hall, Sports Court, Guest Room, Community Hall, and Temporary Visitor Parking Bays.

## Concurrency & Booking Rules
- Backend reservations execute inside a strict SQL transaction with `SELECT ... FOR UPDATE` locks against `sms_society_facilities` and overlapping `sms_facility_bookings` rows.
- Enforces facility operating hours, weekday availability (`available_days`), blocked maintenance dates (`sms_facility_blocked_dates`), and monthly per-unit limits (`max_bookings_per_unit_month`).
- Supports `cancellation_hours_cutoff` and configurable `refund_policy_pct`.
