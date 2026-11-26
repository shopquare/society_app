<?php
// api/platform_admin.php
// SaaS Platform Super Admin Console API: Societies, Plans, Billing & Support Tickets

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/audit_logger.php';

initApi(['GET', 'POST']);
requireApiPermission('saas.platform');
if (!isPlatformSuperAdmin()) {
    jsonError('Forbidden: SaaS Platform Super Admin privileges strictly required.', 403);
}

$actorId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $societies = $conn->query(
            "SELECT s.*,
                    COALESCE(sub.status, 'Active') AS subscription_status,
                    sub.expires_at, sub.grace_until, sub.billing_cycle,
                    p.id AS plan_id, p.plan_name, p.monthly_price, p.max_flats,
                    (SELECT COUNT(*) FROM sms_users u WHERE u.society_id = s.id) AS total_users,
                    (SELECT COUNT(*) FROM sms_units un WHERE un.society_id = s.id) AS total_flats
             FROM sms_societies s
             LEFT JOIN sms_society_subscriptions sub ON s.id = sub.society_id
             LEFT JOIN sms_subscription_plans p ON sub.plan_id = p.id
             ORDER BY s.id ASC"
        )->fetchAll();

        $plans = $conn->query("SELECT * FROM sms_subscription_plans ORDER BY monthly_price ASC")->fetchAll();

        $invoices = $conn->query(
            "SELECT si.*, s.society_name, p.plan_name
             FROM sms_saas_invoices si
             INNER JOIN sms_societies s ON si.society_id = s.id
             INNER JOIN sms_subscription_plans p ON si.plan_id = p.id
             ORDER BY si.created_at DESC LIMIT 80"
        )->fetchAll();

        $tickets = $conn->query(
            "SELECT t.*, s.society_name
             FROM sms_platform_tickets t
             INNER JOIN sms_societies s ON t.society_id = s.id
             ORDER BY FIELD(t.status, 'Open', 'In_Progress', 'Resolved', 'Closed'), t.created_at DESC LIMIT 80"
        )->fetchAll();

        $mrr = 0.0;
        foreach ($societies as $soc) {
            if (in_array($soc['subscription_status'], ['Active', 'Grace_Period'], true)) {
                $mrr += (float)($soc['monthly_price'] ?? 0);
            }
        }

        jsonSuccess([
            'kpis' => [
                'total_societies' => count($societies),
                'active_societies' => count(array_filter($societies, fn($s) => ($s['status'] ?? 'Active') === 'Active')),
                'trial_societies' => count(array_filter($societies, fn($s) => ($s['subscription_status'] ?? '') === 'Trial')),
                'estimated_mrr' => $mrr
            ],
            'societies' => $societies,
            'plans' => $plans,
            'saas_invoices' => $invoices,
            'tickets' => $tickets
        ]);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to load SaaS platform console.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonInput();
    requireValidCsrfToken($data['csrf_token'] ?? '');
    $action = trim((string)($data['action'] ?? ''));

    try {
        if ($action === 'assign_subscription') {
            $sid = (int)($data['society_id'] ?? 0);
            $pid = (int)($data['plan_id'] ?? 0);
            $cycle = trim((string)($data['billing_cycle'] ?? 'Monthly'));
            $status = trim((string)($data['status'] ?? 'Active'));
            $socStatus = ($status === 'Expired') ? 'Grace_Period' : (($status === 'Trial') ? 'Trial' : 'Active');

            if ($sid <= 0 || $pid <= 0) jsonError('Society and Plan IDs required.', 400);

            $plan = $conn->prepare("SELECT * FROM sms_subscription_plans WHERE id = :pid LIMIT 1");
            $plan->execute([':pid' => $pid]);
            $pRow = $plan->fetch();
            if (!$pRow) jsonError('Subscription plan not found.', 404);

            $days = ($cycle === 'Annual') ? 365 : 30;
            $expSql = ($status === 'Expired') ? "DATE_SUB(NOW(), INTERVAL 1 DAY)" : "DATE_ADD(NOW(), INTERVAL {$days} DAY)";
            $graceSql = ($status === 'Expired') ? "DATE_SUB(NOW(), INTERVAL 1 DAY)" : "DATE_ADD(NOW(), INTERVAL " . ($days + 7) . " DAY)";

            $conn->prepare(
                "INSERT INTO sms_society_subscriptions (society_id, plan_id, billing_cycle, status, starts_at, expires_at, grace_until)
                 VALUES (:sid, :pid, :cyc, :st, NOW(), {$expSql}, {$graceSql})
                 ON DUPLICATE KEY UPDATE plan_id = VALUES(plan_id), billing_cycle = VALUES(billing_cycle), status = VALUES(status), starts_at = NOW(), expires_at = {$expSql}, grace_until = {$graceSql}"
            )->execute([':sid' => $sid, ':pid' => $pid, ':cyc' => $cycle, ':st' => $status]);

            $conn->prepare("UPDATE sms_societies SET status = :ss WHERE id = :sid")->execute([':ss' => $socStatus, ':sid' => $sid]);

            $price = ($cycle === 'Annual') ? (float)$pRow['annual_price'] : (float)$pRow['monthly_price'];
            if ($price > 0 && !empty($data['generate_invoice'])) {
                $invNo = 'SAAS-' . date('Ym') . '-' . $sid . '-' . strtoupper(bin2hex(random_bytes(2)));
                $conn->prepare(
                    "INSERT INTO sms_saas_invoices (invoice_number, society_id, plan_id, amount, billing_period, status) VALUES (:ino, :sid, :pid, :amt, :bp, 'Unpaid')"
                )->execute([':ino' => $invNo, ':sid' => $sid, ':pid' => $pid, ':amt' => $price, ':bp' => "{$cycle} ({$pRow['plan_name']})"]);
            }
            jsonSuccess(['message' => 'Society subscription plan updated cleanly.']);
        } elseif ($action === 'toggle_society_suspend') {
            $sid = (int)($data['society_id'] ?? 0);
            $newSt = trim((string)($data['status'] ?? 'Active'));
            if ($sid <= 1 && $newSt === 'Suspended') jsonError('Primary flagship tenant cannot be suspended.', 400);
            if (!in_array($newSt, ['Active', 'Grace_Period', 'Suspended', 'Trial'], true)) jsonError('Invalid society status.', 400);
            $conn->prepare("UPDATE sms_societies SET status = :st WHERE id = :sid")->execute([':st' => $newSt, ':sid' => $sid]);
            jsonSuccess(['message' => "Society status set to {$newSt}."]);
        } elseif ($action === 'mark_saas_invoice_paid') {
            $iid = (int)($data['invoice_id'] ?? 0);
            $ref = trim((string)($data['payment_ref'] ?? ('SAAS-PAY-' . time())));
            $conn->prepare("UPDATE sms_saas_invoices SET status = 'Paid', paid_at = NOW(), payment_ref = :ref WHERE id = :id")
                ->execute([':ref' => $ref, ':id' => $iid]);
            jsonSuccess(['message' => 'SaaS platform invoice marked Paid.']);
        } elseif ($action === 'update_ticket') {
            $tid = (int)($data['ticket_id'] ?? 0);
            $st = trim((string)($data['status'] ?? 'Resolved'));
            $resp = trim((string)($data['platform_response'] ?? ''));
            $conn->prepare("UPDATE sms_platform_tickets SET status = :st, platform_response = :resp WHERE id = :id")
                ->execute([':st' => $st, ':resp' => $resp ?: null, ':id' => $tid]);
            jsonSuccess(['message' => 'Support ticket updated cleanly.']);
        } elseif ($action === 'save_plan') {
            $id = (int)($data['id'] ?? 0);
            $name = trim((string)($data['plan_name'] ?? ''));
            $mo = max(0, (float)($data['monthly_price'] ?? 0));
            $ann = max(0, (float)($data['annual_price'] ?? 0));
            $flats = max(5, (int)($data['max_flats'] ?? 50));
            $guards = max(1, (int)($data['max_guards'] ?? 2));
            $gates = max(1, (int)($data['max_gates'] ?? 1));
            $conn->prepare("UPDATE sms_subscription_plans SET plan_name=:pn, monthly_price=:mo, annual_price=:an, max_flats=:mf, max_guards=:mg, max_gates=:mt WHERE id=:id")
                ->execute([':pn' => $name, ':mo' => $mo, ':an' => $ann, ':mf' => $flats, ':mg' => $guards, ':mt' => $gates, ':id' => $id]);
            jsonSuccess(['message' => 'Subscription tier parameters updated.']);
        } else {
            jsonError('Invalid platform action.', 400);
        }
    } catch (Throwable $e) {
        logAndFail($e, 'SaaS platform action failed.');
    }
}
