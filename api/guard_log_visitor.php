<?php
// /society_app/api/guard_log_visitor.php

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

initApi(['POST']);
requireApiAuth([4]);

$data = getJsonInput();
requireValidCsrfToken($data['csrf_token'] ?? '');

$societyId = (int)$_SESSION['society_id'];
$guardId = (int)$_SESSION['user_id'];

$residentId = (int)($data['resident_id'] ?? 0);
$visitorName = trim((string)($data['visitor_name'] ?? ''));
$phoneNumber = trim((string)($data['phone_number'] ?? ''));
$purpose = trim((string)($data['purpose'] ?? 'Guest'));
$photoBase64 = (string)($data['photo_base64'] ?? '');
$pinCode = trim((string)($data['pin_code'] ?? ''));

$allowedPurposes = ['Delivery', 'Guest', 'Service', 'Cab', 'Meeting'];

if ($residentId <= 0) {
    jsonError('Please select a valid Destination Unit.', 400);
}
if ($visitorName === '' || $phoneNumber === '') {
    jsonError('Visitor name and phone number are required.', 400);
}
if (!in_array($purpose, $allowedPurposes, true)) {
    jsonError('Invalid visit purpose selected.', 400);
}

/**
 * Validates, resizes (max 480px width), and saves a Base64 visitor photo outside public HTTP paths.
 */
function saveValidatedVisitorPhoto(string $photoBase64): ?string {
    if ($photoBase64 === '') {
        return null;
    }

    if (!preg_match('/^data:image\/(png|jpeg|jpg|webp);base64,(.+)$/s', $photoBase64, $matches)) {
        jsonError('Invalid photo format.', 400);
    }

    $binary = base64_decode($matches[2], true);
    if ($binary === false || strlen($binary) === 0) {
        jsonError('Corrupted photo data.', 400);
    }
    if (strlen($binary) > 2 * 1024 * 1024) {
        jsonError('Visitor photo exceeds the 2 MB limit.', 400);
    }

    $imgInfo = @getimagesizefromstring($binary);
    if ($imgInfo === false || empty($imgInfo[0]) || empty($imgInfo[1])) {
        jsonError('Uploaded file is not a valid image.', 400);
    }

    $uploadDir = dirname(__DIR__) . '/uploads/visitors/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filePrefix = time() . '_' . bin2hex(random_bytes(6));

    // Compress and downscale to JPEG when GD is available
    if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
        $src = @imagecreatefromstring($binary);
        if ($src === false) {
            jsonError('Unable to decode visitor image.', 400);
        }

        $origW = imagesx($src);
        $origH = imagesy($src);
        $maxW = 480;
        $dst = $src;

        if ($origW > $maxW) {
            $newW = $maxW;
            $newH = max(1, (int)round($origH * ($maxW / $origW)));
            $resized = imagecreatetruecolor($newW, $newH);
            if ($resized !== false) {
                imagecopyresampled($resized, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
                imagedestroy($src);
                $dst = $resized;
            }
        }

        $fileName = $filePrefix . '.jpg';
        $fullPath = $uploadDir . $fileName;
        $ok = imagejpeg($dst, $fullPath, 75);
        imagedestroy($dst);

        if (!$ok) {
            throw new RuntimeException('Failed to write visitor photo.');
        }

        return $fileName;
    }

    $extMap = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];
    $ext = $extMap[$imgInfo[2]] ?? null;
    if ($ext === null) {
        jsonError('Unsupported image type.', 400);
    }

    $fileName = $filePrefix . '.' . $ext;
    $fullPath = $uploadDir . $fileName;
    if (file_put_contents($fullPath, $binary) === false) {
        throw new RuntimeException('Failed to save visitor photo.');
    }

    return $fileName;
}

