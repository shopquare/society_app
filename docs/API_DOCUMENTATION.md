# API Documentation

All API endpoints are located under `/society_app/api/` and return `application/json` (except `api/logout.php`, which issues an HTTP redirect).

## Authentication & Public Endpoints

| Endpoint | Method | Auth | CSRF | Request Body (JSON) | Response |
|---|---|---|---|---|---|
| `api/verify_society.php` | POST | Public | No | `{ "society_code": "STRING" }` | `200` `{ status, message, data: { society_id, society_name, city } }` |
| `api/register.php` | POST | Public | No (sent by UI, ignored) | `{ societyId, email, password, firstName, lastName, phone, occupancyType, block, unitNumber, leaseExpiry }` | `201` `{ status, message }` |
| `api/login.php` | POST | Public | No (sent by UI, ignored) | `{ email, password }` | `200` `{ status, message, data: { redirect, firstName } }` |
| `api/logout.php` | GET | Any | No | None | `302` redirect to `../login` |

## Shared Authenticated Endpoints

| Endpoint | Method | Auth | CSRF | Request | Response |
|---|---|---|---|---|---|
| `api/get_notices.php` | GET | `$_SESSION['logged_in']` | N/A | None | `200` `{ status, data: [{ id, title, content, created_at, first_name, last_name }] }` |

## Admin / Committee Endpoints (`role_id` 1 or 2)

| Endpoint | Method | CSRF | Payload / Query | Notes |
|---|---|---|---|---|
| `api/admin_get_pending.php` | GET | N/A | None | Returns `pending` users in `$_SESSION['society_id']` |
| `api/admin_get_users.php` | GET | N/A | None | Returns non-pending users joined with `sms_user_units` |
| `api/admin_update_status.php` | POST | Yes | `{ user_id, status: "active"|"rejected", csrf_token }` | Updates `sms_users.status` scoped to `society_id` |
| `api/admin_get_guards.php` | GET | N/A | None | Lists users with `role_id = 4` in `society_id` |
| `api/admin_create_guard.php` | POST | Yes | `{ first_name, last_name, email, phone_number, csrf_token }` | Creates active guard with default password `'guard123'` |
| `api/admin_create_notice.php` | POST | Yes | `{ title, content, csrf_token }` | Inserts into `sms_notices` |
| `api/admin_get_invoices.php` | GET | N/A | None | Mutates overdue `Unpaid` invoices, returns society ledger |
| `api/admin_create_invoice.php` | POST | Yes | `{ resident_id ("ALL"\|int), title, amount, due_date, csrf_token }` | Single or transactional bulk invoice generation |
| `api/admin_get_complaints.php` | GET | N/A | None | Lists all complaints in `society_id` |
| `api/admin_update_complaint.php` | POST | Yes | `{ complaint_id, status: "Open"|"In Progress"|"Resolved", csrf_token }` | Updates complaint status scoped to `society_id` |
| `api/admin_get_society.php` | GET | N/A | None | Returns `sms_societies.*` for `$_SESSION['society_id']` |
| `api/admin_update_society.php` | POST | Yes | `{ society_name, address, city, state, zip_code, csrf_token }` | Updates society profile |
| `api/admin_reset_invite.php` | POST | Yes | `{ csrf_token }` | Generates 8-char uppercase hex `society_code` |

## Resident Endpoints (`role_id` 3)

| Endpoint | Method | CSRF | Payload | Notes |
|---|---|---|---|---|
| `api/resident_get_invoices.php` | GET | N/A | None | Auto-marks overdue, returns resident's invoices |
| `api/resident_pay_invoice.php` | POST | Yes | `{ invoice_id, csrf_token }` | Marks unpaid/overdue invoice as `'Paid'` |
| `api/resident_create_complaint.php` | POST | Yes | `{ category, description, csrf_token }` | Inserts `'Open'` complaint |
| `api/resident_get_complaints.php` | GET | N/A | None | Returns resident's complaints |
| `api/resident_generate_pin.php` | POST | Yes | `{ visitor_name, valid_date, csrf_token }` | Generates 6-digit `'Active'` PIN in `sms_visitor_passes` |
| `api/resident_get_pending_visitors.php` | GET | N/A | None | Checks `$_SESSION['user_id']` (no explicit `role_id` check); returns `'Pending'` visitors |
| `api/resident_handle_visitor_approval.php` | POST | **No** | `{ visitor_id, action: "Approve"\|string }` | Sets status `'Entered'` or `'Denied'`; lacks CSRF & `role_id` check |
| `api/save_push_subscription.php` | POST | Yes | `{ subscription, csrf_token }` | Upserts push JSON in `sms_user_push_subscriptions` |
| `api/update_visitor_status.php` | POST | **No** | `{ visitor_id, status }` | **CRITICAL**: No authentication or ownership check; called by `sw.js` |

## Guard Endpoints (`role_id` 4)

| Endpoint | Method | Auth Check | CSRF | Payload | Notes |
|---|---|---|---|---|---|
| `api/guard_get_visitors.php` | GET | `role_id == 4` | N/A | None | Today's visitors in `society_id` |
| `api/guard_log_visitor.php` | POST | **Partial** (`society_id` & `user_id` only; no `role_id` check) | **No** | `{ resident_id, visitor_name, purpose, photo_base64 }` | Writes PNG, inserts visitor (`phone_number` ignored!), sends Web Push |
| `api/guard_verify_pin.php` | POST | `role_id == 4` | Yes | `{ pin_code, csrf_token }` | Validates active PIN (`valid_date >= CURDATE()`) and marks `'Used'` |
| `api/guard_visitor_exit.php` | POST | `role_id == 4` | Yes | `{ visitor_id, csrf_token }` | Marks `'Entered'` visitor as `'Exited'` with `exit_time = NOW()` |

## Error Handling & Rate Limiting
- **Error Handling**: All endpoints catch `PDOException` (or `Exception`) and return HTTP `500` or `400` with raw `$e->getMessage()` exposed in JSON.
- **Rate Limiting / Timeouts**: None implemented in PHP; Web Push calls in `guard_log_visitor.php` execute synchronously during the HTTP request without an explicit timeout configuration.
