<?php
// includes/saas_guard.php
// Multi-Tenant SaaS Subscription Enforcement, Grace Period & Plan Quota Helpers

/**
 * Fetches active plan & subscription state for a society.
 * Preserves full data read access even if subscription enters Expired state.
 */
function getSocietySubscriptionContext(PDO $conn, int $societyId): array {
    $stmt = $conn->prepare(
        "SELECT sub.*, p.plan_slug, p.plan_name, p.max_flats, p.max_guards, p.max_gates, p.features_json, s.status AS society_status
         FROM sms_society_subscriptions sub
         INNER JOIN sms_subscription_plans p ON sub.plan_id = p.id
         INNER JOIN sms_societies s ON sub.society_id = s.id
         WHERE sub.society_id = :sid
         LIMIT 1"
    );
    $stmt->execute([':sid' => $societyId]);
    $row = $stmt->fetch();

    if (!$row) {
        return [
            'plan_slug' => 'enterprise',
            'plan_name' => 'Default Plan',
            'status' => 'Active',
            'is_writable' => true,
            'max_flats' => 5000,
            'max_guards' => 100,
            'max_gates' => 30,
            'features' => ['multi_gate' => true, 'community_suite' => true, 'financial_v2' => true, 'service_directory' => true, 'document_vault' => true, 'whatsapp_alerts' => true, 'api_access' => true]
        ];
    }

    $features = json_decode((string)($row['features_json'] ?? '{}'), true) ?: [];
    $effectiveStatus = (string)$row['status'];
    $nowTs = time();
    if ($effectiveStatus !== 'Expired' && !empty($row['expires_at']) && strtotime($row['expires_at']) < $nowTs) {
        $graceTs = !empty($row['grace_until']) ? strtotime($row['grace_until']) : strtotime($row['expires_at'] . ' +7 days');
        $effectiveStatus = ($nowTs <= $graceTs) ? 'Grace_Period' : 'Expired';
    }

    $isWritable = !in_array($effectiveStatus, ['Expired', 'Cancelled'], true) && (($row['society_status'] ?? 'Active') !== 'Suspended');

    return [
        'plan_slug' => (string)$row['plan_slug'],
        'plan_name' => (string)$row['plan_name'],
        'status' => $effectiveStatus,
        'is_writable' => $isWritable,
        'max_flats' => (int)$row['max_flats'],
        'max_guards' => (int)$row['max_guards'],
        'max_gates' => (int)$row['max_gates'],
        'features' => $features,
        'expires_at' => $row['expires_at'] ?? null,
        'grace_until' => $row['grace_until'] ?? null,
    ];
}

/**
 * Checks whether a society's subscription plan enables a specific feature key.
 */
function hasSocietyFeature(PDO $conn, int $societyId, string $featureKey): bool {
    $ctx = getSocietySubscriptionContext($conn, $societyId);
    if ($ctx['status'] === 'Expired') {
        return false;
    }
    return !empty($ctx['features'][$featureKey]);
}

/**
 * Verifies society quota before adding flats, guards, or gates.
 */
function canSocietyAddEntity(PDO $conn, int $societyId, string $entityType): array {
    $ctx = getSocietySubscriptionContext($conn, $societyId);
    if (!$ctx['is_writable']) {
        return ['allowed' => false, 'message' => 'Society subscription expired beyond grace period. Existing data is preserved in read-only mode; renew plan to create new records.'];
    }

    if ($entityType === 'flats') {
        $curr = (int)$conn->query("SELECT COUNT(*) FROM sms_units WHERE society_id = {$societyId}")->fetchColumn();
        if ($curr >= $ctx['max_flats']) {
            return ['allowed' => false, 'message' => "Flat limit ({$ctx['max_flats']}) reached on {$ctx['plan_name']} plan. Upgrade plan to add more flats."];
        }
    } elseif ($entityType === 'guards') {
        $curr = (int)$conn->query("SELECT COUNT(*) FROM sms_users WHERE society_id = {$societyId} AND role_id = 4 AND status = 'active'")->fetchColumn();
        if ($curr >= $ctx['max_guards']) {
            return ['allowed' => false, 'message' => "Guard quota ({$ctx['max_guards']}) reached on {$ctx['plan_name']} plan."];
        }
    } elseif ($entityType === 'gates') {
        $curr = (int)$conn->query("SELECT COUNT(*) FROM sms_gates WHERE society_id = {$societyId} AND is_active = 1")->fetchColumn();
        if ($curr >= $ctx['max_gates']) {
            return ['allowed' => false, 'message' => "Active gate limit ({$ctx['max_gates']}) reached on {$ctx['plan_name']} plan."];
        }
    }

    return ['allowed' => true, 'message' => 'Within plan quota'];
}
