<?php
// tests/run_tests.php
// Safe, non-destructive unit & security-invariant test suite (no live DB mutations).

define('TEST_MODE', true);

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/csrf.php';

$passed = 0;
$failed = 0;

function assertTrue(bool $condition, string $label): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "[PASS] {$label}\n";
    } else {
        $failed++;
        echo "[FAIL] {$label}\n";
    }
}

function assertSame($expected, $actual, string $label): void {
    assertTrue($expected === $actual, $label . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')');
}

// 1. Environment Loader Tests
$tmpEnv = __DIR__ . '/.tmp_test_' . bin2hex(random_bytes(4)) . '.env';
file_put_contents($tmpEnv, "TEST_SAMPLE_KEY=hello_world\n# comment\nTEST_QUOTED=\"quoted value\"\n");
loadEnvFile($tmpEnv);
assertSame('hello_world', envValue('TEST_SAMPLE_KEY'), 'env.php loads unquoted key');
assertSame('quoted value', envValue('TEST_QUOTED'), 'env.php strips surrounding quotes');
@unlink($tmpEnv);

// 2. Role Redirect & hasRole Tests (subpath + root deployment)
$_ENV['APP_BASE_PATH'] = '/society_app';
putenv('APP_BASE_PATH=/society_app');
assertSame('/society_app/admin/dashboard', getRoleDashboardPath(1), 'Role 1 maps to admin dashboard (subpath)');
assertSame('/society_app/admin/dashboard', getRoleDashboardPath(2), 'Role 2 maps to admin dashboard (subpath)');
assertSame('/society_app/resident/dashboard', getRoleDashboardPath(3), 'Role 3 maps to resident dashboard (subpath)');
assertSame('/society_app/guard/dashboard', getRoleDashboardPath(4), 'Role 4 maps to guard dashboard (subpath)');

$_ENV['APP_BASE_PATH'] = '';
putenv('APP_BASE_PATH=');
assertSame('/admin/dashboard', getRoleDashboardPath(1), 'Role 1 maps to admin dashboard (root)');
assertSame('/guard/dashboard', getRoleDashboardPath(4), 'Role 4 maps to guard dashboard (root)');
$_ENV['APP_BASE_PATH'] = '/society_app';
putenv('APP_BASE_PATH=/society_app');

$_SESSION['role_id'] = '4';
assertTrue(hasRole(4), 'hasRole handles string vs int role_id cleanly');
assertTrue(hasRole([3, 4]), 'hasRole matches array roles cleanly');
assertTrue(!hasRole([1, 2]), 'hasRole rejects unauthorized roles');

// 3. CSRF Token Tests
$tok1 = generateCsrfToken();
assertTrue(strlen($tok1) === 64, 'CSRF token is 64 hex chars');
assertTrue(verifyCsrfToken($tok1), 'verifyCsrfToken accepts valid token');
assertTrue(!verifyCsrfToken('invalid-token'), 'verifyCsrfToken rejects invalid token');
assertTrue(!verifyCsrfToken(null), 'verifyCsrfToken rejects null');
$tok2 = rotateCsrfToken();
assertTrue($tok1 !== $tok2 && verifyCsrfToken($tok2) && !verifyCsrfToken($tok1), 'rotateCsrfToken invalidates old token');

// 4. HMAC Visitor Action Token Tests
putenv('APP_KEY=' . bin2hex(random_bytes(32)));
$_ENV['APP_KEY'] = getenv('APP_KEY');

$secret = envValue('APP_KEY');
$exp = time() + 3600;
$payload = '42|99|' . $exp;
$token = base64_encode($payload . '|' . hash_hmac('sha256', $payload, $secret));
$parts = explode('|', base64_decode($token));
$valid = count($parts) === 4
    && (int)$parts[0] === 42
    && (int)$parts[2] >= time()
    && hash_equals(hash_hmac('sha256', $parts[0] . '|' . $parts[1] . '|' . $parts[2], $secret), $parts[3]);
