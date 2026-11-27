# SecureGate Responsive Design Standards

This document defines the responsive layout rules, breakpoints, and mobile-first component patterns enforced across all SecureGate views.

---

## 1. Supported Breakpoints & Target Devices

| Tier | Width Range | Tailwind Prefix | Layout Pattern |
|---|---|---|---|
| **Small Mobile** | `320px – 375px` | Default (unprefixed) | Single-column forms (`grid-cols-1`), compact topbar, off-canvas drawers, visitor card lists |
| **Standard Mobile** | `376px – 480px` | Default | 2-column KPI summary grids (`grid-cols-2`), full-width action buttons |
| **Large Mobile** | `481px – 767px` | `sm:` (`>= 640px`) | 2-column form pairs (`sm:grid-cols-2`), inline search + filter controls (`sm:flex-row`) |
| **Tablet** | `768px – 1024px` | `md:` (`>= 768px`) | Desktop data tables enabled on Guard Terminal (`md:block`), 3-column summary grids |
| **Laptop** | `1025px – 1440px` | `lg:` (`>= 1024px`) | Persistent desktop sidebar / top dropdown bar (`lg:flex`), 3-column dashboard layouts (`lg:grid-cols-3`) |
| **Large Desktop** | `1441px+` | `xl:` / `max-w-7xl` | Centered max-width container (`1280px`) preserving readable line lengths |

---

## 2. Mobile-First Viewport & Safe Area Rules

* Every view declares:
  ```html
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  ```
* Top navigation bars apply `style="padding-top: env(safe-area-inset-top, 0px);"` for notched mobile phones and Android WebView edge-to-edge rendering.
* Global `body { overflow-x: hidden; }` prevents accidental horizontal page drift from animated transitions.

---

## 3. Component Responsiveness Patterns

1. **Navigation Drawers:**
   * **Admin Portal (`modules/admin/navbar.php`):** Uses interactive dropdown menus at `lg+` and switches to a scrollable `#mobile-menu` drawer below `1024px` displaying the user's name and role.
   * **Resident Dashboard (`modules/resident/dashboard.php`):** Uses a collapsible sticky desktop sidebar (`#resident-desktop-sidebar`) at `lg+` and a slide-over drawer (`#resident-mobile-drawer`) with backdrop below `1024px`.
   * **Resident Sub-Modules (`modules/resident/navbar.php`):** Uses horizontal quick-nav pills at `lg+` and a 2-column grid mobile drawer below `1024px`.
2. **Notifications Drawer (`includes/notification_widget.php`):**
   * Uses `fixed sm:absolute inset-x-3 sm:inset-x-auto sm:right-0` so on `320px–639px` viewports the feed spans safely inside screen margins (`12px` inset on both sides) without clipping.
3. **Tables & Lists:**
   * **Guard Terminal Visitors:** Renders thumb-friendly stacked Visitor Cards (`#visitors-mobile-cards`) on `< 768px` (`md:hidden`) and the full multi-column table on `md:block`.
   * **Admin & Resident Data Tables:** Wrapped in `.overflow-x-auto.sg-scroll-thin` containers inside rounded cards so tables never force body-level horizontal overflow.
4. **Forms & Filter Bars:**
   * Multi-field rows use `grid-cols-1 sm:grid-cols-2` or `grid-cols-1 sm:grid-cols-3` (e.g., `register_society.php`, `buildings.php`, `facilities.php`, `roles.php`, `services.php`).
   * Search and filter toolbars use `flex-wrap` and `flex-col sm:flex-row` (e.g., `admin/advertisements.php`, `admin/notices.php`, `admin/reports.php`, `resident/community.php`).
5. **Touch Targets & Anti-Zoom:**
   * Under `@media (max-width: 767px)`, inputs/selects/textareas enforce `font-size: 16px` and `min-height: 42px`, while interactive controls enforce `min-height: 38px`.
   * Guard camera controls (`Flip`, `Capture`, `Close`, `Retake Photo`) and `Mark Exit` actions use generous padding (`px-4 py-2.5` / `px-6 py-2.5`).
