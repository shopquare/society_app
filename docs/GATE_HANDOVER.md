# Gate Handover Management — SecureGate v2

## Overview
The Gate Handover module enforces accountable shift transitions between outgoing and incoming security guards with real-time snapshot counts, unresolved incident carryover, immutable post-acknowledgment locking, and administrative auditability.

## Database Tables
- `sms_gate_handovers`: Stores `gate_id`, `outgoing_guard_id`, `incoming_guard_id`, `shift_name`, `handover_type` (`Normal`/`Emergency`), snapshot counters (`pending_visitors_count`, `pending_parcels_count`, `unresolved_incidents_count`), `shift_notes`, `suspicious_activity_notes`, `equipment_issues`, `next_guard_instructions`, `discrepancy_notes`, `status` (`Pending_Ack`, `Acknowledged`, `Discrepancy_Reported`), `started_at`, and `acknowledged_at`.
- `sms_gate_incidents`: Tracks open, investigating, and resolved gate incidents across shifts.

## Workflows
1. **Outgoing Guard (`api/guard_handover.php` -> `start_handover`)**:
   - Captures authoritative pending approvals, parcels, and open incident counts for the guard's authorized gate.
   - Records notes, suspicious activity, hardware issues, and instructions.
   - Dispatches `gate_handover` notification to guards and security managers and logs to `sms_audit_logs`.
2. **Incoming Guard (`acknowledge_handover` / `report_discrepancy`)**:
   - Reviews pending handover card in Guard Terminal (`/guard/dashboard` -> Shift Handover).
   - Either confirms receipt (`Acknowledged`) or logs discrepancies (`Discrepancy_Reported`, notifying admins).
   - **Immutability**: Once acknowledged/reported, subsequent edit requests return `403 Forbidden`.
3. **Emergency Handover**:
   - Available on both Guard Terminal (`emergency_handover`) and Admin Console (`/admin/gates` -> `create_emergency_handover`) when the outgoing guard is unavailable.
4. **Admin Oversight & CSV Export**:
   - Search handovers by guard, filter by date or shift, view missing 24h handover alerts, resolve incidents, and download CSV reports via `/api/admin_manage_gates.php?export=handovers_csv`.
