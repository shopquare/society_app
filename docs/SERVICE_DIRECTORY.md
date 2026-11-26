# Local Services & Verified Business Directory (v2)

## Overview
SecureGate v2 equips residents with a vetted neighborhood & campus business directory covering 17 essential daily and emergency service categories (Plumbers, Electricians, Carpenters, Painters, AC/Appliance Repair, WiFi, Tutors, Domestic Helpers, Milk Suppliers, Newspapers, Laundry, Pest Control, Doctors/Pharmacies, Ambulance, Taxi/Auto, Tiffin, Pet Care).

## Key Workflows
1. **Verified & Featured Listings (`sms_service_providers`)**
   - Admins and authorized staff (`services.manage`) curate, verify (`Society Verified` badge), feature, activate, or reject providers on `/admin/services`.
   - Residents suggest new neighborhood service providers on `/resident/services`; non-privileged resident suggestions enter a `Pending` moderation queue before appearing publicly.
2. **Anti-Abuse Ratings & Reviews (`sms_service_reviews`)**
   - Enforces `UNIQUE KEY uq_svc_review (provider_id, resident_id)` so each resident can submit at most one rating/review per provider.
   - Server recalculates `avg_rating` and `review_count` inside a database transaction on every new review.
3. **Favorites & Click-to-Contact (`sms_service_favorites`)**
   - Residents bookmark favorite providers and launch one-tap phone (`tel:`) or WhatsApp (`https://wa.me/`) chats directly from mobile or desktop.
4. **Community Reporting (`sms_service_reports`)**
   - Incorrect phone numbers or service complaints are flagged to the moderation queue and handled in `/admin/services` and `/admin/moderation`.
