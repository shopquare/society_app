# Performance Audit

## Summary
The application is lightweight on the server side due to minimal dependencies (`minishlink/web-push` + PDO), but exhibits several frontend, database-query, asset-handling, and synchronous-I/O bottlenecks that will degrade user experience as societies scale.

## Findings

### 1. Runtime Tailwind CSS Play CDN on Every Page Load
- **Location**: All views in `modules/auth/`, `modules/admin/`, `modules/guard/`, `modules/resident/`
- **Issue**: `<script src="https://cdn.tailwindcss.com"></script>` downloads and executes the full Tailwind JIT compiler in the browser on every navigation, blocking initial render and increasing mobile CPU/battery usage.
- **Recommendation**: Compile a purged, minified production CSS bundle (`assets/css/tailwind.min.css`) via Tailwind CLI during build/deployment.

### 2. Uncompressed / Unbounded Visitor Photo Uploads (32.8 MB Across 44 Files)
- **Location**: `api/guard_log_visitor.php` (lines 48–66), `modules/guard/dashboard.php` (lines 178–186), `uploads/visitors/`
- **Issue**: `canvas.toDataURL('image/png')` captures full camera resolution as lossless PNG (~500 KB to 1.1 MB per visitor). Already 44 test/visitor photos occupy **32.79 MB**. Loading `guard_get_visitors.php` renders all today's full-size PNGs as 40×40px avatars!
- **Recommendation**: Downscale canvas to max ~480px width and export as JPEG (`image/jpeg`, quality `0.75`, ~30–50 KB per photo—a 90%+ reduction), and serve thumbnails.

### 3. Synchronous Web Push Execution in `guard_log_visitor.php`
- **Location**: `api/guard_log_visitor.php` (lines 91–103)
- **Issue**: `sendOneNotification()` executes a live outbound HTTP request to the push provider synchronously before returning JSON to the Guard terminal. Slow or unreachable push endpoints stall the guard's gate-entry UI.
- **Recommendation**: Configure short Guzzle timeouts or queue push notifications asynchronously; also delete expired (`410 Gone`) push subscriptions.

### 4. Continuous Resident Polling Every 4 Seconds
- **Location**: `modules/resident/dashboard.php` (line 344)
- **Issue**: `setInterval(checkPendingVisitors, 4000)` polls `api/resident_get_pending_visitors.php` continuously—even when the browser tab is hidden/backgrounded—generating 15 DB queries per minute per open resident tab.
- **Recommendation**: Pause polling when `document.hidden` is true (`visibilitychange` event), increase interval or use exponential backoff, or leverage Web Push / Server-Sent Events (SSE).

### 5. Non-Sargable Date Query & Missing Pagination
- **Location**:
  - `api/guard_get_visitors.php` (line 22): `WHERE DATE(v.entry_time) = CURDATE()` disables B-tree index usage on `entry_time`.
  - `api/admin_get_users.php`, `api/admin_get_invoices.php`, `api/admin_get_complaints.php`, `api/get_notices.php`: No `LIMIT` or pagination; queries return entire society history.
- **Recommendation**: Rewrite date filter as `v.entry_time >= CURDATE() AND v.entry_time < DATE_ADD(CURDATE(), INTERVAL 1 DAY)`, add composite indexes on `(society_id, entry_time)`, `(society_id, status)`, `(resident_id, status)`, and implement server-side pagination.

### 6. Write Operations Inside `GET` Endpoints
- **Location**: `api/admin_get_invoices.php` (line 17), `api/resident_get_invoices.php` (line 17)
- **Issue**: Every page load runs an `UPDATE sms_invoices SET status = 'Overdue' ...` write query before `SELECT`, creating unnecessary row locks.
- **Recommendation**: Run overdue status transitions via a daily cron job or compute overdue display dynamically in SQL/UI (`status = 'Unpaid' AND due_date < CURDATE()`).

### 7. Blocking External CDN Assets Without Fallbacks
- **Location**: All views (`cdn.tailwindcss.com`, `fonts.googleapis.com`, `cdn.jsdelivr.net` in `includes/header.php`)
- **Issue**: Offline or flaky mobile connections at security gates will fail to load Tailwind styling and fonts.
- **Recommendation**: Self-host compiled CSS and fonts and cache static shell assets in `sw.js`.
