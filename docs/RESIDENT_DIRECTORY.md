# Resident Directory — SecureGate v2

## Overview
Privacy-first searchable society neighbor directory (`api/resident_directory.php`, `/resident/directory`).

## Privacy & Anti-Scraping Safeguards
- Stored preferences in `sms_resident_privacy_settings`: `show_in_directory` (default 1), `show_phone` (default **0**), `show_email` (default **0**), `show_family` (default **0**).
- Enforces strict society membership check, per-page result cap (`20` rows/page), and per-user rate limiting (`checkRateLimit`) to prevent bulk directory scraping.
