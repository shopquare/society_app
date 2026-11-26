<?php
// /society_app/api/login.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['POST']);

$data = getJsonInput();
requireValidCsrfToken($data['csrf_token'] ?? '');

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
checkRateLimit('login_' . $ip, 15, 300);

$email = trim((string)($data['email'] ?? ''));
$password = (string)($data['password'] ?? '');
$remember = !empty($data['remember']);

if ($email === '' || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonError('Please provide a valid email address and password.', 400);
}

try {
    $query = "SELECT u.id, u.society_id, u.password_hash, u.role_id, u.status,
                     p.first_name, un.unit_number
              FROM sms_users u
              LEFT JOIN sms_user_profiles p ON u.id = p.user_id
              LEFT JOIN sms_user_units un ON u.id = un.user_id AND un.is_current_occupant = 1
              WHERE u.email = :email
              LIMIT 1";

    $stmt = $conn->prepare($query);
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        if ($user['status'] === 'pending') {
            jsonError('Your account is currently pending committee approval.', 403);
        }
        if ($user['status'] !== 'active') {
            jsonError('Your account is suspended or deactivated. Contact admin.', 403);
        }

        $updateStmt = $conn->prepare('UPDATE sms_users SET last_login = NOW() WHERE id = :id');
        $updateStmt->execute([':id' => $user['id']]);

        // Prevent session fixation & optionally extend cookie for "Remember me"
        session_regenerate_id(true);
        if ($remember) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                session_id(),
                [
                    'expires' => time() + (7 * 86400),
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => 'Lax',
                ]
            );
        }

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['society_id'] = (int)$user['society_id'];
        $_SESSION['role_id'] = (int)$user['role_id'];
        $_SESSION['first_name'] = $user['first_name'] ?? '';
        $_SESSION['unit_number'] = $user['unit_number'] ?? null;
        $_SESSION['logged_in'] = true;

        loadUserPermissionContext($conn, (int)$user['id'], (int)$user['society_id'], (int)$user['role_id']);
        issuePersistentToken($conn, (int)$user['id']);

        rotateCsrfToken();

        jsonSuccess([
            'message' => 'Login successful.',
            'data' => [
                'redirect' => getRoleDashboardPath((int)$user['role_id']),
                'firstName' => $user['first_name'] ?? '',
            ],
        ]);
    }

    jsonError('Invalid email address or password.', 401);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to process login at this time.');
}
