# Admin Permissions, Governance, Moderation & Reports (v2)

## Overview
SecureGate v2 replaces binary admin flags with a granular, society-isolated Role-Based Access Control (RBAC) system alongside centralized content moderation, tenant lifecycle KYC tracking, staff attendance management, and exportable compliance reports.

## Granular RBAC Architecture (`includes/permissions.php`)
- **Tables**: `sms_permissions` (29 permissions across operations, community, finance, governance, platform), `sms_admin_roles` (e.g., `Society Super Admin`, `Committee President`, `Treasurer`, `Security Convener`, `Community Moderator`), `sms_role_permissions`, `sms_user_admin_roles`.
- **Enforcement**:
  - `hasPermission(string $permissionKey)` checks effective permissions loaded into `$_SESSION`.
  - `requirePermission(string $permissionKey)` guards SSR module pages (`/admin/roles`, `/admin/moderation`, `/admin/reports`, etc.).
  - `requireApiPermission(string $permissionKey)` guards REST endpoints.
  - **Anti-Escalation Protection**: Non-platform admins cannot assign `saas.platform` (`canActorGrantPermissions()`), and committee admins can only delegate permissions they themselves possess.

## Moderation & Tenant Verification Console (`/admin/moderation`, `api/admin_moderation_center.php`)
- Unified review queues for:
  - Marketplace item reports (Dismiss or Archive listing)
  - Society Discussion reports (Dismiss or Remove thread)
  - Local Service Provider reports (Dismiss or Disable provider)
- Resident & Tenant KYC Lifecycle Tracker (`sms_user_units.verification_status`, `lease_end_date`, `kyc_notes`) supporting Move-In Verification, Lease Expiry tracking, and Move-Out completion.

## Staff & Attendance Management (`/admin/staff`, `api/admin_manage_staff.php`)
- Manage Security Guards, Daily Help / Domestic Staff pass codes (`sms_daily_staff`, `sms_domestic_staff`), and shift Check-In / Check-Out attendance (`sms_staff_attendance`).

## Automated Reports & Audit Logs (`/admin/reports`, `api/admin_reports.php`, `api/admin_get_audit_logs.php`)
- Filterable reports exportable directly to **CSV** (with spreadsheet formula-injection sanitization) and **Pure-PHP PDF**:
  1. `security_activity`: Visitor entries across gates
  2. `gate_handovers`: Guard shift handover compliance & discrepancies
  3. `financial_collection`: Maintenance billing & collection status
  4. `overdue_payments`: Outstanding member dues aging list
  5. `staff_attendance`: Guard & society staff shift presence
- **Compliance Audit Explorer**: Paginated inspection of immutable entries in `sms_audit_logs`.
