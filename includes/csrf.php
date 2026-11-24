<?php
// society_app/includes/csrf.php

require_once __DIR__ . '/auth_middleware.php';
startSecureSession();

/**
 * Generates or returns the session CSRF token.
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Rotates the CSRF token (call upon login or privilege change).
 */
function rotateCsrfToken(): string {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

/**
 * Outputs a hidden HTML input field containing the CSRF token.
 */
function csrfField(): string {
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" id="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validates a submitted CSRF token against the session token.
 */
function verifyCsrfToken($submittedToken): bool {
    if (empty($_SESSION['csrf_token']) || !is_string($submittedToken) || $submittedToken === '') {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $submittedToken);
}

/**
 * Enforces CSRF validation for API endpoints.
 */
function requireValidCsrfToken($submittedToken): void {
    if (!verifyCsrfToken($submittedToken)) {
        http_response_code(403);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode([
            'status' => 'error',
            'message' => 'Security validation failed. Please refresh the page and try again.'
        ]);
        exit();
    }
}
