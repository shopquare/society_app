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
| `api/guard_log_visitor.php` | `POST` | Logs visitor entry with live WebRTC camera photo, separate `visitor_name` and `visitor_tag`, enforces 10MB photo & 10-digit phone |
| `api/guard_get_visitors.php` | `GET` | Fetches today's visitor log with pagination (`limit`, `offset`), tags, and status tracking |
| `api/guard_visitor_exit.php` | `POST` | Records exit timestamp (`exit_time`) and marks visitor status as `'Exited'` |
| `api/guard_verify_pin.php` | `POST` | Verifies 6-digit guest fast-track PIN, enforces 48-hour auto-expiry, rejects expired/used passes |
| `api/guard_parcels.php` | `GET`/`POST` | Manages gate parcel locker entries and marks resident pickups |
| `api/guard_get_emergency_alerts.php` | `GET` | Real-time guard feed for active emergency alerts with coordinates, type, resident contact |
| `api/guard_acknowledge_emergency.php` | `POST` | Updates emergency lifecycle (`Acknowledged`, `Responding`, `Resolved`, `Cancelled`) with guard assignment |

---

## 3. Resident Endpoints (`role_id = 3`)

| Endpoint | Method | Description |
| :--- | :---: | :--- |
| `api/resident_get_pending_visitors.php` | `GET` | Real-time polling endpoint (4s) for incoming visitors awaiting approval |
| `api/resident_handle_visitor_approval.php` | `POST` | Submits resident decision (`'Entered'` / `'Denied'`) with action token |
| `api/resident_generate_pin.php` | `POST` | Generates 6-digit guest entry PIN with strict 48-hour expiry and WhatsApp invite link |
| `api/resident_save_delivery_preapproval.php` | `POST` | Creates 1-tap pre-cleared pass for Swiggy, Zomato, Amazon, Blinkit, etc. |
| `api/resident_trigger_emergency.php` | `POST` | Multi-category SOS alert (Medical, Fire, Threat, Accident, Theft, Other) with GPS geolocation |
| `api/resident_get_emergency_status.php` | `GET` | Returns live status of active emergency incident response |
| `api/resident_facility_booking.php` | `GET`/`POST` | Lists society amenities and reserves booking slots with double-booking prevention |
| `api/resident_manage_family.php` | `GET`/`POST`/`DELETE` | Roster of unit family members with emergency contact flag and phone validation |
| `api/resident_manage_staff.php` | `GET`/`POST` | Verified domestic daily help directory (Maid, Driver, Cook, Cleaner) with 1-tap mapping |
| `api/marketplace_get_listings.php` | `GET` | Scoped marketplace listings for own society with category & condition filters |
| `api/marketplace_create_listing.php` | `POST` | Posts buy/sell item with up to 5 photos, price, condition, contact options |
| `api/marketplace_action.php` | `POST` | Manages listings: mark sold, delete, toggle favorites, report inappropriate content |
| `api/resident_submit_ad.php` | `POST` | Submits resident advertising campaign with manual UPI / bank payment verification |
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
| `api/admin_manage_company_ads.php` | `GET`/`POST` | CRUD for company advertisements (Zomato, Swiggy, Blinkit, local businesses), scheduling, priority, targeting, logo/banner uploads, and KPI metrics |
| `api/track_ad_event.php` | `GET`/`POST` | Telemetry endpoint recording impressions and clicks in `sms_advertisement_analytics` with optional 302 click redirect |
| `api/admin_manage_ads.php` | `GET`/`POST` | Manages Google AdSense settings (Publisher ID, Slot ID) and verifies resident ad payments |
| `api/get_advertisements.php` | `GET` | Serves custom banners, brand carousel, and offers & deals; strictly excludes emergency/auth/gate screens |

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
