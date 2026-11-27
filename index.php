<?php
// /society_app/index.php

// 1. Load Global Dependencies & Hardened Session
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_middleware.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';

startSecureSession();

// 2. Emit baseline security headers for HTML routes
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// 3. Pass request to the Router
require_once __DIR__ . '/routes.php';
