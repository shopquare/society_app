# Multiple Gate Management — SecureGate v2

## Overview
Multi-Gate Management scales SecureGate from single-entrance tracking to multi-entrance complexes (Main Gate, Back Gate, Tower A/B Entrances, Service/Delivery Gates, Parking & Pedestrian Gates) while preserving strict tenant and gate-level guard isolation.

## Schema
- `sms_gates`: Society-scoped gates (`gate_name`, `gate_type`, optional `building_id`, `open_time`, `close_time`, `is_active`).
- `sms_gate_assignments`: Maps guards (`role_id = 4`) to authorized gates and shifts (`Morning`, `Afternoon`, `Night`).
- `sms_visitors.gate_id`: Links every logged visitor entry to the exact entrance gate.

## Security Enforcement
- Guard APIs (`api/guard_log_visitor.php`, `api/guard_get_visitors.php`, `api/guard_handover.php`, `api/guard_incidents.php`) enforce `assertGuardAuthorizedForGate($conn, $societyId, $guardId, $gateId)` from `includes/gate_helper.php`.
- Guards cannot read or submit entries for gates outside their active society assignments (`403 Forbidden`).

## Admin Console & Reporting (`/admin/gates`)
- Create, edit, and toggle gates with configurable operating hours and tower associations.
- Assign or remove guards per gate and shift with automatic guard notification.
- Live KPIs per gate (today's visitors, deliveries, open incidents) and CSV export (`/api/admin_manage_gates.php?export=gates_csv`).
