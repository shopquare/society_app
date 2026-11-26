<?php
// includes/channels/WhatsAppProvider.php
// Outbound WhatsApp Business / Cloud API Dispatch Adapter

function sendWhatsAppMessage(string $phone, string $title, string $message): array {
    $apiUrl = trim((string)envValue('WHATSAPP_API_URL', ''));
    $apiToken = trim((string)envValue('WHATSAPP_API_TOKEN', ''));
    $digits = preg_replace('/\D+/', '', $phone);
    if (strlen($digits) === 10) {
        $digits = '91' . $digits;
    }

    if ($digits === '' || strlen($digits) < 10) {
        return ['status' => 'Failed', 'detail' => 'Invalid recipient phone number for WhatsApp'];
    }

    if ($apiUrl === '' || $apiToken === '') {
        return ['status' => 'Skipped', 'detail' => 'WhatsApp API credentials unconfigured (sandbox dry-run OK for +' . $digits . ')'];
    }

    if (!function_exists('curl_init')) {
        return ['status' => 'Failed', 'detail' => 'PHP cURL extension unavailable'];
    }

    $payload = json_encode([
        'messaging_product' => 'whatsapp',
        'to' => $digits,
        'type' => 'text',
        'text' => ['body' => "*{$title}*\n{$message}"]
    ]);

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiToken
        ]
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($resp !== false && $code >= 200 && $code < 300) {
        return ['status' => 'Sent', 'detail' => 'HTTP ' . $code . ' delivered to WhatsApp Cloud API'];
    }
    return ['status' => 'Failed', 'detail' => 'WhatsApp API error HTTP ' . $code . ' ' . $err];
}
