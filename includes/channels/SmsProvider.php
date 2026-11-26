<?php
// includes/channels/SmsProvider.php
// Outbound Transactional SMS / OTP Gateway Adapter

function sendSmsMessage(string $phone, string $message): array {
    $apiUrl = trim((string)envValue('SMS_API_URL', ''));
    $apiKey = trim((string)envValue('SMS_API_KEY', ''));
    $senderId = trim((string)envValue('SMS_SENDER_ID', 'SECGAT'));
    $digits = preg_replace('/\D+/', '', $phone);
    if (strlen($digits) === 10) {
        $digits = '91' . $digits;
    }

    if ($digits === '' || strlen($digits) < 10) {
        return ['status' => 'Failed', 'detail' => 'Invalid recipient mobile number for SMS'];
    }

    if ($apiUrl === '' || $apiKey === '') {
        return ['status' => 'Skipped', 'detail' => 'SMS Gateway credentials unconfigured (sandbox dry-run OK for +' . $digits . ')'];
    }

    if (!function_exists('curl_init')) {
        return ['status' => 'Failed', 'detail' => 'PHP cURL extension unavailable'];
    }

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
        CURLOPT_POSTFIELDS => json_encode([
            'sender_id' => $senderId,
            'to' => $digits,
            'message' => mb_substr($message, 0, 320)
        ])
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($resp !== false && $code >= 200 && $code < 300) {
        return ['status' => 'Sent', 'detail' => 'SMS accepted by gateway HTTP ' . $code];
    }
    return ['status' => 'Failed', 'detail' => 'SMS gateway error HTTP ' . $code . ' ' . $err];
}
