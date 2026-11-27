# SecureGate Design System

The SecureGate design system unifies all Admin, Platform, Resident, Guard, and Authentication portals on Tailwind CSS v3 + CSS Custom Properties (`assets/css/style.css`) and `assets/js/tailwind-config.js`.

---

## 1. Color Palette

### Brand Scale (`brand-*` in `assets/js/tailwind-config.js`)
| Token | Hex | CSS Variable | Usage |
|---|---|---|---|
| `brand-50` | `#EFF6FF` | `--sg-brand-50` | Subtle highlights, active menu tint, badges |
| `brand-100` | `#DBEAFE` | `--sg-brand-100` | Border accents, light text on `brand-900` headers |
| `brand-200` | `#BFDBFE` | — | Card highlights, focus rings |
| `brand-600` | `#2563EB` | — | Interactive hover borders |
| `brand-700` | `#1D4ED8` | `--sg-brand-700` | Keyboard `:focus-visible` outline |
| `brand-800` | `#1E40AF` | `--sg-brand-800` | Primary button hover state |
| `brand-900` | `#1E3A8A` | `--sg-brand-900` | Primary brand actions, Resident Portal header, CTAs |
| `brand-950` | `#172554` | — | Resident mobile drawer background |

### Semantic & Neutral Surfaces
| Role | Tailwind / CSS Token | Usage |
|---|---|---|
| Page Canvas | `bg-slate-50` (`#F8FAFC`) | Global body background across all portals |
| Elevated Card | `bg-white` (`#FFFFFF`) / `.sg-card` | Primary content cards, forms, tables |
| Structural Border | `border-slate-200` (`#E2E8F0`) | Cards, dividers, table headers |
| Admin / Guard Shell | `bg-slate-900` (`#0F172A`) | Admin navbar, Guard terminal bar, Resident desktop sidebar |
| Success | `emerald-600` / `emerald-700` | Verified badges, paid receipts, confirmations |
| Warning / Promo | `amber-500` / `amber-100` | Pending states, sponsored badges, shift handovers |
| Danger / SOS | `rose-600` / `rose-700` | Emergency SOS panic controls, destructive confirmations |

---

## 2. Typography

* **Sans-Serif (UI & Forms):** `'Inter', system-ui, -apple-system, sans-serif` (`font-sans`)
* **Display Serif (Headings & Brand):** `'Playfair Display', Georgia, serif` (`font-serif`)

### Hierarchy Scale
* **Page `<h1>`:** `text-2xl sm:text-3xl font-bold font-serif text-slate-900`
* **Section `<h2>`:** `text-lg sm:text-xl font-bold font-serif text-slate-900`
* **Card / Modal `<h3>`:** `text-base font-bold font-serif text-slate-900`
* **Body & Table Content:** `text-sm` (`14px`) default; `text-xs` (`12px`) for compact data tables and metadata
* **KPI Metric Values:** `text-2xl font-black`
* **Mobile Input Floor:** Inputs, selects, and textareas enforce `font-size: 16px` under `@media (max-width: 767px)` in `assets/css/style.css` to prevent automatic Safari/WebView zoom.

---

## 3. Spacing, Radius & Elevation

* **Container Width:** `max-w-7xl mx-auto px-4 sm:px-6 lg:px-8` (compact sub-views use `max-w-5xl` or `max-w-6xl`).
* **Card Radius:** `rounded-xl` (`0.75rem`) for standard cards; `rounded-2xl` (`1rem`) for modals and hero containers.
* **Control Radius:** `rounded-lg` (`0.5rem`) for inputs, selects, and buttons.
* **Shadows:**
  * KPI cards: `shadow-2xs` (`0 1px 2px rgba(15,23,42,0.04)`)
  * Content cards: `shadow-xs` / `shadow-sm`
  * Modals & popovers: `shadow-2xl`

---

## 4. Shared Components

* **Navigation:**
  * Admin & Platform: `modules/admin/navbar.php` (permission-gated dropdown groups, click + hover + Escape keyboard controls, mobile drawer).
  * Resident Secondary Modules: `modules/resident/navbar.php` (unified module pill switcher, mobile grid drawer, notification bell, logout).
* **Notifications (`includes/notification_widget.php`):** Live polling unread counter, priority color-coded feed, mobile-safe inset drawer, and channel preferences modal.
* **Confirmation & Prompt Dialogs (`assets/js/app.js`):**
  * `showConfirmDialog({ title, message, confirmText, cancelText, danger })`
  * `showPromptDialog({ title, message, placeholder, defaultValue, confirmText, required })`
  * `showToast(message, type)` (`success`, `error`, `warning`, `info`)
  * `openPhotoLightbox(url, name, meta)`
* **Accessibility Utilities (`assets/css/style.css`):** `.sg-skip-link`, global `:focus-visible` outline (`2px solid #1D4ED8`), `.sg-scroll-thin`, and `.sg-skeleton` shimmer loader.
