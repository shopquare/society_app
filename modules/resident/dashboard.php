<?php
// /society_app/modules/resident/dashboard.php

if (!isset($_SESSION['logged_in']) || !hasRole(3)) {
    header('Location: ' . appBasePath() . '/login');
    exit();
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/api_bootstrap.php';
$csrfToken = generateCsrfToken();
$vapidPublicKey = envValue('VAPID_PUBLIC_KEY', '');
$base = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');

$user_id = (int)($_SESSION['user_id'] ?? 0);
$society_id = (int)($_SESSION['society_id'] ?? 0);

$residentInfo = [];
try {
    $stmt = $conn->prepare(
        "SELECT p.first_name, p.last_name,
                uu.unit_number, uu.occupancy_type,
                s.society_name
         FROM sms_users u
         INNER JOIN sms_user_profiles p ON u.id = p.user_id
         LEFT JOIN sms_user_units uu ON u.id = uu.user_id AND uu.is_current_occupant = 1
         LEFT JOIN sms_societies s ON u.society_id = s.id
         WHERE u.id = :user_id
         LIMIT 1"
    );
    $stmt->execute([':user_id' => $user_id]);
    $residentInfo = $stmt->fetch() ?: [];
} catch (PDOException $e) {
    error_log('Resident dashboard profile load error: ' . $e->getMessage());
}

$firstName = $residentInfo['first_name'] ?? ($_SESSION['first_name'] ?? 'Resident');
$lastName = $residentInfo['last_name'] ?? '';
$unitNumber = $residentInfo['unit_number'] ?? ($_SESSION['unit_number'] ?? 'N/A');
$societyName = $residentInfo['society_name'] ?? 'Society Portal';
$occupancyType = $residentInfo['occupancy_type'] ?? 'Resident';

$invoices = [];
try {
    $invStmt = $conn->prepare(
        "SELECT id, title, amount, due_date,
                CASE
                    WHEN status = 'Unpaid' AND due_date < CURDATE() THEN 'Overdue'
                    ELSE status
                END AS status
         FROM sms_invoices
         WHERE resident_id = :user_id AND society_id = :society_id
         ORDER BY due_date DESC
         LIMIT 5"
    );
    $invStmt->execute([':user_id' => $user_id, ':society_id' => $society_id]);
    $invoices = $invStmt->fetchAll();
} catch (PDOException $e) {
    error_log('Resident dashboard invoices load error: ' . $e->getMessage());
}

$passes = [];
try {
    $passStmt = $conn->prepare(
        'SELECT id, visitor_name, pin_code, valid_date, status
         FROM sms_visitor_passes
         WHERE resident_id = :user_id AND society_id = :society_id
         ORDER BY created_at DESC LIMIT 5'
    );
    $passStmt->execute([':user_id' => $user_id, ':society_id' => $society_id]);
    $passes = $passStmt->fetchAll();
} catch (PDOException $e) {
    error_log('Resident dashboard passes load error: ' . $e->getMessage());
}

$recentVisitors = [];
try {
    $visStmt = $conn->prepare(
        "SELECT id, visitor_name, phone_number, vehicle_number, purpose, photo_path, status, entry_time, exit_time
         FROM sms_visitors
         WHERE resident_id = :user_id AND society_id = :society_id
         ORDER BY entry_time DESC LIMIT 5"
    );
    $visStmt->execute([':user_id' => $user_id, ':society_id' => $society_id]);
    $recentVisitors = $visStmt->fetchAll();
} catch (PDOException $e) {
    error_log('Resident dashboard visitors load error: ' . $e->getMessage());
}

$activePreapprovals = [];
try {
    $now = date('Y-m-d H:i:s');
    $preStmt = $conn->prepare(
        "SELECT id, company, instruction, valid_until, created_at
         FROM sms_delivery_preapprovals
         WHERE resident_id = :user_id AND society_id = :society_id AND status = 'Active' AND valid_until > :now
         ORDER BY created_at DESC LIMIT 6"
    );
    $preStmt->execute([':user_id' => $user_id, ':society_id' => $society_id, ':now' => $now]);
    $activePreapprovals = $preStmt->fetchAll();
} catch (PDOException $e) {
    error_log('Resident dashboard preapprovals load error: ' . $e->getMessage());
}

$waitingParcels = [];
try {
    $parcelsStmt = $conn->prepare(
        "SELECT id, courier_company, recipient_name, received_at
         FROM sms_gate_parcels
         WHERE resident_id = :user_id AND society_id = :society_id AND status = 'At Gate'
         ORDER BY received_at DESC"
    );
    $parcelsStmt->execute([':user_id' => $user_id, ':society_id' => $society_id]);
    $waitingParcels = $parcelsStmt->fetchAll();
} catch (PDOException $e) {
    error_log('Resident dashboard parcels load error: ' . $e->getMessage());
}

$registeredVehicles = [];
try {
    $vehStmt = $conn->prepare(
        "SELECT id, vehicle_type, vehicle_number, parking_slot, created_at
         FROM sms_vehicles
         WHERE resident_id = :user_id AND society_id = :society_id
         ORDER BY created_at DESC"
    );
    $vehStmt->execute([':user_id' => $user_id, ':society_id' => $society_id]);
    $registeredVehicles = $vehStmt->fetchAll();
} catch (PDOException $e) {
    error_log('Resident dashboard vehicles load error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="manifest" href="<?php echo $base; ?>/manifest.json">
    <meta name="theme-color" content="#1E3A8A">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="app-base" content="<?php echo $base; ?>">
    <title>Resident Dashboard - <?php echo htmlspecialchars($societyName, ENT_QUOTES, 'UTF-8'); ?></title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script>
    <script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans flex flex-col min-h-screen">

    <nav class="bg-brand-900 shadow-md border-b border-brand-800 sticky top-0 z-50" style="padding-top: env(safe-area-inset-top, 0px);">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-white/10 rounded flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    </div>
                    <span class="font-serif text-xl font-semibold text-white tracking-wide"><?php echo htmlspecialchars($societyName, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>

                <div class="flex items-center gap-3">
                    <button type="button" onclick="openEmergencyModal()" class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs rounded-lg shadow-sm flex items-center gap-1.5 transition-colors cursor-pointer animate-pulse" title="Trigger Emergency Guard SOS">
                        <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                        <span>SOS</span>
                    </button>
                    <a href="<?php echo $base; ?>/resident/financials" class="hidden sm:inline-flex text-sm font-medium text-brand-50 hover:text-white px-3 py-1.5 rounded-lg hover:bg-white/10 transition-colors">My Dues</a>
                    <div class="text-right">
                        <div class="text-sm font-medium text-white"><?php echo htmlspecialchars(trim($firstName . ' ' . $lastName), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="text-xs text-brand-50">Unit <?php echo htmlspecialchars((string)$unitNumber, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <form action="<?php echo $base; ?>/api/logout.php" method="POST" class="m-0">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" aria-label="Log Out" title="Log Out" class="p-2 text-brand-50 hover:text-white hover:bg-white/10 rounded-lg transition-colors cursor-pointer">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8">

        <div class="mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
            <div>
                <div class="flex flex-wrap items-center gap-3 mb-1">
                    <h1 class="text-2xl font-bold text-slate-900 font-serif">Welcome back, <?php echo htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8'); ?>!</h1>
                    <span class="bg-brand-50 text-brand-900 text-xs font-bold px-2.5 py-1 rounded border border-brand-200">
                        Unit <?php echo htmlspecialchars((string)$unitNumber, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                    <span class="bg-slate-100 text-slate-600 text-xs font-medium px-2 py-1 rounded border border-slate-200">
                        <?php echo htmlspecialchars(ucfirst($occupancyType), ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
                <p class="text-sm text-slate-500">Resident Portal for <span class="font-semibold text-slate-700"><?php echo htmlspecialchars($societyName, ENT_QUOTES, 'UTF-8'); ?></span></p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="button" onclick="requestPushPermission()" id="btn-push-perm" class="px-3 py-2 bg-brand-900 text-white text-xs font-bold rounded shadow hover:bg-brand-800 transition-colors">Enable Push Alerts</button>
                <button type="button" onclick="testLoudAlert()" class="px-3 py-2 bg-rose-700 hover:bg-rose-800 text-white text-xs font-bold rounded shadow flex items-center gap-1.5 transition-colors cursor-pointer"><span>🚨</span> Test Loud Gate Alert</button>
                <a href="<?php echo $base; ?>/resident/financials" class="sm:hidden px-3 py-2 bg-emerald-700 text-white text-xs font-bold rounded shadow hover:bg-emerald-800 transition-colors">My Dues</a>
            </div>
        </div>

        <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

        <div id="gate-alerts-section" class="hidden mb-8">
            <div class="bg-amber-50 border-2 border-amber-400 rounded-xl p-6 shadow-lg">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-3">
                        <span class="w-4 h-4 bg-red-600 rounded-full animate-ping"></span>
                        <h2 class="text-xl font-bold text-amber-900 font-serif">Visitor Waiting at Gate!</h2>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="testLoudAlert()" class="text-xs bg-rose-600 text-white font-bold px-3 py-1 rounded-full hover:bg-rose-700 cursor-pointer">Test Ring</button>
                        <button type="button" id="btn-mute-chime" onclick="toggleMuteChime()" class="text-xs bg-white border border-amber-300 text-amber-900 font-bold px-3 py-1 rounded-full hover:bg-amber-100 cursor-pointer">Mute Chime</button>
                        <span class="text-xs bg-red-600 text-white font-bold px-3 py-1 rounded-full uppercase tracking-wider">Action Required</span>
                    </div>
                </div>
                <div id="pending-visitors-container" class="space-y-4"></div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-1">
                <div id="guest-pass-card" class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 sticky top-24">
                    <h2 class="text-lg font-bold text-slate-900 font-serif mb-2">Generate Guest Pass</h2>
                    <p class="text-xs text-slate-500 mb-4">Create a 6-digit secure PIN for your incoming guest. Share it with them to skip gate friction.</p>

                    <form id="pass-form" onsubmit="event.preventDefault(); generatePass();">
                        <div class="space-y-4">
                            <div>
                                <label for="visitor_name" class="block text-sm font-medium text-slate-700 mb-1">Visitor Full Name</label>
                                <input type="text" id="visitor_name" required class="w-full px-4 py-2 bg-white rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:border-brand-900" placeholder="e.g. Rahul Sharma">
                            </div>

                            <div>
                                <label for="valid_date" class="block text-sm font-medium text-slate-700 mb-1">Valid Date</label>
                                <input type="date" id="valid_date" required class="w-full px-4 py-2 bg-white rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:border-brand-900">
                            </div>

                            <button type="submit" id="btn-pass-submit" class="w-full px-6 py-2.5 text-sm font-bold text-white bg-brand-900 hover:bg-brand-800 rounded-lg shadow-sm transition-all">
                                Generate Pass PIN
                            </button>
                        </div>
                    </form>

                    <div id="pin-result-box" class="hidden mt-6 p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-center shadow-sm">
                        <div class="text-xs text-emerald-800 uppercase tracking-wider font-bold">Your Guest Entry PIN</div>
                        <div class="flex items-center justify-center gap-3 my-2">
                            <span id="generated-pin-display" class="text-3xl font-black text-brand-900 tracking-[0.25em]">------</span>
                            <button type="button" onclick="copyCurrentPin()" class="p-1.5 text-slate-500 hover:text-brand-900 rounded hover:bg-white transition" title="Copy PIN code">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                            </button>
                        </div>
                        <div id="generated-pass-subtitle" class="text-xs text-emerald-700 font-medium mb-3">Pass created! Share with your visitor:</div>
                        <div class="space-y-2">
                            <button type="button" onclick="shareGeneratedPassWhatsApp()" class="w-full py-2 px-3 bg-[#25D366] hover:bg-[#20bd5a] text-white font-bold text-xs rounded-lg shadow flex items-center justify-center gap-2 transition cursor-pointer">
                                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                                <span>Share via WhatsApp</span>
                            </button>
                            <div class="flex gap-2">
                                <button type="button" onclick="copyGeneratedPassText()" class="flex-1 py-1.5 px-3 bg-white border border-emerald-300 hover:bg-emerald-100 text-emerald-800 font-semibold text-xs rounded-lg transition cursor-pointer">
                                    Copy Invite
                                </button>
                                <button type="button" onclick="shareGeneratedPassNative()" class="py-1.5 px-3 bg-white border border-slate-300 hover:bg-slate-100 text-slate-700 font-semibold text-xs rounded-lg transition cursor-pointer" title="More Share Options">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2 space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 flex flex-col justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-800 mb-4 border-b border-slate-100 pb-2">Quick Actions</h2>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                                <button type="button" onclick="openEmergencyModal()" class="flex flex-col items-center justify-center p-3 bg-rose-50 hover:bg-rose-100 text-rose-800 rounded-lg border border-rose-200 transition-colors group cursor-pointer">
                                    <span class="text-xl mb-1">🚨</span>
                                    <span class="text-xs font-bold text-center">Emergency SOS</span>
                                </button>
                                <button type="button" onclick="openDeliveryModal()" class="flex flex-col items-center justify-center p-3 bg-amber-50 hover:bg-amber-100 text-amber-900 rounded-lg border border-amber-200 transition-colors group cursor-pointer">
                                    <span class="text-xl mb-1">📦</span>
                                    <span class="text-xs font-bold text-center">Pre-approve Delivery</span>
                                </button>
                                <button type="button" onclick="scrollToPassForm()" class="flex flex-col items-center justify-center p-3 bg-slate-50 hover:bg-brand-50 hover:text-brand-900 rounded-lg border border-slate-200 transition-colors group cursor-pointer">
                                    <span class="text-xl mb-1">🔑</span>
                                    <span class="text-xs font-bold text-center">Guest Pass PIN</span>
                                </button>
                                <button type="button" onclick="openDirectoryModal()" class="flex flex-col items-center justify-center p-3 bg-indigo-50 hover:bg-indigo-100 text-indigo-900 rounded-lg border border-indigo-200 transition-colors group cursor-pointer">
                                    <span class="text-xl mb-1">📞</span>
                                    <span class="text-xs font-bold text-center">Gate Intercom</span>
                                </button>
                                <a href="<?php echo $base; ?>/resident/financials" class="flex flex-col items-center justify-center p-3 bg-emerald-50 hover:bg-emerald-100 text-emerald-900 rounded-lg border border-emerald-200 transition-colors group cursor-pointer">
                                    <span class="text-xl mb-1">🧾</span>
                                    <span class="text-xs font-bold text-center">Dues & Receipts</span>
                                </a>
                                <button type="button" onclick="toggleModal('complaint-modal')" class="flex flex-col items-center justify-center p-3 bg-slate-50 hover:bg-brand-50 hover:text-brand-900 rounded-lg border border-slate-200 transition-colors group cursor-pointer">
                                    <span class="text-xl mb-1">📢</span>
                                    <span class="text-xs font-bold text-center">Raise Complaint</span>
                                </button>
                                <button type="button" onclick="openVehiclesModal()" class="flex flex-col items-center justify-center p-3 bg-blue-50 hover:bg-blue-100 text-blue-900 rounded-lg border border-blue-200 transition-colors group cursor-pointer">
                                    <span class="text-xl mb-1">🚗</span>
                                    <span class="text-xs font-bold text-center">My Vehicles (<?php echo count($registeredVehicles); ?>)</span>
                                </button>
                                <button type="button" onclick="openParcelsModal()" class="relative flex flex-col items-center justify-center p-3 bg-teal-50 hover:bg-teal-100 text-teal-900 rounded-lg border border-teal-200 transition-colors group cursor-pointer">
                                    <?php if (!empty($waitingParcels)): ?>
                                        <span class="absolute -top-1 -right-1 bg-red-600 text-white text-[10px] font-black px-1.5 py-0.5 rounded-full animate-pulse"><?php echo count($waitingParcels); ?></span>
                                    <?php endif; ?>
                                    <span class="text-xl mb-1">📬</span>
                                    <span class="text-xs font-bold text-center">Gate Parcels</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100">
                        <h2 class="text-lg font-semibold text-slate-800 mb-4 border-b border-slate-100 pb-2 flex justify-between items-center">
                            Notice Board
                            <span id="notice-count-badge" class="hidden bg-brand-50 text-brand-900 text-xs font-bold px-2 py-0.5 rounded uppercase tracking-wide">0</span>
                        </h2>
                        <div id="resident-notices-container" class="space-y-3 max-h-48 overflow-y-auto pr-1">
                            <p class="text-xs text-slate-400">Loading announcements...</p>
                        </div>
                    </div>
                </div>

                <!-- Gate Security Parcel Locker Banner -->
                <?php if (!empty($waitingParcels)): ?>
                <div class="bg-gradient-to-r from-blue-900 via-indigo-900 to-slate-900 text-white rounded-xl shadow-md p-5 border border-indigo-700">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <span class="text-3xl">📦</span>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="font-bold text-base text-amber-300">Package Waiting at Security Gate!</h3>
                                    <span class="bg-amber-400 text-blue-950 text-[10px] font-black px-2 py-0.5 rounded-full uppercase tracking-wider">Ready for Pickup</span>
                                </div>
                                <p class="text-xs text-indigo-100 mt-0.5">
                                    Gate security received <?php echo count($waitingParcels); ?> courier parcel(s) (<?php echo htmlspecialchars(implode(', ', array_unique(array_column($waitingParcels, 'courier_company'))), ENT_QUOTES, 'UTF-8'); ?>) for Flat <?php echo htmlspecialchars((string)$unitNumber, ENT_QUOTES, 'UTF-8'); ?>.
                                </p>
                            </div>
                        </div>
                        <button type="button" onclick="openParcelsModal()" class="px-4 py-2 bg-amber-400 hover:bg-amber-300 text-blue-950 font-bold text-xs rounded-lg shadow transition cursor-pointer">
                            View Parcels
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Active Delivery Pre-approvals -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-4 pb-2 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">📦</span>
                            <h2 class="text-base font-bold text-slate-900 font-serif">Delivery Gate Instructions</h2>
                        </div>
                        <button type="button" onclick="openDeliveryModal()" class="text-xs bg-amber-500 hover:bg-amber-600 text-white font-bold px-3 py-1.5 rounded-lg shadow-sm transition cursor-pointer flex items-center gap-1">
                            <span>+ Pre-approve Delivery</span>
                        </button>
                    </div>

                    <div id="preapprovals-list-container" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <?php if (empty($activePreapprovals)): ?>
                            <div id="empty-preapprovals" class="col-span-full py-4 text-center text-xs text-slate-400 bg-slate-50 rounded-lg border border-dashed border-slate-200">
                                No active delivery pre-approvals. Tap "+ Pre-approve Delivery" for Swiggy, Zomato, Amazon, Blinkit, etc.
                            </div>
                        <?php else: ?>
                            <?php foreach ($activePreapprovals as $pre): ?>
                                <div id="preapproval-card-<?php echo $pre['id']; ?>" class="p-3 bg-amber-50/70 border border-amber-200 rounded-lg flex items-center justify-between">
                                    <div>
                                        <div class="text-xs font-bold text-amber-950 flex items-center gap-1.5">
                                            <span><?php echo htmlspecialchars($pre['company'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="bg-amber-200 text-amber-800 text-[10px] px-1.5 py-0.5 rounded font-semibold">Active</span>
                                        </div>
                                        <div class="text-[11px] text-amber-800 font-medium mt-0.5"><?php echo htmlspecialchars($pre['instruction'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="text-[10px] text-slate-500">Valid until <?php echo htmlspecialchars(date('h:i A', strtotime($pre['valid_until'])), ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                    <button type="button" onclick="cancelDeliveryPreapproval(<?php echo $pre['id']; ?>)" class="text-xs text-rose-600 hover:text-rose-800 font-bold p-1 rounded hover:bg-rose-50 cursor-pointer" title="Cancel instruction">✕</button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="p-4 sm:p-6 border-b border-slate-100 flex justify-between items-center">
                        <h2 class="text-lg font-bold text-slate-900 font-serif">My Maintenance Bills</h2>
                        <a href="<?php echo $base; ?>/resident/financials" class="text-xs font-semibold text-brand-900 hover:text-brand-800 hover:underline">View All & Pay Bills →</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm whitespace-nowrap">
                            <thead class="bg-slate-50 text-slate-600 font-medium border-b border-slate-200">
                                <tr>
                                    <th class="px-6 py-4">Title</th>
                                    <th class="px-6 py-4">Amount</th>
                                    <th class="px-6 py-4">Due Date</th>
                                    <th class="px-6 py-4">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                <?php if (empty($invoices)): ?>
                                    <tr><td colspan="4" class="px-6 py-8 text-center text-slate-500">No maintenance bills found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($invoices as $inv): ?>
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-6 py-4 font-semibold text-slate-900"><?php echo htmlspecialchars($inv['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="px-6 py-4 font-medium text-slate-900">₹<?php echo number_format((float)$inv['amount'], 2); ?></td>
                                            <td class="px-6 py-4 text-slate-500"><?php echo htmlspecialchars(date('d M Y', strtotime($inv['due_date'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="px-6 py-4">
                                                <?php if ($inv['status'] === 'Paid'): ?>
                                                    <span class="bg-emerald-50 text-emerald-700 px-2.5 py-1 rounded-full text-xs font-semibold border border-emerald-200">Paid</span>
                                                <?php elseif ($inv['status'] === 'Overdue'): ?>
                                                    <span class="bg-red-50 text-red-700 px-2.5 py-1 rounded-full text-xs font-semibold border border-red-200">Overdue</span>
                                                <?php else: ?>
                                                    <span class="bg-amber-50 text-amber-700 px-2.5 py-1 rounded-full text-xs font-semibold border border-amber-200">Unpaid</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="p-4 sm:p-6 border-b border-slate-100 flex items-center justify-between">
                        <h2 class="text-lg font-bold text-slate-900 font-serif">My Guest Passes</h2>
                        <span class="text-xs text-slate-500 font-medium">1-Tap WhatsApp Share Ready</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm whitespace-nowrap">
                            <thead class="bg-slate-50 text-slate-600 font-medium border-b border-slate-200">
                                <tr>
                                    <th class="px-6 py-4">Visitor Name</th>
                                    <th class="px-6 py-4">PIN Code</th>
                                    <th class="px-6 py-4">Valid Date</th>
                                    <th class="px-6 py-4">Status</th>
                                    <th class="px-6 py-4 text-center">Share</th>
                                </tr>
                            </thead>
                            <tbody id="guest-passes-tbody" class="divide-y divide-slate-100 text-slate-700">
                                <?php if (empty($passes)): ?>
                                    <tr id="empty-passes-row"><td colspan="5" class="px-6 py-8 text-center text-slate-500">No guest passes generated yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($passes as $p): ?>
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-6 py-4 font-semibold text-slate-900"><?php echo htmlspecialchars($p['visitor_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="px-6 py-4 font-mono font-bold text-brand-900 tracking-wider"><?php echo htmlspecialchars($p['pin_code'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="px-6 py-4 text-slate-500"><?php echo htmlspecialchars(date('d M Y', strtotime($p['valid_date'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="px-6 py-4">
                                                <?php if ($p['status'] === 'Active'): ?>
                                                    <span class="bg-blue-50 text-blue-700 px-2.5 py-1 rounded-full text-xs font-semibold border border-blue-200">Active</span>
                                                <?php elseif ($p['status'] === 'Used'): ?>
                                                    <span class="bg-emerald-50 text-emerald-700 px-2.5 py-1 rounded-full text-xs font-semibold border border-emerald-200">Used</span>
                                                <?php else: ?>
                                                    <span class="bg-slate-100 text-slate-600 px-2.5 py-1 rounded-full text-xs border border-slate-200">Expired</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <div class="flex items-center justify-center gap-1.5">
                                                    <button type="button" onclick="sharePassRowWhatsApp('<?php echo addslashes($p['visitor_name']); ?>', '<?php echo $p['pin_code']; ?>', '<?php echo $p['valid_date']; ?>')" class="p-1.5 bg-[#25D366] hover:bg-[#20bd5a] text-white rounded-md shadow-sm transition cursor-pointer" title="Share via WhatsApp">
                                                        <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                                                    </button>
                                                    <button type="button" onclick="copyPassRowText('<?php echo addslashes($p['visitor_name']); ?>', '<?php echo $p['pin_code']; ?>', '<?php echo $p['valid_date']; ?>')" class="p-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-md transition cursor-pointer" title="Copy Invite Text">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Visitors & Entry History -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="p-4 sm:p-6 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900 font-serif">Recent Visitors & Deliveries</h2>
                            <p class="text-xs text-slate-500">Live entry logs for Unit <?php echo htmlspecialchars((string)$unitNumber, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="text" id="visitor-search-input" onkeyup="filterVisitorHistoryTable()" placeholder="Search visitor..." class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-brand-900 w-44">
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm whitespace-nowrap">
                            <thead class="bg-slate-50 text-slate-600 font-medium border-b border-slate-200">
                                <tr>
                                    <th class="px-6 py-4">Visitor / Delivery</th>
                                    <th class="px-6 py-4">Purpose</th>
                                    <th class="px-6 py-4">Entry Time</th>
                                    <th class="px-6 py-4">Exit Time</th>
                                    <th class="px-6 py-4">Status</th>
                                </tr>
                            </thead>
                            <tbody id="visitor-history-tbody" class="divide-y divide-slate-100 text-slate-700">
                                <?php if (empty($recentVisitors)): ?>
                                    <tr id="empty-visitors-row"><td colspan="5" class="px-6 py-8 text-center text-slate-500">No visitors logged yet for your unit.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recentVisitors as $v): ?>
                                        <tr class="hover:bg-slate-50 visitor-record-row" data-visitor-id="<?php echo (int)$v['id']; ?>">
                                            <td class="px-6 py-4 font-semibold text-slate-900">
                                                <div class="flex items-center gap-3">
                                                    <?php
                                                    $visPhotoUrl = !empty($v['photo_path']) ? resolveVisitorPhotoUrl($v['photo_path'], true) : null;
                                                    $initial = strtoupper(substr($v['visitor_name'] ?? 'V', 0, 1));
                                                    if (!empty($visPhotoUrl)):
                                                    ?>
                                                        <div class="relative group cursor-pointer" onclick="openPhotoLightbox('<?php echo htmlspecialchars($visPhotoUrl, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($v['visitor_name'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($v['purpose'] ?? 'Visitor', ENT_QUOTES, 'UTF-8'); ?>')">
                                                            <img src="<?php echo htmlspecialchars($visPhotoUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                                                 alt="<?php echo htmlspecialchars($v['visitor_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                 class="w-10 h-10 min-w-[40px] rounded-full object-cover border-2 border-slate-200 shadow-sm group-hover:border-brand-600 group-hover:scale-105 transition-all"
                                                                 onerror="this.onerror=null; this.outerHTML='<div class=\'w-10 h-10 min-w-[40px] rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center font-bold text-xs text-slate-700\'><?php echo $initial; ?></div>';">
                                                            <span class="absolute -bottom-1 -right-1 bg-slate-900 text-white p-0.5 rounded-full text-[9px] opacity-0 group-hover:opacity-100 transition-opacity" title="Click to enlarge">🔍</span>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="w-10 h-10 min-w-[40px] rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center font-bold text-xs text-slate-700 shadow-inner">
                                                            <?php echo $initial; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <div class="visitor-name-cell font-bold text-slate-900"><?php echo htmlspecialchars($v['visitor_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                        <?php if (!empty($v['phone_number'])): ?>
                                                            <div class="text-[11px] text-slate-500 font-mono"><?php echo htmlspecialchars($v['phone_number'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($v['vehicle_number'])): ?>
                                                            <div class="text-[10px] text-slate-400 font-mono"><?php echo htmlspecialchars($v['vehicle_number'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-slate-600 visitor-purpose-cell">
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded text-xs font-medium <?php echo $v['purpose'] === 'Delivery' ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-slate-100 text-slate-700'; ?>">
                                                    <?php echo $v['purpose'] === 'Delivery' ? '📦 ' : '👤 '; ?>
                                                    <?php echo htmlspecialchars($v['purpose'], ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-slate-500 text-xs font-mono">
                                                <?php echo htmlspecialchars(date('d M, h:i A', strtotime($v['entry_time'])), ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                            <td class="px-6 py-4 text-slate-500 text-xs font-mono">
                                                <?php echo !empty($v['exit_time']) ? htmlspecialchars(date('h:i A', strtotime($v['exit_time'])), ENT_QUOTES, 'UTF-8') : '<span class="text-slate-400">—</span>'; ?>
                                            </td>
                                            <td class="px-6 py-4 visitor-status-cell">
                                                <?php if ($v['status'] === 'Entered'): ?>
                                                    <span class="bg-emerald-50 text-emerald-700 px-2.5 py-1 rounded-full text-xs font-semibold border border-emerald-200">Entered</span>
                                                <?php elseif ($v['status'] === 'Pending'): ?>
                                                    <span class="bg-amber-50 text-amber-700 px-2.5 py-1 rounded-full text-xs font-semibold border border-amber-200">Pending Gate</span>
                                                <?php elseif ($v['status'] === 'Denied'): ?>
                                                    <span class="bg-rose-50 text-rose-700 px-2.5 py-1 rounded-full text-xs font-semibold border border-rose-200">Denied</span>
                                                <?php else: ?>
                                                    <span class="bg-slate-100 text-slate-600 px-2.5 py-1 rounded-full text-xs border border-slate-200">Exited</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                    <h2 class="text-lg font-bold text-slate-900 font-serif mb-4">My Maintenance Tickets</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm whitespace-nowrap">
                            <thead class="bg-slate-50 text-slate-600 font-medium border-b border-slate-200">
                                <tr>
                                    <th class="px-4 py-3">Ticket ID</th>
                                    <th class="px-4 py-3">Category & Issue</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Submitted On</th>
                                </tr>
                            </thead>
                            <tbody id="resident-tickets" class="divide-y divide-slate-100 text-slate-700">
                                <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">Loading tickets...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- Emergency SOS Modal -->
    <div id="emergency-sos-modal" role="dialog" aria-modal="true" aria-labelledby="emergency-modal-title" onclick="if (event.target === this) closeEmergencyModal()" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex justify-center items-center p-4 overflow-y-auto">
        <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl border-2 border-rose-300 p-6 my-auto">
            <div class="flex justify-between items-center mb-4 pb-3 border-b border-rose-100">
                <div class="flex items-center gap-2">
                    <span class="text-2xl animate-bounce">🚨</span>
                    <div>
                        <h3 id="emergency-modal-title" class="text-lg font-black text-rose-700 tracking-tight">Security & Guard SOS</h3>
                        <p class="text-xs text-rose-500 font-medium">Instantly rings all security guards on duty</p>
                    </div>
                </div>
                <button type="button" onclick="closeEmergencyModal()" aria-label="Close modal" class="text-slate-400 hover:text-slate-600 focus:outline-none p-1 cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form id="emergency-form" onsubmit="event.preventDefault(); submitEmergencySos();">
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2 uppercase tracking-wider">Select Emergency Type</label>
                        <div class="grid grid-cols-2 gap-2.5" id="emergency-type-selector">
                            <label class="flex flex-col items-center justify-center p-3 rounded-xl border-2 border-rose-200 bg-rose-50/50 hover:bg-rose-100/70 cursor-pointer transition has-[:checked]:border-rose-600 has-[:checked]:bg-rose-100">
                                <input type="radio" name="sos_type" value="Security Threat" checked class="hidden">
                                <span class="text-2xl mb-1">🛡️</span>
                                <span class="text-xs font-bold text-rose-900 text-center">Security Threat</span>
                                <span class="text-[10px] text-slate-500 text-center">Intruder, Disturbance</span>
                            </label>
                            <label class="flex flex-col items-center justify-center p-3 rounded-xl border-2 border-rose-200 bg-rose-50/50 hover:bg-rose-100/70 cursor-pointer transition has-[:checked]:border-rose-600 has-[:checked]:bg-rose-100">
                                <input type="radio" name="sos_type" value="Medical Emergency" class="hidden">
                                <span class="text-2xl mb-1">🚑</span>
                                <span class="text-xs font-bold text-rose-900 text-center">Medical</span>
                                <span class="text-[10px] text-slate-500 text-center">Doctor, Ambulance</span>
                            </label>
                            <label class="flex flex-col items-center justify-center p-3 rounded-xl border-2 border-rose-200 bg-rose-50/50 hover:bg-rose-100/70 cursor-pointer transition has-[:checked]:border-rose-600 has-[:checked]:bg-rose-100">
                                <input type="radio" name="sos_type" value="Fire Alert" class="hidden">
                                <span class="text-2xl mb-1">🔥</span>
                                <span class="text-xs font-bold text-rose-900 text-center">Fire / Gas</span>
                                <span class="text-[10px] text-slate-500 text-center">Smoke, Gas Leak</span>
                            </label>
                            <label class="flex flex-col items-center justify-center p-3 rounded-xl border-2 border-rose-200 bg-rose-50/50 hover:bg-rose-100/70 cursor-pointer transition has-[:checked]:border-rose-600 has-[:checked]:bg-rose-100">
                                <input type="radio" name="sos_type" value="General SOS" class="hidden">
                                <span class="text-2xl mb-1">⚠️</span>
                                <span class="text-xs font-bold text-rose-900 text-center">Immediate Help</span>
                                <span class="text-[10px] text-slate-500 text-center">Lift Stuck, Flood</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label for="sos_notes" class="block text-xs font-bold text-slate-700 mb-1">Quick Note (Optional)</label>
                        <input type="text" id="sos_notes" placeholder="e.g. Someone ringing doorbell repeatedly" class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-rose-500">
                    </div>

                    <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-900 text-[11px] leading-relaxed">
                        ⚠️ <strong>Unit <?php echo htmlspecialchars((string)$unitNumber, ENT_QUOTES, 'UTF-8'); ?></strong> location and your phone number will be immediately dispatched with siren audio on all guard consoles.
                    </div>

                    <div class="flex gap-3">
                        <button type="button" onclick="closeEmergencyModal()" class="w-1/3 py-2.5 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg transition cursor-pointer">Cancel</button>
                        <button type="submit" id="btn-submit-sos" class="w-2/3 py-2.5 px-4 bg-rose-600 hover:bg-rose-700 text-white text-xs font-extrabold uppercase tracking-wider rounded-lg shadow-lg shadow-rose-200 transition animate-pulse cursor-pointer">
                            🚨 TRIGGER SOS ALARM
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Delivery Pre-approval Modal -->
    <div id="delivery-modal" role="dialog" aria-modal="true" aria-labelledby="delivery-modal-title" onclick="if (event.target === this) closeDeliveryModal()" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex justify-center items-center p-4 overflow-y-auto">
        <div class="bg-white w-full max-w-md rounded-2xl shadow-xl border border-slate-200 p-6 my-auto">
            <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <span class="text-2xl">📦</span>
                    <div>
                        <h3 id="delivery-modal-title" class="text-lg font-bold text-slate-900 font-serif">Pre-approve Delivery</h3>
                        <p class="text-xs text-slate-500">Gate instructions for Unit <?php echo htmlspecialchars((string)$unitNumber, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
                <button type="button" onclick="closeDeliveryModal()" aria-label="Close modal" class="text-slate-400 hover:text-slate-600 focus:outline-none p-1 cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form id="delivery-form" onsubmit="event.preventDefault(); submitDeliveryPreapproval();">
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2 uppercase tracking-wider">Select Delivery Partner</label>
                        <div class="grid grid-cols-3 gap-2" id="delivery-partner-selector">
                            <label class="flex flex-col items-center justify-center p-2.5 rounded-xl border-2 border-slate-200 bg-slate-50 hover:bg-amber-50 cursor-pointer transition has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50">
                                <input type="radio" name="del_company" value="Swiggy" checked class="hidden">
                                <span class="text-xl mb-0.5">🍕</span>
                                <span class="text-xs font-bold text-slate-800">Swiggy</span>
                            </label>
                            <label class="flex flex-col items-center justify-center p-2.5 rounded-xl border-2 border-slate-200 bg-slate-50 hover:bg-amber-50 cursor-pointer transition has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50">
                                <input type="radio" name="del_company" value="Zomato" class="hidden">
                                <span class="text-xl mb-0.5">🍔</span>
                                <span class="text-xs font-bold text-slate-800">Zomato</span>
                            </label>
                            <label class="flex flex-col items-center justify-center p-2.5 rounded-xl border-2 border-slate-200 bg-slate-50 hover:bg-amber-50 cursor-pointer transition has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50">
                                <input type="radio" name="del_company" value="Amazon" class="hidden">
                                <span class="text-xl mb-0.5">📦</span>
                                <span class="text-xs font-bold text-slate-800">Amazon</span>
                            </label>
                            <label class="flex flex-col items-center justify-center p-2.5 rounded-xl border-2 border-slate-200 bg-slate-50 hover:bg-amber-50 cursor-pointer transition has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50">
                                <input type="radio" name="del_company" value="Blinkit" class="hidden">
                                <span class="text-xl mb-0.5">⚡</span>
                                <span class="text-xs font-bold text-slate-800">Blinkit</span>
                            </label>
                            <label class="flex flex-col items-center justify-center p-2.5 rounded-xl border-2 border-slate-200 bg-slate-50 hover:bg-amber-50 cursor-pointer transition has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50">
                                <input type="radio" name="del_company" value="Zepto" class="hidden">
                                <span class="text-xl mb-0.5">🛍️</span>
                                <span class="text-xs font-bold text-slate-800">Zepto</span>
                            </label>
                            <label class="flex flex-col items-center justify-center p-2.5 rounded-xl border-2 border-slate-200 bg-slate-50 hover:bg-amber-50 cursor-pointer transition has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50">
                                <input type="radio" name="del_company" value="Flipkart" class="hidden">
                                <span class="text-xl mb-0.5">🛒</span>
                                <span class="text-xs font-bold text-slate-800">Flipkart</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wider">Gate Instruction for Guard</label>
                        <select id="del_instruction" class="w-full px-3 py-2.5 text-xs bg-white border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-900 font-semibold text-slate-800">
                            <option value="Leave at Gate / Guard">Leave Package with Security Guard (Do Not Ring)</option>
                            <option value="Allow Entry">Allow Direct Entry to Doorstep</option>
                            <option value="Leave at Doorstep">Leave at Doorstep (Contactless)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wider">Valid For</label>
                        <select id="del_duration" class="w-full px-3 py-2 text-xs bg-white border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-900 font-medium text-slate-800">
                            <option value="2">Next 2 Hours</option>
                            <option value="4" selected>Next 4 Hours</option>
                            <option value="8">Next 8 Hours</option>
                            <option value="24">Today (Until Tomorrow)</option>
                        </select>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button" onclick="closeDeliveryModal()" class="w-1/3 py-2.5 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg transition cursor-pointer">Cancel</button>
                        <button type="submit" id="btn-submit-delivery" class="w-2/3 py-2.5 px-4 bg-brand-900 hover:bg-brand-800 text-white text-xs font-bold rounded-lg shadow transition cursor-pointer">
                            Save Pre-Approval
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Visitor Photo Lightbox Modal -->
    <div id="visitor-photo-lightbox-modal" role="dialog" aria-modal="true" aria-labelledby="lightbox-visitor-name" onclick="if (event.target === this) closePhotoLightbox()" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-md flex flex-col justify-center items-center p-4">
        <div class="relative max-w-md w-full bg-slate-900 rounded-2xl overflow-hidden shadow-2xl border border-slate-700 p-5 text-center my-auto">
            <div class="flex items-center justify-between mb-4">
                <div class="text-left">
                    <h3 id="lightbox-visitor-name" class="text-lg font-bold text-white font-serif tracking-wide">Visitor Photo</h3>
                    <p id="lightbox-photo-meta" class="text-xs text-amber-400 font-medium">📸 Captured at Gate Terminal</p>
                </div>
                <button type="button" onclick="closePhotoLightbox()" aria-label="Close photo preview" class="text-slate-400 hover:text-white p-2 rounded-xl bg-slate-800 hover:bg-slate-700 transition cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div class="relative max-h-[60vh] flex items-center justify-center bg-slate-950 rounded-xl overflow-hidden border border-slate-800 p-2">
                <img id="lightbox-img" src="" alt="Visitor Photo" class="max-h-[55vh] w-auto max-w-full object-contain rounded-lg shadow-lg">
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" onclick="closePhotoLightbox()" class="px-5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-lg transition cursor-pointer">Done</button>
            </div>
        </div>
    </div>

    <div id="complaint-modal" role="dialog" aria-modal="true" aria-labelledby="complaint-modal-title" onclick="if (event.target === this) toggleModal('complaint-modal')" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex justify-center items-center p-4 overflow-y-auto">
        <div class="bg-white w-full max-w-md rounded-xl shadow-lg border border-slate-200 p-6 my-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 id="complaint-modal-title" class="text-lg font-bold text-slate-900 font-serif">Raise a Complaint</h3>
                <button type="button" onclick="toggleModal('complaint-modal')" aria-label="Close modal" class="text-slate-400 hover:text-slate-600 focus:outline-none p-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form id="complaint-form" onsubmit="event.preventDefault(); submitComplaint();">
                <div class="space-y-4">
                    <div>
                        <label for="ticket_category" class="block text-sm font-medium text-slate-700 mb-1">Category</label>
                        <select id="ticket_category" required class="w-full px-4 py-2 bg-white rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:border-brand-900">
                            <option value="">Select an issue...</option>
                            <option value="Plumbing">Plumbing</option>
                            <option value="Electrical">Electrical</option>
                            <option value="Housekeeping">Housekeeping / Cleaning</option>
                            <option value="Security">Security / Noise</option>
                            <option value="General">General Maintenance</option>
                        </select>
                    </div>
                    <div>
                        <label for="ticket_description" class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                        <textarea id="ticket_description" required rows="4" class="w-full px-4 py-2 bg-white rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:border-brand-900" placeholder="Please describe the issue in detail..."></textarea>
                    </div>
                    <button type="submit" id="btn-submit-ticket" class="w-full px-6 py-2.5 text-sm font-medium text-white bg-brand-900 hover:bg-brand-800 rounded-lg shadow-sm transition-all">
                        Submit Ticket
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Gate Intercom & Society Directory Modal -->
    <div id="directory-modal" role="dialog" aria-modal="true" aria-labelledby="directory-modal-title" onclick="if (event.target === this) closeDirectoryModal()" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex justify-center items-center p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-100 animate-in fade-in zoom-in-95 duration-150">
            <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2">
                    <span class="text-xl">📞</span>
                    <h3 id="directory-modal-title" class="text-lg font-bold text-slate-900 font-serif">Gate Intercom & Directory</h3>
                </div>
                <button type="button" onclick="closeDirectoryModal()" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <p class="text-xs text-slate-500 mb-4">Tap to instantly dial gate security, estate facility managers, or emergency services.</p>

            <div class="space-y-2.5">
                <a href="tel:9876543213" class="flex items-center justify-between p-3.5 bg-slate-50 hover:bg-brand-50 border border-slate-200 hover:border-brand-300 rounded-xl transition group">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-slate-900 text-white flex items-center justify-center font-bold text-sm">🛡️</div>
                        <div>
                            <div class="text-xs font-bold text-slate-900 group-hover:text-brand-900">Main Security Gate Intercom</div>
                            <div class="text-[11px] text-slate-500">Guard On Duty • 24x7 Post</div>
                        </div>
                    </div>
                    <span class="px-3 py-1 bg-white group-hover:bg-brand-900 group-hover:text-white text-brand-900 border border-brand-200 text-xs font-bold rounded-lg shadow-2xs transition">Call</span>
                </a>

                <a href="tel:9876543211" class="flex items-center justify-between p-3.5 bg-slate-50 hover:bg-brand-50 border border-slate-200 hover:border-brand-300 rounded-xl transition group">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-brand-900 text-white flex items-center justify-center font-bold text-sm">🏢</div>
                        <div>
                            <div class="text-xs font-bold text-slate-900 group-hover:text-brand-900">Society Management Office</div>
                            <div class="text-[11px] text-slate-500">Estate Admin (10:00 AM - 6:00 PM)</div>
                        </div>
                    </div>
                    <span class="px-3 py-1 bg-white group-hover:bg-brand-900 group-hover:text-white text-brand-900 border border-brand-200 text-xs font-bold rounded-lg shadow-2xs transition">Call</span>
                </a>

                <a href="tel:9876500001" class="flex items-center justify-between p-3.5 bg-slate-50 hover:bg-brand-50 border border-slate-200 hover:border-brand-300 rounded-xl transition group">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-amber-500 text-white flex items-center justify-center font-bold text-sm">⚡</div>
                        <div>
                            <div class="text-xs font-bold text-slate-900 group-hover:text-brand-900">Society Electrician</div>
                            <div class="text-[11px] text-slate-500">Power Backup & Fuse Issues</div>
                        </div>
                    </div>
                    <span class="px-3 py-1 bg-white group-hover:bg-brand-900 group-hover:text-white text-brand-900 border border-brand-200 text-xs font-bold rounded-lg shadow-2xs transition">Call</span>
                </a>

                <a href="tel:9876500002" class="flex items-center justify-between p-3.5 bg-slate-50 hover:bg-brand-50 border border-slate-200 hover:border-brand-300 rounded-xl transition group">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-blue-500 text-white flex items-center justify-center font-bold text-sm">🔧</div>
                        <div>
                            <div class="text-xs font-bold text-slate-900 group-hover:text-brand-900">Society Plumber</div>
                            <div class="text-[11px] text-slate-500">Water Supply & Leakage</div>
                        </div>
                    </div>
                    <span class="px-3 py-1 bg-white group-hover:bg-brand-900 group-hover:text-white text-brand-900 border border-brand-200 text-xs font-bold rounded-lg shadow-2xs transition">Call</span>
                </a>

                <div class="p-3 bg-rose-50 border border-rose-200 rounded-xl mt-3 flex items-center justify-between">
                    <div>
                        <div class="text-xs font-black text-rose-800">Emergency Desks</div>
                        <div class="text-[11px] text-rose-600">Police 112 • Ambulance 108 • Fire 101</div>
                    </div>
                    <a href="tel:112" class="px-3 py-1 bg-rose-600 hover:bg-rose-700 text-white text-xs font-black rounded-lg shadow-xs transition">Dial 112</a>
                </div>
            </div>

            <button type="button" onclick="closeDirectoryModal()" class="w-full mt-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition cursor-pointer">Close</button>
        </div>
    </div>

    <!-- Vehicles & Parking Allocation Modal -->
    <div id="vehicles-modal" role="dialog" aria-modal="true" aria-labelledby="vehicles-modal-title" onclick="if (event.target === this) closeVehiclesModal()" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex justify-center items-center p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 animate-in fade-in zoom-in-95 duration-150">
            <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2">
                    <span class="text-xl">🚗</span>
                    <h3 id="vehicles-modal-title" class="text-lg font-bold text-slate-900 font-serif">My Vehicles & Parking</h3>
                </div>
                <button type="button" onclick="closeVehiclesModal()" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <p class="text-xs text-slate-500 mb-4">Register your personal vehicles so security guards recognize your car/bike instantly and allow seamless gate entry.</p>

            <!-- Add Vehicle Form -->
            <form id="vehicle-form" onsubmit="event.preventDefault(); registerVehicle();" class="p-4 bg-slate-50 border border-slate-200 rounded-xl mb-4">
                <div class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-3">Register New Vehicle</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Vehicle Type</label>
                        <select id="veh_type" class="w-full px-3 py-2 text-xs bg-white border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-900">
                            <option value="4_wheeler">🚗 4 Wheeler (Car / SUV)</option>
                            <option value="2_wheeler">🛵 2 Wheeler (Bike / Scooter)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Parking Slot (Optional)</label>
                        <input type="text" id="veh_slot" class="w-full px-3 py-2 text-xs bg-white border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-900" placeholder="e.g. P-101 or B-12">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="block text-[11px] font-semibold text-slate-600 mb-1">License Plate Number</label>
                    <input type="text" id="veh_number" required class="w-full px-3 py-2 text-xs uppercase bg-white border border-slate-300 rounded-lg font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-900" placeholder="e.g. MH 12 AB 1234">
                </div>
                <button type="submit" id="btn-submit-veh" class="w-full py-2 px-4 bg-brand-900 hover:bg-brand-800 text-white text-xs font-bold rounded-lg shadow transition cursor-pointer">
                    + Register Vehicle
                </button>
            </form>

            <!-- Existing Vehicles List -->
            <div class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-2">Registered Vehicles (Flat <?php echo htmlspecialchars((string)$unitNumber, ENT_QUOTES, 'UTF-8'); ?>)</div>
            <div id="vehicles-list-container" class="space-y-2 max-h-48 overflow-y-auto pr-1">
                <?php if (empty($registeredVehicles)): ?>
                    <div id="empty-vehicles-msg" class="p-3 text-center text-xs text-slate-400 bg-slate-50 rounded-lg border border-dashed border-slate-200">
                        No vehicles registered yet. Add your car or bike above.
                    </div>
                <?php else: ?>
                    <?php foreach ($registeredVehicles as $v): ?>
                        <div id="veh-row-<?php echo $v['id']; ?>" class="flex items-center justify-between p-3 bg-white border border-slate-200 rounded-xl shadow-xs">
                            <div class="flex items-center gap-3">
                                <span class="text-2xl"><?php echo $v['vehicle_type'] === '2_wheeler' ? '🛵' : '🚗'; ?></span>
                                <div>
                                    <div class="text-xs font-black text-slate-900 tracking-wider"><?php echo htmlspecialchars($v['vehicle_number'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="text-[11px] text-slate-500">
                                        <?php echo $v['vehicle_type'] === '2_wheeler' ? '2-Wheeler' : '4-Wheeler'; ?> 
                                        • Slot: <span class="font-semibold text-brand-900"><?php echo htmlspecialchars($v['parking_slot'] ?? 'Unassigned', ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </div>
                            </div>
                            <button type="button" onclick="deleteVehicle(<?php echo $v['id']; ?>)" class="text-xs text-rose-600 hover:text-rose-800 font-bold p-1.5 rounded-lg hover:bg-rose-50 transition cursor-pointer" title="Remove Vehicle">
                                Remove
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <button type="button" onclick="closeVehiclesModal()" class="w-full mt-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition cursor-pointer">Close</button>
        </div>
    </div>

    <!-- Gate Parcel Locker Modal -->
    <div id="parcels-modal" role="dialog" aria-modal="true" aria-labelledby="parcels-modal-title" onclick="if (event.target === this) closeParcelsModal()" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex justify-center items-center p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 animate-in fade-in zoom-in-95 duration-150">
            <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2">
                    <span class="text-xl">📦</span>
                    <h3 id="parcels-modal-title" class="text-lg font-bold text-slate-900 font-serif">Gate Parcel Locker</h3>
                </div>
                <button type="button" onclick="closeParcelsModal()" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <p class="text-xs text-slate-500 mb-4">Packages safely received by main gate security for Flat <?php echo htmlspecialchars((string)$unitNumber, ENT_QUOTES, 'UTF-8'); ?>. Collect them from Gate 1 security desk.</p>

            <div id="parcels-list-modal-container" class="space-y-3 max-h-64 overflow-y-auto pr-1">
                <?php if (empty($waitingParcels)): ?>
                    <div class="p-6 text-center text-xs text-slate-400 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                        <span class="text-3xl block mb-2">📭</span>
                        No parcels waiting at the gate right now.
                    </div>
                <?php else: ?>
                    <?php foreach ($waitingParcels as $p): ?>
                        <div class="p-4 bg-indigo-50/70 border border-indigo-200 rounded-xl flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-brand-900 text-white flex items-center justify-center font-bold text-lg">📦</div>
                                <div>
                                    <div class="text-xs font-bold text-slate-900 flex items-center gap-2">
                                        <span><?php echo htmlspecialchars($p['courier_company'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="bg-amber-100 text-amber-900 text-[10px] font-black px-2 py-0.5 rounded-full uppercase">At Gate</span>
                                    </div>
                                    <div class="text-[11px] text-slate-600 mt-0.5">For: <?php echo htmlspecialchars($p['recipient_name'] ?? 'Flat Resident', ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="text-[10px] text-slate-500">Received: <?php echo htmlspecialchars(date('d M, h:i A', strtotime($p['received_at'])), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                            </div>
                            <span class="px-3 py-1 bg-white border border-indigo-300 text-indigo-900 font-bold text-[11px] rounded-lg">Held Safely</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <button type="button" onclick="closeParcelsModal()" class="w-full mt-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition cursor-pointer">Close</button>
        </div>
    </div>

    <script>
        const csrfToken = document.getElementById('csrf_token').value;
        const vapidPublicKey = <?php echo json_encode((string)$vapidPublicKey); ?>;
        const residentUnitFallback = <?php echo json_encode((string)$unitNumber); ?>;

        let lastPendingCount = 0;
        let alertInterval = null;
        let visitorPollTimer = null;
        let isChimeMuted = false;

        function openPhotoLightbox(url, name, meta) {
            if (!url) return;
            const modal = document.getElementById('visitor-photo-lightbox-modal');
            const img = document.getElementById('lightbox-img');
            const nameEl = document.getElementById('lightbox-visitor-name');
            const metaEl = document.getElementById('lightbox-photo-meta');
            if (!modal || !img) return;
            img.src = url;
            if (nameEl) nameEl.textContent = name || 'Visitor Photo';
            if (metaEl) metaEl.textContent = meta ? `📸 ${meta}` : '📸 Captured at Gate Terminal';
            modal.classList.remove('hidden');
        }

        function closePhotoLightbox() {
            const modal = document.getElementById('visitor-photo-lightbox-modal');
            const img = document.getElementById('lightbox-img');
            if (modal) modal.classList.add('hidden');
            if (img) img.src = '';
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('valid_date').value = new Date().toISOString().split('T')[0];
            fetchNotices();
            fetchMyTickets();
            checkPendingVisitors();
            startVisitorPolling();
            registerPwaServiceWorker();

            // Auto-start persistent native background service if running inside Android APK
            if (window.SocietyHubNative && typeof window.SocietyHubNative.startBackgroundService === 'function') {
                try {
                    window.SocietyHubNative.startBackgroundService();
                } catch (e) {
                    console.log('Native background service init:', e);
                }
            }

            document.addEventListener('visibilitychange', () => {
                stopVisitorPolling();
                if (document.hidden) {
                    // Keep polling at a relaxed 6s interval in background tabs
                    visitorPollTimer = setInterval(checkPendingVisitors, 6000);
                } else {
                    checkPendingVisitors();
                    startVisitorPolling();
                }
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    const modal = document.getElementById('complaint-modal');
                    if (modal && !modal.classList.contains('hidden')) {
                        toggleModal('complaint-modal');
                    }
                }
            });

            if (window.SocietyHubNative && window.SocietyHubNative.isNativeApp()) {
                const btn = document.getElementById('btn-push-perm');
                if (btn) {
                    btn.innerText = 'Gate Alerts: Active (Urgent Ring Mode) ✓';
                    btn.classList.remove('bg-brand-900', 'hover:bg-brand-800');
                    btn.classList.add('bg-emerald-600', 'cursor-default');
                    btn.disabled = true;
                }
            } else if ('Notification' in window && Notification.permission === 'granted') {
                const btn = document.getElementById('btn-push-perm');
                if (btn) {
                    btn.innerText = 'Notifications Enabled ✓';
                    btn.classList.remove('bg-brand-900', 'hover:bg-brand-800');
                    btn.classList.add('bg-emerald-600', 'cursor-default');
                    btn.disabled = true;
                }
            }
        });

        function startVisitorPolling() {
            if (!visitorPollTimer) {
                visitorPollTimer = setInterval(checkPendingVisitors, 4000);
            }
        }

        function stopVisitorPolling() {
            if (visitorPollTimer) {
                clearInterval(visitorPollTimer);
                visitorPollTimer = null;
            }
        }

        async function registerPwaServiceWorker() {
            if ('serviceWorker' in navigator && 'PushManager' in window) {
                try {
                    await navigator.serviceWorker.register(appPath('/sw.js'), { scope: appPath('/') || '/' });
                } catch (error) {
                    console.error('Service Worker registration failed:', error);
                }
            }
        }

        function urlB64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }

        async function requestPushPermission() {
            if (window.SocietyHubNative) {
                showToast('🔔 Native Loud Gate Alerts are active on this device! Rings continuously even when app is closed.', 'info');
                return;
            }
            if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
                showToast('Web Push is not supported in this mobile browser. For continuous loud gate ring alerts, please use our SecureGate Android App!', 'warning');
                return;
            }
            if (!vapidPublicKey) {
                showToast('Push notifications are not configured on the server.', 'error');
                return;
            }

            const btn = document.getElementById('btn-push-perm');

            try {
                const registration = await navigator.serviceWorker.ready;
                const permission = await Notification.requestPermission();

                if (permission === 'granted') {
                    const subscription = await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlB64ToUint8Array(vapidPublicKey)
                    });

                    const response = await fetch(appPath('/api/save_push_subscription.php'), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ subscription: subscription, csrf_token: csrfToken })
                    });

                    const result = await response.json();

                    if (response.ok && result.status === 'success') {
                        btn.innerText = 'Notifications Enabled ✓';
                        btn.classList.remove('bg-brand-900', 'hover:bg-brand-800');
                        btn.classList.add('bg-emerald-600', 'cursor-default');
                        btn.disabled = true;
                        showToast('Background push notifications enabled!', 'success');
                    } else {
                        showToast(result.message || 'Failed to save subscription.', 'error');
                    }
                } else if (permission === 'denied') {
                    showToast('Notification permission was blocked.', 'warning');
                }
            } catch (error) {
                showToast('Error setting up push subscription.', 'error');
            }
        }

        function toggleModal(modalId) {
            document.getElementById(modalId).classList.toggle('hidden');
        }

        function scrollToPassForm() {
            const card = document.getElementById('guest-pass-card');
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            document.getElementById('visitor_name').focus();
        }

        let sharedAudioCtx = null;
        let isAudioUnlocked = false;

        function getAudioContext() {
            if (!sharedAudioCtx) {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (AudioCtx) {
                    sharedAudioCtx = new AudioCtx();
                }
            }
            if (sharedAudioCtx && sharedAudioCtx.state === 'suspended') {
                sharedAudioCtx.resume().catch(() => {});
            }
            return sharedAudioCtx;
        }

        function unlockAudio() {
            if (isAudioUnlocked) return;
            const ctx = getAudioContext();
            if (ctx) {
                // Play silent 1-sample buffer to satisfy mobile browser autoplay policies
                const buffer = ctx.createBuffer(1, 1, 22050);
                const source = ctx.createBufferSource();
                source.buffer = buffer;
                source.connect(ctx.destination);
                source.start(0);
                ctx.resume().then(() => {
                    isAudioUnlocked = true;
                }).catch(() => {});
            }
        }
        window.addEventListener('click', unlockAudio, { passive: true });
        window.addEventListener('touchstart', unlockAudio, { passive: true });
        window.addEventListener('keydown', unlockAudio, { passive: true });

        function toggleMuteChime() {
            isChimeMuted = !isChimeMuted;
            document.getElementById('btn-mute-chime').textContent = isChimeMuted ? 'Unmute Chime' : 'Mute Chime';
            if (isChimeMuted) {
                stopContinuousAlert();
            } else if (lastPendingCount > 0) {
                startContinuousAlert();
            }
        }

        function playLoudRingTone(duration = 1.0) {
            try {
                const ctx = getAudioContext();
                if (!ctx) return;

                const now = ctx.currentTime;
                const gainNode = ctx.createGain();
                // High gain for loud audible ringing
                gainNode.gain.setValueAtTime(0.85, now);
                gainNode.gain.setValueAtTime(0.85, now + duration - 0.05);
                gainNode.gain.linearRampToValueAtTime(0.001, now + duration);
                gainNode.connect(ctx.destination);

                // Loud telephone / intercom dual-tone frequencies: 440 Hz + 480 Hz
                const osc1 = ctx.createOscillator();
                const osc2 = ctx.createOscillator();

                osc1.type = 'sine';
                osc1.frequency.setValueAtTime(440, now);

                osc2.type = 'sine';
                osc2.frequency.setValueAtTime(480, now);

                osc1.connect(gainNode);
                osc2.connect(gainNode);

                osc1.start(now);
                osc2.start(now);
                osc1.stop(now + duration);
                osc2.stop(now + duration);
            } catch (e) {
                console.warn('Audio tone error', e);
            }
        }

        function triggerRingAlert() {
            if ('vibrate' in navigator) {
                try {
                    navigator.vibrate([800, 300, 800, 300, 800]);
                } catch (e) {}
            }

            // Dual-pulse phone ring cadence: 1.0s ring, 0.2s pause, 1.0s ring
            playLoudRingTone(1.0);
            setTimeout(() => {
                if (!isChimeMuted && (alertInterval || lastPendingCount > 0)) {
                    playLoudRingTone(1.0);
                }
            }, 1200);
        }

        function startContinuousAlert() {
            if (isChimeMuted || alertInterval) return;
            unlockAudio();
            triggerRingAlert();
            alertInterval = setInterval(() => {
                if (!isChimeMuted) {
                    triggerRingAlert();
                }
            }, 3200);
        }

        function stopContinuousAlert() {
            if (alertInterval) {
                clearInterval(alertInterval);
                alertInterval = null;
            }
            if (window.SocietyHubNative && typeof window.SocietyHubNative.stopVisitorAlert === 'function') {
                try {
                    window.SocietyHubNative.stopVisitorAlert();
                } catch (e) {}
            }
        }

        function testLoudAlert() {
            unlockAudio();
            if (window.SocietyHubNative && typeof window.SocietyHubNative.triggerVisitorAlert === 'function') {
                window.SocietyHubNative.triggerVisitorAlert(
                    999999,
                    'Test Visitor (Rahul)',
                    'Delivery Package',
                    residentUnitFallback || '101',
                    '',
                    'TEST_TOKEN'
                );
                showToast('🚨 Native Loud Gate Alert launched! Tap ALLOW or DENY on the call screen.', 'success');
            } else {
                showToast('🚨 Testing continuous loud alert ringtone & vibration...', 'warning');
                isChimeMuted = false;
                document.getElementById('btn-mute-chime').textContent = 'Mute Chime';
                startContinuousAlert();
                setTimeout(() => {
                    if (lastPendingCount === 0) {
                        stopContinuousAlert();
                        showToast('Alert test completed.', 'info');
                    }
                }, 6500);
            }
        }

        async function fetchNotices() {
            const container = document.getElementById('resident-notices-container');
            const badge = document.getElementById('notice-count-badge');

            try {
                const response = await fetch(appPath('/api/get_notices.php'));
                const result = await response.json();

                if (response.ok && result.status === 'success') {
                    const notices = result.data || [];
                    if (notices.length === 0) {
                        container.innerHTML = `<p class="text-xs text-slate-400">No announcements posted yet.</p>`;
                        badge.classList.add('hidden');
                        return;
                    }

                    badge.textContent = `${notices.length}`;
                    badge.classList.remove('hidden');

                    container.innerHTML = notices.slice(0, 5).map(n => `
                        <div class="bg-amber-50/70 border-l-4 border-amber-400 p-3 rounded-r-lg">
                            <div class="flex justify-between items-start gap-2 mb-1">
                                <p class="text-xs font-semibold text-amber-900">${escapeHtml(n.title)}</p>
                                <span class="text-[11px] text-amber-700 shrink-0">${escapeHtml(formatDisplayDate(n.created_at))}</span>
                            </div>
                            <p class="text-xs text-amber-800 whitespace-pre-wrap">${escapeHtml(n.content)}</p>
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = `<p class="text-xs text-red-500">Unable to load notices.</p>`;
                }
            } catch (e) {
                container.innerHTML = `<p class="text-xs text-red-500">Network error loading notices.</p>`;
            }
        }

        async function checkPendingVisitors() {
            try {
                const response = await fetch(appPath('/api/resident_get_pending_visitors.php'));
                const result = await response.json();

                const section = document.getElementById('gate-alerts-section');
                const container = document.getElementById('pending-visitors-container');

                if (response.ok && result.status === 'success' && result.data.length > 0) {
                    if (result.data.length > lastPendingCount) {
                        isChimeMuted = false;
                        document.getElementById('btn-mute-chime').textContent = 'Mute Chime';
                    }
                    lastPendingCount = result.data.length;
                    startContinuousAlert();

                    // Native Android Bridge: rings continuously on USAGE_ALARM stream, wakes lock screen, shows call alert
                    if (window.SocietyHubNative && typeof window.SocietyHubNative.triggerVisitorAlert === 'function') {
                        const firstVis = result.data[0];
                        try {
                            window.SocietyHubNative.triggerVisitorAlert(
                                Number(firstVis.id),
                                String(firstVis.visitor_name || 'Guest'),
                                String(firstVis.purpose || 'Entry Request'),
                                String(firstVis.unit_number || residentUnitFallback || 'Your Flat'),
                                String(firstVis.photo_url || ''),
                                String(firstVis.action_token || '')
                            );
                        } catch (e) {
                            console.log('Native bridge call error:', e);
                        }
                    }

                    section.classList.remove('hidden');

                    container.innerHTML = result.data.map(vis => {
                        const safeName = escapeHtml(vis.visitor_name || 'Guest');
                        const initial = (vis.visitor_name || '?').charAt(0).toUpperCase();
                        const photoSrc = vis.photo_url || '';
                        const photoHtml = photoSrc
                            ? `
                                <div class="relative group cursor-pointer" onclick="openPhotoLightbox('${escapeHtml(photoSrc)}', '${safeName}', '${escapeHtml(vis.purpose || 'Entry Request')} | Unit ${escapeHtml(verifiedUnit)}')">
                                    <img src="${escapeHtml(photoSrc)}" alt="${safeName}" class="w-20 h-20 sm:w-24 sm:h-24 min-w-[80px] sm:min-w-[96px] rounded-2xl object-cover border-2 border-amber-400 shadow-md group-hover:scale-105 group-hover:border-amber-500 transition-all ring-2 ring-amber-300/50" onerror="this.onerror=null; this.outerHTML='<div class=\\\'w-20 h-20 sm:w-24 sm:h-24 min-w-[80px] sm:min-w-[96px] rounded-2xl bg-amber-200 border-2 border-amber-300 flex flex-col items-center justify-center font-bold text-amber-800 text-2xl shadow-inner\\\'><span>${escapeHtml(initial)}</span><span class=\\\'text-[10px] text-amber-700 font-normal mt-0.5\\\'>No Photo</span></div>';">
                                    <span class="absolute bottom-1 right-1 bg-slate-900/80 text-white text-[9px] px-1.5 py-0.5 rounded-full font-bold flex items-center gap-0.5">🔍 Zoom</span>
                                </div>
                              `
                            : `
                                <div class="w-20 h-20 sm:w-24 sm:h-24 min-w-[80px] sm:min-w-[96px] rounded-2xl bg-amber-200 border-2 border-amber-300 flex flex-col items-center justify-center font-bold text-amber-800 text-2xl shadow-inner">
                                    <span>${escapeHtml(initial)}</span>
                                    <span class="text-[10px] text-amber-700 font-normal mt-0.5">No Photo</span>
                                </div>
                              `;

                        const verifiedUnit = vis.unit_number || residentUnitFallback;

                        return `
                            <div class="bg-white p-4 rounded-lg border border-amber-300 flex flex-col sm:flex-row items-center justify-between gap-4 shadow-md">
                                <div class="flex items-center gap-4 text-left w-full">
                                    ${photoHtml}
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-slate-900 text-base">${safeName}</span>
                                            <span class="bg-brand-50 text-brand-900 text-xs font-bold px-2 py-0.5 rounded border border-brand-200">Unit ${escapeHtml(verifiedUnit)}</span>
                                        </div>
                                        <div class="text-xs text-slate-600 font-medium mt-0.5">Purpose: <span class="text-amber-800 font-bold">${escapeHtml(vis.purpose)}</span> | Ph: ${escapeHtml(vis.phone_number || 'N/A')}</div>
                                        <div class="text-xs text-slate-400 mt-0.5">Arrived at: ${escapeHtml(formatDisplayTime(vis.entry_time))}</div>
                                    </div>
                                </div>
                                <div class="flex gap-2 w-full sm:w-auto">
                                    <button type="button" onclick="handleVisitorApproval(${Number(vis.id)}, 'Approve')" class="flex-1 sm:flex-none px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs rounded-lg shadow transition-all cursor-pointer">Allow Entry</button>
                                    <button type="button" onclick="handleVisitorApproval(${Number(vis.id)}, 'Decline')" class="flex-1 sm:flex-none px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-semibold text-xs rounded-lg shadow transition-all cursor-pointer">Deny</button>
                                </div>
                            </div>
                        `;
                    }).join('');
                } else {
                    stopContinuousAlert();
                    if (lastPendingCount > 0) {
                        refreshResidentVisitorHistory();
                    }
                    lastPendingCount = 0;
                    section.classList.add('hidden');
                    container.innerHTML = '';
                }
            } catch (err) {
                console.error('Polling error', err);
            }
        }

        async function handleVisitorApproval(visitorId, action) {
            // Instantly update the DOM row if visible
            const isApproved = (action === 'Approve' || action === 'Entered' || action === 'Allow' || action === 'allow');
            const targetRow = document.querySelector(`tr[data-visitor-id="${visitorId}"]`);
            if (targetRow) {
                const statusCell = targetRow.querySelector('.visitor-status-cell');
                if (statusCell) {
                    statusCell.innerHTML = isApproved
                        ? '<span class="bg-emerald-50 text-emerald-700 px-2.5 py-1 rounded-full text-xs font-semibold border border-emerald-200">Entered</span>'
                        : '<span class="bg-rose-50 text-rose-700 px-2.5 py-1 rounded-full text-xs font-semibold border border-rose-200">Denied</span>';
                }
            }

            try {
                const response = await fetch(appPath('/api/resident_handle_visitor_approval.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ visitor_id: visitorId, action: action, csrf_token: csrfToken })
                });

                const result = await response.json();
                if (response.ok) {
                    stopContinuousAlert();
                    if (window.SocietyHubNative && typeof window.SocietyHubNative.stopVisitorAlert === 'function') {
                        try { window.SocietyHubNative.stopVisitorAlert(); } catch (e) {}
                    }
                    checkPendingVisitors();
                    refreshResidentVisitorHistory();
                    showToast(result.message || (isApproved ? 'Visitor entered society ✓' : 'Visitor entry denied.'), 'success');
                } else {
                    showToast(result.message || 'Unable to process decision.', 'error');
                }
            } catch (err) {
                showToast('Error connecting to server.', 'error');
            }
        }

        async function refreshResidentVisitorHistory() {
            try {
                const response = await fetch(appPath('/api/resident_get_visitor_history.php?limit=5'));
                const result = await response.json();
                if (response.ok && result.status === 'success' && Array.isArray(result.visitors)) {
                    renderResidentVisitorHistory(result.visitors);
                }
            } catch (e) {
                console.warn('Could not refresh visitor history:', e);
            }
        }

        function renderResidentVisitorHistory(visitors) {
            const tbody = document.getElementById('visitor-history-tbody');
            if (!tbody) return;
            if (visitors.length === 0) {
                tbody.innerHTML = '<tr id="empty-visitors-row"><td colspan="5" class="px-6 py-8 text-center text-slate-500">No visitors logged yet for your unit.</td></tr>';
                return;
            }
            tbody.innerHTML = visitors.map(v => {
                const safeName = escapeHtml(v.visitor_name || 'Guest');
                const initial = (v.visitor_name || '?').charAt(0).toUpperCase();
                const isDelivery = (v.purpose === 'Delivery');
                const purposeBadge = isDelivery
                    ? '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded text-xs font-medium bg-amber-50 text-amber-800 border border-amber-200">📦 Delivery</span>'
                    : `<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700">👤 ${escapeHtml(v.purpose)}</span>`;

                let statusBadge = '';
                if (v.status === 'Entered') {
                    statusBadge = '<span class="bg-emerald-50 text-emerald-700 px-2.5 py-1 rounded-full text-xs font-semibold border border-emerald-200">Entered</span>';
                } else if (v.status === 'Pending') {
                    statusBadge = '<span class="bg-amber-50 text-amber-700 px-2.5 py-1 rounded-full text-xs font-semibold border border-amber-200">Pending Gate</span>';
                } else if (v.status === 'Denied') {
                    statusBadge = '<span class="bg-rose-50 text-rose-700 px-2.5 py-1 rounded-full text-xs font-semibold border border-rose-200">Denied</span>';
                } else {
                    statusBadge = '<span class="bg-slate-100 text-slate-600 px-2.5 py-1 rounded-full text-xs border border-slate-200">Exited</span>';
                }

                const vehicleHtml = v.vehicle_number ? `<div class="text-[10px] text-slate-400 font-mono">${escapeHtml(v.vehicle_number)}</div>` : '';
                const exitTimeHtml = v.exit_time ? escapeHtml(formatDisplayTime(v.exit_time)) : '<span class="text-slate-400">—</span>';

                const photoSrc = v.photo_url || '';
                const avatarHtml = photoSrc
                    ? `
                        <div class="relative group cursor-pointer" onclick="openPhotoLightbox('${escapeHtml(photoSrc)}', '${safeName}', '${escapeHtml(v.purpose || 'Visitor')}')">
                            <img src="${escapeHtml(photoSrc)}" alt="${safeName}" class="w-10 h-10 min-w-[40px] rounded-full object-cover border-2 border-slate-200 shadow-sm group-hover:border-brand-600 group-hover:scale-105 transition-all" onerror="this.onerror=null; this.outerHTML='<div class=\\\'w-10 h-10 min-w-[40px] rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center font-bold text-xs text-slate-700\\\'>${escapeHtml(initial)}</div>';">
                            <span class="absolute -bottom-1 -right-1 bg-slate-900 text-white p-0.5 rounded-full text-[9px] opacity-0 group-hover:opacity-100 transition-opacity" title="Click to enlarge">🔍</span>
                        </div>
                      `
                    : `
                        <div class="w-10 h-10 min-w-[40px] rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center font-bold text-xs text-slate-700 shadow-inner">
                            ${escapeHtml(initial)}
                        </div>
                      `;

                return `
                    <tr class="hover:bg-slate-50 visitor-record-row" data-visitor-id="${Number(v.id)}">
                        <td class="px-6 py-4 font-semibold text-slate-900">
                            <div class="flex items-center gap-3">
                                ${avatarHtml}
                                <div>
                                    <div class="visitor-name-cell font-bold text-slate-900">${safeName}</div>
                                    ${v.phone_number ? `<div class="text-[11px] text-slate-500 font-mono">${escapeHtml(v.phone_number)}</div>` : ''}
                                    ${vehicleHtml ? `<div class="text-[10px] text-slate-400 font-mono">${vehicleHtml}</div>` : ''}
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-slate-600 visitor-purpose-cell">
                            ${purposeBadge}
                        </td>
                        <td class="px-6 py-4 text-slate-500 text-xs font-mono">
                            ${escapeHtml(formatDisplayTime(v.entry_time))}
                        </td>
                        <td class="px-6 py-4 text-slate-500 text-xs font-mono">
                            ${exitTimeHtml}
                        </td>
                        <td class="px-6 py-4 visitor-status-cell">
                            ${statusBadge}
                        </td>
                    </tr>
                `;
            }).join('');
        }

        async function generatePass() {
            const btn = document.getElementById('btn-pass-submit');
            btn.innerText = 'Generating...';
            btn.disabled = true;

            const payload = {
                visitor_name: document.getElementById('visitor_name').value.trim(),
                valid_date: document.getElementById('valid_date').value,
                csrf_token: csrfToken
            };

            try {
                const response = await fetch(appPath('/api/resident_generate_pin.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();

                if (response.ok && result.status === 'success') {
                    document.getElementById('pass-form').reset();
                    document.getElementById('valid_date').value = new Date().toISOString().split('T')[0];

                    lastGeneratedPass = {
                        visitor_name: result.visitor_name || payload.visitor_name,
                        pin_code: result.pin_code,
                        valid_date: result.valid_date || payload.valid_date
                    };

                    document.getElementById('generated-pin-display').textContent = result.pin_code;
                    document.getElementById('pin-result-box').classList.remove('hidden');

                    prependGuestPassRow(lastGeneratedPass.visitor_name, lastGeneratedPass.pin_code, lastGeneratedPass.valid_date);
                    showToast('Guest pass generated! Ready to share on WhatsApp.', 'success');
                } else {
                    showToast(result.message || 'Failed to generate pass.', 'error');
                }
            } catch (error) {
                showToast('An error occurred while generating the pass.', 'error');
            } finally {
                btn.innerText = 'Generate Pass PIN';
                btn.disabled = false;
            }
        }

        function prependGuestPassRow(visitorName, pinCode, validDate) {
            const tbody = document.getElementById('guest-passes-tbody');
            const emptyRow = document.getElementById('empty-passes-row');
            if (emptyRow) emptyRow.remove();

            const tr = document.createElement('tr');
            tr.className = 'hover:bg-slate-50';
            tr.innerHTML = `
                <td class="px-6 py-4 font-semibold text-slate-900">${escapeHtml(visitorName)}</td>
                <td class="px-6 py-4 font-mono font-bold text-brand-900 tracking-wider">${escapeHtml(pinCode)}</td>
                <td class="px-6 py-4 text-slate-500">${escapeHtml(formatDisplayDate(validDate))}</td>
                <td class="px-6 py-4"><span class="bg-blue-50 text-blue-700 px-2.5 py-1 rounded-full text-xs font-semibold border border-blue-200">Active</span></td>
                <td class="px-6 py-4 text-center">
                    <div class="flex items-center justify-center gap-1.5">
                        <button type="button" onclick="sharePassRowWhatsApp('${escapeHtml(visitorName)}', '${escapeHtml(pinCode)}', '${escapeHtml(validDate)}')" class="p-1.5 bg-[#25D366] hover:bg-[#20bd5a] text-white rounded-md shadow-sm transition cursor-pointer" title="Share via WhatsApp">
                            <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                        </button>
                        <button type="button" onclick="copyPassRowText('${escapeHtml(visitorName)}', '${escapeHtml(pinCode)}', '${escapeHtml(validDate)}')" class="p-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-md transition cursor-pointer" title="Copy Invite Text">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                        </button>
                    </div>
                </td>
            `;
            tbody.prepend(tr);
            while (tbody.rows.length > 5) {
                tbody.deleteRow(tbody.rows.length - 1);
            }
        }

        async function fetchMyTickets() {
            const tbody = document.getElementById('resident-tickets');
            try {
                const response = await fetch(appPath('/api/resident_get_complaints.php'));
                const result = await response.json();

                if (response.ok && result.status === 'success') {
                    if (result.data.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">You haven't submitted any tickets yet.</td></tr>`;
                        return;
                    }

                    tbody.innerHTML = result.data.map(ticket => {
                        let statusBadge = ticket.status === 'Open'
                            ? `<span class="bg-slate-100 text-slate-600 px-2 py-1 rounded text-xs font-semibold border border-slate-200">Open</span>`
                            : ticket.status === 'In Progress'
                                ? `<span class="bg-brand-50 text-brand-700 px-2 py-1 rounded text-xs font-semibold border border-brand-200">In Progress</span>`
                                : `<span class="bg-emerald-50 text-emerald-700 px-2 py-1 rounded text-xs font-semibold border border-emerald-200">Resolved</span>`;

                        return `
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-4 py-3 font-medium text-slate-900">#${Number(ticket.id)}</td>
                                <td class="px-4 py-3 text-slate-700 whitespace-normal max-w-xs">
                                    <div class="font-semibold">${escapeHtml(ticket.category)}</div>
                                    <div class="text-xs text-slate-500 truncate">${escapeHtml(ticket.description)}</div>
                                </td>
                                <td class="px-4 py-3">${statusBadge}</td>
                                <td class="px-4 py-3 text-xs text-slate-500">${escapeHtml(formatDisplayDate(ticket.created_at))}</td>
                            </tr>
                        `;
                    }).join('');
                } else {
                    tbody.innerHTML = `<tr><td colspan="4" class="px-4 py-8 text-center text-red-500">${escapeHtml(result.message || 'Error loading tickets.')}</td></tr>`;
                }
            } catch (error) {
                tbody.innerHTML = `<tr><td colspan="4" class="px-4 py-8 text-center text-red-500">Error loading tickets.</td></tr>`;
            }
        }

        async function submitComplaint() {
            const btn = document.getElementById('btn-submit-ticket');
            btn.innerText = 'Submitting...';
            btn.disabled = true;

            const payload = {
                category: document.getElementById('ticket_category').value,
                description: document.getElementById('ticket_description').value.trim(),
                csrf_token: csrfToken
            };

            try {
                const response = await fetch(appPath('/api/resident_create_complaint.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();

                if (response.ok) {
                    document.getElementById('complaint-form').reset();
                    toggleModal('complaint-modal');
                    fetchMyTickets();
                    showToast(result.message || 'Ticket submitted.', 'success');
                } else {
                    showToast(result.message || 'Unable to submit ticket.', 'error');
                }
            } catch (error) {
                showToast('An error occurred while submitting.', 'error');
            } finally {
                btn.innerText = 'Submit Ticket';
                btn.disabled = false;
            }
        }

        // --- WHATSAPP & PASS SHARING UTILITIES ---
        let lastGeneratedPass = null;
        const societyNameFallback = "<?php echo htmlspecialchars($societyName, ENT_QUOTES, 'UTF-8'); ?>";

        function buildPassInviteMessage(visitorName, pinCode, validDate) {
            const dateStr = validDate ? formatDisplayDate(validDate) : 'Today';
            return `Hi ${visitorName},\nHere is your entry pass for Unit ${residentUnitFallback || '101'} at ${societyNameFallback}:\n\n🔑 PIN: ${pinCode}\n📅 Valid: ${dateStr}\n\nPlease show this 6-digit PIN at the main security gate for fast-track entry.\n- Sent via SecureGate`;
        }

        function sharePassRowWhatsApp(visitorName, pinCode, validDate) {
            const text = buildPassInviteMessage(visitorName, pinCode, validDate);
            const waUrl = `https://api.whatsapp.com/send?text=${encodeURIComponent(text)}`;
            window.open(waUrl, '_blank');
        }

        function copyPassRowText(visitorName, pinCode, validDate) {
            const text = buildPassInviteMessage(visitorName, pinCode, validDate);
            copyToClipboard(text, 'Guest pass invite copied to clipboard!');
        }

        function shareGeneratedPassWhatsApp() {
            if (!lastGeneratedPass) return;
            sharePassRowWhatsApp(lastGeneratedPass.visitor_name, lastGeneratedPass.pin_code, lastGeneratedPass.valid_date);
        }

        function copyGeneratedPassText() {
            if (!lastGeneratedPass) return;
            copyPassRowText(lastGeneratedPass.visitor_name, lastGeneratedPass.pin_code, lastGeneratedPass.valid_date);
        }

        function copyCurrentPin() {
            if (!lastGeneratedPass) return;
            copyToClipboard(lastGeneratedPass.pin_code, `PIN ${lastGeneratedPass.pin_code} copied!`);
        }

        async function shareGeneratedPassNative() {
            if (!lastGeneratedPass) return;
            const text = buildPassInviteMessage(lastGeneratedPass.visitor_name, lastGeneratedPass.pin_code, lastGeneratedPass.valid_date);
            if (window.SocietyHubNative && typeof window.SocietyHubNative.shareText === 'function') {
                window.SocietyHubNative.shareText(text, 'Share Guest Pass');
                return;
            }
            if (navigator.share) {
                try {
                    await navigator.share({
                        title: 'Guest Pass PIN',
                        text: text
                    });
                } catch (e) {}
            } else {
                copyGeneratedPassText();
            }
        }

        function copyToClipboard(text, successMsg) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => {
                    showToast(successMsg, 'success');
                }).catch(() => {
                    fallbackCopy(text, successMsg);
                });
            } else {
                fallbackCopy(text, successMsg);
            }
        }

        function fallbackCopy(text, successMsg) {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            try {
                document.execCommand('copy');
                showToast(successMsg, 'success');
            } catch (e) {
                showToast('Unable to copy text.', 'error');
            }
            document.body.removeChild(ta);
        }

        // --- EMERGENCY SOS UTILITIES ---
        function openEmergencyModal() {
            document.getElementById('emergency-sos-modal').classList.remove('hidden');
        }

        function closeEmergencyModal() {
            document.getElementById('emergency-sos-modal').classList.add('hidden');
        }

        async function submitEmergencySos() {
            const btn = document.getElementById('btn-submit-sos');
            btn.innerText = 'DISPATCHING ALARM...';
            btn.disabled = true;

            const selectedRadio = document.querySelector('input[name="sos_type"]:checked');
            const alertType = selectedRadio ? selectedRadio.value : 'General SOS';
            const notes = document.getElementById('sos_notes').value.trim();

            try {
                const response = await fetch(appPath('/api/resident_trigger_emergency.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        alert_type: alertType,
                        notes: notes,
                        csrf_token: csrfToken
                    })
                });

                const result = await response.json();
                if (response.ok && result.status === 'success') {
                    closeEmergencyModal();
                    document.getElementById('emergency-form').reset();
                    showToast('🚨 SOS Dispatched! Security guards have been alerted with siren.', 'warning');
                } else {
                    showToast(result.message || 'Failed to dispatch SOS.', 'error');
                }
            } catch (e) {
                showToast('Network error triggering emergency alert.', 'error');
            } finally {
                btn.innerText = '🚨 TRIGGER SOS ALARM';
                btn.disabled = false;
            }
        }

        // --- DIRECTORY & INTERCOM UTILITIES ---
        function openDirectoryModal() {
            document.getElementById('directory-modal').classList.remove('hidden');
        }

        function closeDirectoryModal() {
            document.getElementById('directory-modal').classList.add('hidden');
        }

        // --- DELIVERY PRE-APPROVAL UTILITIES ---
        function openDeliveryModal() {
            document.getElementById('delivery-modal').classList.remove('hidden');
        }

        function closeDeliveryModal() {
            document.getElementById('delivery-modal').classList.add('hidden');
        }

        async function submitDeliveryPreapproval() {
            const btn = document.getElementById('btn-submit-delivery');
            btn.innerText = 'Saving...';
            btn.disabled = true;

            const selectedCompany = document.querySelector('input[name="del_company"]:checked');
            const company = selectedCompany ? selectedCompany.value : 'Swiggy';
            const instruction = document.getElementById('del_instruction').value;
            const durationHours = parseInt(document.getElementById('del_duration').value, 10);

            try {
                const response = await fetch(appPath('/api/resident_save_delivery_preapproval.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        company: company,
                        instruction: instruction,
                        duration_hours: durationHours,
                        csrf_token: csrfToken
                    })
                });

                const result = await response.json();
                if (response.ok && result.status === 'success') {
                    closeDeliveryModal();
                    showToast(`📦 Pre-approved ${company}! Gate instruction: ${instruction}`, 'success');
                    reloadDeliveryPreapprovals();
                } else {
                    showToast(result.message || 'Failed to pre-approve delivery.', 'error');
                }
            } catch (e) {
                showToast('Network error saving delivery pre-approval.', 'error');
            } finally {
                btn.innerText = 'Save Pre-Approval';
                btn.disabled = false;
            }
        }

        async function cancelDeliveryPreapproval(id) {
            if (!confirm('Cancel this delivery instruction?')) return;
            try {
                const response = await fetch(appPath('/api/resident_cancel_delivery_preapproval.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, csrf_token: csrfToken })
                });
                const result = await response.json();
                if (response.ok) {
                    const card = document.getElementById(`preapproval-card-${id}`);
                    if (card) card.remove();
                    showToast('Delivery instruction cancelled.', 'info');
                    reloadDeliveryPreapprovals();
                } else {
                    showToast(result.message || 'Unable to cancel.', 'error');
                }
            } catch (e) {
                showToast('Network error cancelling instruction.', 'error');
            }
        }

        async function reloadDeliveryPreapprovals() {
            try {
                const response = await fetch(appPath('/api/resident_get_delivery_preapprovals.php'));
                const result = await response.json();
                const container = document.getElementById('preapprovals-list-container');
                if (response.ok && result.status === 'success') {
                    if (result.data.preapprovals.length === 0) {
                        container.innerHTML = `<div id="empty-preapprovals" class="col-span-full py-4 text-center text-xs text-slate-400 bg-slate-50 rounded-lg border border-dashed border-slate-200">No active delivery pre-approvals. Tap "+ Pre-approve Delivery" for Swiggy, Zomato, Amazon, Blinkit, etc.</div>`;
                        return;
                    }
                    container.innerHTML = result.data.preapprovals.map(pre => `
                        <div id="preapproval-card-${pre.id}" class="p-3 bg-amber-50/70 border border-amber-200 rounded-lg flex items-center justify-between">
                            <div>
                                <div class="text-xs font-bold text-amber-950 flex items-center gap-1.5">
                                    <span>${escapeHtml(pre.company)}</span>
                                    <span class="bg-amber-200 text-amber-800 text-[10px] px-1.5 py-0.5 rounded font-semibold">Active</span>
                                </div>
                                <div class="text-[11px] text-amber-800 font-medium mt-0.5">${escapeHtml(pre.instruction)}</div>
                                <div class="text-[10px] text-slate-500">Valid until ${formatDisplayTime(pre.valid_until)}</div>
                            </div>
                            <button type="button" onclick="cancelDeliveryPreapproval(${pre.id})" class="text-xs text-rose-600 hover:text-rose-800 font-bold p-1 rounded hover:bg-rose-50 cursor-pointer" title="Cancel instruction">✕</button>
                        </div>
                    `).join('');
                }
            } catch (e) {}
        }

        function formatDisplayTime(dateStr) {
            return window.formatDisplayTime ? window.formatDisplayTime(dateStr) : dateStr;
        }

        // --- VISITOR HISTORY FILTER ---
        function filterVisitorHistoryTable() {
            const input = document.getElementById('visitor-search-input');
            const query = input ? input.value.toLowerCase().trim() : '';
            const rows = document.querySelectorAll('.visitor-record-row');
            rows.forEach(row => {
                const name = row.querySelector('.visitor-name-cell')?.textContent.toLowerCase() || '';
                const purpose = row.querySelector('.visitor-purpose-cell')?.textContent.toLowerCase() || '';
                if (name.includes(query) || purpose.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // --- VEHICLE MANAGEMENT UTILITIES ---
        function openVehiclesModal() {
            document.getElementById('vehicles-modal').classList.remove('hidden');
        }

        function closeVehiclesModal() {
            document.getElementById('vehicles-modal').classList.add('hidden');
        }

        async function registerVehicle() {
            const btn = document.getElementById('btn-submit-veh');
            btn.innerText = 'Registering...';
            btn.disabled = true;

            const vehicleType = document.getElementById('veh_type').value;
            const vehicleNumber = document.getElementById('veh_number').value.trim();
            const parkingSlot = document.getElementById('veh_slot').value.trim();

            try {
                const response = await fetch(appPath('/api/resident_vehicles.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        vehicle_type: vehicleType,
                        vehicle_number: vehicleNumber,
                        parking_slot: parkingSlot,
                        csrf_token: csrfToken
                    })
                });

                const result = await response.json();
                if (response.ok && result.status === 'success') {
                    showToast('🚗 Vehicle registered successfully!', 'success');
                    document.getElementById('vehicle-form').reset();
                    reloadVehiclesList();
                } else {
                    showToast(result.message || 'Failed to register vehicle.', 'error');
                }
            } catch (e) {
                showToast('Network error registering vehicle.', 'error');
            } finally {
                btn.innerText = '+ Register Vehicle';
                btn.disabled = false;
            }
        }

        async function deleteVehicle(vehicleId) {
            if (!confirm('Are you sure you want to remove this vehicle?')) return;

            try {
                const response = await fetch(appPath('/api/resident_vehicles.php'), {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        vehicle_id: vehicleId,
                        csrf_token: csrfToken
                    })
                });

                const result = await response.json();
                if (response.ok && result.status === 'success') {
                    const row = document.getElementById(`veh-row-${vehicleId}`);
                    if (row) row.remove();
                    showToast('Vehicle removed.', 'info');
                    reloadVehiclesList();
                } else {
                    showToast(result.message || 'Failed to remove vehicle.', 'error');
                }
            } catch (e) {
                showToast('Network error removing vehicle.', 'error');
            }
        }

        async function reloadVehiclesList() {
            try {
                const response = await fetch(appPath('/api/resident_vehicles.php'));
                const result = await response.json();
                const container = document.getElementById('vehicles-list-container');
                if (response.ok && result.status === 'success') {
                    if (result.vehicles.length === 0) {
                        container.innerHTML = `<div id="empty-vehicles-msg" class="p-3 text-center text-xs text-slate-400 bg-slate-50 rounded-lg border border-dashed border-slate-200">No vehicles registered yet. Add your car or bike above.</div>`;
                        return;
                    }
                    container.innerHTML = result.vehicles.map(v => `
                        <div id="veh-row-${v.id}" class="flex items-center justify-between p-3 bg-white border border-slate-200 rounded-xl shadow-xs">
                            <div class="flex items-center gap-3">
                                <span class="text-2xl">${v.vehicle_type === '2_wheeler' ? '🛵' : '🚗'}</span>
                                <div>
                                    <div class="text-xs font-black text-slate-900 tracking-wider">${escapeHtml(v.vehicle_number)}</div>
                                    <div class="text-[11px] text-slate-500">
                                        ${v.vehicle_type === '2_wheeler' ? '2-Wheeler' : '4-Wheeler'} 
                                        • Slot: <span class="font-semibold text-brand-900">${escapeHtml(v.parking_slot || 'Unassigned')}</span>
                                    </div>
                                </div>
                            </div>
                            <button type="button" onclick="deleteVehicle(${v.id})" class="text-xs text-rose-600 hover:text-rose-800 font-bold p-1.5 rounded-lg hover:bg-rose-50 transition cursor-pointer" title="Remove Vehicle">
                                Remove
                            </button>
                        </div>
                    `).join('');
                }
            } catch (e) {}
        }

        // --- GATE PARCEL LOCKER UTILITIES ---
        function openParcelsModal() {
            document.getElementById('parcels-modal').classList.remove('hidden');
        }

        function closeParcelsModal() {
            document.getElementById('parcels-modal').classList.add('hidden');
        }
    </script>
</body>
</html>
