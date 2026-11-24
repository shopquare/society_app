<?php
// includes/api_bootstrap.php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth_middleware.php';
require_once __DIR__ . '/csrf.php';

/**
 * Emits standard JSON + security headers, starts session, and enforces HTTP method.
 */
function initApi(array $allowedMethods = ['GET']): void {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Robots-Tag: noindex, nofollow');
        header('Access-Control-Allow-Methods: ' . implode(', ', $allowedMethods));
    }

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, $allowedMethods, true)) {
        jsonError('Method not allowed.', 405);
    }

    startSecureSession();
}

/**
 * Sends a JSON success payload and exits.
 */
function jsonSuccess(array $payload = [], int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode(array_merge(['status' => 'success'], $payload));
    exit();
}

/**
 * Sends a JSON error payload and exits.
 */
function jsonError(string $message, int $statusCode = 400, array $extra = []): void {
    http_response_code($statusCode);
    echo json_encode(array_merge(['status' => 'error', 'message' => $message], $extra));
    exit();
}

/**
 * Parses raw JSON request body safely as an associative array.
 */
function getJsonInput(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        jsonError('Invalid JSON payload.', 400);
    }
    return $decoded;
}

/**
 * Requires an authenticated session and (optionally) one of the allowed role IDs.
 */
function requireApiAuth(?array $allowedRoles = null): void {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || empty($_SESSION['user_id']) || empty($_SESSION['society_id'])) {
        jsonError('Authentication required.', 401);
    }
    if ($allowedRoles !== null && !hasRole($allowedRoles)) {
        jsonError('Unauthorized access.', 403);
    }
}

/**
 * Logs internal exception details and returns a safe generic error response.
 */
function logAndFail(Throwable $e, string $publicMessage = 'An internal server error occurred.'): void {
    error_log(sprintf('[%s] %s in %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
    jsonError($publicMessage, 500);
}

/**
 * Generates an HMAC-SHA256 signed token for background Service Worker visitor actions.
 */
function signVisitorActionToken(int $visitorId, int $residentId, int $ttlSeconds = 86400): string {
    $secret = envValue('APP_KEY', '');
    if ($secret === '') {
        return '';
    }
    $exp = time() + $ttlSeconds;
    $payload = $visitorId . '|' . $residentId . '|' . $exp;
    $sig = hash_hmac('sha256', $payload, $secret);
    return base64_encode($payload . '|' . $sig);
}

/**
 * Verifies a Service Worker visitor action token and returns the bound resident_id, or null if invalid.
 */
function verifyVisitorActionToken(string $token, int $expectedVisitorId): ?int {
    $secret = envValue('APP_KEY', '');
    if ($secret === '' || $token === '') {
        return null;
    }
    $decoded = base64_decode($token, true);
    if ($decoded === false) {
        return null;
    }
    $parts = explode('|', $decoded);
    if (count($parts) !== 4) {
        return null;
    }
    [$vid, $rid, $exp, $sig] = $parts;
    if ((int)$vid !== $expectedVisitorId || (int)$exp < time()) {
        return null;
    }
    $expectedSig = hash_hmac('sha256', $vid . '|' . $rid . '|' . $exp, $secret);
    if (!hash_equals($expectedSig, $sig)) {
        return null;
    }
    return (int)$rid;
}

/**
 * Simple file-backed fixed-window rate limiter keyed by client/action identifier.
 */
function checkRateLimit(string $bucketKey, int $maxAttempts = 10, int $windowSeconds = 300): void {
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'society_app_rl';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $file = $dir . DIRECTORY_SEPARATOR . sha1($bucketKey) . '.json';
    $now = time();
    $attempts = [];

    if (is_file($file)) {
        $raw = @file_get_contents($file);
        $parsed = $raw ? json_decode($raw, true) : null;
        if (is_array($parsed)) {
            $attempts = array_values(array_filter($parsed, static fn($ts) => is_int($ts) && ($now - $ts) < $windowSeconds));
        }
    }

    if (count($attempts) >= $maxAttempts) {
        jsonError('Too many requests. Please wait a few minutes and try again.', 429);
    }

    $attempts[] = $now;
    @file_put_contents($file, json_encode($attempts), LOCK_EX);
}
