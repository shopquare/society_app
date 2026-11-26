<?php
// includes/channels/EmailProvider.php
// Outbound SMTP / Shared-Hosting Email Dispatch Adapter

function sendEmailMessage(string $toEmail, string $toName, string $subject, string $body): array {
    $toEmail = trim($toEmail);
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['status' => 'Failed', 'detail' => 'Invalid recipient email address'];
    }

    $smtpHost = trim((string)envValue('SMTP_HOST', ''));
    $fromEmail = trim((string)envValue('SMTP_FROM', 'noreply@securegate.local'));

    if ($smtpHost === '') {
        return ['status' => 'Skipped', 'detail' => 'SMTP_HOST unconfigured (sandbox dry-run OK for ' . $toEmail . ')'];
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/plain; charset=UTF-8',
        'From: SecureGate <' . $fromEmail . '>',
        'Reply-To: ' . $fromEmail
    ];
    $formattedBody = "Hello " . ($toName ?: 'Resident') . ",\r\n\r\n" . $body . "\r\n\r\n-- SecureGate Notification Service";

    $ok = @mail($toEmail, $subject, $formattedBody, implode("\r\n", $headers));
    if ($ok) {
        return ['status' => 'Sent', 'detail' => 'Email handed off via mail/SMTP transport'];
    }
    return ['status' => 'Failed', 'detail' => 'PHP mail/SMTP transport rejected message'];
}
