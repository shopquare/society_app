<?php
// api/resident_payments.php
// Resident Partial/Advance/UPI Payments, PDF Receipt Download & Society Financial Transparency API

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/pdf_generator.php';
require_once __DIR__ . '/../includes/notification_service.php';

initApi(['GET', 'POST']);
requireApiAuth([1, 2, 3]);

$societyId = (int)$_SESSION['society_id'];
$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = trim((string)($_GET['action'] ?? 'list'));

    try {
        if ($action === 'receipt_pdf') {
            $payId = (int)($_GET['payment_id'] ?? 0);
            $isAdmin = hasPermission('finance.view');
            $sql = "SELECT pay.*, s.society_name, s.address AS society_address,
                           CONCAT(p.first_name,' ',p.last_name) AS resident_name,
                           i.invoice_number, i.title AS invoice_title, COALESCE(i.balance_amount, 0) AS invoice_balance
                    FROM sms_payments pay
                    INNER JOIN sms_societies s ON pay.society_id = s.id
                    INNER JOIN sms_user_profiles p ON pay.resident_id = p.user_id
                    LEFT JOIN sms_invoices i ON pay.invoice_id = i.id
                    WHERE pay.id = :pid AND pay.society_id = :sid" . ($isAdmin ? "" : " AND pay.resident_id = :uid") . " LIMIT 1";
            $stmt = $conn->prepare($sql);
            $params = [':pid' => $payId, ':sid' => $societyId];
            if (!$isAdmin) $params[':uid'] = $userId;
            $stmt->execute($params);
            $pay = $stmt->fetch();
            if (!$pay) jsonError('Payment receipt not found or unauthorized.', 404);

            $lines = [
                "Society: {$pay['society_name']}",
                "Address: " . ($pay['society_address'] ?: 'Campus Premises'),
                "---",
                "OFFICIAL PAYMENT RECEIPT",
                "Receipt Number:    {$pay['receipt_number']}",
                "Transaction ID:    {$pay['transaction_id']}",
                "Payment Date:      " . date('d M Y, h:i A', strtotime($pay['payment_date'])),
                "Payment Method:    {$pay['payment_method']}",
                "---",
                "Resident Name:     {$pay['resident_name']}",
                "Flat / Unit No:    {$pay['unit_number']}",
                "Invoice Reference: " . ($pay['invoice_number'] ?: 'Advance Payment'),
                "Bill Description:  " . ($pay['invoice_title'] ?: 'Society Maintenance Advance'),
                "---",
                sprintf("Amount Paid:       Rs. %.2f", (float)$pay['amount']),
                sprintf("Remaining Balance: Rs. %.2f", (float)$pay['invoice_balance']),
                "Status:            {$pay['status']}"
            ];
            generateSimplePdf("{$pay['society_name']} - Payment Receipt", $lines, $pay['receipt_number'] . ".pdf");
        }

        if ($action === 'transparency') {
            // Strict privacy guarantee: Aggregate society-wide figures only; zero individual resident PII
            $collStmt = $conn->prepare("SELECT COALESCE(SUM(paid_amount),0) AS collected, COALESCE(SUM(CASE WHEN status IN ('Unpaid','Partially_Paid','Overdue') THEN balance_amount ELSE 0 END),0) AS outstanding FROM sms_invoices WHERE society_id = :sid");
            $collStmt->execute([':sid' => $societyId]);
            $duesSummary = $collStmt->fetch() ?: ['collected' => 0, 'outstanding' => 0];

            $incStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM sms_society_income WHERE society_id = :sid");
            $incStmt->execute([':sid' => $societyId]);
            $otherInc = (float)$incStmt->fetchColumn();

            $expStmt = $conn->prepare("SELECT category, SUM(amount) AS total FROM sms_society_expenses WHERE society_id = :sid GROUP BY category ORDER BY total DESC");
            $expStmt->execute([':sid' => $societyId]);
            $expByCat = $expStmt->fetchAll();

            $majorExp = $conn->prepare("SELECT category, title, amount, expense_date FROM sms_society_expenses WHERE society_id = :sid ORDER BY amount DESC, expense_date DESC LIMIT 10");
            $majorExp->execute([':sid' => $societyId]);

            $budgets = $conn->prepare(
                "SELECT b.fiscal_year, b.category, b.monthly_limit, b.annual_limit,
                        (SELECT COALESCE(SUM(e.amount),0) FROM sms_society_expenses e WHERE e.society_id = b.society_id AND e.category = b.category) AS utilized
                 FROM sms_budgets b WHERE b.society_id = :sid ORDER BY b.annual_limit DESC"
            );
            $budgets->execute([':sid' => $societyId]);

            jsonSuccess([
                'summary' => [
                    'maintenance_collected' => (float)$duesSummary['collected'],
                    'other_income' => $otherInc,
                    'total_outstanding' => (float)$duesSummary['outstanding'],
                    'total_expenses' => array_sum(array_map(fn($x) => (float)$x['total'], $expByCat))
                ],
                'expense_categories' => $expByCat,
                'major_expenditures' => $majorExp->fetchAll(),
                'approved_budgets' => $budgets->fetchAll()
            ]);
        }

        // Default: user's own invoices + payment history
        $invStmt = $conn->prepare(
            "SELECT *, CASE WHEN status = 'Unpaid' AND due_date < CURDATE() THEN 'Overdue' ELSE status END AS computed_status
             FROM sms_invoices WHERE society_id = :sid AND resident_id = :uid ORDER BY due_date DESC LIMIT 40"
        );
        $invStmt->execute([':sid' => $societyId, ':uid' => $userId]);

        $payStmt = $conn->prepare("SELECT * FROM sms_payments WHERE society_id = :sid AND resident_id = :uid ORDER BY payment_date DESC LIMIT 40");
        $payStmt->execute([':sid' => $societyId, ':uid' => $userId]);

        jsonSuccess(['invoices' => $invStmt->fetchAll(), 'payments' => $payStmt->fetchAll()]);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to load payment records.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonInput();
    requireValidCsrfToken($data['csrf_token'] ?? '');

    $invoiceId = !empty($data['invoice_id']) ? (int)$data['invoice_id'] : null;
    $amount = (float)($data['amount'] ?? 0);
    $method = trim((string)($data['payment_method'] ?? 'UPI'));
    $txId = trim((string)($data['transaction_id'] ?? ('UPI-' . strtoupper(bin2hex(random_bytes(4))))));
    $isAdvance = ($invoiceId === null) ? 1 : 0;

    if ($amount <= 0) jsonError('Payment amount must be positive.', 400);
    if (!in_array($method, ['UPI', 'Gateway', 'Bank_Transfer'], true)) $method = 'UPI';

    try {
        $conn->beginTransaction();
        $unitNum = (string)($conn->query("SELECT unit_number FROM sms_user_units WHERE user_id = {$userId} AND is_current_occupant = 1 LIMIT 1")->fetchColumn() ?: 'N/A');
        $rcpt = 'RCPT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));

        if ($invoiceId !== null) {
            $iStmt = $conn->prepare("SELECT * FROM sms_invoices WHERE id = :id AND resident_id = :uid AND society_id = :sid FOR UPDATE");
            $iStmt->execute([':id' => $invoiceId, ':uid' => $userId, ':sid' => $societyId]);
            $inv = $iStmt->fetch();
            if (!$inv || $inv['status'] === 'Paid') {
                $conn->rollBack();
                jsonError('Invoice not found or already paid.', 400);
            }
            $totalDue = (float)$inv['amount'] + (float)$inv['penalty_amount'];
            $newPaid = min($totalDue, (float)$inv['paid_amount'] + $amount);
            $newBal = max(0, round($totalDue - $newPaid, 2));
            $newSt = ($newBal <= 0.01) ? 'Paid' : 'Partially_Paid';

            $conn->prepare("UPDATE sms_invoices SET paid_amount = :pa, balance_amount = :bal, status = :st, updated_at = NOW() WHERE id = :id")
                ->execute([':pa' => $newPaid, ':bal' => $newBal, ':st' => $newSt, ':id' => $invoiceId]);
        }

        $conn->prepare(
            "INSERT INTO sms_payments (society_id, invoice_id, resident_id, unit_number, amount, payment_method, transaction_id, receipt_number, is_advance, status, payment_date)
             VALUES (:sid, :iid, :uid, :unit, :amt, :pm, :tx, :rcpt, :adv, 'Completed', NOW())"
        )->execute([
            ':sid' => $societyId, ':iid' => $invoiceId, ':uid' => $userId, ':unit' => $unitNum,
            ':amt' => $amount, ':pm' => $method, ':tx' => $txId, ':rcpt' => $rcpt, ':adv' => $isAdvance
        ]);
        $payId = (int)$conn->lastInsertId();
        $conn->commit();

        dispatchNotification($conn, [
            'society_id' => $societyId, 'user_ids' => [$userId], 'category' => 'payment_reminder', 'priority' => 'Normal',
            'title' => "Payment Confirmed: {$rcpt}", 'message' => "Received Rs. " . number_format($amount, 2) . " via {$method}. Download your PDF receipt anytime.",
            'action_url' => '/resident/financials', 'channels' => ['in_app']
        ]);

        jsonSuccess(['message' => 'Payment recorded successfully!', 'receipt_number' => $rcpt, 'payment_id' => $payId], 201);
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        logAndFail($e, 'Unable to process payment.');
    }
}
