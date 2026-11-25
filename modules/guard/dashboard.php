<?php
// /society_app/modules/guard/dashboard.php
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['logged_in']) || !hasRole(4)) {
    header('Location: ' . appBasePath() . '/login');
    exit();
}
$csrfToken = generateCsrfToken();
$base = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');

$residents = [];
try {
    $resStmt = $conn->prepare(
        "SELECT u.id, uu.unit_number, p.first_name, p.last_name
         FROM sms_users u
         INNER JOIN sms_user_profiles p ON u.id = p.user_id
         LEFT JOIN sms_user_units uu ON u.id = uu.user_id AND uu.is_current_occupant = 1
         WHERE u.society_id = :society_id AND u.role_id = 3 AND u.status = 'active'
         ORDER BY uu.unit_number ASC"
    );
    $resStmt->execute([':society_id' => (int)$_SESSION['society_id']]);
    $residents = $resStmt->fetchAll();
} catch (PDOException $e) {
    error_log('Guard residents lookup error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="app-base" content="<?php echo $base; ?>">
    <title>Gatekeeper Terminal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script>
    <script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans flex flex-col min-h-screen">

    <nav class="bg-slate-900 shadow-md border-b border-slate-800 sticky top-0 z-50" style="padding-top: env(safe-area-inset-top, 0px);">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between h-16 items-center">
            <span class="font-serif text-xl font-semibold text-white tracking-wide">Gatekeeper Terminal</span>
            <div class="flex items-center gap-3">
                <a href="<?php echo $base; ?>/download" class="text-xs font-bold text-slate-300 hover:text-white px-2.5 py-1 rounded bg-white/10 hover:bg-white/20 transition-colors flex items-center gap-1" title="Download Mobile App">
                    <span>📱</span> App
                </a>
                <div class="text-right">
                    <div class="text-sm font-medium text-white"><?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Guard', ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="text-xs text-slate-400">On Duty</div>
                </div>
                <form action="<?php echo $base; ?>/api/logout.php" method="POST" class="m-0">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" aria-label="Log Out" title="Log Out" class="p-2 text-slate-400 hover:text-white rounded-lg hover:bg-white/10 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8">

        <!-- Live Emergency SOS Banner for Guards -->
        <div id="guard-emergency-banner" class="hidden mb-6 bg-rose-600 text-white p-5 rounded-2xl shadow-xl border-2 border-rose-700 animate-pulse flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <span class="text-4xl animate-bounce">🚨</span>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="bg-white text-rose-700 text-xs font-black px-2 py-0.5 rounded-full uppercase tracking-wider">ACTIVE EMERGENCY</span>
                        <span class="text-xs text-rose-100 font-mono" id="emergency-time-stamp">Just now</span>
                    </div>
                    <h3 class="text-xl font-black tracking-tight mt-1" id="emergency-unit-title">EMERGENCY AT FLAT 101</h3>
                    <p class="text-sm text-rose-100 font-medium" id="emergency-details-desc">Medical Emergency • Resident John • Phone: 9876543212</p>
                    <p class="text-xs text-rose-200 mt-0.5 italic" id="emergency-notes-text"></p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" onclick="acknowledgeGuardEmergency()" id="btn-ack-emergency" class="px-5 py-2.5 bg-white hover:bg-rose-50 text-rose-700 font-black text-xs rounded-xl shadow-lg transition cursor-pointer uppercase tracking-wider">
                    ✓ Acknowledge & Dispatch
                </button>
            </div>
        </div>

        <!-- Gate Traffic KPI Summary Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Entries Today</span>
                <div class="text-2xl font-black text-slate-900 mt-1" id="stat-entries-today">0</div>
            </div>
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Currently Inside</span>
                <div class="text-2xl font-black text-emerald-700 mt-1" id="stat-currently-inside">0</div>
            </div>
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Exited</span>
                <div class="text-2xl font-black text-slate-600 mt-1" id="stat-exited-count">0</div>
            </div>
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Overstay / Alerts</span>
                <div class="text-2xl font-black text-rose-600 mt-1" id="stat-overstay-count">0</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <div class="lg:col-span-1 space-y-6">

                <!-- Vehicle License Plate Lookup Card -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">🚗</span>
                            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Vehicle Plate Lookup</h2>
                        </div>
                        <span class="text-[10px] bg-blue-50 text-blue-800 font-bold px-2 py-0.5 rounded border border-blue-200">Resident & Parking</span>
                    </div>
                    <p class="text-xs text-slate-500 mb-3">Scan or type vehicle plate number to verify resident ownership and assigned parking slot.</p>
                    <div class="flex gap-2 mb-2">
                        <input type="text" id="guard_plate_input" onkeydown="if(event.key==='Enter') lookupVehiclePlate();" class="w-full px-3 py-2 text-xs uppercase font-black tracking-widest bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:border-brand-900" placeholder="e.g. MH12AB1234">
                        <button type="button" onclick="lookupVehiclePlate()" id="btn-plate-lookup" class="px-3.5 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-lg transition cursor-pointer">Lookup</button>
                    </div>

                    <!-- Plate Result Display -->
                    <div id="plate-result-box" class="hidden p-3 rounded-xl border text-xs"></div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-bold text-slate-900 font-serif">New Visitor Entry</h2>
                        <button type="button" onclick="openLogParcelModal()" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-teal-50 hover:bg-teal-100 text-teal-900 border border-teal-200 flex items-center gap-1 cursor-pointer" title="Log parcel at gate locker">
                            <span>📦 Log Parcel</span>
                        </button>
                    </div>

                    <div class="mb-6 p-4 bg-brand-50 border border-brand-200 rounded-lg flex gap-2">
                        <input type="text" id="fast_track_pin" maxlength="6" inputmode="numeric" aria-label="6-digit guest pass PIN" class="w-full px-4 py-2 bg-white rounded text-center tracking-[0.4em] font-bold border border-brand-300 focus:ring-2 focus:border-brand-900" placeholder="6-DIGIT PIN">
                        <button type="button" id="btn-verify-pin" onclick="verifyFastTrackPin()" class="px-4 py-2 bg-brand-900 text-white font-bold rounded shadow hover:bg-brand-800 transition-colors">Verify</button>
                    </div>
                    <div id="pin-verified-banner" class="hidden mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-xs text-emerald-800 font-medium flex justify-between items-center">
                        <span id="pin-verified-text">Pre-approved pass active</span>
                        <button type="button" onclick="clearVerifiedPin()" class="text-emerald-700 underline font-semibold ml-2">Clear</button>
                    </div>
                    <hr class="mb-4 border-slate-100">

                    <form id="visitor-form" onsubmit="event.preventDefault(); logVisitor();">
                        <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" id="verified_pin_code" value="">
                        <input type="hidden" id="verified_resident_id" value="">

                        <div class="space-y-4">
                            <div>
                                <span class="block text-sm font-medium text-slate-700 mb-1">Visitor Photo</span>
                                <div class="relative bg-slate-100 rounded-lg overflow-hidden border border-slate-300 aspect-video flex flex-col items-center justify-center">
                                    <video id="camera-stream" autoplay playsinline class="absolute inset-0 w-full h-full object-cover hidden"></video>
                                    <img id="photo-preview" alt="Captured visitor photo" class="absolute inset-0 w-full h-full object-cover hidden" />

                                    <div id="camera-prompt-container" class="z-10 flex flex-col items-center gap-2 p-3 text-center">
                                        <button type="button" id="start-cam-btn" onclick="startCamera()" class="bg-brand-900 hover:bg-brand-800 text-white px-4 py-2.5 rounded-lg shadow text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer">
                                            <span>📹</span> Start Live Camera
                                        </button>
                                        <button type="button" onclick="triggerNativeCamera()" class="bg-white hover:bg-slate-50 text-slate-800 border border-slate-300 px-3.5 py-1.5 rounded-lg shadow-2xs text-xs font-bold transition flex items-center gap-1.5 cursor-pointer">
                                            <span>📸</span> Snap with Phone Camera
                                        </button>
                                    </div>

                                    <button type="button" id="capture-btn" onclick="capturePhoto()" class="hidden z-10 absolute bottom-3 bg-brand-900 hover:bg-brand-800 text-white px-5 py-2.5 rounded-lg shadow text-xs font-bold flex items-center gap-1.5 cursor-pointer">
                                        <span>📷</span> Take Photo
                                    </button>
                                    <button type="button" id="retake-btn" onclick="retakePhoto()" class="hidden z-10 absolute bottom-3 bg-slate-800 hover:bg-slate-700 text-white px-5 py-2 rounded-lg shadow text-xs font-bold flex items-center gap-1.5 cursor-pointer">
                                        <span>🔄</span> Retake Photo
                                    </button>
                                </div>
                                <input type="file" id="camera-file-input" accept="image/*" capture="environment" onchange="handleNativePhotoCapture(event)" class="hidden">
                                <input type="hidden" id="photo_base64">
                                <canvas id="camera-canvas" class="hidden"></canvas>
                            </div>

                            <div>
                                <label for="resident_search" class="block text-sm font-medium text-slate-700 mb-1">Destination Unit</label>
                                <input type="text" id="resident_search" list="unit-list" required oninput="onResidentSearchInput()" class="w-full px-4 py-2 bg-white rounded-lg border border-slate-300 focus:ring-2 focus:border-brand-900" placeholder="Type unit number or name...">
                                <datalist id="unit-list">
                                    <?php foreach ($residents as $res): ?>
                                        <option data-id="<?php echo (int)$res['id']; ?>" value="Unit <?php echo htmlspecialchars((string)($res['unit_number'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars(trim($res['first_name'] . ' ' . $res['last_name']), ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Quick Company Tags</label>
                                <div class="flex flex-wrap gap-1.5">
                                    <button type="button" onclick="selectCompanyTag('Swiggy', 'Delivery')" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-200 transition cursor-pointer">🍕 Swiggy</button>
                                    <button type="button" onclick="selectCompanyTag('Zomato', 'Delivery')" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-900 border border-rose-200 transition cursor-pointer">🍔 Zomato</button>
                                    <button type="button" onclick="selectCompanyTag('Amazon', 'Delivery')" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-yellow-50 hover:bg-yellow-100 text-yellow-900 border border-yellow-200 transition cursor-pointer">📦 Amazon</button>
                                    <button type="button" onclick="selectCompanyTag('Blinkit', 'Delivery')" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-lime-50 hover:bg-lime-100 text-lime-900 border border-lime-200 transition cursor-pointer">⚡ Blinkit</button>
                                    <button type="button" onclick="selectCompanyTag('Zepto', 'Delivery')" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-purple-50 hover:bg-purple-100 text-purple-900 border border-purple-200 transition cursor-pointer">🛒 Zepto</button>
                                    <button type="button" onclick="selectCompanyTag('Flipkart', 'Delivery')" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-900 border border-blue-200 transition cursor-pointer">🛍️ Flipkart</button>
                                    <button type="button" onclick="selectCompanyTag('Cab', 'Cab')" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-300 transition cursor-pointer">🚕 Cab</button>
                                </div>
                            </div>

                            <div>
                                <label for="visitor_name" class="block text-sm font-medium text-slate-700 mb-1">Visitor Name</label>
                                <input type="text" id="visitor_name" required class="w-full px-4 py-2 bg-white rounded-lg border border-slate-300 focus:ring-2 focus:border-brand-900">
                            </div>
                            <div>
                                <label for="phone_number" class="block text-sm font-medium text-slate-700 mb-1">Phone Number</label>
                                <input type="tel" id="phone_number" required class="w-full px-4 py-2 bg-white rounded-lg border border-slate-300 focus:ring-2 focus:border-brand-900">
                            </div>
                            <div>
                                <label for="purpose" class="block text-sm font-medium text-slate-700 mb-1">Purpose</label>
                                <select id="purpose" required class="w-full px-4 py-2 bg-white rounded-lg border border-slate-300 focus:ring-2 focus:border-brand-900">
                                    <option value="Delivery">Delivery</option>
                                    <option value="Guest">Guest</option>
                                    <option value="Service">Service / Repair</option>
                                    <option value="Cab">Cab</option>
                                </select>
                            </div>

                            <button type="submit" id="btn-submit" class="w-full px-6 py-2.5 text-sm font-bold text-white bg-brand-900 hover:bg-brand-800 rounded-lg shadow-sm mt-4 cursor-pointer">Log Entry</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="lg:col-span-2">
                <div class="flex items-center gap-2 mb-4">
                    <button type="button" id="tab-btn-visitors" onclick="switchGuardTab('visitors')" class="px-4 py-2 bg-slate-900 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center gap-1.5 cursor-pointer">
                        <span>👥 Visitors Log</span>
                    </button>
                    <button type="button" id="tab-btn-parcels" onclick="switchGuardTab('parcels')" class="px-4 py-2 bg-white text-slate-700 hover:bg-slate-100 border border-slate-200 font-bold text-xs rounded-xl shadow-2xs transition flex items-center gap-1.5 cursor-pointer">
                        <span>📦 Parcel Locker</span>
                        <span id="guard-parcels-badge" class="bg-amber-100 text-amber-900 text-[10px] font-black px-1.5 py-0.2 rounded-full">0</span>
                    </button>
                </div>

                <!-- Tab 1: Visitors Log -->
                <div id="guard-tab-visitors" class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="p-4 sm:p-6 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900 font-serif">Today's Visitors</h2>
                            <span class="text-xs text-slate-400">Live gate log • Auto-refreshes every 5s</span>
                        </div>
                        <div class="w-full sm:w-64">
                            <input type="text" id="visitor-search-input" oninput="filterVisitors()" placeholder="Search visitor, unit, phone..." class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-900">
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm whitespace-nowrap">
                            <thead class="bg-slate-50 text-slate-600 font-medium border-b border-slate-200">
                                <tr>
                                    <th class="px-6 py-4">Visitor</th>
                                    <th class="px-6 py-4">Destination</th>
                                    <th class="px-6 py-4">Entry</th>
                                    <th class="px-6 py-4">Status / Action</th>
                                </tr>
                            </thead>
                            <tbody id="visitors-tbody"><tr><td colspan="4" class="px-6 py-12 text-center text-slate-500">Loading...</td></tr></tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab 2: Parcel Locker -->
                <div id="guard-tab-parcels" class="hidden bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="p-4 sm:p-6 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900 font-serif">Gate Parcel Locker</h2>
                            <span class="text-xs text-slate-400">Deliveries held securely at gate for residents</span>
                        </div>
                        <button type="button" onclick="openLogParcelModal()" class="px-3.5 py-1.5 bg-brand-900 hover:bg-brand-800 text-white font-bold text-xs rounded-lg shadow-sm transition flex items-center gap-1.5 cursor-pointer">
                            <span>+ Log Received Parcel</span>
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm whitespace-nowrap">
                            <thead class="bg-slate-50 text-slate-600 font-medium border-b border-slate-200">
                                <tr>
                                    <th class="px-6 py-4">Destination Flat</th>
                                    <th class="px-6 py-4">Courier & Recipient</th>
                                    <th class="px-6 py-4">Received Time</th>
                                    <th class="px-6 py-4">Action</th>
                                </tr>
                            </thead>
                            <tbody id="guard-parcels-tbody"><tr><td colspan="4" class="px-6 py-12 text-center text-slate-500">Loading parcels...</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Guard Log Parcel Modal -->
    <div id="log-parcel-modal" role="dialog" aria-modal="true" aria-labelledby="parcel-modal-title" onclick="if (event.target === this) closeLogParcelModal()" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex justify-center items-center p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-100 animate-in fade-in zoom-in-95 duration-150">
            <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2">
                    <span class="text-xl">📦</span>
                    <h3 id="parcel-modal-title" class="text-lg font-bold text-slate-900 font-serif">Log Gate Parcel</h3>
                </div>
                <button type="button" onclick="closeLogParcelModal()" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <p class="text-xs text-slate-500 mb-4">Log courier package received for a resident. The resident will receive an immediate in-app pickup alert.</p>

            <form id="parcel-form" onsubmit="event.preventDefault(); submitLogParcel();">
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Destination Flat</label>
                        <select id="parcel_unit" required class="w-full px-3 py-2 text-xs bg-white border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-900 font-semibold text-slate-800">
                            <option value="">Select Flat...</option>
                            <?php foreach ($residents as $res): ?>
                                <option value="<?php echo htmlspecialchars((string)$res['unit_number'], ENT_QUOTES, 'UTF-8'); ?>">
                                    Flat <?php echo htmlspecialchars((string)$res['unit_number'], ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars(trim($res['first_name'] . ' ' . $res['last_name']), ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Courier Company</label>
                        <div class="grid grid-cols-3 gap-1.5 mb-2">
                            <button type="button" onclick="setCourierName('Amazon')" class="px-2 py-1 bg-slate-100 hover:bg-amber-100 text-slate-800 text-xs font-semibold rounded-lg border border-slate-200">Amazon</button>
                            <button type="button" onclick="setCourierName('Flipkart')" class="px-2 py-1 bg-slate-100 hover:bg-amber-100 text-slate-800 text-xs font-semibold rounded-lg border border-slate-200">Flipkart</button>
                            <button type="button" onclick="setCourierName('BlueDart')" class="px-2 py-1 bg-slate-100 hover:bg-amber-100 text-slate-800 text-xs font-semibold rounded-lg border border-slate-200">BlueDart</button>
                            <button type="button" onclick="setCourierName('Swiggy Instamart')" class="px-2 py-1 bg-slate-100 hover:bg-amber-100 text-slate-800 text-xs font-semibold rounded-lg border border-slate-200">Swiggy</button>
                            <button type="button" onclick="setCourierName('Zomato')" class="px-2 py-1 bg-slate-100 hover:bg-amber-100 text-slate-800 text-xs font-semibold rounded-lg border border-slate-200">Zomato</button>
                            <button type="button" onclick="setCourierName('Delhivery')" class="px-2 py-1 bg-slate-100 hover:bg-amber-100 text-slate-800 text-xs font-semibold rounded-lg border border-slate-200">Delhivery</button>
                        </div>
                        <input type="text" id="parcel_courier" required class="w-full px-3 py-2 text-xs bg-white border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-900" placeholder="or type courier company...">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Recipient Name (Optional)</label>
                        <input type="text" id="parcel_recipient" class="w-full px-3 py-2 text-xs bg-white border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-900" placeholder="e.g. John Resident">
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button" onclick="closeLogParcelModal()" class="w-1/3 py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg transition cursor-pointer">Cancel</button>
                        <button type="submit" id="btn-submit-parcel" class="w-2/3 py-2 px-3 bg-brand-900 hover:bg-brand-800 text-white text-xs font-bold rounded-lg shadow transition cursor-pointer">
                            Hold at Gate Locker
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        const csrfToken = document.getElementById('csrf_token').value;
        let stream = null;
        let visitorRefreshTimer = null;

        document.addEventListener('DOMContentLoaded', () => {
            fetchVisitors();
            startVisitorPolling();
            fetchGuardParcels();
            setInterval(fetchGuardParcels, 8000);
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    stopVisitorPolling();
                } else {
                    fetchVisitors();
                    startVisitorPolling();
                    fetchGuardParcels();
                }
            });
        });

        function startVisitorPolling() {
            if (!visitorRefreshTimer) {
                visitorRefreshTimer = setInterval(fetchVisitors, 5000);
            }
        }

        function stopVisitorPolling() {
            if (visitorRefreshTimer) {
                clearInterval(visitorRefreshTimer);
                visitorRefreshTimer = null;
            }
        }

        function onResidentSearchInput() {
            // Clear bound PIN if guard manually changes unit
            if (document.getElementById('verified_pin_code').value) {
                clearVerifiedPin();
            }
        }

        function clearVerifiedPin() {
            document.getElementById('verified_pin_code').value = '';
            document.getElementById('verified_resident_id').value = '';
            document.getElementById('pin-verified-banner').classList.add('hidden');
        }

        async function verifyFastTrackPin() {
            const pinInput = document.getElementById('fast_track_pin');
            const pinCode = pinInput.value.trim();

            if (!/^\d{6}$/.test(pinCode)) {
                showToast('Please enter a valid 6-digit PIN.', 'warning');
                return;
            }

            try {
                const response = await fetch(appPath('/api/guard_verify_pin.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ pin_code: pinCode, csrf_token: csrfToken })
                });
                const result = await response.json();

                if (response.ok && result.status === 'success') {
                    const data = result.data;
                    document.getElementById('visitor_name').value = data.visitor_name || '';
                    document.getElementById('purpose').value = 'Guest';
                    document.getElementById('resident_search').value = `Unit ${data.unit_number || 'N/A'} - ${data.first_name || ''} ${data.last_name || ''}`.trim();
                    document.getElementById('verified_pin_code').value = data.pin_code || pinCode;
                    document.getElementById('verified_resident_id').value = data.resident_id;
                    document.getElementById('pin-verified-text').textContent = `Pre-approved PIN ${pinCode} for Unit ${data.unit_number || 'N/A'} — will auto-enter on submit.`;
                    document.getElementById('pin-verified-banner').classList.remove('hidden');
                    pinInput.value = '';
                    showToast('PIN verified! Enter visitor phone and click Log Entry.', 'success');
                } else {
                    showToast(result.message || 'Invalid PIN.', 'error');
                }
            } catch (error) {
                showToast('Error verifying PIN.', 'error');
            }
        }

        function triggerNativeCamera() {
            // 1. If running inside native Android app, use direct camera intent
            if (window.SocietyHubNative && typeof window.SocietyHubNative.openNativeCamera === 'function') {
                window.SocietyHubNative.openNativeCamera();
                return;
            }

            // 2. Browser fallback: trigger file input with capture="environment"
            const input = document.getElementById('camera-file-input');
            if (input) {
                input.value = '';
                input.click();
            }
        }

        // Global callback invoked directly by native Android app when photo is captured
        window.handleNativePhotoCaptured = function(base64DataUrl) {
            document.getElementById('photo_base64').value = base64DataUrl;

            const preview = document.getElementById('photo-preview');
            preview.src = base64DataUrl;
            preview.classList.remove('hidden');

            const video = document.getElementById('camera-stream');
            video.classList.add('hidden');
            if (stream) {
                stream.getTracks().forEach(t => t.stop());
                stream = null;
            }

            const promptBox = document.getElementById('camera-prompt-container');
            if (promptBox) promptBox.classList.add('hidden');
            document.getElementById('capture-btn').classList.add('hidden');
            document.getElementById('retake-btn').classList.remove('hidden');

            showToast('Visitor photo attached successfully ✓', 'success');
        };

        async function startCamera() {
            const promptBox = document.getElementById('camera-prompt-container');
            const video = document.getElementById('camera-stream');

            // 1. If running inside native Android app, directly trigger crisp native camera intent
            if (window.SocietyHubNative && typeof window.SocietyHubNative.openNativeCamera === 'function') {
                window.SocietyHubNative.openNativeCamera();
                return;
            }

            // 2. Try browser WebRTC camera
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                try {
                    let mediaStream = null;
                    try {
                        mediaStream = await navigator.mediaDevices.getUserMedia({
                            video: { facingMode: { ideal: 'environment' }, width: { ideal: 640 }, height: { ideal: 480 } }
                        });
                    } catch (e1) {
                        mediaStream = await navigator.mediaDevices.getUserMedia({ video: true });
                    }

                    if (mediaStream) {
                        stream = mediaStream;
                        video.srcObject = stream;
                        video.classList.remove('hidden');
                        if (promptBox) promptBox.classList.add('hidden');
                        document.getElementById('capture-btn').classList.remove('hidden');
                        document.getElementById('photo-preview').classList.add('hidden');
                        return;
                    }
                } catch (err) {
                    console.warn('Live viewfinder stream error:', err);
                    triggerNativeCamera();
                    return;
                }
            }

            triggerNativeCamera();
        }

        function handleNativePhotoCapture(event) {
            const file = event.target.files && event.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    const canvas = document.getElementById('camera-canvas');
                    const maxW = 480;
                    const scale = img.width > maxW ? (maxW / img.width) : 1;
                    canvas.width = Math.max(1, Math.round(img.width * scale));
                    canvas.height = Math.max(1, Math.round(img.height * scale));

                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

                    const base64Data = canvas.toDataURL('image/jpeg', 0.82);
                    document.getElementById('photo_base64').value = base64Data;

                    const preview = document.getElementById('photo-preview');
                    preview.src = base64Data;
                    preview.classList.remove('hidden');

                    const video = document.getElementById('camera-stream');
                    video.classList.add('hidden');
                    if (stream) {
                        stream.getTracks().forEach(t => t.stop());
                        stream = null;
                    }

                    const promptBox = document.getElementById('camera-prompt-container');
                    if (promptBox) promptBox.classList.add('hidden');
                    document.getElementById('capture-btn').classList.add('hidden');
                    document.getElementById('retake-btn').classList.remove('hidden');

                    showToast('Visitor photo attached successfully ✓', 'success');
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }

        function capturePhoto() {
            const video = document.getElementById('camera-stream');
            const canvas = document.getElementById('camera-canvas');
            const maxW = 480;
            const scale = video.videoWidth > maxW ? (maxW / video.videoWidth) : 1;
            canvas.width = Math.max(1, Math.round(video.videoWidth * scale));
            canvas.height = Math.max(1, Math.round(video.videoHeight * scale));
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

            const base64Data = canvas.toDataURL('image/jpeg', 0.78);
            document.getElementById('photo_base64').value = base64Data;

            document.getElementById('photo-preview').src = base64Data;
            document.getElementById('photo-preview').classList.remove('hidden');
            video.classList.add('hidden');
            if (stream) {
                stream.getTracks().forEach(t => t.stop());
                stream = null;
            }
            document.getElementById('capture-btn').classList.add('hidden');
            document.getElementById('retake-btn').classList.remove('hidden');
            showToast('Visitor photo captured ✓', 'success');
        }

        function retakePhoto() {
            document.getElementById('photo_base64').value = '';
            document.getElementById('photo-preview').classList.add('hidden');
            const promptBox = document.getElementById('camera-prompt-container');
            if (promptBox) promptBox.classList.remove('hidden');
            document.getElementById('camera-stream').classList.add('hidden');
            document.getElementById('capture-btn').classList.add('hidden');
            document.getElementById('retake-btn').classList.add('hidden');
            const fileInput = document.getElementById('camera-file-input');
            if (fileInput) fileInput.value = '';
        }

        let rawVisitorsData = [];

        function selectCompanyTag(name, category) {
            const vNameInput = document.getElementById('visitor_name');
            const purposeInput = document.getElementById('purpose');

            vNameInput.value = `${name} Partner`;
            purposeInput.value = category;
            vNameInput.focus();
            vNameInput.select();
        }

        function filterVisitors() {
            renderVisitorsTable(rawVisitorsData);
        }

        async function fetchVisitors() {
            try {
                const response = await fetch(appPath('/api/guard_get_visitors.php'));
                const result = await response.json();

                if (response.ok && result.status === 'success') {
                    rawVisitorsData = result.data || [];
                    updateGateKpis(rawVisitorsData);
                    renderVisitorsTable(rawVisitorsData);
                }
            } catch (error) {
                const tbody = document.getElementById('visitors-tbody');
                if (tbody) tbody.innerHTML = `<tr><td colspan="4" class="text-center text-red-500 py-8">Error loading log.</td></tr>`;
            }
        }

        function updateGateKpis(visitors) {
            const entriesToday = visitors.length;
            const insideCount = visitors.filter(v => v.status === 'Entered').length;
            const exitedCount = visitors.filter(v => v.status === 'Exited').length;

            const now = Date.now();
            let overstayCount = 0;
            visitors.forEach(v => {
                if (v.status === 'Entered') {
                    const dt = parseMySqlDate(v.entry_time);
                    if (dt && (now - dt.getTime()) > 45 * 60000) {
                        overstayCount++;
                    }
                }
            });

            const statEntries = document.getElementById('stat-entries-today');
            const statInside = document.getElementById('stat-currently-inside');
            const statExited = document.getElementById('stat-exited-count');
            const statOverstay = document.getElementById('stat-overstay-count');

            if (statEntries) statEntries.innerText = entriesToday;
            if (statInside) statInside.innerText = insideCount;
            if (statExited) statExited.innerText = exitedCount;
            if (statOverstay) statOverstay.innerText = overstayCount;
        }

        function renderVisitorsTable(visitors) {
            const tbody = document.getElementById('visitors-tbody');
            if (!tbody) return;

            const searchInput = document.getElementById('visitor-search-input');
            const query = (searchInput?.value || '').toLowerCase().trim();

            const filtered = query ? visitors.filter(v => {
                return (v.visitor_name && v.visitor_name.toLowerCase().includes(query)) ||
                       (v.unit_number && v.unit_number.toLowerCase().includes(query)) ||
                       (v.phone_number && v.phone_number.includes(query)) ||
                       (v.purpose && v.purpose.toLowerCase().includes(query));
            }) : visitors;

            if (filtered.length === 0) {
                tbody.innerHTML = `<tr><td colspan="4" class="text-center text-slate-500 py-8">${query ? 'No visitors matching "' + escapeHtml(query) + '"' : 'No visitors logged today.'}</td></tr>`;
                return;
            }

            const now = Date.now();
            filtered.sort((a, b) => {
                if (a.status === 'Entered' && b.status !== 'Entered') return -1;
                if (a.status !== 'Entered' && b.status === 'Entered') return 1;
                return (parseMySqlDate(b.entry_time)?.getTime() || 0) - (parseMySqlDate(a.entry_time)?.getTime() || 0);
            });

            tbody.innerHTML = filtered.map(vis => {
                let actionCell = '';
                let rowClass = 'hover:bg-slate-50 border-b border-slate-100 transition-colors';
                let alertBadge = '';

                const safeName = escapeHtml(vis.visitor_name || 'Guest');
                const initial = (vis.visitor_name || '?').charAt(0).toUpperCase();
                const photoSrc = vis.photo_url || '';
                const avatarHTML = photoSrc
                    ? `<img src="${escapeHtml(photoSrc)}" class="w-10 h-10 min-w-[40px] rounded-full object-cover border border-slate-200 shadow-sm cursor-pointer hover:ring-2 hover:ring-brand-500 transition-all" alt="${safeName}" onclick="openPhotoLightbox('${escapeHtml(photoSrc)}', '${safeName}', '${escapeHtml(vis.purpose || 'Visitor')} | Flat ${escapeHtml(vis.unit_number || 'Unit')}')" onerror="this.onerror=null; this.outerHTML='<div class=\\\'w-10 h-10 min-w-[40px] rounded-full bg-slate-200 flex items-center justify-center text-slate-500 font-bold shadow-sm\\\'>${escapeHtml(initial)}</div>';">`
                    : `<div class="w-10 h-10 min-w-[40px] rounded-full bg-slate-200 flex items-center justify-center text-slate-500 font-bold shadow-sm">${escapeHtml(initial)}</div>`;

                if (vis.status === 'Entered') {
                    actionCell = `<button type="button" onclick="markExit(${Number(vis.id)}, this)" class="px-4 py-1.5 text-xs font-bold text-white bg-slate-800 hover:bg-slate-700 shadow-sm transition-all rounded cursor-pointer">Mark Exit</button>`;

                    if (vis.purpose === 'Delivery' || vis.purpose === 'Cab' || vis.purpose === 'Service') {
                        const entryDt = parseMySqlDate(vis.entry_time);
                        const minutesPassed = entryDt ? Math.floor((now - entryDt.getTime()) / 60000) : 0;
                        if (minutesPassed > 45) {
                            rowClass = 'bg-red-50 hover:bg-red-100 border-b border-red-200 transition-colors';
                            alertBadge = `<span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-red-600 text-white animate-pulse">OVERSTAY: ${minutesPassed} mins</span>`;
                        }
                    }
                } else if (vis.status === 'Pending') {
                    actionCell = `<span class="bg-amber-50 text-amber-800 px-2.5 py-1 rounded-full text-xs font-semibold border border-amber-200">Awaiting Approval</span>`;
                } else if (vis.status === 'Denied') {
                    actionCell = `<span class="bg-red-50 text-red-700 px-2.5 py-1 rounded-full text-xs font-semibold border border-red-200">Entry Denied</span>`;
                } else {
                    actionCell = `<span class="bg-slate-100 text-slate-600 px-2.5 py-1 rounded-full text-xs border border-slate-200">Exited ${escapeHtml(formatDisplayTime(vis.exit_time))}</span>`;
                }

                return `
                    <tr class="${rowClass}">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                ${avatarHTML}
                                <div>
                                    <div class="font-bold text-slate-900">${safeName} ${alertBadge}</div>
                                    <div class="text-xs text-slate-500">${escapeHtml(vis.phone_number || 'N/A')} | ${escapeHtml(vis.purpose)}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4"><div class="font-semibold text-slate-800">Unit ${escapeHtml(vis.unit_number || 'N/A')}</div></td>
                        <td class="px-6 py-4 font-medium text-slate-900">${escapeHtml(formatDisplayTime(vis.entry_time))}</td>
                        <td class="px-6 py-4">${actionCell}</td>
                    </tr>
                `;
            }).join('');
        }

        async function logVisitor() {
            const btn = document.getElementById('btn-submit');
            btn.innerText = 'Logging...';
            btn.disabled = true;

            const verifiedResidentId = document.getElementById('verified_resident_id').value;
            const verifiedPinCode = document.getElementById('verified_pin_code').value;
            const searchVal = document.getElementById('resident_search').value;
            const options = Array.from(document.querySelectorAll('#unit-list option'));
            const matchedOption = options.find(opt => opt.value === searchVal);

            const residentId = verifiedResidentId || (matchedOption ? matchedOption.getAttribute('data-id') : '');
            if (!residentId) {
                showToast('Please select a valid unit from the list.', 'warning');
                btn.innerText = 'Log Entry';
                btn.disabled = false;
                return;
            }

            const payload = {
                resident_id: residentId,
                visitor_name: document.getElementById('visitor_name').value.trim(),
                phone_number: document.getElementById('phone_number').value.trim(),
                purpose: document.getElementById('purpose').value,
                photo_base64: document.getElementById('photo_base64').value,
                pin_code: verifiedPinCode,
                csrf_token: csrfToken
            };

            try {
                const response = await fetch(appPath('/api/guard_log_visitor.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await response.json();
                if (response.ok) {
                    document.getElementById('visitor-form').reset();
                    clearVerifiedPin();
                    retakePhoto();
                    fetchVisitors();
                    showToast(result.message || 'Visitor logged.', 'success');
                } else {
                    showToast(result.message || 'Failed to log visitor.', 'error');
                }
            } catch (error) {
                showToast('Error connecting to server.', 'error');
            } finally {
                btn.innerText = 'Log Entry';
                btn.disabled = false;
            }
        }

        async function markExit(visitorId, btn) {
            btn.innerText = '...';
            btn.disabled = true;
            try {
                const response = await fetch(appPath('/api/guard_visitor_exit.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ visitor_id: visitorId, csrf_token: csrfToken })
                });
                const result = await response.json();
                if (response.ok) {
                    fetchVisitors();
                    showToast(result.message || 'Visitor marked as exited.', 'success');
                } else {
                    showToast(result.message || 'Could not mark exit.', 'error');
                    btn.innerText = 'Mark Exit';
                    btn.disabled = false;
                }
            } catch (error) {
                showToast('Error marking exit.', 'error');
                btn.innerText = 'Mark Exit';
                btn.disabled = false;
            }
        }

        // --- LIVE EMERGENCY SOS MONITORING ---
        let activeEmergencyAlert = null;

        async function pollEmergencyAlerts() {
            try {
                const response = await fetch(appPath('/api/guard_get_emergency_alerts.php'));
                const result = await response.json();
                if (response.ok && result.status === 'success') {
                    const banner = document.getElementById('guard-emergency-banner');
                    const activeAlerts = result.data.alerts.filter(a => a.status === 'Active');
                    if (activeAlerts.length > 0) {
                        activeEmergencyAlert = activeAlerts[0];
                        document.getElementById('emergency-unit-title').textContent = `🚨 ${activeEmergencyAlert.alert_type.toUpperCase()} - FLAT ${activeEmergencyAlert.unit_number}`;
                        document.getElementById('emergency-details-desc').textContent = `Resident: ${activeEmergencyAlert.first_name || 'Resident'} • Phone: ${activeEmergencyAlert.phone_number || 'N/A'}`;
                        document.getElementById('emergency-notes-text').textContent = activeEmergencyAlert.notes ? `Note: "${activeEmergencyAlert.notes}"` : '';
                        document.getElementById('emergency-time-stamp').textContent = activeEmergencyAlert.created_at;
                        banner.classList.remove('hidden');
                        playEmergencySiren();
                    } else {
                        activeEmergencyAlert = null;
                        banner.classList.add('hidden');
                    }
                }
            } catch (e) {}
        }

        async function acknowledgeGuardEmergency() {
            if (!activeEmergencyAlert) return;
            const btn = document.getElementById('btn-ack-emergency');
            btn.innerText = 'Acknowledging...';
            btn.disabled = true;

            try {
                const response = await fetch(appPath('/api/guard_acknowledge_emergency.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        alert_id: activeEmergencyAlert.id,
                        status: 'Acknowledged',
                        csrf_token: csrfToken
                    })
                });
                const result = await response.json();
                if (response.ok) {
                    showToast('Emergency acknowledged. Guard dispatched.', 'success');
                    document.getElementById('guard-emergency-banner').classList.add('hidden');
                    pollEmergencyAlerts();
                } else {
                    showToast(result.message || 'Error acknowledging.', 'error');
                }
            } catch (e) {
                showToast('Network error.', 'error');
            } finally {
                btn.innerText = '✓ Acknowledge & Dispatch';
                btn.disabled = false;
            }
        }

        let emergencyAudioCtx = null;
        function playEmergencySiren() {
            try {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx) return;
                if (!emergencyAudioCtx) emergencyAudioCtx = new AudioCtx();
                if (emergencyAudioCtx.state === 'suspended') emergencyAudioCtx.resume();
                const osc = emergencyAudioCtx.createOscillator();
                const gain = emergencyAudioCtx.createGain();
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(800, emergencyAudioCtx.currentTime);
                osc.frequency.linearRampToValueAtTime(1400, emergencyAudioCtx.currentTime + 0.3);
                osc.frequency.linearRampToValueAtTime(800, emergencyAudioCtx.currentTime + 0.6);
                gain.gain.setValueAtTime(0.3, emergencyAudioCtx.currentTime);
                gain.gain.linearRampToValueAtTime(0.01, emergencyAudioCtx.currentTime + 0.6);
                osc.connect(gain);
                gain.connect(emergencyAudioCtx.destination);
                osc.start();
                osc.stop(emergencyAudioCtx.currentTime + 0.6);
            } catch (e) {}
        }

        setInterval(pollEmergencyAlerts, 4000);
        pollEmergencyAlerts();

        // --- VEHICLE PLATE SCANNER & LOOKUP ---
        async function lookupVehiclePlate() {
            const input = document.getElementById('guard_plate_input');
            const plate = input.value.trim();
            const resultBox = document.getElementById('plate-result-box');
            const btn = document.getElementById('btn-plate-lookup');

            if (plate.length < 3) {
                showToast('Please enter at least 3 characters of vehicle plate.', 'warning');
                return;
            }

            btn.innerText = '...';
            btn.disabled = true;

            try {
                const response = await fetch(appPath('/api/guard_lookup_vehicle.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ plate_number: plate, csrf_token: csrfToken })
                });

                const res = await response.json();
                resultBox.classList.remove('hidden');

                if (response.ok && res.status === 'success' && res.matched) {
                    const p = res.primary;
                    resultBox.className = 'mt-3 p-3 rounded-xl border border-emerald-300 bg-emerald-50 text-emerald-950';
                    resultBox.innerHTML = `
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-black text-xs uppercase tracking-wider text-emerald-800">✅ Registered Resident</span>
                            <span class="text-[10px] font-bold bg-emerald-200 text-emerald-900 px-2 py-0.5 rounded">Flat ${escapeHtml(p.unit_number)}</span>
                        </div>
                        <div class="font-black text-sm text-slate-900 tracking-wider">${escapeHtml(p.vehicle_number)} (${p.vehicle_type === '2_wheeler' ? '2-Wheeler' : '4-Wheeler'})</div>
                        <div class="text-[11px] text-slate-600 mt-0.5">Resident: <span class="font-bold text-slate-900">${escapeHtml(p.resident_name)}</span> • Slot: <span class="font-bold text-brand-900">${escapeHtml(p.parking_slot)}</span></div>
                        <div class="mt-2 pt-2 border-t border-emerald-200 flex justify-between items-center">
                            <span class="text-[10px] text-emerald-700">Resident Verified • Allow Direct Entry</span>
                            <button type="button" onclick="autoFillResidentVehicle('${escapeHtml(p.unit_number)}', '${escapeHtml(p.resident_name)}', '${escapeHtml(p.vehicle_number)}')" class="px-2 py-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[10px] rounded transition">Fill Log</button>
                        </div>
                    `;
                    showToast(`✅ Plate verified: Flat ${p.unit_number} (Slot ${p.parking_slot})`, 'success');
                } else {
                    resultBox.className = 'mt-3 p-3 rounded-xl border border-amber-300 bg-amber-50 text-amber-950';
                    resultBox.innerHTML = `
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-black text-xs uppercase tracking-wider text-amber-800">⚠️ Unregistered Vehicle</span>
                            <span class="text-[10px] font-bold bg-amber-200 text-amber-900 px-2 py-0.5 rounded">Visitor Entry</span>
                        </div>
                        <div class="text-[11px] text-amber-900 font-medium">Plate "${escapeHtml(plate)}" is not registered in society records. Please log as visitor.</div>
                    `;
                    showToast('Vehicle not in resident directory. Please register entry.', 'warning');
                }
            } catch (e) {
                showToast('Network error during vehicle lookup.', 'error');
            } finally {
                btn.innerText = 'Lookup';
                btn.disabled = false;
            }
        }

        function autoFillResidentVehicle(unit, name, plate) {
            document.getElementById('resident_search').value = `Unit ${unit} - ${name}`;
            document.getElementById('visitor_name').value = `${name} (Self)`;
            document.getElementById('purpose').value = 'Guest';
            showToast('Form pre-filled for resident vehicle.', 'info');
        }

        // --- GUARD TABS & PARCEL LOCKER ---
        function switchGuardTab(tab) {
            const tabVisitors = document.getElementById('guard-tab-visitors');
            const tabParcels = document.getElementById('guard-tab-parcels');
            const btnVisitors = document.getElementById('tab-btn-visitors');
            const btnParcels = document.getElementById('tab-btn-parcels');

            if (tab === 'visitors') {
                tabVisitors.classList.remove('hidden');
                tabParcels.classList.add('hidden');
                btnVisitors.className = 'px-4 py-2 bg-slate-900 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center gap-1.5 cursor-pointer';
                btnParcels.className = 'px-4 py-2 bg-white text-slate-700 hover:bg-slate-100 border border-slate-200 font-bold text-xs rounded-xl shadow-2xs transition flex items-center gap-1.5 cursor-pointer';
            } else {
                tabVisitors.classList.add('hidden');
                tabParcels.classList.remove('hidden');
                btnParcels.className = 'px-4 py-2 bg-slate-900 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center gap-1.5 cursor-pointer';
                btnVisitors.className = 'px-4 py-2 bg-white text-slate-700 hover:bg-slate-100 border border-slate-200 font-bold text-xs rounded-xl shadow-2xs transition flex items-center gap-1.5 cursor-pointer';
                fetchGuardParcels();
            }
        }

        async function fetchGuardParcels() {
            try {
                const response = await fetch(appPath('/api/guard_parcels.php?status=At+Gate'));
                const result = await response.json();
                const tbody = document.getElementById('guard-parcels-tbody');
                const badge = document.getElementById('guard-parcels-badge');

                if (response.ok && result.status === 'success') {
                    const parcels = result.parcels || [];
                    badge.textContent = parcels.length;

                    if (parcels.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="4" class="px-6 py-8 text-center text-slate-400 text-xs">No active packages in gate locker right now.</td></tr>`;
                        return;
                    }

                    tbody.innerHTML = parcels.map(p => `
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition">
                            <td class="px-6 py-4">
                                <span class="bg-brand-50 text-brand-900 font-black text-xs px-2.5 py-1 rounded-lg border border-brand-200">Flat ${escapeHtml(p.unit_number)}</span>
                                <div class="text-[11px] text-slate-500 mt-1">${escapeHtml(p.resident_name)}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-xs font-bold text-slate-900 flex items-center gap-1.5">
                                    <span>📦 ${escapeHtml(p.courier_company)}</span>
                                </div>
                                <div class="text-[11px] text-slate-500">For: ${escapeHtml(p.recipient_name || p.resident_name)}</div>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-500">
                                ${new Date(p.received_at.replace(' ', 'T')).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                            </td>
                            <td class="px-6 py-4">
                                <button type="button" onclick="handOverParcel(${p.id})" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-lg shadow-2xs transition cursor-pointer">
                                    ✓ Hand Over
                                </button>
                            </td>
                        </tr>
                    `).join('');
                }
            } catch (e) {}
        }

        function openLogParcelModal() {
            document.getElementById('log-parcel-modal').classList.remove('hidden');
        }

        function closeLogParcelModal() {
            document.getElementById('log-parcel-modal').classList.add('hidden');
        }

        function setCourierName(name) {
            document.getElementById('parcel_courier').value = name;
        }

        async function submitLogParcel() {
            const btn = document.getElementById('btn-submit-parcel');
            btn.innerText = 'Logging...';
            btn.disabled = true;

            const unit = document.getElementById('parcel_unit').value;
            const courier = document.getElementById('parcel_courier').value.trim();
            const recipient = document.getElementById('parcel_recipient').value.trim();

            try {
                const response = await fetch(appPath('/api/guard_parcels.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        unit_number: unit,
                        courier_company: courier,
                        recipient_name: recipient,
                        csrf_token: csrfToken
                    })
                });

                const result = await response.json();
                if (response.ok && result.status === 'success') {
                    closeLogParcelModal();
                    document.getElementById('parcel-form').reset();
                    showToast(`📦 Parcel from ${courier} logged for Flat ${unit}! Resident notified.`, 'success');
                    fetchGuardParcels();
                } else {
                    showToast(result.message || 'Failed to log parcel.', 'error');
                }
            } catch (e) {
                showToast('Network error logging parcel.', 'error');
            } finally {
                btn.innerText = 'Hold at Gate Locker';
                btn.disabled = false;
            }
        }

        async function handOverParcel(parcelId) {
            if (!confirm('Confirm parcel collected by resident?')) return;

            try {
                const response = await fetch(appPath('/api/guard_parcels.php'), {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ parcel_id: parcelId, csrf_token: csrfToken })
                });

                const result = await response.json();
                if (response.ok && result.status === 'success') {
                    showToast('Parcel marked collected by resident.', 'success');
                    fetchGuardParcels();
                } else {
                    showToast(result.message || 'Unable to update status.', 'error');
                }
            } catch (e) {
                showToast('Network error.', 'error');
            }
        }
    </script>
</body>
</html>
