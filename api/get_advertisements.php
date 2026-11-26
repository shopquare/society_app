<?php
// /society_app/api/get_advertisements.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['GET']);

$page = strtolower(trim((string)($_GET['page'] ?? 'resident_dashboard')));
$societyId = isset($_SESSION['society_id']) ? (int)$_SESSION['society_id'] : 1;

// SAFETY INVARIANT: Strictly forbid ads on Emergency, Login, Auth, and Guard Security terminals
$prohibitedPages = ['emergency', 'sos', 'login', 'register', 'forgot_password', 'reset_password', 'guard_dashboard', 'auth', 'terminal'];
foreach ($prohibitedPages as $prohibited) {
    if (strpos($page, $prohibited) !== false) {
        jsonSuccess([
            'ads' => [],
            'adsense' => null,
            'notice' => 'Advertisements are strictly prohibited on security, emergency, and authentication interfaces.'
        ]);
    }
}

try {
    $today = date('Y-m-d');

    // 1. Fetch custom banners
    $stmt = $conn->prepare(
        "SELECT id, advertiser_name, title, target_url, banner_image_path, impressions_count, clicks_count
         FROM sms_advertisements
         WHERE (society_id = :society_id OR society_id IS NULL)
           AND ad_type IN ('Custom_Banner', 'Resident_Paid')
           AND status = 'Approved'
           AND (start_date IS NULL OR start_date <= :today1)
           AND (end_date IS NULL OR end_date >= :today2)
           AND (permitted_pages = 'all' OR permitted_pages LIKE :page_like)
         ORDER BY RAND()
         LIMIT 3"
    );
    $stmt->execute([
        ':society_id' => $societyId,
        ':today1' => $today,
        ':today2' => $today,
        ':page_like' => '%' . $page . '%'
    ]);
    $ads = $stmt->fetchAll();

    foreach ($ads as &$ad) {
        if (!empty($ad['banner_image_path'])) {
            $ad['image_url'] = appBasePath() . '/uploads/ads/' . htmlspecialchars($ad['banner_image_path'], ENT_QUOTES, 'UTF-8');
        } else {
            $ad['image_url'] = null;
        }
    }
    unset($ad);

    // 2. Fetch AdSense config
    $setStmt = $conn->prepare(
        "SELECT adsense_enabled, adsense_publisher_id, adsense_slot_id
         FROM sms_ad_settings
         WHERE society_id = :society_id OR society_id IS NULL
         ORDER BY id DESC LIMIT 1"
    );
    $setStmt->execute([':society_id' => $societyId]);
    $settings = $setStmt->fetch();

    $adsense = null;
    if ($settings && !empty($settings['adsense_enabled'])) {
        $adsense = [
            'publisher_id' => $settings['adsense_publisher_id'],
            'slot_id' => $settings['adsense_slot_id']
        ];
    }

    jsonSuccess([
        'ads' => $ads,
        'adsense' => $adsense
    ]);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to load advertisements.');
}
