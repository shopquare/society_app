# SocietyHub (`society_app`) — Technical Documentation Index

Welcome to the technical documentation for **SocietyHub / Society Management System**, a multi-tenant residential housing society and gate security management portal built with PHP, MySQL, Tailwind CSS, and Web Push PWA APIs.

> [!WARNING]
> **Pre-Production Audit Status**: This repository is currently in an **MVP / Pre-Production** state with critical security and functional defects (including an unauthenticated visitor status endpoint, hardcoded credentials/VAPID keys, and a broken Guard login redirect). Review [`SECURITY_AUDIT.md`](./SECURITY_AUDIT.md), [`BUG_REPORT.md`](./BUG_REPORT.md), and [`GIT_GUIDE.md`](./GIT_GUIDE.md) before committing or deploying.

## Documentation Index

### Overview & Architecture
- [`PROJECT_OVERVIEW.md`](./PROJECT_OVERVIEW.md) — Executive & non-technical overview, target users, feature status summary.
- [`ARCHITECTURE.md`](./ARCHITECTURE.md) — System architecture, front-controller routing, API structure, and Mermaid architecture diagram.
- [`WORKFLOWS.md`](./WORKFLOWS.md) — End-to-end Mermaid diagrams for Authentication, Visitor Gate Approval, Fast-Track PINs, Billing, and Helpdesk.
- [`DIRECTORY_STRUCTURE.md`](./DIRECTORY_STRUCTURE.md) — File-by-file and folder responsibility guide (including active vs legacy/unused files).
- [`CODEBASE_ANALYSIS.md`](./CODEBASE_ANALYSIS.md) — Deep-dive review of entry points, modules, APIs, coupling, and maintainability.

### Features, Roles, Data & APIs
- [`FEATURES.md`](./FEATURES.md) — Complete feature inventory with entry points, dependencies, DB tables, and defects.
- [`USER_ROLES_AND_PERMISSIONS.md`](./USER_ROLES_AND_PERMISSIONS.md) — Role definitions (`1, 2, 3, 4`), route/API authorization matrix, and privilege gaps.
- [`DATABASE_DOCUMENTATION.md`](./DATABASE_DOCUMENTATION.md) — Schema documentation (4 defined + 7 query-inferred tables), ER diagram, and integrity risks.
- [`API_DOCUMENTATION.md`](./API_DOCUMENTATION.md) — Endpoint reference for all 30 files under `api/`.
- [`DEPENDENCIES.md`](./DEPENDENCIES.md) — Composer dependencies (`composer.lock`), CDN libraries, browser APIs, and risk analysis.

### Audits & Quality Assurance
- [`SECURITY_AUDIT.md`](./SECURITY_AUDIT.md) — 16 security findings (Critical/High/Medium/Low) with exploitation scenarios and fixes.
- [`BUG_REPORT.md`](./BUG_REPORT.md) — Confirmed and potential functional bugs with file paths and line numbers.
- [`UI_UX_REVIEW.md`](./UI_UX_REVIEW.md) — UI/UX review covering navigation, forms, states, accessibility, and consistency.
- [`RESPONSIVE_AUDIT.md`](./RESPONSIVE_AUDIT.md) — Mobile, tablet, and desktop layout and overflow analysis.
- [`PERFORMANCE_AUDIT.md`](./PERFORMANCE_AUDIT.md) — Asset, polling, upload size, and SQL query performance findings.
- [`SEO_AUDIT.md`](./SEO_AUDIT.md) — Applicability assessment and public-page findings.
- [`TESTING_GUIDE.md`](./TESTING_GUIDE.md) — Safe checks executed (`php -l`, `test.php`), untested areas, and QA strategy.

### Operations, Git & Roadmap
- [`CI_CD_GUIDE.md`](./CI_CD_GUIDE.md) — GitHub Actions CI/CD setup for GitHub → InfinityFree FTP (`society.infy.click`).
- [`GIT_GUIDE.md`](./GIT_GUIDE.md) — Git initialization state, `.gitignore` coverage, and workflow.
- [`INSTALLATION_GUIDE.md`](./INSTALLATION_GUIDE.md) — Local XAMPP/Apache/MySQL installation steps.
- [`CONFIGURATION_GUIDE.md`](./CONFIGURATION_GUIDE.md) — Database, routing, VAPID Web Push, and PWA configuration.
- [`DEPLOYMENT_GUIDE.md`](./DEPLOYMENT_GUIDE.md) — Pre-deployment checklist and production hardening requirements.
- [`IMPROVEMENT_ROADMAP.md`](./IMPROVEMENT_ROADMAP.md) — Categorized prioritized roadmap and tailored future feature proposals.
- [`CHANGELOG.md`](./CHANGELOG.md) — Record of non-destructive analysis, Git setup, and documentation work.
