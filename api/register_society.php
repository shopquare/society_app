<?php
// api/register_society.php
// Self-Service SaaS Tenant Onboarding: Create New Society + Initial Super Admin + Subscription Plan

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/audit_logger.php';

initApi(['GET', 'POST']);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $plans = $conn->query("SELECT id, plan_slug, plan_name, monthly_price, annual_price, max_flats, max_guards, max_gates, features_json FROM sms_subscription_plans WHERE is_active = 1 ORDER BY monthly_price ASC")->fetchAll();
    jsonSuccess(['plans' => $plans]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonInput();
    requireValidCsrfToken($data['csrf_token'] ?? '');

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!checkRateLimit("register_society_{$ip}", 5, 3600)) {
        jsonError('Too many society onboarding attempts. Please try again later.', 429);
    }

    $societyName = trim((string)($data['society_name'] ?? ''));
    $societyCode = strtoupper(preg_replace('/[^A-Z0-9_-]/i', '', trim((string)($data['society_code'] ?? ''))));
    $city = trim((string)($data['city'] ?? 'Pune'));
    $state = trim((string)($data['state'] ?? 'Maharashtra'));
    $zip = trim((string)($data['zip_code'] ?? '411001'));
    $address = trim((string)($data['address'] ?? ''));
    $planId = max(1, (int)($data['plan_id'] ?? 1));

    $firstName = trim((string)($data['first_name'] ?? ''));
    $lastName = trim((string)($data['last_name'] ?? 'Admin'));
    $email = strtolower(trim((string)($data['email'] ?? '')));
    $phone = normalizeIndianPhoneNumber((string)($data['phone_number'] ?? ''));
    $password = (string)($data['password'] ?? '');

    if ($societyName === '' || strlen($societyCode) < 3 || $firstName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6 || $phone === '') {
        jsonError('Please fill all required society and administrator fields cleanly (password min 6 chars, valid email & phone).', 400);
    }

    try {
        $chkSoc = $conn->prepare("SELECT id FROM sms_societies WHERE society_code = :sc LIMIT 1");
        $chkSoc->execute([':sc' => $societyCode]);
        if ($chkSoc->fetch()) {
            jsonError('Society invite code is already taken. Choose another unique code.', 409);
        }

        $chkMail = $conn->prepare("SELECT id FROM sms_users WHERE email = :em LIMIT 1");
        $chkMail->execute([':em' => $email]);
        if ($chkMail->fetch()) {
            jsonError('Administrator email address is already registered.', 409);
        }

        $plan = $conn->prepare("SELECT * FROM sms_subscription_plans WHERE id = :pid AND is_active = 1 LIMIT 1");
        $plan->execute([':pid' => $planId]);
        $planRow = $plan->fetch();
        if (!$planRow) {
            jsonError('Selected subscription plan not found.', 400);
        }

        $conn->beginTransaction();

        $trialEnd = date('Y-m-d H:i:s', strtotime('+14 days'));
        $conn->prepare(
            "INSERT INTO sms_societies (society_name, society_code, address, city, state, zip_code, status, trial_ends_at)
             VALUES (:sn, :sc, :ad, :ct, :st, :zp, 'Trial', :te)"
        )->execute([
            ':sn' => $societyName, ':sc' => $societyCode, ':ad' => $address ?: $city,
            ':ct' => $city, ':st' => $state, ':zp' => $zip, ':te' => $trialEnd
        ]);
        $newSocId = (int)$conn->lastInsertId();

        // Create initial Society Super Admin (role_id = 1, NOT platform super admin)
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $conn->prepare(
            "INSERT INTO sms_users (society_id, email, password_hash, role_id, is_platform_admin, status)
             VALUES (:sid, :em, :pw, 1, 0, 'active')"
        )->execute([':sid' => $newSocId, ':em' => $email, ':pw' => $hash]);
        $newAdminId = (int)$conn->lastInsertId();

        $conn->prepare("INSERT INTO sms_user_profiles (user_id, first_name, last_name, phone_number) VALUES (:uid, :fn, :ln, :ph)")
            ->execute([':uid' => $newAdminId, ':fn' => $firstName, ':ln' => $lastName, ':ph' => $phone]);

        // Seed starter subscription, main gate & starter building block
        $conn->prepare(
            "INSERT INTO sms_society_subscriptions (society_id, plan_id, billing_cycle, status, starts_at, expires_at, grace_until)
             VALUES (:sid, :pid, 'Monthly', 'Trial', NOW(), :exp, DATE_ADD(:exp2, INTERVAL 7 DAY))"
        )->execute([':sid' => $newSocId, ':pid' => $planId, ':exp' => $trialEnd, ':exp2' => $trialEnd]);

        $conn->prepare(
            "INSERT INTO sms_gates (society_id, gate_name, gate_code, gate_type, operating_hours, is_active)
             VALUES (:sid, 'Main Entrance Gate', 'MAIN', 'Main_Gate', '24x7', 1)"
        )->execute([':sid' => $newSocId]);

        $conn->prepare("INSERT INTO sms_buildings (society_id, building_name, block_code, total_floors) VALUES (:sid, 'Tower A', 'A', 10)")
            ->execute([':sid' => $newSocId]);

        $conn->commit();
        logAudit($conn, $newSocId, $newAdminId, 'SOCIETY_ONBOARDED', 'society', $newSocId, ['plan' => $planRow['plan_name']]);

        jsonSuccess([
            'message' => "Welcome to SecureGate! Society '{$societyName}' created with code {$societyCode} (14-day Trial).",
            'society_id' => $newSocId,
            'society_code' => $societyCode
        ], 201);
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        logAndFail($e, 'Unable to complete society registration.');
    }
}
