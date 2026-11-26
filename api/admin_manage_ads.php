<?php
// /society_app/api/admin_manage_ads.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['GET', 'POST']);
requireApiAuth([1, 2]);

$societyId = (int)$_SESSION['society_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // 1. AdSense settings
        $setStmt = $conn->prepare("SELECT * FROM sms_ad_settings WHERE society_id = :sid ORDER BY id DESC LIMIT 1");
        $setStmt->execute([':sid' => $societyId]);
        $settings = $setStmt->fetch() ?: [
            'adsense_enabled' => 0,
            'adsense_publisher_id' => '',
            'adsense_slot_id' => '',
            'allow_resident_ads' => 1
        ];
        if (!isset($settings['custom_ads_enabled'])) {
            $settings['custom_ads_enabled'] = (int)($settings['allow_resident_ads'] ?? 1);
        }

        // 2. All advertisements with payment details
        $adStmt = $conn->prepare(
            "SELECT a.*, a.headline AS title, p.transaction_ref AS payment_reference, p.amount, p.payment_status AS payment_status,
                    u.email AS user_email, prof.first_name, prof.last_name, pkg.package_name
             FROM sms_advertisements a
             LEFT JOIN sms_advertisement_payments p ON a.id = p.ad_id
             LEFT JOIN sms_users u ON a.user_id = u.id
             LEFT JOIN sms_user_profiles prof ON u.id = prof.user_id
             LEFT JOIN sms_advertisement_packages pkg ON p.package_id = pkg.id
             WHERE a.society_id = :sid OR a.society_id IS NULL
             ORDER BY a.created_at DESC"
        );
        $adStmt->execute([':sid' => $societyId]);
        $ads = $adStmt->fetchAll();

        // 3. Packages
        $pkgStmt = $conn->query("SELECT * FROM sms_advertisement_packages WHERE is_active = 1 ORDER BY price ASC");
        $packages = $pkgStmt->fetchAll();

        jsonSuccess([
            'settings' => $settings,
            'ads' => $ads,
            'packages' => $packages
        ]);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to load monetization settings.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonInput();
    requireValidCsrfToken($data['csrf_token'] ?? '');

    $action = trim((string)($data['action'] ?? ''));

    if ($action === 'update_adsense') {
        $enabled = !empty($data['adsense_enabled']) ? 1 : 0;
        $pubId = trim((string)($data['adsense_publisher_id'] ?? ''));
        $slotId = trim((string)($data['adsense_slot_id'] ?? ''));

        $stmt = $conn->prepare(
            "INSERT INTO sms_ad_settings (society_id, ads_enabled, adsense_publisher_id, adsense_slot_id, allow_resident_ads)
             VALUES (:sid, :en, :pub, :slot, 1)
             ON DUPLICATE KEY UPDATE
             ads_enabled = VALUES(ads_enabled),
             adsense_publisher_id = VALUES(adsense_publisher_id),
             adsense_slot_id = VALUES(adsense_slot_id)"
        );
        $stmt->execute([
            ':sid' => $societyId,
            ':en' => $enabled,
            ':pub' => $pubId,
            ':slot' => $slotId
        ]);

        jsonSuccess(['message' => 'AdSense settings updated successfully.']);
    } elseif ($action === 'verify_payment') {
        $adId = (int)($data['ad_id'] ?? 0);
        $decision = trim((string)($data['decision'] ?? '')); // 'Approved' or 'Rejected'

        if ($adId <= 0 || !in_array($decision, ['Approved', 'Rejected'], true)) {
            jsonError('Invalid advertisement verification parameters.', 400);
        }

        $conn->beginTransaction();
        try {
            $adStatus = ($decision === 'Approved') ? 'Active' : 'Rejected';
            $upAd = $conn->prepare("UPDATE sms_advertisements SET status = :st WHERE id = :id AND (society_id = :sid OR society_id IS NULL)");
            $upAd->execute([':st' => $adStatus, ':id' => $adId, ':sid' => $societyId]);

            $upPay = $conn->prepare("UPDATE sms_advertisement_payments SET payment_status = :st, approved_by = :uid, approved_at = NOW() WHERE ad_id = :id");
            $upPay->execute([':st' => $decision, ':uid' => (int)$_SESSION['user_id'], ':id' => $adId]);

            $conn->commit();
            jsonSuccess(['message' => "Advertisement {$decision} successfully!"]);
        } catch (Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            logAndFail($e, 'Unable to verify advertisement payment.');
        }
    } else {
        jsonError('Unknown action.', 400);
    }
}
