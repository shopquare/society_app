<?php
// /society_app/api/get_advertisements.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['GET']);

$page = strtolower(trim((string)($_GET['page'] ?? 'resident_dashboard')));
$placement = strtolower(trim((string)($_GET['placement'] ?? '')));
$category = trim((string)($_GET['category'] ?? ''));
$societyId = isset($_SESSION['society_id']) ? (int)$_SESSION['society_id'] : 1;

// SAFETY INVARIANT: Strictly forbid ads on Emergency, Login, Auth, and Guard Security terminals
$prohibitedPages = ['emergency', 'sos', 'login', 'register', 'forgot_password', 'reset_password', 'guard_dashboard', 'auth', 'terminal'];
foreach ($prohibitedPages as $prohibited) {
    if (strpos($page, $prohibited) !== false) {
        jsonSuccess([
            'ads' => [],
            'banners' => [],
            'carousel' => [],
            'cards' => [],
            'offers' => [],
            'adsense' => null,
            'notice' => 'Advertisements are strictly prohibited on security, emergency, and authentication interfaces.'
        ]);
    }
}

try {
    $today = date('Y-m-d');

    // 1. Fetch valid, active company advertisements
    $query = "SELECT id, society_id, advertiser_name, company_name, company_logo_path,
                     headline, body_text, banner_image_path, target_url, contact_phone,
                     category, offer_badge, cta_text, display_locations, target_audience,
                     priority, start_date, end_date, impressions_count, clicks_count
              FROM sms_advertisements
              WHERE (society_id = :society_id OR society_id IS NULL)
                AND status IN ('Active', 'Approved')
                AND start_date <= :today1
                AND end_date >= :today2";

    $params = [
        ':society_id' => $societyId,
        ':today1' => $today,
        ':today2' => $today
    ];

    if ($category !== '' && $category !== 'all') {
        $query .= " AND category = :cat";
        $params[':cat'] = $category;
    }

    $query .= " ORDER BY priority DESC, RAND()";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $allAds = $stmt->fetchAll();

    $base = appBasePath();
    $banners = [];
    $carousel = [];
    $cards = [];
    $offers = [];

    foreach ($allAds as &$ad) {
        $ad['company_name'] = $ad['company_name'] ?: $ad['advertiser_name'];
        $ad['logo_url'] = !empty($ad['company_logo_path']) 
            ? $base . '/uploads/ads/' . ltrim($ad['company_logo_path'], '/') 
            : null;
        $ad['banner_url'] = !empty($ad['banner_image_path']) 
            ? $base . '/uploads/ads/' . ltrim($ad['banner_image_path'], '/') 
            : null;
        $ad['image_url'] = $ad['banner_url']; // backward-compatibility

        $locations = array_map('trim', explode(',', strtolower($ad['display_locations'] ?? '')));

        // Categorize into placements
        if (in_array('carousel', $locations, true) || in_array('all', $locations, true)) {
            $carousel[] = $ad;
        }
        if (in_array('dashboard_banner', $locations, true) || in_array('resident_dashboard', $locations, true)) {
            $banners[] = $ad;
        }
        if (in_array('dashboard_cards', $locations, true) || in_array('offers_deals', $locations, true) || in_array('marketplace', $locations, true) || in_array('community', $locations, true)) {
            $cards[] = $ad;
        }
        $offers[] = $ad;
    }
    unset($ad);

    // Filter by placement if explicitly requested
    $filteredAds = $allAds;
    if ($placement !== '') {
        $filteredAds = array_filter($allAds, function($item) use ($placement) {
            $locs = array_map('trim', explode(',', strtolower($item['display_locations'] ?? '')));
            return in_array($placement, $locs, true) || in_array('all', $locs, true);
        });
        $filteredAds = array_values($filteredAds);
    }

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
        'ads' => $filteredAds,
        'banners' => $banners,
        'carousel' => $carousel,
        'cards' => $cards,
        'offers' => $offers,
        'adsense' => $adsense,
        'count' => count($filteredAds)
    ]);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to load advertisements.');
}