try {
    // Ensure destination resident exists, is active, and belongs to guard's society
    $resStmt = $conn->prepare(
        "SELECT u.id, uu.unit_number
         FROM sms_users u
         LEFT JOIN sms_user_units uu ON u.id = uu.user_id AND uu.is_current_occupant = 1
         WHERE u.id = :id AND u.society_id = :society_id AND u.role_id = 3 AND u.status = 'active'
         LIMIT 1"
    );
    $resStmt->execute([':id' => $residentId, ':society_id' => $societyId]);
    $resRow = $resStmt->fetch();
    if (!$resRow) {
        jsonError('Destination resident not found or inactive.', 400);
    }
    $targetUnitNumber = trim((string)($resRow['unit_number'] ?? ''));

    $photoFileName = saveValidatedVisitorPhoto($photoBase64);
    $photoPathDb = $photoFileName;

    $conn->beginTransaction();

    $visitorStatus = 'Pending';
    if ($pinCode !== '') {
        if (!preg_match('/^\d{6}$/', $pinCode)) {
            $conn->rollBack();
            jsonError('Invalid 6-digit pass PIN format.', 400);
        }

        $passStmt = $conn->prepare(
            "SELECT id FROM sms_visitor_passes
             WHERE pin_code = :pin_code
               AND society_id = :society_id
               AND resident_id = :resident_id
               AND status = 'Active'
               AND valid_date >= CURDATE()
             LIMIT 1
             FOR UPDATE"
        );
        $passStmt->execute([
            ':pin_code' => $pinCode,
            ':society_id' => $societyId,
            ':resident_id' => $residentId,
        ]);
        $pass = $passStmt->fetch();

        if (!$pass) {
            $conn->rollBack();
            jsonError('Guest pass PIN is invalid, expired, or already used.', 400);
        }

        $markPass = $conn->prepare("UPDATE sms_visitor_passes SET status = 'Used' WHERE id = :id");
        $markPass->execute([':id' => $pass['id']]);
        $visitorStatus = 'Entered';
    }

    $deliveryInstruction = null;
    if ($purpose === 'Delivery' && $visitorStatus === 'Pending') {
        $now = date('Y-m-d H:i:s');
        $preStmt = $conn->prepare(
            "SELECT id, company, instruction FROM sms_delivery_preapprovals
             WHERE society_id = :society_id AND resident_id = :resident_id AND status = 'Active' AND valid_until > :now
             ORDER BY created_at DESC LIMIT 1"
        );
        $preStmt->execute([
            ':society_id' => $societyId,
            ':resident_id' => $residentId,
            ':now' => $now
        ]);
        $preRow = $preStmt->fetch();
        if ($preRow) {
            $deliveryInstruction = $preRow['instruction'];
            if ($deliveryInstruction === 'Allow Entry') {
                $visitorStatus = 'Entered';
            }
        }
    }

    $nowIst = date('Y-m-d H:i:s');
    $stmt = $conn->prepare(
        'INSERT INTO sms_visitors (society_id, resident_id, guard_id, visitor_name, phone_number, purpose, photo_path, status, entry_time)
         VALUES (:society_id, :resident_id, :guard_id, :visitor_name, :phone_number, :purpose, :photo_path, :status, :entry_time)'
    );
    $stmt->execute([
        ':society_id' => $societyId,
        ':resident_id' => $residentId,
        ':guard_id' => $guardId,
        ':visitor_name' => $visitorName,
        ':phone_number' => $phoneNumber,
        ':purpose' => $purpose,
        ':photo_path' => $photoPathDb,
        ':status' => $visitorStatus,
        ':entry_time' => $nowIst,
    ]);

    $visitorId = (int)$conn->lastInsertId();
    $conn->commit();

    // Dispatch Web Push only when resident approval is pending and VAPID keys are configured
    if ($visitorStatus === 'Pending') {
        $vapidPublic = envValue('VAPID_PUBLIC_KEY', '');
        $vapidPrivate = envValue('VAPID_PRIVATE_KEY', '');
        $vapidSubject = envValue('VAPID_SUBJECT', '');

        if ($vapidPublic !== '' && $vapidPrivate !== '' && $vapidSubject !== '') {
            $subStmt = $conn->prepare('SELECT subscription FROM sms_user_push_subscriptions WHERE user_id = :user_id LIMIT 1');
            $subStmt->execute([':user_id' => $residentId]);
            $subRow = $subStmt->fetch();

            if ($subRow && !empty($subRow['subscription'])) {
                try {
                    $auth = [
                        'VAPID' => [
                            'subject' => $vapidSubject,
                            'publicKey' => $vapidPublic,
                            'privateKey' => $vapidPrivate,
                        ],
                    ];

                    $httpClient = new \GuzzleHttp\Client([
                        'timeout' => 4.0,
                        'connect_timeout' => 2.0,
                    ]);
                    $webPush = new \Minishlink\WebPush\WebPush($auth, [], $httpClient);
                    $subDecoded = json_decode($subRow['subscription'], true);

                    if (is_array($subDecoded)) {
                        $subscription = \Minishlink\WebPush\Subscription::create($subDecoded);
                        $actionToken = signVisitorActionToken($visitorId, $residentId);
                        $baseUrl = appUrl() . appBasePath();

                        $unitLabel = ($targetUnitNumber !== '') ? ('Flat ' . $targetUnitNumber) : 'your unit';
                        $payload = json_encode([
                            'title' => 'SocietyHub: Visitor Arrival',
                            'body' => $visitorName . ' (' . $purpose . ') has arrived at the gate for ' . $unitLabel . '.',
                            'icon' => $baseUrl . '/assets/icon-192.png',
                            'badge' => $baseUrl . '/assets/icon-192.png',
                            'tag' => 'society-visitor-' . $visitorId,
                            'visitor_id' => $visitorId,
                            'action_token' => $actionToken,
                            'url' => appBasePath() . '/resident/dashboard',
                        ]);

                        $report = $webPush->sendOneNotification($subscription, $payload);
                        if (!$report->isSuccess() && $report->isSubscriptionExpired()) {
                            $delStmt = $conn->prepare('DELETE FROM sms_user_push_subscriptions WHERE user_id = :user_id');
                            $delStmt->execute([':user_id' => $residentId]);
                        }
                    }
                } catch (Throwable $pushError) {
                    error_log('Push Notification Failed: ' . $pushError->getMessage());
                }
            }
        }
    }

    $successMsg = ($visitorStatus === 'Entered')
        ? 'Pre-approved visitor logged and marked Entered.'
        : 'Visitor logged and resident notified.';
    if ($deliveryInstruction) {
        $successMsg .= " [Instruction: {$deliveryInstruction}]";
    }

    jsonSuccess([
        'success' => true,
        'visitor_id' => $visitorId,
        'visitor_status' => $visitorStatus,
        'delivery_instruction' => $deliveryInstruction,
        'message' => $successMsg,
    ], 201);
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    logAndFail($e, 'Unable to log visitor entry.');
}
