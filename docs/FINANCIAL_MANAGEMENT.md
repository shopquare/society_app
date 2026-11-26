# Financial Management & Accounting System (v2)

## Overview
SecureGate v2 introduces an end-to-end residential society accounting engine compatible with XAMPP and shared PHP/MySQL hosting, eliminating heavy external dependencies via a native pure-PHP PDF report generator (`includes/pdf_generator.php`).

## Key Capabilities
1. **Recurring Auto-Billing Engine (`sms_maintenance_rules`, `api/admin_financial_v2.php`)**
   - Supports unit-type billing (`1BHK`, `2BHK`, `3BHK`, `Penthouse`, `Shop`) or society-wide defaults.
   - Duplicate prevention prevents generating multiple invoices for the same resident and billing period.
2. **Server-Side Late Penalty Engine (`includes/finance_helper.php`)**
   - `calculateLatePenaltyAmount()` evaluates grace days, fixed vs. percentage penalties, `One_Time` vs. `Monthly` compounding, and configurable penalty caps (`max_penalty`) strictly using server-side dates.
3. **Partial, Advance & Refund Payment Tracking (`sms_payments`, `api/resident_payments.php`)**
   - Residents can make partial, full, or advance maintenance payments via UPI QR, Gateway, or Bank Transfer.
   - Admins can reconcile cash/cheque payments, advance credits, or issue full/partial refunds with automatic `sms_invoices` balance updates inside row-locked DB transactions (`FOR UPDATE`).
4. **Income, Expense & Annual Category Budgets (`sms_society_income`, `sms_society_expenses`, `sms_budgets`)**
   - Multi-category income & expenditure tracking with voucher/invoice attachment support via `saveBase64Upload()`.
   - Real-time Budget vs. Actual utilization tracking.
5. **Financial Transparency Mode**
   - Residents view society-wide total collections, other income, total expenditure by category, top expenditures, and budget utilization on `/resident/financials`.
   - **Strict Privacy Guarantee**: Individual unit or resident PII (names, phone numbers, email addresses, flat dues) is never exposed on transparency endpoints.
6. **PDF Receipts & Financial Statements (`includes/pdf_generator.php`)**
   - Official resident receipts (`api/resident_payments.php?action=receipt_pdf&payment_id=...`)
   - Annual/Period Income & Expense Statement PDF (`api/admin_financial_v2.php?export=income_expense_pdf`)
   - Outstanding Dues CSV Export (`api/admin_financial_v2.php?export=dues_csv`)
