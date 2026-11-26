# API Documentation — SecureGate

All JSON API endpoints are located under `api/` and initialize through `includes/api_bootstrap.php`. Requests and responses strictly use `Content-Type: application/json; charset=utf-8`.

---

## 1. Authentication & Public Endpoints

| Endpoint | Method | Auth Scope | CSRF | Parameters / Body (JSON) | Description |
| :--- | :---: | :---: | :---: | :--- | :--- |
| `api/verify_society.php` | `POST` | Public | Optional | `{"society_code": "SUNSHINE2026"}` | Validates society invite code during signup |
| `api/register.php` | `POST` | Public | Yes | `{"societyId", "email", "password", "firstName", "lastName", "phone", "occupancyType", "block", "unitNumber"}` | 3-step resident registration |
| `api/login.php` | `POST` | Public | Yes | `{"email", "password"}` | Authenticates user, rotates session ID, returns role redirect |
| `api/logout.php` | `POST` | All | Yes | `{"csrf_token"}` | Destroys session, deletes cookies, and redirects to `/login` |

---

## 2. Gate & Security Guard Endpoints (`role_id = 4`)

| Endpoint | Method | Description |
| :--- | :---: | :--- |
| `api/guard_log_visitor.php` | `POST` | Logs visitor entry with live base64 camera photo, triggers resident push & native alert |
| `api/guard_get_visitors.php` | `GET` | Fetches today's visitor log with pagination (`limit`, `offset`) and status tracking |
| `api/guard_visitor_exit.php` | `POST` | Records exit timestamp (`exit_time`) and marks visitor status as `'Exited'` |
| `api/guard_verify_pin.php` | `POST` | Verifies 6-digit guest fast-track PIN and auto-fills visitor form |
| `api/guard_parcels.php` | `GET`/`POST` | Manages gate parcel locker entries and marks resident pickups |

---

## 3. Resident Endpoints (`role_id = 3`)

| Endpoint | Method | Description |
| :--- | :---: | :--- |
| `api/resident_get_pending_visitors.php` | `GET` | Real-time polling endpoint (4s) for incoming visitors awaiting approval |
| `api/resident_handle_visitor_approval.php` | `POST` | Submits resident decision (`'Entered'` / `'Denied'`) with action token |
| `api/resident_generate_pin.php` | `POST` | Generates 6-digit fast-track guest entry PIN for expected visitors |
| `api/resident_save_delivery_preapproval.php` | `POST` | Creates 1-tap pre-cleared pass for Swiggy, Zomato, Amazon, Blinkit, etc. |
| `api/resident_trigger_emergency.php` | `POST` | Red panic alarm instantly broadcasting emergency alert to gate & admin |
| `api/resident_get_invoices.php` | `GET` | Retrieves resident maintenance bills and payment history |
| `api/resident_pay_invoice.php` | `POST` | Marks maintenance invoice as paid with transaction ledger update |
| `api/resident_create_complaint.php` | `POST` | Submits maintenance/repair ticket by category |
| `api/resident_get_complaints.php` | `GET` | Lists tickets raised by resident with live status |
| `api/resident_vehicles.php` | `GET`/`POST` | Registers resident vehicles and maps allocated parking slots |
| `api/resident_get_parcels.php` | `GET` | Views held deliveries in the gate parcel locker |

---

## 4. Committee & Super Admin Endpoints (`role_id = 1, 2`)

| Endpoint | Method | Description |
| :--- | :---: | :--- |
| `api/admin_get_pending.php` | `GET` | Returns residents awaiting onboarding approval (`WHERE status = 'pending'`) |
| `api/admin_get_users.php` | `GET` | Returns all processed active residents for the Resident Directory |
| `api/admin_update_status.php` | `POST` | Approves (`'active'`) or rejects (`'rejected'`) a resident registration |
| `api/admin_get_guards.php` | `GET` | Lists all security guard terminal accounts |
| `api/admin_create_guard.php` | `POST` | Provisions a new guard account with credentials and profile |
| `api/admin_create_notice.php` | `POST` | Broadcasts an announcement to the society notice board |
| `api/admin_get_invoices.php` | `GET` | Returns complete billing ledger, auto-marks overdue bills, supports CSV export |
| `api/admin_create_invoice.php` | `POST` | Issues single or bulk ("ALL") maintenance invoices |
| `api/admin_get_complaints.php` | `GET` | Returns all society complaints for Kanban board |
| `api/admin_update_complaint.php` | `POST` | Updates complaint status (`'Open'`, `'In Progress'`, `'Resolved'`) |
| `api/admin_get_society.php` | `GET` | Returns society profile details and current invite code |
| `api/admin_update_society.php` | `POST` | Updates complex name, address, city, and settings |
| `api/admin_reset_invite.php` | `POST` | Regenerates secret 8-character society registration invite code |
| `api/admin_get_audit_logs.php` | `GET` | Returns immutable security log trail with IP addresses and actor IDs |

---

## 5. Media & Streaming Endpoints

| Endpoint | Method | Auth | Description |
| :--- | :---: | :---: | :--- |
| `api/get_visitor_photo.php` | `GET` | Signed Token | Streams compressed visitor camera photo with HMAC token authorization |
| `/download/apk` | `GET` | Public | Streams latest `SecureGate.apk` binary package with `application/vnd.android.package-archive` |

---

## 6. Standardized Response Format

### Success Response (`200 OK` / `201 Created`):
```json
{
  "status": "success",
  "message": "Operation completed successfully.",
  "data": { ... }
}
```

### Error Response (`400`, `401`, `403`, `404`, `429`, `500`):
```json
{
  "status": "error",
  "message": "Descriptive, safe error message without internal stack traces."
}
```
