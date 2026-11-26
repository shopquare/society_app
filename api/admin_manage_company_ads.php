<?php
// /society_app/api/admin_manage_company_ads.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['GET', 'POST']);
requireApiAuth([1, 2]);

$societyId = (int)$_SESSION['society_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $search = trim((string)($_GET['search'] ?? ''));
        $status = trim((string)($_GET['status'] ?? ''));
        $category = trim((string)($_GET['category'] ?? ''));

        $query = "SELECT a.*, 
                         CASE 
                             WHEN a.end_date < CURDATE() THEN 'Expired'
                             WHEN a.start_date > CURDATE() AND a.status = 'Active' THEN 'Scheduled'
                             ELSE a.status
                         END AS calculated_status
                  FROM sms_advertisements a
                  WHERE (a.society_id = :society_id OR a.society_id IS NULL)";
        
        $params = [':society_id' => $societyId];

        if ($search !== '') {
            $query .= " AND (a.company_name LIKE :s1 OR a.advertiser_name LIKE :s2 OR a.headline LIKE :s3 OR a.body_text LIKE :s4)";
            $term = '%' . $search . '%';
            $params[':s1'] = $term;
            $params[':s2'] = $term;
            $params[':s3'] = $term;
            $params[':s4'] = $term;
        }

        if ($category !== '' && $category !== 'all') {
            $query .= " AND a.category = :category";
            $params[':category'] = $category;
        }

        if ($status !== '' && $status !== 'all') {
            if ($status === 'Expired') {
                $query .= " AND (a.end_date < CURDATE() OR a.status = 'Expired')";
            } elseif ($status === 'Scheduled') {
                $query .= " AND (a.start_date > CURDATE() AND a.status = 'Active')";
            } else {
                $query .= " AND a.status = :status AND a.end_date >= CURDATE()";
                $params[':status'] = $status;
            }
        }

        $query .= " ORDER BY a.priority DESC, a.created_at DESC";

        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $ads = $stmt->fetchAll();

        foreach ($ads as &$ad) {
            $impressions = (int)($ad['impressions_count'] ?? 0);
            $clicks = (int)($ad['clicks_count'] ?? 0);
            $ad['ctr'] = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0.0;
            
            $ad['logo_url'] = !empty($ad['company_logo_path']) 
                ? appBasePath() . '/uploads/ads/' . ltrim($ad['company_logo_path'], '/') 
                : null;
            $ad['banner_url'] = !empty($ad['banner_image_path']) 
                ? appBasePath() . '/uploads/ads/' . ltrim($ad['banner_image_path'], '/') 
                : null;
        }
        unset($ad);

        // 2. Metrics & KPI Summary
        $metricStmt = $conn->prepare(
            "SELECT 
                COUNT(*) AS total_ads,
                SUM(CASE WHEN status = 'Active' AND start_date <= CURDATE() AND end_date >= CURDATE() THEN 1 ELSE 0 END) AS active_campaigns,
                SUM(CASE WHEN status = 'Active' AND start_date > CURDATE() THEN 1 ELSE 0 END) AS scheduled_campaigns,
                SUM(CASE WHEN end_date < CURDATE() OR status = 'Expired' THEN 1 ELSE 0 END) AS expired_campaigns,
                COALESCE(SUM(impressions_count), 0) AS total_impressions,
                COALESCE(SUM(clicks_count), 0) AS total_clicks
             FROM sms_advertisements
             WHERE society_id = :society_id OR society_id IS NULL"
        );
        $metricStmt->execute([':society_id' => $societyId]);
        $metrics = $metricStmt->fetch() ?: [
            'total_ads' => 0,
            'active_campaigns' => 0,
            'scheduled_campaigns' => 0,
            'expired_campaigns' => 0,
            'total_impressions' => 0,
            'total_clicks' => 0
        ];

        $totImp = (int)$metrics['total_impressions'];
        $totClk = (int)$metrics['total_clicks'];
        $metrics['avg_ctr'] = $totImp > 0 ? round(($totClk / $totImp) * 100, 2) : 0.0;

        // Revenue from verified ad payments
        $revStmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total_rev FROM sms_advertisement_payments WHERE society_id = :sid AND payment_status = 'Approved'");
        $revStmt->execute([':sid' => $societyId]);
        $metrics['revenue'] = (float)($revStmt->fetchColumn() ?: 0.0);

        // 3. Packages
        $pkgStmt = $conn->query("SELECT * FROM sms_advertisement_packages WHERE is_active = 1 ORDER BY price ASC");
        $packages = $pkgStmt->fetchAll();

        jsonSuccess([
            'ads' => $ads,
            'metrics' => $metrics,
            'packages' => $packages
        ]);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to load company advertisements.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if multipart form data or json
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $isMultipart = str_contains($contentType, 'multipart/form-data');

    $postData = $isMultipart ? $_POST : getJsonInput();
    requireValidCsrfToken($postData['csrf_token'] ?? '');

    $action = trim((string)($postData['action'] ?? ''));

    if ($action === 'create' || $action === 'update') {
        $adId = (int)($postData['ad_id'] ?? 0);
        $companyName = trim((string)($postData['company_name'] ?? ''));
        $headline = trim((string)($postData['headline'] ?? ''));
        $bodyText = trim((string)($postData['body_text'] ?? ''));
        $targetUrl = trim((string)($postData['target_url'] ?? ''));
        $contactPhone = trim((string)($postData['contact_phone'] ?? ''));
        $category = trim((string)($postData['category'] ?? 'Food'));
        $offerBadge = trim((string)($postData['offer_badge'] ?? ''));
        $ctaText = trim((string)($postData['cta_text'] ?? 'View Offer')) ?: 'View Offer';
        $startDate = trim((string)($postData['start_date'] ?? date('Y-m-d')));
        $endDate = trim((string)($postData['end_date'] ?? date('Y-m-d', strtotime('+30 days'))));
        $targetAudience = trim((string)($postData['target_audience'] ?? 'all'));
        $priority = max(1, min(10, (int)($postData['priority'] ?? 5)));
        $status = trim((string)($postData['status'] ?? 'Active'));

        // Handle placements array or comma string
        $displayLocations = 'dashboard_banner,carousel,offers_deals';
        if (isset($postData['display_locations'])) {
            if (is_array($postData['display_locations'])) {
                $displayLocations = implode(',', array_map('trim', $postData['display_locations']));
            } elseif (is_string($postData['display_locations'])) {
                $displayLocations = trim($postData['display_locations']);
            }
        }

        if ($companyName === '') {
            jsonError('Company Name is required.', 400);
        }
        if ($headline === '') {
            jsonError('Advertisement Title / Headline is required.', 400);
        }
        if ($targetUrl !== '' && !filter_var($targetUrl, FILTER_VALIDATE_URL)) {
            jsonError('Please provide a valid advertisement URL starting with http:// or https://.', 400);
        }
        if ($startDate > $endDate) {
            jsonError('Campaign End Date cannot be earlier than Start Date.', 400);
        }

        $validCategories = ['Food', 'Grocery', 'Shopping', 'Healthcare', 'Education', 'Services', 'Real_Estate', 'Automotive', 'Entertainment', 'Other'];
        if (!in_array($category, $validCategories, true)) {
            $category = 'Other';
        }

        $validStatuses = ['Draft', 'Active', 'Paused', 'Expired'];
        if (!in_array($status, $validStatuses, true)) {
            $status = 'Active';
        }

        // File uploads handling
        $logoPath = null;
        $bannerPath = null;

        $uploadDir = dirname(__DIR__) . '/uploads/ads';
        if (!is_dir($uploadDir . '/logos')) {
            @mkdir($uploadDir . '/logos', 0755, true);
        }
        if (!is_dir($uploadDir . '/banners')) {
            @mkdir($uploadDir . '/banners', 0755, true);
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];

        // 1. Process Logo
        if (!empty($_FILES['company_logo']['tmp_name'])) {
            $logoFile = $_FILES['company_logo'];
            if ($logoFile['error'] === UPLOAD_ERR_OK) {
                if ($logoFile['size'] > 5 * 1024 * 1024) {
                    jsonError('Company logo size exceeds 5 MB limit.', 400);
                }
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $logoFile['tmp_name']);
                finfo_close($finfo);

                if (!in_array($mime, $allowedMimes, true)) {
                    jsonError('Invalid logo file format. Permitted formats: JPEG, PNG, WebP, SVG.', 400);
                }

                $ext = match ($mime) {
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    'image/svg+xml' => 'svg',
                    default => 'jpg'
                };
                $safeName = 'logos/logo_' . bin2hex(random_bytes(8)) . '.' . $ext;
                if (move_uploaded_file($logoFile['tmp_name'], $uploadDir . '/' . $safeName)) {
                    $logoPath = $safeName;
                }
            }
        }

        // 2. Process Banner Image
        if (!empty($_FILES['banner_image']['tmp_name'])) {
            $bannerFile = $_FILES['banner_image'];
            if ($bannerFile['error'] === UPLOAD_ERR_OK) {
                if ($bannerFile['size'] > 10 * 1024 * 1024) {
                    jsonError('Banner image size exceeds 10 MB limit.', 400);
                }
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $bannerFile['tmp_name']);
                finfo_close($finfo);

                if (!in_array($mime, $allowedMimes, true)) {
                    jsonError('Invalid banner image format. Permitted formats: JPEG, PNG, WebP, SVG.', 400);
                }

                $ext = match ($mime) {
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    'image/svg+xml' => 'svg',
                    default => 'jpg'
                };
                $safeName = 'banners/banner_' . bin2hex(random_bytes(8)) . '.' . $ext;
                if (move_uploaded_file($bannerFile['tmp_name'], $uploadDir . '/' . $safeName)) {
                    $bannerPath = $safeName;
                }
            }
        }

        if ($action === 'create') {
            $insertStmt = $conn->prepare(
                "INSERT INTO sms_advertisements (
                    society_id, advertiser_name, company_name, company_logo_path, user_id, ad_type,
                    headline, body_text, banner_image_path, target_url, contact_phone, category,
                    offer_badge, cta_text, display_locations, target_audience, priority,
                    start_date, end_date, status
                ) VALUES (
                    :society_id, :adv_name, :comp_name, :logo, :uid, 'Company_Sponsored',
                    :headline, :body_text, :banner, :url, :phone, :category,
                    :badge, :cta, :locations, :audience, :priority,
                    :start_date, :end_date, :status
                )"
            );
            $insertStmt->execute([
                ':society_id' => $societyId,
                ':adv_name' => $companyName,
                ':comp_name' => $companyName,
                ':logo' => $logoPath,
                ':uid' => (int)$_SESSION['user_id'],
                ':headline' => $headline,
                ':body_text' => $bodyText,
                ':banner' => $bannerPath,
                ':url' => $targetUrl,
                ':phone' => $contactPhone,
                ':category' => $category,
                ':badge' => $offerBadge,
                ':cta' => $ctaText,
                ':locations' => $displayLocations,
                ':audience' => $targetAudience,
                ':priority' => $priority,
                ':start_date' => $startDate,
                ':end_date' => $endDate,
                ':status' => $status
            ]);
            $newId = (int)$conn->lastInsertId();

            if (function_exists('logAudit')) {
                logAudit($conn, (int)$_SESSION['user_id'], 'CREATE_COMPANY_AD', "Created advertisement #{$newId} for '{$companyName}'");
            }

            jsonSuccess([
                'message' => "Advertisement for {$companyName} created successfully!",
                'ad_id' => $newId
            ]);
        } else {
            // Update
            if ($adId <= 0) {
                jsonError('Invalid advertisement ID.', 400);
            }

            // Check existence and tenancy
            $chk = $conn->prepare("SELECT * FROM sms_advertisements WHERE id = :id AND (society_id = :sid OR society_id IS NULL)");
            $chk->execute([':id' => $adId, ':sid' => $societyId]);
            $existing = $chk->fetch();
            if (!$existing) {
                jsonError('Advertisement not found or unauthorized.', 404);
            }

            $finalLogo = $logoPath ?? $existing['company_logo_path'];
            $finalBanner = $bannerPath ?? $existing['banner_image_path'];

            $upStmt = $conn->prepare(
                "UPDATE sms_advertisements SET
                    advertiser_name = :adv_name,
                    company_name = :comp_name,
                    company_logo_path = :logo,
                    headline = :headline,
                    body_text = :body_text,
                    banner_image_path = :banner,
                    target_url = :url,
                    contact_phone = :phone,
                    category = :category,
                    offer_badge = :badge,
                    cta_text = :cta,
                    display_locations = :locations,
                    target_audience = :audience,
                    priority = :priority,
                    start_date = :start_date,
                    end_date = :end_date,
                    status = :status
                 WHERE id = :id AND (society_id = :sid OR society_id IS NULL)"
            );
            $upStmt->execute([
                ':adv_name' => $companyName,
                ':comp_name' => $companyName,
                ':logo' => $finalLogo,
                ':headline' => $headline,
                ':body_text' => $bodyText,
                ':banner' => $finalBanner,
                ':url' => $targetUrl,
                ':phone' => $contactPhone,
                ':category' => $category,
                ':badge' => $offerBadge,
                ':cta' => $ctaText,
                ':locations' => $displayLocations,
                ':audience' => $targetAudience,
                ':priority' => $priority,
                ':start_date' => $startDate,
                ':end_date' => $endDate,
                ':status' => $status,
                ':id' => $adId,
                ':sid' => $societyId
            ]);

            if (function_exists('logAudit')) {
                logAudit($conn, (int)$_SESSION['user_id'], 'UPDATE_COMPANY_AD', "Updated advertisement #{$adId} for '{$companyName}'");
            }

            jsonSuccess(['message' => "Advertisement #{$adId} updated successfully!"]);
        }
    } elseif ($action === 'toggle_status') {
        $adId = (int)($postData['ad_id'] ?? 0);
        $newStatus = trim((string)($postData['new_status'] ?? 'Active'));

        if (!in_array($newStatus, ['Active', 'Paused', 'Draft'], true)) {
            jsonError('Invalid status specified.', 400);
        }

        $stmt = $conn->prepare("UPDATE sms_advertisements SET status = :st WHERE id = :id AND (society_id = :sid OR society_id IS NULL)");
        $stmt->execute([':st' => $newStatus, ':id' => $adId, ':sid' => $societyId]);

        if (function_exists('logAudit')) {
            logAudit($conn, (int)$_SESSION['user_id'], 'TOGGLE_AD_STATUS', "Changed ad #{$adId} status to {$newStatus}");
        }

        jsonSuccess(['message' => "Campaign status updated to {$newStatus}."]);
    } elseif ($action === 'delete') {
        $adId = (int)($postData['ad_id'] ?? 0);
        if ($adId <= 0) {
            jsonError('Invalid advertisement ID.', 400);
        }

        $stmt = $conn->prepare("DELETE FROM sms_advertisements WHERE id = :id AND (society_id = :sid OR society_id IS NULL)");
        $stmt->execute([':id' => $adId, ':sid' => $societyId]);

        if (function_exists('logAudit')) {
            logAudit($conn, (int)$_SESSION['user_id'], 'DELETE_COMPANY_AD', "Deleted ad #{$adId}");
        }

        jsonSuccess(['message' => 'Advertisement removed successfully.']);
    } elseif ($action === 'save_package') {
        $pkgId = (int)($postData['package_id'] ?? 0);
        $name = trim((string)($postData['package_name'] ?? ''));
        $days = (int)($postData['duration_days'] ?? 7);
        $price = (float)($postData['price'] ?? 0.0);
        $desc = trim((string)($postData['description'] ?? ''));

        if ($name === '' || $days <= 0 || $price < 0) {
            jsonError('Please provide valid package name, positive duration days, and price.', 400);
        }

        if ($pkgId > 0) {
            $stmt = $conn->prepare("UPDATE sms_advertisement_packages SET package_name = :name, duration_days = :days, price = :price, description = :desc WHERE id = :id");
            $stmt->execute([':name' => $name, ':days' => $days, ':price' => $price, ':desc' => $desc, ':id' => $pkgId]);
            $msg = 'Advertising package updated successfully.';
        } else {
            $stmt = $conn->prepare("INSERT INTO sms_advertisement_packages (package_name, duration_days, price, description, is_active) VALUES (:name, :days, :price, :desc, 1)");
            $stmt->execute([':name' => $name, ':days' => $days, ':price' => $price, ':desc' => $desc]);
            $msg = 'New advertising package created successfully.';
        }

        jsonSuccess(['message' => $msg]);
    } else {
        jsonError('Unsupported or invalid action requested.', 400);
    }
}
