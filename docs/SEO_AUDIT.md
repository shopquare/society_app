# SEO Audit

## Applicability Assessment
**Mostly Not Applicable (Authenticated SaaS / Intranet Portal).**

SocietyHub is a private, multi-tenant residential portal whose core functionality sits behind authentication (`admin/`, `resident/`, `guard/`). Publicly reachable pages are limited to `/login` and `/register`. Search engine indexing of private dashboards, resident directories, or uploaded visitor photos is undesirable.

## Public-Page Findings (`/login`, `/register`)
- **Page Titles**: Present (`Society Login - Portal Access`, `Society Registration - Portal Access`), though generic and lacking society/brand naming (`SocietyHub`).
- **Meta Descriptions**: Missing on all pages (`modules/auth/login.php`, `modules/auth/register.php`).
- **Heading Hierarchy**: Clean single `<h1>` per page.
- **Canonical URLs / Open Graph / Structured Data**: Not implemented (optional for a private portal; useful only if a public landing page is added).
- **`robots.txt` & `sitemap.xml`**: **Missing.** Because `/uploads/visitors/`, `/modules/resident/login.php`, `/test.php`, and `/test_push_trigger.php` exist in the webroot, there is currently no `robots.txt` disallowing crawling of `/api/`, `/uploads/`, `/modules/`, or `/config/`.
- **Image Alt Text**: Guard table avatars (`modules/guard/dashboard.php` line 240) have generic `alt="Photo"`; resident pending visitor `<img>` (`modules/resident/dashboard.php` line 496) lacks an `alt` attribute.
- **Page Speed**: Penalized by runtime Tailwind Play CDN (`cdn.tailwindcss.com`) and external Google Fonts without pre-built CSS.

## Recommendations
1. Add a `robots.txt` (or `X-Robots-Tag: noindex, nofollow` HTTP headers) to prevent indexing of `/api/`, `/uploads/`, `/modules/`, and internal portal routes.
2. Add `<meta name="description">` to `/login` and `/register` if public discovery of the portal login is desired.
3. Add descriptive `alt` attributes to all dynamically rendered `<img>` elements.
