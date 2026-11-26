# Society Discussions — SecureGate v2

## Overview
Provides a multi-tenant, moderated resident community forum (`api/community_discussions.php`, `/resident/community?tab=discussions`, `/admin/community`).

## Features & Moderation
- Categories: General Discussion, Society Improvements, Events, Recommendations, Local Services, Community Help.
- Supports post pinning, threaded comments (`parent_id`), like toggles, search, and abuse reporting (`sms_discussion_reports`).
- Protected by per-user spam throttling (`checkRateLimit`), moderator user restrictions (`sms_discussion_restrictions`), and strict `society_id` scoping.
