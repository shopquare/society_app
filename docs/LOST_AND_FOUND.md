# Lost & Found — SecureGate v2

## Overview
Enables residents and guards to report lost or found items cleanly without exposing personal phone numbers or emails (`api/community_lost_found.php`, `/resident/community?tab=lostfound`, `/admin/community`).

## Workflow & Privacy
- Status workflow: `New` -> `Under Review` -> `Claimed` -> `Returned` -> `Closed`.
- Private Claims: Claimants submit identifying proof in `sms_lost_found_claims`, visible only to authorized moderators (`community.moderate`).
- Public listings display only masked initials (`First L.`) and custody location (`With Finder / Security Desk`).
