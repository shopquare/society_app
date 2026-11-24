# UI / UX Review

## 1. Visual Design, Typography & Consistency
- **Strengths**: Active views share a coherent, clean palette (`brand-900: #1E3A8A`, `slate-50` backgrounds, white rounded cards) and clear visual hierarchy.
- **Inconsistencies**:
  - `modules/admin/*.php` loads `/society_app/assets/js/tailwind-config.js` referencing `Inter` and `Playfair Display`, **but omits the Google Fonts `<link>` tag** present in `auth/`, `guard/`, and `resident/` views—causing fallback system serif/sans fonts on Admin screens.
  - Resident portal uses `bg-brand-900` navbar while Admin and Guard use `bg-slate-900`.
  - Notifications/alerts are inconsistent: `login.php` uses inline Tailwind alert banners, while `admin/*`, `guard/*`, and `resident/*` rely on jarring native browser `alert()` and `confirm()` popups.

## 2. Navigation & Information Architecture
- **Broken / Missing Links**:
  - **Resident Financials Unreachable**: `modules/resident/dashboard.php` renders "My Maintenance Bills" with **no link or button** to `/society_app/resident/financials`, leaving residents unable to navigate to the "Pay Now" screen.
  - **Forgot Password Dead Link**: `modules/auth/login.php` links "Forgot Password?" to `#`.
  - **Guard Name Missing on Mobile**: Guard and Resident navbars hide user name/unit (`hidden sm:block`), showing only title + logout icon on mobile.
  - **Admin Directory Lacks Search/Filter**: `modules/admin/users.php` has no search box or status filter (`Active` vs `Rejected`), unlike `modules/admin/financials.php`.

## 3. Forms, Validation & Feedback States
- **Loading States**: Well implemented across tables (animated SVG spinners) and submit buttons (`Verifying...`, `Generating...`, `disabled = true`).
- **Empty States**: Clean empty-state cards/rows implemented on Admin Pending Requests, Notices, Complaints Kanban, and Resident tables.
- **Error & Success States**:
  - `modules/resident/dashboard.php` **fails silently** if `fetchMyTickets()` or `checkPendingVisitors()` fails in certain branches, and its profile SQL error is swallowed silently.
  - `modules/guard/dashboard.php` `markExit()` gives **no error alert** when `response.ok` is false (only catches network errors), and `btn` stays stuck on `'...'` if the API returns 400/500!
  - `modules/admin/complaints.php` has no error UI if `fetchComplaints()` fails (`console.error` only).
- **Forms**:
  - `modules/guard/dashboard.php` uses `<input list="unit-list">` (`<datalist>`), which has poor UX on mobile Safari/Chrome (browser dropdown behavior varies and requires an exact string match `"Unit X - Name"`).
  - `modules/auth/register.php` "Remember me" on login has no effect; Step 2 `last_name` is optional in UI even though Step 2 doesn't mark fields clearly with `*` (unlike `settings.php`).

## 4. Modals, Dropdowns & Accessibility
- **Modal Accessibility (`resident/dashboard.php`)**: `#complaint-modal` cannot be dismissed with `Escape` or backdrop click, lacks `role="dialog"` / `aria-modal="true"`, and does not trap focus.
- **ARIA & Labels**:
  - Form `<label>` elements lack `for` attributes matching input `id`s across almost all views.
  - Icon-only buttons (logout links, mobile hamburger `#mobile-menu-btn`, modal close `×`, password eye toggle) lack `aria-label` attributes (`logout` has `title="Log Out"` only on desktop).
- **Continuous Chime UX (`resident/dashboard.php`)**: `startContinuousAlert()` rings every 3 seconds indefinitely while a visitor is `'Pending'`, with no "Mute / Snooze" button.

## 5. UX Recommendations (No Redesign Required)
1. Add a prominent **"View All & Pay Bills →"** link in `resident/dashboard.php`'s Maintenance Bills card header.
2. Replace native `alert()`/`confirm()` with lightweight toast/modal notifications.
3. Add the Google Fonts `<link>` to Admin pages and `aria-label` / `for` attributes across forms and icon buttons.
4. Fix `markExit()` error handling in `guard/dashboard.php` so failed requests restore the button and display the server message.
5. Replace `<datalist>` unit selection with a searchable select component or store `resident_id` cleanly.
