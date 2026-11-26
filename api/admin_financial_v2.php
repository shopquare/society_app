<?php
// api/admin_financial_v2.php
// Recurring Auto-Billing, Partial/Advance/Refund Payments, Server Penalties, WhatsApp Reminders & PDF/CSV Reports

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/finance_helper.php';
require_once __DIR__ . '/../includes/pdf_generator.php';
require_once __DIR__ . '/../includes/audit_logger.php';
require_once __DIR__ . '/../includes/notification_service.php';

initApi(['GET', 'POST']);
requireApiPermission('finance.view');

$societyId = (int)$_SESSION['society_id'];
$actorId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $export = trim((string)($_GET['export'] ?? ''));

    try {
        if ($export === 'dues_csv') {
            $stmt = $conn->prepare(
                "SELECT i.invoice_number, CONCAT(p.first_name,' ',p.last_name) AS resident, COALESCE(i.unit_number, uu.unit_number) AS unit,
                        i.title, i.base_amount, i.penalty_amount, i.paid_amount, i.balance_amount, i.due_date, i.status
                 FROM sms_invoices i
                 INNER JOIN sms_user_profiles p ON i.resident_id = p.user_id
                 LEFT JOIN sms_user_units uu ON i.resident_id = uu.user_id AND uu.is_current_occupant = 1
                 WHERE i.society_id = :sid ORDER BY i.due_date DESC"
            );
            $stmt->execute([':sid' => $societyId]);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="outstanding_dues_' . date('Ymd') . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Invoice #', 'Resident', 'Unit', 'Title', 'Base', 'Penalty', 'Paid', 'Balance', 'Due Date', 'Status']);
            foreach ($stmt->fetchAll() as $r) fputcsv($out, array_values($r));
            fclose($out);
            exit;
        }

        if ($export === 'income_expense_pdf') {
            $socName = (string)($conn->query("SELECT society_name FROM sms_societies WHERE id = {$societyId}")->fetchColumn() ?: 'SecureGate Society');
            $incTotal = (float)$conn->query("SELECT COALESCE(SUM(amount),0) FROM sms_society_income WHERE society_id = {$societyId}")->fetchColumn();
            $collTotal = (float)$conn->query("SELECT COALESCE(SUM(paid_amount),0) FROM sms_invoices WHERE society_id = {$societyId}")->fetchColumn();
            $expTotal = (float)$conn->query("SELECT COALESCE(SUM(amount),0) FROM sms_society_expenses WHERE society_id = {$societyId}")->fetchColumn();
            $duesTotal = (float)$conn->query("SELECT COALESCE(SUM(balance_amount),0) FROM sms_invoices WHERE society_id = {$societyId} AND status IN ('Unpaid','Partially_Paid','Overdue')")->fetchColumn();

            $expRows = $conn->query("SELECT category, SUM(amount) AS total FROM sms_society_expenses WHERE society_id = {$societyId} GROUP BY category ORDER BY total DESC")->fetchAll();
            $lines = [
                "Society: {$socName}",
                "Report Period: Fiscal Year " . date('Y'),
                "---",
                "FINANCIAL SUMMARY",
                sprintf("Maintenance Collected:   Rs. %.2f", $collTotal),
                sprintf("Other Society Income:    Rs. %.2f", $incTotal),
                sprintf("Total Receipts / Income: Rs. %.2f", $collTotal + $incTotal),
                sprintf("Total Expenditure:       Rs. %.2f", $expTotal),
                sprintf("Net Surplus / (Deficit): Rs. %.2f", ($collTotal + $incTotal) - $expTotal),
                sprintf("Pending Member Dues:     Rs. %.2f", $duesTotal),
                "---",
                "EXPENSE BREAKDOWN BY CATEGORY"
            ];
            foreach ($expRows as $er) {
                $lines[] = sprintf("%-30s Rs. %.2f", $er['category'], (float)$er['total']);
            }
            generateSimplePdf("{$socName} - Income & Expense Statement", $lines, "financial_statement_" . date('Ymd') . ".pdf");
        }

        // Dashboard Payload
        $kpis = $conn->prepare(
            "SELECT
                COALESCE(SUM(amount + penalty_amount), 0) AS total_billed,
                COALESCE(SUM(paid_amount), 0) AS total_collected,
                COALESCE(SUM(CASE WHEN status IN ('Unpaid','Partially_Paid','Overdue') THEN balance_amount ELSE 0 END), 0) AS total_outstanding,
                SUM(CASE WHEN status = 'Paid' THEN 1 ELSE 0 END) AS paid_count,
                SUM(CASE WHEN status IN ('Unpaid','Overdue') THEN 1 ELSE 0 END) AS unpaid_count,
                SUM(CASE WHEN status = 'Partially_Paid' THEN 1 ELSE 0 END) AS partial_count,
                SUM(CASE WHEN status IN ('Unpaid','Partially_Paid','Overdue') AND due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue_count
             FROM sms_invoices WHERE society_id = :sid"
        );
        $kpis->execute([':sid' => $societyId]);
        $kpiRow = $kpis->fetch() ?: [];
        $billed = (float)($kpiRow['total_billed'] ?? 0);
        $collected = (float)($kpiRow['total_collected'] ?? 0);
        $kpiRow['collection_pct'] = $billed > 0 ? round(($collected / $billed) * 100, 1) : 0;

        $rules = $conn->prepare("SELECT * FROM sms_maintenance_rules WHERE society_id = :sid ORDER BY id ASC");
        $rules->execute([':sid' => $societyId]);

        $invs = $conn->prepare(
            "SELECT i.*, CONCAT(p.first_name,' ',p.last_name) AS resident_name, COALESCE(i.unit_number, uu.unit_number) AS unit_number
             FROM sms_invoices i
             INNER JOIN sms_user_profiles p ON i.resident_id = p.user_id
             LEFT JOIN sms_user_units uu ON i.resident_id = uu.user_id AND uu.is_current_occupant = 1
             WHERE i.society_id = :sid ORDER BY i.due_date DESC, i.id DESC LIMIT 100"
        );
        $invs->execute([':sid' => $societyId]);

        $pays = $conn->prepare(
            "SELECT pay.*, CONCAT(p.first_name,' ',p.last_name) AS resident_name, i.invoice_number
             FROM sms_payments pay
             INNER JOIN sms_user_profiles p ON pay.resident_id = p.user_id
             LEFT JOIN sms_invoices i ON pay.invoice_id = i.id
             WHERE pay.society_id = :sid ORDER BY pay.payment_date DESC LIMIT 80"
        );
        $pays->execute([':sid' => $societyId]);

        $unitDues = $conn->prepare(
            "SELECT COALESCE(i.unit_number, uu.unit_number) AS unit_number, CONCAT(p.first_name,' ',p.last_name) AS resident_name,
                    SUM(i.balance_amount) AS outstanding_balance, COUNT(*) AS open_invoices
             FROM sms_invoices i
             INNER JOIN sms_user_profiles p ON i.resident_id = p.user_id
             LEFT JOIN sms_user_units uu ON i.resident_id = uu.user_id AND uu.is_current_occupant = 1
             WHERE i.society_id = :sid AND i.status IN ('Unpaid','Partially_Paid','Overdue')
             GROUP BY COALESCE(i.unit_number, uu.unit_number), i.resident_id, resident_name
             ORDER BY outstanding_balance DESC"
        );
        $unitDues->execute([':sid' => $societyId]);

        jsonSuccess([
            'kpis' => $kpiRow,
            'rules' => $rules->fetchAll(),
            'invoices' => $invs->fetchAll(),
            'payments' => $pays->fetchAll(),
            'unit_dues' => $unitDues->fetchAll()
        ]);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to load advanced financial overview.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireApiPermission('finance.manage');
    $data = getJsonInput();
    requireValidCsrfToken($data['csrf_token'] ?? '');
    $action = trim((string)($data['action'] ?? ''));

    try {
        if ($action === 'save_rule') {
            $name = trim((string)($data['rule_name'] ?? ''));
            $flatType = trim((string)($data['flat_type'] ?? ''));
            $amt = (float)($data['base_amount'] ?? 0);
            $dueDays = max(1, (int)($data['due_days'] ?? 10));
            $pType = trim((string)($data['penalty_type'] ?? 'Fixed'));
            $pFreq = trim((string)($data['penalty_frequency'] ?? 'One_Time'));
            $pVal = max(0, (float)($data['penalty_value'] ?? 0));
            $grace = max(0, (int)($data['grace_days'] ?? 5));
            $maxPen = max(0, (float)($data['max_penalty'] ?? 2000));

            if ($name === '' || $amt <= 0) jsonError('Rule name and base amount required.', 400);

            $conn->prepare(
                "INSERT INTO sms_maintenance_rules (society_id, rule_name, flat_type, base_amount, due_days, penalty_type, penalty_frequency, penalty_value, grace_days, max_penalty)
                 VALUES (:sid, :rn, :ft, :amt, :dd, :pt, :pf, :pv, :gr, :mp)"
            )->execute([
                ':sid' => $societyId, ':rn' => $name, ':ft' => ($flatType !== '' ? $flatType : null),
                ':amt' => $amt, ':dd' => $dueDays, ':pt' => $pType, ':pf' => $pFreq,
                ':pv' => $pVal, ':gr' => $grace, ':mp' => $maxPen
            ]);
            jsonSuccess(['message' => 'Maintenance billing rule created.'], 201);
        } elseif ($action === 'run_auto_billing') {
            $monthLabel = trim((string)($data['billing_month'] ?? date('F Y')));
            $rules = $conn->query("SELECT * FROM sms_maintenance_rules WHERE society_id = {$societyId} AND is_active = 1")->fetchAll();
            if (empty($rules)) jsonError('No active maintenance billing rules found.', 400);

            $resStmt = $conn->prepare(
                "SELECT u.id AS user_id, uu.unit_number, COALESCE(un.flat_type, '2BHK') AS flat_type
                 FROM sms_users u
                 INNER JOIN sms_user_units uu ON u.id = uu.user_id AND uu.is_current_occupant = 1
                 LEFT JOIN sms_units un ON uu.society_id = un.society_id AND uu.unit_number = un.unit_number
                 WHERE u.society_id = :sid AND u.role_id = 3 AND u.status = 'active'"
            );
            $resStmt->execute([':sid' => $societyId]);
            $activeResidents = $resStmt->fetchAll();

            $createdCount = 0;
            $conn->beginTransaction();
            $chkDup = $conn->prepare("SELECT id FROM sms_invoices WHERE society_id = :sid AND resident_id = :rid AND title = :title LIMIT 1");
            $insInv = $conn->prepare(
                "INSERT INTO sms_invoices (invoice_number, society_id, resident_id, unit_number, title, base_amount, amount, penalty_amount, paid_amount, balance_amount, rule_id, due_date, status, created_at)
                 VALUES (:invno, :sid, :rid, :unit, :title, :amt, :amt2, 0.00, 0.00, :bal, :rule_id, :due, 'Unpaid', NOW())"
            );

            foreach ($activeResidents as $res) {
                $matchedRule = null;
                foreach ($rules as $r) {
                    if ($r['flat_type'] === $res['flat_type']) { $matchedRule = $r; break; }
                }
                if (!$matchedRule) {
                    foreach ($rules as $r) {
                        if (empty($r['flat_type'])) { $matchedRule = $r; break; }
                    }
                }
                if (!$matchedRule) $matchedRule = $rules[0];

                $title = "{$matchedRule['rule_name']} - {$monthLabel}";
                $chkDup->execute([':sid' => $societyId, ':rid' => (int)$res['user_id'], ':title' => $title]);
                if ($chkDup->fetch()) continue;

                $due = date('Y-m-d', strtotime('+' . (int)$matchedRule['due_days'] . ' days'));
                $invNo = 'INV-' . date('Ym') . '-' . strtoupper(bin2hex(random_bytes(2)));
                $amt = (float)$matchedRule['base_amount'];
                $insInv->execute([
                    ':invno' => $invNo, ':sid' => $societyId, ':rid' => (int)$res['user_id'], ':unit' => $res['unit_number'],
                    ':title' => $title, ':amt' => $amt, ':amt2' => $amt, ':bal' => $amt,
                    ':rule_id' => (int)$matchedRule['id'], ':due' => $due
                ]);
                $createdCount++;
            }
            $conn->commit();
            logAudit($conn, $societyId, $actorId, 'RUN_AUTO_BILLING', 'invoice', null, ['month' => $monthLabel, 'generated' => $createdCount]);
            jsonSuccess(['message' => "{$createdCount} monthly maintenance invoices generated cleanly."]);
        } elseif ($action === 'apply_late_penalties') {
            $today = date('Y-m-d');
            $invs = $conn->prepare(
                "SELECT i.*, r.penalty_type, r.penalty_frequency, r.penalty_value, r.grace_days, r.max_penalty
                 FROM sms_invoices i
                 LEFT JOIN sms_maintenance_rules r ON i.rule_id = r.id
                 WHERE i.society_id = :sid AND i.status IN ('Unpaid','Partially_Paid','Overdue') AND i.due_date < :td"
            );
            $invs->execute([':sid' => $societyId, ':td' => $today]);
            $updated = 0;

            foreach ($invs->fetchAll() as $inv) {
                $pType = $inv['penalty_type'] ?: 'Fixed';
                $pFreq = $inv['penalty_frequency'] ?: 'One_Time';
                $pVal = (float)($inv['penalty_value'] ?? 250.00);
                $grace = (int)($inv['grace_days'] ?? 5);
                $maxPen = (float)($inv['max_penalty'] ?? 1500.00);

                $newPen = calculateLatePenaltyAmount((float)$inv['amount'], $inv['due_date'], $today, $pType, $pFreq, $pVal, $grace, $maxPen);
                $newBal = max(0, round(((float)$inv['amount'] + $newPen) - (float)$inv['paid_amount'], 2));
                $conn->prepare("UPDATE sms_invoices SET penalty_amount = :pen, balance_amount = :bal, status = 'Overdue' WHERE id = :id")
                    ->execute([':pen' => $newPen, ':bal' => $newBal, ':id' => (int)$inv['id']]);
                if ($newPen > 0) $updated++;
            }
            logAudit($conn, $societyId, $actorId, 'APPLY_LATE_PENALTIES', 'invoice', null, ['updated_count' => $updated]);
            jsonSuccess(['message' => "Server penalty engine updated {$updated} overdue invoice(s)."]);
        } elseif ($action === 'record_payment') {
            $invoiceId = !empty($data['invoice_id']) ? (int)$data['invoice_id'] : null;
            $residentId = (int)($data['resident_id'] ?? 0);
            $amount = (float)($data['amount'] ?? 0);
            $method = trim((string)($data['payment_method'] ?? 'Cash'));
            $txRef = trim((string)($data['transaction_id'] ?? ('TXN-' . strtoupper(bin2hex(random_bytes(4))))));
            $isAdvance = !empty($data['is_advance']) ? 1 : 0;
            $isRefund = !empty($data['is_refund']) ? 1 : 0;
            $notes = trim((string)($data['notes'] ?? ''));

            if ($residentId <= 0 || $amount <= 0) jsonError('Resident and positive amount are required.', 400);

            $conn->beginTransaction();
            $unitNum = (string)($conn->query("SELECT unit_number FROM sms_user_units WHERE user_id = {$residentId} AND is_current_occupant = 1 LIMIT 1")->fetchColumn() ?: 'N/A');
            $receiptNo = 'RCPT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));

            $conn->prepare(
                "INSERT INTO sms_payments (society_id, invoice_id, resident_id, unit_number, amount, payment_method, transaction_id, receipt_number, is_advance, is_refund, status, reconciled_by, reconciled_at, notes, payment_date)
                 VALUES (:sid, :iid, :rid, :unit, :amt, :pm, :tx, :rcpt, :adv, :ref, :st, :rec_by, NOW(), :notes, NOW())"
            )->execute([
                ':sid' => $societyId, ':iid' => $invoiceId, ':rid' => $residentId, ':unit' => $unitNum,
                ':amt' => $amount, ':pm' => $method, ':tx' => $txRef, ':rcpt' => $receiptNo,
                ':adv' => $isAdvance, ':ref' => $isRefund, ':st' => $isRefund ? 'Refunded' : 'Reconciled',
                ':rec_by' => $actorId, ':notes' => $notes ?: null
            ]);

            if ($invoiceId !== null) {
                $iStmt = $conn->prepare("SELECT * FROM sms_invoices WHERE id = :id AND society_id = :sid FOR UPDATE");
                $iStmt->execute([':id' => $invoiceId, ':sid' => $societyId]);
                if ($inv = $iStmt->fetch()) {
                    $newPaid = $isRefund ? max(0, (float)$inv['paid_amount'] - $amount) : ((float)$inv['paid_amount'] + $amount);
                    $totalDue = (float)$inv['amount'] + (float)$inv['penalty_amount'];
                    $newBal = max(0, round($totalDue - $newPaid, 2));
                    $newStatus = $isRefund ? 'Refunded' : ($newBal <= 0.01 ? 'Paid' : 'Partially_Paid');
                    $conn->prepare("UPDATE sms_invoices SET paid_amount = :pa, balance_amount = :bal, status = :st, updated_at = NOW() WHERE id = :id")
                        ->execute([':pa' => $newPaid, ':bal' => $newBal, ':st' => $newStatus, ':id' => $invoiceId]);
                }
            }
            $conn->commit();

            dispatchNotification($conn, [
                'society_id' => $societyId, 'user_ids' => [$residentId], 'category' => 'payment_reminder', 'priority' => 'Normal',
                'title' => $isRefund ? "Refund Processed: ₹{$amount}" : "Payment Receipt Issued: {$receiptNo}",
                'message' => "Transaction {$txRef} for ₹{$amount} via {$method} recorded.",
                'action_url' => '/resident/financials', 'channels' => ['in_app', 'email']
            ]);
            jsonSuccess(['message' => 'Payment transaction recorded.', 'receipt_number' => $receiptNo], 201);
        } elseif ($action === 'send_whatsapp_reminders') {
            $dues = $conn->prepare(
                "SELECT i.id, i.invoice_number, i.resident_id, COALESCE(i.unit_number, uu.unit_number) AS unit_number,
                        i.balance_amount, i.due_date, CONCAT(p.first_name,' ',p.last_name) AS resident_name
                 FROM sms_invoices i
                 INNER JOIN sms_user_profiles p ON i.resident_id = p.user_id
                 LEFT JOIN sms_user_units uu ON i.resident_id = uu.user_id AND uu.is_current_occupant = 1
                 WHERE i.society_id = :sid AND i.status IN ('Unpaid','Partially_Paid','Overdue')"
            );
            $dues->execute([':sid' => $societyId]);
            $count = 0;
            foreach ($dues->fetchAll() as $d) {
                $bal = number_format((float)$d['balance_amount'], 2);
                dispatchNotification($conn, [
                    'society_id' => $societyId, 'user_ids' => [(int)$d['resident_id']], 'category' => 'payment_reminder', 'priority' => 'High',
                    'title' => "Payment Reminder: {$d['invoice_number']} (Unit {$d['unit_number']})",
                    'message' => "Dear {$d['resident_name']}, outstanding balance Rs. {$bal} for {$d['invoice_number']} is due by {$d['due_date']}. Pay via SecureGate.",
                    'action_url' => '/resident/financials', 'channels' => ['in_app', 'whatsapp', 'email']
                ]);
                $count++;
            }
            jsonSuccess(['message' => "Queued {$count} WhatsApp/Email/In-App maintenance payment reminders."]);
        } else {
            jsonError('Unknown financial action.', 400);
        }
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        logAndFail($e, 'Financial operation failed.');
    }
}
