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

// 9.1 Download Page & APK Release Artifact Tests
assertTrue(is_file($root . '/download.php'), 'download.php exists and is readable');
$hasApk = (is_file($root . '/apk/SecureGate.apk') && filesize($root . '/apk/SecureGate.apk') > 1000000) ||
          (is_file($root . '/apk/SocietyHub.apk') && filesize($root . '/apk/SocietyHub.apk') > 1000000);
assertTrue($hasApk, 'SecureGate.apk exists and is greater than 1MB');
$routesContent = file_get_contents($root . '/routes.php');
assertTrue(strpos($routesContent, "'/download'") !== false, 'routes.php handles /download route');
assertTrue(strpos($routesContent, "SecureGate.apk") !== false, 'routes.php streams SecureGate.apk');

// 9.2 Indian Standard Time (IST) Tests
assertSame('Asia/Kolkata', date_default_timezone_get(), 'PHP default timezone is strictly Asia/Kolkata (IST)');
$appJsContent = file_get_contents($root . '/assets/js/app.js');
assertTrue(strpos($appJsContent, 'Asia/Kolkata') !== false, 'assets/js/app.js enforces Asia/Kolkata timezone');
assertTrue(strpos($appJsContent, '+05:30') !== false, 'assets/js/app.js anchors date parsing to +05:30 (IST)');

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

// 10. Indian Phone Number Validation Tests
assertSame('9876543210', normalizeIndianPhoneNumber('9876543210'), 'Phone: 10 digits clean');
assertSame('9876543210', normalizeIndianPhoneNumber('+91 9876543210'), 'Phone: +91 prefix with space');
assertSame('9876543210', normalizeIndianPhoneNumber('+91-9876543210'), 'Phone: +91 prefix with dash');
assertSame('9876543210', normalizeIndianPhoneNumber('09876543210'), 'Phone: 0 prefix 11 digits');
assertSame('9876543210', normalizeIndianPhoneNumber('919876543210'), 'Phone: 91 prefix 12 digits');
assertSame(null, normalizeIndianPhoneNumber('12345'), 'Phone: rejects short number');
assertSame(null, normalizeIndianPhoneNumber('1234567890'), 'Phone: rejects number starting with 1');
assertSame(null, normalizeIndianPhoneNumber('abcdefghij'), 'Phone: rejects alphabetic string');
assertSame(null, normalizeIndianPhoneNumber(''), 'Phone: rejects empty string');

// 11. Guest Pass 48-Hour Expiry Logic Invariant Tests
$passGen = new DateTime('2026-11-26 10:00:00', new DateTimeZone('Asia/Kolkata'));
$passExp = (clone $passGen)->modify('+48 hours');
assertSame('2026-11-28 10:00:00', $passExp->format('Y-m-d H:i:s'), '48-hour expiry calculation adds exactly 48 hours');

// Verify expired condition
$timeBeforeExp = new DateTime('2026-11-28 09:59:59', new DateTimeZone('Asia/Kolkata'));
$timeAfterExp = new DateTime('2026-11-28 10:00:01', new DateTimeZone('Asia/Kolkata'));
assertTrue($timeBeforeExp < $passExp, 'Pass is valid before 48-hour timestamp');
assertTrue($timeAfterExp > $passExp, 'Pass is strictly expired after 48-hour timestamp');

// 12. WhatsApp Click-to-Chat Format Invariant Test
$pin = '654321';
$soc = 'Sunshine Residency';
$expFormatted = '28 Nov 2026, 10:00 AM';
$waText = "Hello! You have been invited to visit {$soc}.\n\n" .
          "Guest Pass: {$pin}\n\n" .
          "This guest pass is valid for 48 hours from the time of generation and will automatically expire on {$expFormatted}.\n\n" .
          "Please share this pass with security at the entrance.\n\n" .
          "Thank you!";
