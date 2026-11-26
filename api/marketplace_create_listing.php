<?php
// /society_app/api/marketplace_create_listing.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['POST']);
requireApiAuth([3]);

$societyId = (int)$_SESSION['society_id'];
$sellerId = (int)$_SESSION['user_id'];

$data = getJsonInput();
requireValidCsrfToken($data['csrf_token'] ?? '');

$title = trim((string)($data['title'] ?? ''));
$description = trim((string)($data['description'] ?? ''));
$category = trim((string)($data['category'] ?? 'Other'));
$price = (float)($data['price'] ?? 0);
$conditionType = trim((string)($data['condition_type'] ?? 'Good'));
$contactPreference = trim((string)($data['contact_preference'] ?? 'Phone'));
$contactPhone = trim((string)($data['contact_phone'] ?? ''));
$photos = is_array($data['photos'] ?? null) ? $data['photos'] : [];

$allowedCategories = ['Furniture', 'Electronics', 'Vehicles', 'Books', 'Home Appliances', 'Sports & Fitness', 'Kids & Toys', 'Fashion', 'Other'];
$allowedConditions = ['New', 'Like New', 'Good', 'Used'];
$allowedPreferences = ['Phone', 'WhatsApp', 'In-Person'];

if ($title === '' || strlen($title) < 3) {
    jsonError('Please provide a listing title (at least 3 characters).', 400);
}
if ($price < 0) {
    jsonError('Price cannot be negative.', 400);
}
if (!in_array($category, $allowedCategories, true)) {
    $category = 'Other';
}
if (!in_array($conditionType, $allowedConditions, true)) {
    $conditionType = 'Good';
}
if (!in_array($contactPreference, $allowedPreferences, true)) {
    $contactPreference = 'Phone';
}

if ($contactPhone !== '') {
    $cleanPhone = normalizeIndianPhoneNumber($contactPhone);
    if ($cleanPhone === null) {
        jsonError('Please provide a valid 10-digit phone number for contact.', 400);
    }
    $contactPhone = $cleanPhone;
}

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare(
        "INSERT INTO sms_marketplace_listings (society_id, seller_id, title, description, category, price, condition_type, contact_preference, contact_phone, status)
         VALUES (:society_id, :seller_id, :title, :description, :category, :price, :condition_type, :contact_pref, :contact_phone, 'Active')"
    );
    $stmt->execute([
        ':society_id' => $societyId,
        ':seller_id' => $sellerId,
        ':title' => $title,
        ':description' => $description !== '' ? $description : null,
        ':category' => $category,
        ':price' => $price,
        ':condition_type' => $conditionType,
        ':contact_pref' => $contactPreference,
        ':contact_phone' => $contactPhone !== '' ? $contactPhone : null
    ]);

    $listingId = (int)$conn->lastInsertId();

    // Process up to 5 photos
    $uploadDir = dirname(__DIR__) . '/uploads/marketplace/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $photoCount = 0;
    foreach ($photos as $base64Photo) {
        if ($photoCount >= 5) break;
        if (!is_string($base64Photo) || $base64Photo === '') continue;

        if (preg_match('/^data:image\/(png|jpeg|jpg|webp);base64,(.+)$/s', $base64Photo, $matches)) {
            $binary = base64_decode($matches[2], true);
            if ($binary === false || strlen($binary) === 0 || strlen($binary) > 10 * 1024 * 1024) continue;

            $imgInfo = @getimagesizefromstring($binary);
            if ($imgInfo === false) continue;

            $extMap = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];
            $ext = $extMap[$imgInfo[2]] ?? 'jpg';

            $fileName = 'mkt_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $targetPath = $uploadDir . $fileName;

            if (file_put_contents($targetPath, $binary) !== false) {
                $isPrimary = ($photoCount === 0) ? 1 : 0;
                $imgStmt = $conn->prepare(
                    "INSERT INTO sms_marketplace_images (listing_id, image_path, is_primary)
                     VALUES (:listing_id, :image_path, :is_primary)"
                );
                $imgStmt->execute([
                    ':listing_id' => $listingId,
                    ':image_path' => $fileName,
                    ':is_primary' => $isPrimary
                ]);
                $photoCount++;
            }
        }
    }

    $conn->commit();

    jsonSuccess([
        'message' => 'Listing posted successfully!',
        'listing_id' => $listingId
    ]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    logAndFail($e, 'Unable to create marketplace listing.');
}
