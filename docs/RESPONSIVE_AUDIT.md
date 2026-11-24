# Mobile & Responsive Design Audit

## Summary
The UI uses Tailwind CSS responsive utilities (`sm:`, `md:`, `lg:`) and a mobile viewport meta tag (`width=device-width, initial-scale=1.0`) across all active views. Overall mobile structure is functional, with specific layout, table-usability, touch-target, and overflow defects identified below.

## Findings by Screen & Component

### 1. Auth Pages (`modules/auth/login.php`, `modules/auth/register.php`)
- **Positive**: Centered single-column cards (`max-w-md` / `max-w-xl`) with `p-6` padding adapt cleanly to mobile and desktop.
- **Issue (Mobile Grid Cramping)**: In `modules/auth/register.php` (lines 117, 131, 163), Password/Confirm, First/Last Name, Owner/Tenant radios, and Block/Unit use `grid-cols-2` without a `grid-cols-1 sm:grid-cols-2` breakpoint, squeezing two inputs side-by-side on narrow 320px–375px screens.
- **Issue (Small Touch Targets)**: Password visibility eye button and "Remember me" checkbox are small (`w-4 h-4` / icon-only) without `min-h-[44px]` padding.

### 2. Admin Portal (`modules/admin/*.php`)
- **Positive**: `modules/admin/navbar.php` provides a clean hamburger toggle (`md:hidden`) and slide-down mobile menu; `complaints.php` switches from vertical stack (`flex-col`) on mobile to 3-column Kanban (`lg:grid-cols-3`) on desktop.
- **Issues**:
  - **`dashboard.php` (line 54)**: Forces `min-w-[800px]` inside `overflow-x-auto`, requiring horizontal scrolling on mobile and tablet portrait views.
  - **`users.php`, `financials.php`, `guards.php`**: Tables rely on `overflow-x-auto` + `whitespace-nowrap`, which causes horizontal table scrolling on `< 768px` screens instead of responsive stacked card layouts.
  - **Admin `navbar.php` (line 34)**: Hides user name (`hidden sm:block`) and desktop logout button (`hidden sm:block`) on mobile, moving logout inside the hamburger menu (works, though `mobile-menu-btn` lacks `aria-expanded` / `aria-label`).
  - **`financials.php` & `notices.php` & `guards.php` (`sticky top-24`)**: Form cards work well on desktop (`lg:col-span-1`), and stack above lists on mobile.

### 3. Resident Portal (`modules/resident/dashboard.php`, `modules/resident/financials.php`)
- **Positive**: Header stacks cleanly (`flex-col sm:flex-row`), gate alert cards switch from vertical (`flex-col`) to horizontal (`sm:flex-row`), and Quick Actions use a 2-column grid.
- **Issues**:
  - **Missing Mobile Nav / Financials Link**: `resident/dashboard.php` shows the resident name/unit only on `hidden sm:block` (hidden on mobile header) and provides no navigation link to `/resident/financials` on any screen size.
  - **Modal Overflow (`resident/dashboard.php` line 302)**: `#complaint-modal` lacks `overflow-y-auto p-4` scroll handling for short landscape mobile viewports.
  - **Tables**: Maintenance Bills, Guest Passes, and Maintenance Tickets use `overflow-x-auto whitespace-nowrap`, forcing horizontal swipe on mobile.
  - **Unused `includes/header.php` Warning**: `includes/header.php` sets `h-screen overflow-hidden` on `<body>`, which would clip content if used (currently unused by active views).

### 4. Guard Gatekeeper Terminal (`modules/guard/dashboard.php`)
- **Issues**:
  - **Camera Buttons (`aspect-video`)**: On narrow phones, `Start Camera`, `Take Photo`, and `Retake` buttons overlay the bottom of the video feed; `Take Photo` could benefit from larger thumb-friendly touch sizing (`py-3 px-6`) for one-handed gate operation.
  - **Today's Visitors Table**: Uses horizontal scroll (`overflow-x-auto whitespace-nowrap`) rather than mobile-friendly visitor cards, making it harder for guards using phones/tablets at the gate to read Overstay badges and tap `Mark Exit`.

### 5. Accessibility & Typography
- **Font Sizing**: Several secondary labels and badges use `text-[10px]` / `text-[11px]` (below 12px readability threshold on mobile).
- **Native `alert()` / `confirm()`**: Heavy reliance on blocking browser `alert()` and `confirm()` dialogs across all modules degrades mobile PWA UX.
- **Accessibility**: Interactive icon buttons (logout, modal close, password eye toggle, mobile hamburger) lack `aria-label` attributes, and form inputs lack explicit `for`/`id` label associations.