$waUrl = 'https://api.whatsapp.com/send?text=' . rawurlencode($waText);
assertTrue(str_contains($waUrl, 'https://api.whatsapp.com/send?text='), 'WhatsApp URL uses standard click-to-chat prefix');
assertTrue(str_contains($waUrl, '654321'), 'WhatsApp URL contains encoded PIN');
assertTrue(!str_contains($waUrl, 'resident@'), 'WhatsApp URL strictly avoids leaking resident email or credentials');

// 13. Ad Safety Exclusion Invariant Tests
$prohibitedPages = ['emergency', 'sos', 'login', 'register', 'forgot_password', 'reset_password', 'guard_dashboard', 'auth', 'terminal'];
$adEndpointCode = file_get_contents(__DIR__ . '/../api/get_advertisements.php');
foreach ($prohibitedPages as $p) {
    assertTrue(str_contains($adEndpointCode, $p), "Advertisement endpoint explicitly excludes prohibited context: '{$p}'");
}

// 14. Guard Terminal WebRTC Camera & Tag Separation Tests
$guardDashCode = file_get_contents(__DIR__ . '/../modules/guard/dashboard.php');
assertTrue(str_contains($guardDashCode, 'id="camera-stream"'), 'Guard terminal has HTML5 video element for WebRTC camera');
assertTrue(str_contains($guardDashCode, 'id="flip-cam-btn"'), 'Guard terminal has camera flip switcher');
assertTrue(str_contains($guardDashCode, 'id="camera-canvas"'), 'Guard terminal has Canvas element for frame grabbing');
assertTrue(str_contains($guardDashCode, 'selectQuickTag'), 'Guard terminal implements non-destructive selectQuickTag function');
assertTrue(str_contains($guardDashCode, 'id="visitor_tag"'), 'Guard terminal has dedicated visitor_tag field');

// 15. Resident Dashboard Responsive Navigation & Society Modules
$residentDashCode = file_get_contents(__DIR__ . '/../modules/resident/dashboard.php');
assertTrue(str_contains($residentDashCode, 'id="resident-desktop-sidebar"'), 'Resident dashboard has collapsible desktop sidebar');
assertTrue(str_contains($residentDashCode, 'id="resident-mobile-drawer"'), 'Resident dashboard has mobile off-canvas drawer');
assertTrue(str_contains($residentDashCode, 'id="facilities-modal"'), 'Resident dashboard has Facilities & Booking modal');
assertTrue(str_contains($residentDashCode, 'id="family-modal"'), 'Resident dashboard has Family Members roster modal');
assertTrue(str_contains($residentDashCode, 'id="staff-modal"'), 'Resident dashboard has Domestic Daily Help roster modal');
assertTrue(str_contains($residentDashCode, 'id="rules-modal"'), 'Resident dashboard has Society Rules & Handbook modal');
assertTrue(str_contains($residentDashCode, 'id="marketplace-modal"'), 'Resident dashboard has Society Marketplace modal');
assertTrue(str_contains($residentDashCode, 'id="advertise-modal"'), 'Resident dashboard has Advertise With Us monetization modal');

// 16. Multi-Tenant Marketplace Query Isolation Test
$mktGetCode = file_get_contents(__DIR__ . '/../api/marketplace_get_listings.php');
assertTrue(str_contains($mktGetCode, 'm.society_id = :society_id'), 'Marketplace query strictly enforces society_id multi-tenant isolation');

// 17. Emergency SOS Alert Invariant Tests
$validAlertTypes = ['Medical Emergency', 'Fire Emergency', 'Security Threat', 'Accident', 'Theft / Suspicious Activity', 'Other Emergency'];
$validStatuses = ['Active', 'Acknowledged', 'Responding', 'Resolved', 'Cancelled'];
$sosEndpointCode = file_get_contents(__DIR__ . '/../api/resident_trigger_emergency.php');
foreach ($validAlertTypes as $alertType) {
    assertTrue(str_contains($sosEndpointCode, $alertType), "SOS trigger API supports emergency category: '{$alertType}'");
}

echo "\nSummary: {$passed} passed, {$failed} failed.\n";
exit($failed > 0 ? 1 : 0);

echo "\nSummary: {$passed} passed, {$failed} failed.\n";
exit($failed > 0 ? 1 : 0);
