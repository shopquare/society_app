# Vendor & Contractor Accounting (v2)

## Overview
The Vendor Management module (`/admin/vendors`) tracks external society service contracts, AMC vendors, security agencies, housekeeping contractors, and utility suppliers (`sms_vendors`, `sms_vendor_payments`).

## Permissions Required
- **View Vendor Ledger**: `finance.view`
- **Add / Edit Vendors & Record Vendor Payouts**: `vendors.manage`

## Workflow
1. **Vendor Directory (`sms_vendors`)**:
   - Store vendor name, service category (`Security Agency`, `Housekeeping`, `Lift & AMC`, `Water Tanker`, `Plumbing & Electrical`, `Gardening`, `Other`), contact person, phone number, and optional GST/PAN identifier.
2. **Vendor Invoice & Payout Tracking (`sms_vendor_payments`)**:
   - Log vendor invoices with amount due, partial or full payment amount, payment method (`Bank_Transfer`, `Cheque`, `UPI`, `Cash`), due date, and optional base64 invoice/voucher scan (`uploads/vouchers/`).
   - Automatic payment status resolution (`Pending`, `Partial`, `Paid`).
3. **Expense Integration**:
   - Society expenses recorded in `api/admin_accounting.php` can link directly to a registered `vendor_id` for unified audit trails and vendor spend reporting.
