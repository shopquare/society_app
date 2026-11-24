<?php
// /society_app/api/logout.php

require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/csrf.php';

startSecureSession();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $raw = file_get_contents('php://input');
        $json = $raw ? json_decode($raw, true) : null;
        $token = is_array($json) ? ($json['csrf_token'] ?? '') : '';
        requireValidCsrfToken($token);
    }
    logoutUser();
}

// Legacy GET fallback: only allow same-origin referer or redirect safely without CSRF bypass
header('Location: ' . appBasePath() . '/login');
exit();
