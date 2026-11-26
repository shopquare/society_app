# Polls & Voting System — SecureGate v2

## Overview
Provides society-wide surveys and formal gated-community elections (`/admin/polls`, `/resident/community?tab=polls`, `api/community_polls.php`).

## Governance & Security Rules
- **Poll Types**: General Survey, Society Improvement, Facility Preference, Festival Planning, Society Election, Committee Decision.
- **Election Safeguard**: `Society Election` polls require explicit `is_governance_certified` confirmation before publishing.
- **Duplicate Prevention**: Atomic `UNIQUE (poll_id, user_id)` constraint in `sms_poll_voters` paired with `FOR UPDATE` row locking prevents repeat votes.
- **Anonymous Confidentiality**: When `is_anonymous = 1`, voter participation is recorded in `sms_poll_voters` while `sms_poll_votes.user_id` is stored strictly as `NULL`.
- **Result Publication Controls**: Supports `Immediate`, `After_Close`, and `Admin_Only` visibility plus CSV export.
