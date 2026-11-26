<?php
// api/admin_accounting.php
// Income, Expenses, Vendor Management & Annual/Category Budgets API

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/upload_helper.php';
require_once __DIR__ . '/../includes/audit_logger.php';

initApi(['GET', 'POST']);
requireApiPermission('finance.view');

$societyId = (int)$_SESSION['society_id'];
$actorId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $income = $conn->prepare("SELECT * FROM sms_society_income WHERE society_id = :sid ORDER BY income_date DESC LIMIT 60");
        $income->execute([':sid' => $societyId]);

        $expenses = $conn->prepare(
            "SELECT e.*, v.vendor_name FROM sms_society_expenses e LEFT JOIN sms_vendors v ON e.vendor_id = v.id
             WHERE e.society_id = :sid ORDER BY e.expense_date DESC LIMIT 80"
        );
        $expenses->execute([':sid' => $societyId]);

        $vendors = $conn->prepare("SELECT * FROM sms_vendors WHERE society_id = :sid ORDER BY vendor_name ASC");
        $vendors->execute([':sid' => $societyId]);

        $vPays = $conn->prepare(
            "SELECT vp.*, v.vendor_name FROM sms_vendor_payments vp INNER JOIN sms_vendors v ON vp.vendor_id = v.id
             WHERE vp.society_id = :sid ORDER BY vp.due_date DESC LIMIT 60"
        );
        $vPays->execute([':sid' => $societyId]);

        $budgets = $conn->prepare(
            "SELECT b.*,
                    (SELECT COALESCE(SUM(e.amount), 0) FROM sms_society_expenses e WHERE e.society_id = b.society_id AND e.category = b.category) AS actual_spent
             FROM sms_budgets b WHERE b.society_id = :sid ORDER BY b.annual_limit DESC"
        );
        $budgets->execute([':sid' => $societyId]);

        jsonSuccess([
            'income' => $income->fetchAll(),
            'expenses' => $expenses->fetchAll(),
            'vendors' => $vendors->fetchAll(),
            'vendor_payments' => $vPays->fetchAll(),
            'budgets' => $budgets->fetchAll()
        ]);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to load accounting ledger.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonInput();
    requireValidCsrfToken($data['csrf_token'] ?? '');
    $action = trim((string)($data['action'] ?? ''));

    try {
        if ($action === 'add_income') {
            requireApiPermission('finance.manage');
            $cat = trim((string)($data['category'] ?? 'Miscellaneous Income'));
            $title = trim((string)($data['title'] ?? ''));
            $amt = (float)($data['amount'] ?? 0);
            $dt = trim((string)($data['income_date'] ?? date('Y-m-d')));
            $ref = trim((string)($data['reference_no'] ?? ''));
            if ($title === '' || $amt <= 0) jsonError('Title and positive amount required.', 400);

            $conn->prepare("INSERT INTO sms_society_income (society_id, category, title, amount, income_date, reference_no, recorded_by) VALUES (:sid, :cat, :t, :amt, :dt, :ref, :by)")
                ->execute([':sid' => $societyId, ':cat' => $cat, ':t' => $title, ':amt' => $amt, ':dt' => $dt, ':ref' => $ref ?: null, ':by' => $actorId]);
            jsonSuccess(['message' => 'Income recorded.'], 201);
        } elseif ($action === 'add_expense') {
            requireApiPermission('finance.manage');
            $cat = trim((string)($data['category'] ?? 'Maintenance Expenses'));
            $title = trim((string)($data['title'] ?? ''));
            $amt = (float)($data['amount'] ?? 0);
            $dt = trim((string)($data['expense_date'] ?? date('Y-m-d')));
            $vid = !empty($data['vendor_id']) ? (int)$data['vendor_id'] : null;
            if ($title === '' || $amt <= 0) jsonError('Expense description and amount required.', 400);

            $voucher = null;
            if (!empty($data['voucher_base64'])) {
                $up = saveBase64Upload((string)$data['voucher_base64'], 'vouchers', ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], 8388608);
                $voucher = $up['relative_path'];
            }
            $conn->prepare("INSERT INTO sms_society_expenses (society_id, category, title, amount, expense_date, vendor_id, voucher_path, recorded_by) VALUES (:sid, :cat, :t, :amt, :dt, :vid, :vp, :by)")
                ->execute([':sid' => $societyId, ':cat' => $cat, ':t' => $title, ':amt' => $amt, ':dt' => $dt, ':vid' => $vid, ':vp' => $voucher, ':by' => $actorId]);
            logAudit($conn, $societyId, $actorId, 'ADD_EXPENSE', 'expense', (int)$conn->lastInsertId(), ['category' => $cat, 'amount' => $amt]);
            jsonSuccess(['message' => 'Society expense recorded.'], 201);
        } elseif ($action === 'save_vendor') {
            requireApiPermission('vendors.manage');
            $id = (int)($data['id'] ?? 0);
            $name = trim((string)($data['vendor_name'] ?? ''));
            $cat = trim((string)($data['service_category'] ?? 'Maintenance'));
            $cp = trim((string)($data['contact_person'] ?? ''));
            $phone = trim((string)($data['phone_number'] ?? ''));
            $gst = trim((string)($data['gst_or_pan'] ?? ''));
            if ($name === '' || $phone === '') jsonError('Vendor name and phone required.', 400);

            if ($id > 0) {
                $conn->prepare("UPDATE sms_vendors SET vendor_name=:vn, service_category=:sc, contact_person=:cp, phone_number=:ph, gst_or_pan=:gst WHERE id=:id AND society_id=:sid")
                    ->execute([':vn' => $name, ':sc' => $cat, ':cp' => $cp ?: null, ':ph' => $phone, ':gst' => $gst ?: null, ':id' => $id, ':sid' => $societyId]);
            } else {
                $conn->prepare("INSERT INTO sms_vendors (society_id, vendor_name, service_category, contact_person, phone_number, gst_or_pan) VALUES (:sid, :vn, :sc, :cp, :ph, :gst)")
                    ->execute([':sid' => $societyId, ':vn' => $name, ':sc' => $cat, ':cp' => $cp ?: null, ':ph' => $phone, ':gst' => $gst ?: null]);
            }
            jsonSuccess(['message' => 'Vendor details saved.']);
        } elseif ($action === 'record_vendor_payment') {
            requireApiPermission('vendors.manage');
            $vid = (int)($data['vendor_id'] ?? 0);
            $invNo = trim((string)($data['invoice_number'] ?? ('VINV-' . time())));
            $amt = (float)($data['amount'] ?? 0);
            $paidAmt = (float)($data['paid_amount'] ?? $amt);
            $pm = trim((string)($data['payment_method'] ?? 'Bank_Transfer'));
            $due = trim((string)($data['due_date'] ?? date('Y-m-d')));
            if ($vid <= 0 || $amt <= 0) jsonError('Vendor and invoice amount required.', 400);

            $doc = null;
            if (!empty($data['document_base64'])) {
                $up = saveBase64Upload((string)$data['document_base64'], 'vouchers', ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], 8388608);
                $doc = $up['relative_path'];
            }
            $status = $paidAmt >= $amt ? 'Paid' : ($paidAmt > 0 ? 'Partial' : 'Pending');

            $conn->prepare(
                "INSERT INTO sms_vendor_payments (society_id, vendor_id, invoice_number, amount, paid_amount, payment_method, due_date, paid_at, status, document_path, recorded_by)
                 VALUES (:sid, :vid, :ino, :amt, :pamt, :pm, :due, :pat, :st, :dp, :by)"
            )->execute([
                ':sid' => $societyId, ':vid' => $vid, ':ino' => $invNo, ':amt' => $amt, ':pamt' => $paidAmt,
                ':pm' => $pm, ':due' => $due, ':pat' => ($status === 'Paid' ? date('Y-m-d H:i:s') : null),
                ':st' => $status, ':dp' => $doc, ':by' => $actorId
            ]);
            jsonSuccess(['message' => 'Vendor payment recorded cleanly.'], 201);
        } elseif ($action === 'save_budget') {
            requireApiPermission('budgets.manage');
            $fy = trim((string)($data['fiscal_year'] ?? '2026-2027'));
            $cat = trim((string)($data['category'] ?? 'Maintenance Expenses'));
            $mo = max(0, (float)($data['monthly_limit'] ?? 0));
            $ann = max($mo, (float)($data['annual_limit'] ?? ($mo * 12)));
            $notes = trim((string)($data['notes'] ?? ''));

            $conn->prepare(
                "INSERT INTO sms_budgets (society_id, fiscal_year, category, monthly_limit, annual_limit, notes, created_by)
                 VALUES (:sid, :fy, :cat, :mo, :ann, :notes, :by)
                 ON DUPLICATE KEY UPDATE monthly_limit = VALUES(monthly_limit), annual_limit = VALUES(annual_limit), notes = VALUES(notes)"
            )->execute([':sid' => $societyId, ':fy' => $fy, ':cat' => $cat, ':mo' => $mo, ':ann' => $ann, ':notes' => $notes ?: null, ':by' => $actorId]);
            jsonSuccess(['message' => 'Budget saved successfully.']);
        } else {
            jsonError('Unknown accounting action.', 400);
        }
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to save accounting record.');
    }
}
