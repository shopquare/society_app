# SecureGate — Technical Documentation Index

Welcome to the technical documentation for **SecureGate** (formerly SocietyHub), an enterprise multi-tenant residential housing society and security terminal platform built with PHP 8.2+, MySQL, Tailwind CSS, Web Push PWA APIs, and a native **Android App** (Kotlin + Jetpack Compose).

> [!NOTE]
> **Production Status (v1.1.0)**: The platform is fully hardened, rebranded as **SecureGate**, and deployed live at `https://society.infy.click`. All 40 security and invariant tests are passing (`tests/run_tests.php`).

---

## Documentation Directory

### 1. Overview & Architecture
* [**`PROJECT_OVERVIEW.md`**](./PROJECT_OVERVIEW.md) — Executive & non-technical overview, personas, architecture summary, and mobile app details.
* [**`ARCHITECTURE.md`**](./ARCHITECTURE.md) — System architecture, front-controller routing (`routes.php`), security layers, and Mermaid diagrams.
* [**`WORKFLOWS.md`**](./WORKFLOWS.md) — End-to-end Mermaid sequence diagrams for Visitor Gate Approval, Fast-Track PINs, Delivery Pre-Approvals, and Emergency Panic alerts.
* [**`DIRECTORY_STRUCTURE.md`**](./DIRECTORY_STRUCTURE.md) — Complete repository file & folder organization guide.
* [**`CODEBASE_ANALYSIS.md`**](./CODEBASE_ANALYSIS.md) — Deep-dive review of entry points, security middleware, and code organization.

### 2. Features, Roles, Data & APIs
* [**`FEATURES.md`**](./FEATURES.md) — Comprehensive feature matrix across Super Admin, Committee, Resident, and Guard roles.
* [**`USER_ROLES_AND_PERMISSIONS.md`**](./USER_ROLES_AND_PERMISSIONS.md) — Role definitions (`1, 2, 3, 4`), route guards, and pre-seeded test accounts.
* [**`DATABASE_DOCUMENTATION.md`**](./DATABASE_DOCUMENTATION.md) — Complete schema documentation (17 tables), foreign keys, indexes, auto-seeder, and ER diagram.
* [**`API_DOCUMENTATION.md`**](./API_DOCUMENTATION.md) — Comprehensive endpoint reference for all JSON endpoints under `api/`.
* [**`DEPENDENCIES.md`**](./DEPENDENCIES.md) — PHP Composer dependencies, Android Gradle libraries, CDN assets, and browser APIs.

### 3. Operations, CI/CD & Guides
* [**`CI_CD_GUIDE.md`**](./CI_CD_GUIDE.md) — GitHub Actions CI/CD pipeline to InfinityFree production FTP (`society.infy.click`).
* [**`INSTALLATION_GUIDE.md`**](./INSTALLATION_GUIDE.md) — Local development setup with XAMPP, Apache, MySQL, and Android Studio.
* [**`CONFIGURATION_GUIDE.md`**](./CONFIGURATION_GUIDE.md) — Environment variables (`.env`), database settings, VAPID Web Push, and Android build config.
* [**`DEPLOYMENT_GUIDE.md`**](./DEPLOYMENT_GUIDE.md) — Production hosting checklist and FTP deployment procedures.
* [**`TESTING_GUIDE.md`**](./TESTING_GUIDE.md) — Automated testing guide for the 40-assertion test suite in `tests/run_tests.php`.
* [**`GIT_GUIDE.md`**](./GIT_GUIDE.md) — Git workflow, branch hygiene, and `.gitignore` standards.
* [**`CHANGELOG.md`**](./CHANGELOG.md) — Chronological release history from v1.0.0 through v1.1.0.

### 4. Audits & Quality Reports
* [**`SECURITY_AUDIT.md`**](./SECURITY_AUDIT.md) — Security posture, implemented defenses, rate limiting, and HMAC tokens.
* [**`UI_UX_REVIEW.md`**](./UI_UX_REVIEW.md) — Interface design principles, accessibility, color palettes, and responsive layouts.
* [**`PERFORMANCE_AUDIT.md`**](./PERFORMANCE_AUDIT.md) — Asset optimization, caching policies, and query indexing.
* [**`RESPONSIVE_AUDIT.md`**](./RESPONSIVE_AUDIT.md) — Device viewport audit (mobile, tablet, desktop, and gate terminal).
* [**`SEO_AUDIT.md`**](./SEO_AUDIT.md) — Search indexing policies (`robots.txt`), OpenGraph meta tags, and PWA manifest.
* [**`IMPROVEMENT_ROADMAP.md`**](./IMPROVEMENT_ROADMAP.md) — Feature evolution plan and roadmap for future versions.
