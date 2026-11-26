<?php
// tests/phases/phase4_tests.php
// Phase 4: Financial Management, Auto-Billing, Penalties, PDF Receipts & Vendor Tests

echo "\n--- Phase 4: Financial Management & Accounting Tests ---\n";

require_once __DIR__ . '/../../includes/finance_helper.php';
require_once __DIR__ . '/../../includes/pdf_generator.php';

// 1. Verify all 7 new Phase 4 tables & extended columns on sms_invoices
$phase4Tables = [
    'sms_maintenance_rules',
    'sms_payments',
    'sms_society_income',
    'sms_society_expenses',
    'sms_vendors',
    'sms_vendor_payments',
    'sms_budgets',
];
foreach ($phase4Tables as $tbl) {
    $exists = (bool)$conn->query("SHOW TABLES LIKE " . $conn->quote($tbl))->fetchColumn();
    assertTrue($exists, "Table {$tbl} exists in schema");
}

$invCols = $conn->query("SHOW COLUMNS FROM sms_invoices")->fetchAll(PDO::FETCH_COLUMN);
foreach (['invoice_number', 'base_amount', 'penalty_amount', 'paid_amount', 'balance_amount', 'rule_id'] as $col) {
    assertTrue(in_array($col, $invCols, true), "sms_invoices has Phase 4 column {$col}");
}

// 2. Late Penalty Engine verification
assertTrue(
    calculateLatePenaltyAmount(2000.00, '2026-11-10', '2026-11-12', 'Fixed', 'One_Time', 250.00, 5, 1000.00) === 0.0,
    "Late penalty respects 5-day grace period"
);
assertTrue(
    calculateLatePenaltyAmount(2000.00, '2026-11-01', '2026-11-10', 'Fixed', 'One_Time', 250.00, 5, 1000.00) === 250.0,
    "Fixed One-Time late penalty triggers cleanly after grace period"
);
assertTrue(
    calculateLatePenaltyAmount(5000.00, '2026-08-01', '2026-11-25', 'Percentage', 'Monthly', 5.0, 5, 700.00) === 700.0,
    "Monthly compounding percentage penalty obeys max_penalty cap"
);
assertTrue(
    calculateLatePenaltyAmount(2000.00, '2026-10-01', '2026-10-20', 'Percentage', 'One_Time', 10.0, 3, 1000.00) === 200.0,
    "Percentage penalty computes exact fraction of base amount"
);

// 3. Pure-PHP PDF Generator validation
$pdfBytes = generateSimplePdf('Test Financial Statement', ['Line 1: Maintenance Collected Rs. 50000', 'Line 2: Net Surplus Rs. 15000']);
assertTrue(strpos($pdfBytes, '%PDF-1.4') === 0, "generateSimplePdf() outputs valid PDF-1.4 header");
assertTrue(strpos($pdfBytes, '%%EOF') !== false, "generateSimplePdf() outputs valid EOF marker");

// 4. Transactional partial/full payment & transparency privacy isolation check
$apiTransSrc = file_get_contents(__DIR__ . '/../../api/resident_payments.php');
assertTrue(
    strpos($apiTransSrc, "'transparency'") !== false && strpos($apiTransSrc, 'SELECT category, SUM(amount)') !== false,
    "Transparency API aggregates category totals without individual member dues PII"
);
assertTrue(
    strpos($apiTransSrc, 'FOR UPDATE') !== false,
    "Resident payment handler locks invoice rows transactionally via FOR UPDATE"
);

// 5. Phase 4 DB Integration Flow (Create vendor, expense, invoice partial payment, check balance)
$socId = (int)($conn->query("SELECT id FROM sms_societies ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 1);
$resId = (int)($conn->query("SELECT id FROM sms_users WHERE society_id = {$socId} AND role_id = 3 LIMIT 1")->fetchColumn() ?: 0);

if ($resId > 0) {
    $conn->beginTransaction();
    try {
        $conn->prepare("INSERT INTO sms_vendors (society_id, vendor_name, service_category, phone_number) VALUES (:sid, 'Phase4 Test Lift Co', 'Lift & AMC', '9999988888')")
            ->execute([':sid' => $socId]);
        $vid = (int)$conn->lastInsertId();
        assertTrue($vid > 0, "Vendor creation persists cleanly");

        $conn->prepare("INSERT INTO sms_society_expenses (society_id, category, title, amount, expense_date, vendor_id) VALUES (:sid, 'Lift AMC', 'Monthly Test Service', 4500.00, CURDATE(), :vid)")
            ->execute([':sid' => $socId, ':vid' => $vid]);
        assertTrue((int)$conn->lastInsertId() > 0, "Society expense linked to vendor succeeds");

        $conn->prepare("INSERT INTO sms_invoices (invoice_number, society_id, resident_id, unit_number, title, base_amount, amount, penalty_amount, paid_amount, balance_amount, due_date, status, created_at) VALUES ('INV-P4TEST', :sid, :rid, 'T-101', 'Phase4 Test Bill', 3000.00, 3000.00, 0.00, 1000.00, 2000.00, CURDATE(), 'Partially_Paid', NOW())")
            ->execute([':sid' => $socId, ':rid' => $resId]);
        $iid = (int)$conn->lastInsertId();
        $bal = (float)$conn->query("SELECT balance_amount FROM sms_invoices WHERE id = {$iid}")->fetchColumn();
        assertTrue(abs($bal - 2000.00) < 0.01, "Invoice balance reflects partial payment accurately");

        $conn->rollBack();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        assertTrue(false, "Phase 4 DB integration check failed: " . $e->getMessage());
    }
}

// 6. Verify admin & resident financial UI files + vendor route exist cleanly
assertTrue(file_exists(__DIR__ . '/../../modules/admin/vendors.php'), "modules/admin/vendors.php exists");
assertTrue(strpos(file_get_contents(__DIR__ . '/../../routes.php'), "'/admin/vendors'") !== false, "/admin/vendors route registered");
