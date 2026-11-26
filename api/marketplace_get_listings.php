<?php
// /society_app/api/marketplace_get_listings.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['GET']);
requireApiAuth([1, 2, 3]);

$societyId = (int)$_SESSION['society_id'];
$userId = (int)$_SESSION['user_id'];

$category = trim((string)($_GET['category'] ?? ''));
$condition = trim((string)($_GET['condition'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));
$myListings = !empty($_GET['my_listings']) ? 1 : 0;

try {
    $sql = "SELECT m.id, m.seller_id, m.title, m.description, m.category, m.price, m.condition_type,
                   m.contact_preference, m.contact_phone, m.status, m.created_at,
                   p.first_name, p.last_name, uu.unit_number,
                   (SELECT image_path FROM sms_marketplace_images mi WHERE mi.listing_id = m.id ORDER BY mi.is_primary DESC, mi.id ASC LIMIT 1) AS primary_image,
                   IF(fav.id IS NOT NULL, 1, 0) AS is_saved
            FROM sms_marketplace_listings m
            INNER JOIN sms_users u ON m.seller_id = u.id
            INNER JOIN sms_user_profiles p ON u.id = p.user_id
            LEFT JOIN sms_user_units uu ON u.id = uu.user_id AND uu.is_current_occupant = 1
            LEFT JOIN sms_marketplace_saved fav ON m.id = fav.listing_id AND fav.user_id = :user_id
            WHERE m.society_id = :society_id";

    $params = [
        ':society_id' => $societyId,
        ':user_id' => $userId
    ];

    if ($myListings) {
        $sql .= " AND m.seller_id = :seller_id AND m.status != 'Deleted'";
        $params[':seller_id'] = $userId;
    } else {
        $sql .= " AND m.status = 'Active'";
    }

    if ($category !== '' && $category !== 'All') {
        $sql .= " AND m.category = :category";
        $params[':category'] = $category;
    }

    if ($condition !== '' && $condition !== 'All') {
        $sql .= " AND m.condition_type = :condition_type";
        $params[':condition_type'] = $condition;
    }

    if ($search !== '') {
        $sql .= " AND (m.title LIKE :search OR m.description LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    $sql .= " ORDER BY m.created_at DESC LIMIT 50";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $listings = $stmt->fetchAll();

    foreach ($listings as &$item) {
        $item['formatted_price'] = '₹' . number_format((float)$item['price'], 2);
        $item['seller_name'] = trim($item['first_name'] . ' ' . $item['last_name']);
        if ($item['primary_image']) {
            $item['image_url'] = appBasePath() . '/uploads/marketplace/' . htmlspecialchars($item['primary_image'], ENT_QUOTES, 'UTF-8');
        } else {
            $item['image_url'] = null;
        }
    }
    unset($item);

    jsonSuccess(['data' => $listings]);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to load marketplace listings.');
}