assertTrue($valid && (int)$parts[1] === 99, 'HMAC visitor action token validates bound visitor_id and resident_id');

// 5. Vehicle Plate Normalization & Logic Tests
$rawPlates = [
    'mh 12 ab 1234' => 'MH12AB1234',
    'MH-12-AB-1234' => 'MH12AB1234',
    '  mh 12  ab  1234  ' => 'MH12AB1234',
    'dl 01 a 0001' => 'DL01A0001'
];
foreach ($rawPlates as $raw => $expected) {
    $normalized = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($raw));
    assertSame($expected, $normalized, "Plate normalization for '{$raw}'");
}

// 6. Database Migrator Table Registry Invariant Test
require_once __DIR__ . '/../includes/db_migrator.php';
$reflector = new ReflectionFunction('isDatabaseInitialized');
$fileContents = file_get_contents(__DIR__ . '/../includes/db_migrator.php');
assertTrue(str_contains($fileContents, "'sms_vehicles'"), "db_migrator includes sms_vehicles table check");
assertTrue(str_contains($fileContents, "'sms_gate_parcels'"), "db_migrator includes sms_gate_parcels table check");
assertTrue(str_contains($fileContents, "'sms_audit_logs'"), "db_migrator includes sms_audit_logs table check");

// 7. Audit Logger Helper Test
require_once __DIR__ . '/../includes/audit_logger.php';
assertTrue(function_exists('logAudit'), 'logAudit helper function exists and is loaded');

// 8. Visitor Photo Resolution & Token Tests
require_once __DIR__ . '/../includes/api_bootstrap.php';
assertSame('photo_123.jpg', resolveVisitorPhotoFilename('/api/get_visitor_photo.php?file=photo_123.jpg'), 'Filename extracted from URL query');
assertSame('photo_123.jpg', resolveVisitorPhotoFilename('uploads/visitors/photo_123.jpg'), 'Filename extracted from relative path');
assertSame('photo_123.jpg', resolveVisitorPhotoFilename('photo_123.jpg'), 'Plain filename preserved');
assertSame('photo_123.jpg', resolveVisitorPhotoFilename('/api/get_visitor_photo.php?file=get_visitor_photo.php%3Ffile%3Dphoto_123.jpg'), 'Double encoded query resolved cleanly');
$photoTok = signVisitorPhotoToken('photo_123.jpg');
assertTrue(verifyVisitorPhotoToken($photoTok, 'photo_123.jpg'), 'Visitor photo token valid for correct filename');
assertTrue(!verifyVisitorPhotoToken($photoTok, 'other_photo.jpg'), 'Visitor photo token rejected for mismatched filename');

// 9. Static Security Guards across Application PHP Files
$root = dirname(__DIR__);
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$hardcodedSecrets = [];
$displayErrorsFiles = [];
foreach ($it as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $path = str_replace('\\', '/', $file->getPathname());
    if (str_contains($path, '/vendor/') || str_contains($path, '/.git/') || str_contains($path, '/tests/')) {
        continue;
    }
    $content = file_get_contents($path);
    if (preg_match("/define\('DB_PASS',\s*'[^']+'\)/", $content) || preg_match("/'privateKey'\s*=>\s*'[^']+'/", $content)) {
        $hardcodedSecrets[] = $path;
    }
    if (preg_match("/ini_set\(\s*['\"]display_errors['\"]\s*,\s*1\s*\)/", $content)) {
        $displayErrorsFiles[] = $path;
    }
}
assertSame(0, count($hardcodedSecrets), 'Zero hardcoded DB passwords or VAPID private keys in PHP files');
assertSame(0, count($displayErrorsFiles), 'Zero display_errors=1 left in PHP files');

echo "\nSummary: {$passed} passed, {$failed} failed.\n";
exit($failed > 0 ? 1 : 0);
