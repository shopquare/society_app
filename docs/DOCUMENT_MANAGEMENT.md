# Digital Society Office & Document Vault (v2)

## Overview
SecureGate v2 digitizes society paperwork into an access-controlled **Private Document Vault** (`sms_documents`) paired with formal **Resident Request Workflows** (`sms_document_requests`).

## Strict Document Privacy & Security (`uploads/private_docs/` + `api/stream_document.php`)
- All vault binaries are stored outside public listing paths in `uploads/private_docs/`, guarded by Apache `.htaccess` (`Require all denied\nDeny from all`).
- Documents can ONLY be downloaded or viewed through `api/stream_document.php`, which verifies:
  1. Active authenticated session + same `society_id`
  2. Valid non-expired HMAC SHA-256 signature bound to `(docId, userId, exp)`
  3. Visibility RBAC:
     - `Public_Members`: any approved society member
     - `Committee_Only`: requires `documents.manage` permission
     - `Resident_Private`: strictly restricted to `owner_user_id === session user` OR `documents.manage`
  4. Strict `realpath()` containment + `X-Content-Type-Options: nosniff`.

## Digital Request Workflows
Residents (`/resident/documents`) can apply for:
- **NOC (Sale / Rent / Passport)**
- **Address Proof Letter**
- **Tenant Police Verification Endorsement**
- **Move-In / Move-Out Gate Clearance**
- **Parking Allotment Request**
- **Interior Renovation Permission**

Admins (`/admin/documents`) inspect requests, attach internal-only verification remarks (`internal_remarks` hidden from residents), provide resident-facing remarks, or click **Approve & Issue Official PDF** to auto-generate a signed PDF certificate into the resident's private vault via `includes/pdf_generator.php`.
