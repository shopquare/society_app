<?php
// /society_app/api/resident_submit_ad.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['POST']);
requireApiAuth([3]);

$societyId = (int)$_SESSION['society_id'];
$residentId = (int)$_SESSION['user_id'];

$data = getJsonInput();
requireValidCsrfToken($data['csrf_token'] ?? '');

$packageId = (int)($data['package_id'] ?? 0);
$title = trim((string)($data['title'] ?? ''));
$targetUrl = trim((string)($data['target_url'] ?? ''));
$paymentMethod = trim((string)($data['payment_method'] ?? 'UPI'));
$paymentRef = trim((string)($data['payment_reference'] ?? ''));
$bannerBase64 = (string)($data['banner_base64'] ?? '');

if ($packageId <= 0 || $title === '') {
    jsonError('Package selection and ad title are required.', 400);
}

if ($paymentRef === '' || strlen($paymentRef) < 4) {
    jsonError('A valid manual payment reference (UPI Transaction ID, Bank Reference, or Cheque No.) is required.', 400);
}

try {
    // Look up package
    $pkgStmt = $conn->prepare("SELECT id, package_name, duration_days, price FROM sms_advertisement_packages WHERE id = :id AND is_active = 1");
    $pkgStmt->execute([':id' => $packageId]);
    $package = $pkgStmt->fetch();

    if (!$package) {
        jsonError('Selected advertisement package is not available.', 400);
    }

    $bannerFileName = null;
    if ($bannerBase64 !== '') {
        if (preg_match('/^data:image\/(png|jpeg|jpg|webp);base64,(.+)$/s', $bannerBase64, $matches)) {
            $binary = base64_decode($matches[2], true);
            if ($binary && strlen($binary) <= 10 * 1024 * 1024) {
                $uploadDir = dirname(__DIR__) . '/uploads/ads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $ext = 'jpg';
                $imgInfo = @getimagesizefromstring($binary);
                if ($imgInfo) {
                    $extMap = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];
                    $ext = $extMap[$imgInfo[2]] ?? 'jpg';
                }
                $bannerFileName = 'ad_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                file_put_contents($uploadDir . $bannerFileName, $binary);
            }
        }
    }

    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d', strtotime('+' . (int)$package['duration_days'] . ' days'));

    $conn->beginTransaction();

    // Normalize payment method enum to schema ('UPI_Transfer','Bank_Transfer','Cash','Cheque')
    $methodMap = ['UPI' => 'UPI_Transfer', 'Bank' => 'Bank_Transfer', 'UPI_Transfer' => 'UPI_Transfer', 'Bank_Transfer' => 'Bank_Transfer', 'Cash' => 'Cash', 'Cheque' => 'Cheque'];
    $normalizedMethod = $methodMap[$paymentMethod] ?? 'UPI_Transfer';

    // Resident ad stays in Pending_Approval until admin confirms manual payment
    $stmt = $conn->prepare(
        "INSERT INTO sms_advertisements (society_id, advertiser_name, user_id, ad_type, headline, target_url, banner_image_path, start_date, end_date, display_locations, status)
         VALUES (:sid, :adv_name, :uid, 'Resident_Paid', :title, :url, :banner, :sdate, :edate, 'dashboard_cards,offers_deals,marketplace', 'Pending_Approval')"
    );
    $stmt->execute([
        ':sid' => $societyId,
        ':adv_name' => 'Resident Ad (User #' . $residentId . ')',
        ':uid' => $residentId,
        ':title' => $title,
        ':url' => $targetUrl !== '' ? $targetUrl : null,
        ':banner' => $bannerFileName,
        ':sdate' => $startDate,
        ':edate' => $endDate
    ]);

    $adId = (int)$conn->lastInsertId();

    // Insert payment record pending admin verification
    $payStmt = $conn->prepare(
        "INSERT INTO sms_advertisement_payments (society_id, ad_id, user_id, package_id, amount, payment_method, transaction_ref, payment_status)
         VALUES (:sid, :ad_id, :uid, :pkg_id, :amount, :method, :ref, 'Pending_Approval')"
    );
    $payStmt->execute([
        ':sid' => $societyId,
        ':ad_id' => $adId,
        ':uid' => $residentId,
        ':pkg_id' => $packageId,
        ':amount' => $package['price'],
        ':method' => $normalizedMethod,
        ':ref' => $paymentRef
    ]);

    $conn->commit();

    jsonSuccess([
        'message' => 'Ad campaign submitted! It will go live after society administration verifies your payment reference: ' . htmlspecialchars($paymentRef, ENT_QUOTES, 'UTF-8') . '.',
        'advertisement_id' => $adId
    ]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    logAndFail($e, 'Unable to submit advertisement campaign.');
}
