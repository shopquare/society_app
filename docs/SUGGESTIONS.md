# Suggestions Box — SecureGate v2

## Overview
Provides a structured resident improvement portal (`api/community_suggestions.php`, `/resident/community?tab=suggestions`, `/admin/community`).

## Workflow & Access Separation
- Statuses: `Submitted` -> `Under Review` -> `Approved` -> `In Progress` -> `Implemented` | `Rejected`.
- Residents may submit anonymously (`is_anonymous = 1`) and see only `admin_response` and `status`.
- Internal triage notes (`internal_notes`) and staff assignments (`assigned_to`) remain strictly restricted to `community.moderate` admins.
