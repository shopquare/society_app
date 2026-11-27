<?php
// /society_app/modules/guard/dashboard.php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/gate_helper.php';

if (!isset($_SESSION['logged_in']) || !hasRole(4)) {
    header('Location: ' . appBasePath() . '/login');
    exit();
}
$csrfToken = generateCsrfToken();
$base = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');
$authorizedGates = getGuardAuthorizedGates($conn, (int)$_SESSION['society_id'], (int)$_SESSION['user_id']);
$defaultGateId = !empty($authorizedGates) ? (int)$authorizedGates[0]['id'] : 1;

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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script>
    <link rel="stylesheet" href="<?php echo $base; ?>/assets/css/style.css">
    <script src="<?php echo $base; ?>/assets/js/app.js"></script>
    <style>
        @keyframes popupSlideIn {
            0% { transform: translateY(-30px) scale(0.92); opacity: 0; }
            60% { transform: translateY(4px) scale(1.02); opacity: 1; }
            100% { transform: translateY(0) scale(1); opacity: 1; }
        }
        .decision-popup-enter {
            animation: popupSlideIn 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans flex flex-col min-h-screen">

    <a href="#main-content" class="sg-skip-link">Skip to main content</a>
    <!-- Live Resident Approval/Denial Popup Container for Gatekeeper -->
    <div id="guard-decision-popup-container" class="fixed top-5 left-1/2 -translate-x-1/2 z-[200] w-full max-w-md px-4 flex flex-col gap-3 pointer-events-none"></div>

    <nav aria-label="Guard terminal navigation" class="bg-slate-900 shadow-md border-b border-slate-800 sticky top-0 z-50" style="padding-top: env(safe-area-inset-top, 0px);">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between h-16 items-center gap-2">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="w-8 h-8 rounded-lg bg-brand-900 border border-white/10 flex items-center justify-center shrink-0 text-sm" aria-hidden="true">🛡️</span>
                <span class="font-serif text-base sm:text-xl font-semibold text-white tracking-wide truncate">Gatekeeper Terminal</span>
            </div>
            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                <button type="button" onclick="testDecisionPopup('approved')" class="text-xs font-bold text-slate-300 hover:text-white px-2.5 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 transition-colors hidden sm:flex items-center gap-1 cursor-pointer" title="Test Resident Decision Popup">
                    <span>🔔</span> Test Alert
                </button>
                <a href="<?php echo $base; ?>/download" class="text-xs font-bold text-slate-300 hover:text-white px-2.5 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 transition-colors hidden sm:flex items-center gap-1" title="Download Mobile App">
                    <span>📱</span> App
                </a>
                <?php require_once __DIR__ . '/../../includes/notification_widget.php'; ?>
                <div class="text-right hidden sm:block leading-tight">
                    <div class="text-sm font-medium text-white"><?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Guard', ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="text-xs text-slate-400">On Duty</div>
                </div>
                <form action="<?php echo $base; ?>/api/logout.php" method="POST" class="m-0">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" aria-label="Log Out" title="Log Out" class="p-2 text-slate-300 hover:text-white rounded-lg hover:bg-white/10 transition-colors cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <main id="main-content" class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-6 sm:py-8">

        <!-- Active Assigned Gate & Shift Selector Bar -->
        <div class="mb-6 bg-slate-900 text-white rounded-xl p-4 sm:p-5 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-start sm:items-center gap-3 min-w-0">
                <span class="text-2xl shrink-0" aria-hidden="true">🛡️</span>
                <div class="min-w-0">
                    <h1 class="text-xs uppercase tracking-wider text-slate-400 font-bold">Assigned Gate Terminal • <?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Guard', ENT_QUOTES, 'UTF-8'); ?> (On Duty)</h1>
                    <div class="flex flex-wrap items-center gap-2 mt-1">
                        <select id="guard_active_gate_id" aria-label="Select assigned gate" onchange="onGuardGateChanged()" class="bg-slate-800 border border-slate-700 text-white text-sm font-bold px-3 py-1.5 rounded-lg focus:outline-none">
                            <?php if (empty($authorizedGates)): ?>
                                <option value="1">Main Gate (Default)</option>
                            <?php else: ?>
                                <?php foreach ($authorizedGates as $ag): ?>
                                    <option value="<?php echo (int)$ag['id']; ?>" data-shift="<?php echo htmlspecialchars((string)$ag['shift_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($ag['gate_name'] . ' (' . $ag['gate_type'] . ')', ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <span id="guard_shift_badge" class="px-2.5 py-0.5 text-xs font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 rounded-full">
                            <?php echo htmlspecialchars((string)($authorizedGates[0]['shift_name'] ?? 'Morning'), ENT_QUOTES, 'UTF-8'); ?> Shift
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2 text-xs">
                <button type="button" onclick="switchGuardTab('handover')" class="px-3 py-1.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold rounded-lg transition cursor-pointer flex items-center gap-1.5">
                    <span>🔄 Shift Handover</span>
                    <span id="pending-handover-dot" class="hidden w-2 h-2 rounded-full bg-rose-600 animate-ping"></span>
                </button>
                <button type="button" onclick="switchGuardTab('incidents')" class="px-3 py-1.5 bg-white/10 hover:bg-white/20 text-white font-bold rounded-lg transition cursor-pointer flex items-center gap-1.5">
                    <span>⚠️ Incidents</span>
                    <span id="open-incidents-badge" class="px-1.5 py-0.2 bg-rose-600 text-white text-[10px] font-black rounded-full">0</span>
                </button>
            </div>
        </div>

        <!-- Live Emergency SOS Banner for Guards -->
        <div id="guard-emergency-banner" class="hidden mb-6 bg-rose-600 text-white p-5 rounded-2xl shadow-xl border-2 border-rose-700 animate-pulse flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <span class="text-4xl animate-bounce">🚨</span>
                <div>
                    <div class="flex items-center gap-2">
                        <span id="emergency-status-badge" class="bg-white text-rose-700 text-xs font-black px-2.5 py-0.5 rounded-full uppercase tracking-wider">ACTIVE EMERGENCY</span>
                        <span class="text-xs text-rose-100 font-mono" id="emergency-time-stamp">Just now</span>
                    </div>
                    <h3 class="text-xl font-black tracking-tight mt-1" id="emergency-unit-title">EMERGENCY AT FLAT 101</h3>
                    <p class="text-sm text-rose-100 font-medium" id="emergency-details-desc">Medical Emergency • Resident John • Phone: 9876543212</p>
                    <p class="text-xs text-rose-200 mt-0.5 italic" id="emergency-notes-text"></p>
                    <div class="flex items-center gap-2 mt-2">
                        <a id="emergency-phone-link" href="#" class="hidden inline-flex items-center gap-1 px-2.5 py-1 bg-white/20 hover:bg-white/30 text-white text-xs font-semibold rounded-lg transition">
                            📞 Call Resident
                        </a>
                        <a id="emergency-map-link" href="#" target="_blank" rel="noopener noreferrer" class="hidden inline-flex items-center gap-1 px-2.5 py-1 bg-white/20 hover:bg-white/30 text-white text-xs font-semibold rounded-lg transition">
                            📍 View Map Location
                        </a>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="updateEmergencyStatus('Acknowledged')" id="btn-ack-emergency" class="px-5 py-2.5 bg-white hover:bg-rose-50 text-rose-700 font-black text-xs rounded-xl shadow-lg transition cursor-pointer uppercase tracking-wider">
                    ✓ Acknowledge & Dispatch
                </button>
                <button type="button" onclick="updateEmergencyStatus('Responding')" id="btn-responding-emergency" class="hidden px-5 py-2.5 bg-amber-400 hover:bg-amber-300 text-slate-900 font-black text-xs rounded-xl shadow-lg transition cursor-pointer uppercase tracking-wider">
                    🏃 Mark Responding
                </button>
                <button type="button" onclick="updateEmergencyStatus('Resolved')" id="btn-resolve-emergency" class="hidden px-5 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-white font-black text-xs rounded-xl shadow-lg transition cursor-pointer uppercase tracking-wider">
                    ✅ Resolve Incident
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
                                <div class="flex items-center justify-between mb-1">
                                    <span class="block text-sm font-medium text-slate-700">Visitor Photo</span>
                                    <span id="photo-status-badge" class="text-[11px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">Optional / Tap to snap</span>
                                </div>
                                <div class="relative bg-slate-100 rounded-lg overflow-hidden border border-slate-300 aspect-video flex flex-col items-center justify-center">
                                    <video id="camera-stream" autoplay playsinline class="absolute inset-0 w-full h-full object-cover hidden"></video>
                                    <img id="photo-preview" alt="Captured visitor photo" class="absolute inset-0 w-full h-full object-cover hidden" />

                                    <div id="camera-prompt-container" class="z-10 flex flex-col items-center gap-2 p-3 text-center">
                                        <button type="button" id="start-cam-btn" onclick="startCamera()" class="bg-brand-900 hover:bg-brand-800 text-white px-5 py-2.5 rounded-lg shadow text-xs font-bold transition-all flex items-center gap-2 cursor-pointer">
                                            <span>📷</span> Capture Visitor Photo
                                        </button>
                                        <button type="button" onclick="triggerFilePicker()" class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 px-3.5 py-1.5 rounded-lg shadow-2xs text-xs font-medium transition flex items-center gap-1.5 cursor-pointer">
                                            <span>📁</span> Upload Photo File (Optional)
                                        </button>
                                    </div>

                                    <!-- In-App Camera Controls Overlay -->
                                    <div id="camera-controls-overlay" class="hidden z-20 absolute bottom-3 left-0 right-0 flex items-center justify-center gap-2 px-3">
                                        <button type="button" id="flip-cam-btn" onclick="flipCamera()" class="bg-slate-900/85 hover:bg-slate-900 text-white px-4 py-2.5 rounded-xl text-xs font-bold backdrop-blur-sm shadow flex items-center gap-1.5 cursor-pointer">
                                            <span>🔄</span> Flip
                                        </button>
                                        <button type="button" id="capture-btn" onclick="capturePhoto()" class="bg-emerald-600 hover:bg-emerald-500 text-white px-6 py-2.5 rounded-xl shadow-lg text-xs font-bold flex items-center gap-1.5 cursor-pointer animate-pulse">
                                            <span>📸</span> Capture
                                        </button>
                                        <button type="button" id="close-cam-btn" onclick="stopCamera()" class="bg-rose-600/90 hover:bg-rose-600 text-white px-4 py-2.5 rounded-xl text-xs font-bold backdrop-blur-sm shadow flex items-center gap-1 cursor-pointer">
                                            <span>✕</span> Close
                                        </button>
                                    </div>

                                    <button type="button" id="retake-btn" onclick="retakePhoto()" class="hidden z-10 absolute bottom-3 bg-slate-800 hover:bg-slate-700 text-white px-5 py-2.5 rounded-xl shadow text-xs font-bold flex items-center gap-1.5 cursor-pointer">
                                        <span>🔄</span> Retake Photo
                                    </button>
                                </div>
                                <input type="file" id="camera-file-input" accept="image/*" onchange="handleNativePhotoCapture(event)" class="hidden">
                                <input type="hidden" id="photo_base64">
                                <canvas id="camera-canvas" class="hidden"></canvas>
                            </div>

                            <div class="relative">
                                <label for="resident_search" class="block text-sm font-medium text-slate-700 mb-1">Destination Unit <span class="text-rose-500">*</span></label>
                                <input type="text" id="resident_search" list="unit-list" autocomplete="off" required oninput="onResidentSearchInput()" onfocus="renderUnitAutocomplete()" class="w-full px-4 py-2.5 bg-white rounded-lg border border-slate-300 focus:ring-2 focus:border-brand-900" placeholder="Type unit number or resident name...">
                                <div id="unit-autocomplete-dropdown" class="hidden absolute left-0 right-0 top-full mt-1 max-h-52 overflow-y-auto bg-white border border-slate-300 rounded-xl shadow-xl z-40 divide-y divide-slate-100 sg-scroll-thin"></div>
                                <datalist id="unit-list">
                                    <?php foreach ($residents as $res): ?>
                                        <option data-id="<?php echo (int)$res['id']; ?>" value="Unit <?php echo htmlspecialchars((string)($res['unit_number'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars(trim($res['first_name'] . ' ' . $res['last_name']), ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>

                            <div>
                                <label for="visitor_name" class="block text-sm font-medium text-slate-700 mb-1">Visitor Name <span class="text-rose-500">*</span></label>
                                <input type="text" id="visitor_name" required placeholder="Full Name (e.g. Vikram Sharma)" class="w-full px-4 py-2 bg-white rounded-lg border border-slate-300 focus:ring-2 focus:border-brand-900">
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label for="visitor_tag" class="block text-sm font-medium text-slate-700 mb-1">Visitor Tag / Category</label>
                                    <select id="visitor_tag" class="w-full px-4 py-2 bg-white rounded-lg border border-slate-300 focus:ring-2 focus:border-brand-900">
                                        <option value="Delivery">Delivery</option>
                                        <option value="Food Delivery">Food Delivery</option>
                                        <option value="E-commerce">E-commerce</option>
                                        <option value="Cab / Taxi">Cab / Taxi</option>
                                        <option value="Guest" selected>Guest</option>
                                        <option value="Family">Family</option>
                                        <option value="Friend">Friend</option>
                                        <option value="Service Provider">Service Provider</option>
                                        <option value="Maintenance">Maintenance</option>
                                        <option value="Domestic Help">Domestic Help</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="purpose" class="block text-sm font-medium text-slate-700 mb-1">Purpose Classification</label>
                                    <select id="purpose" required class="w-full px-4 py-2 bg-white rounded-lg border border-slate-300 focus:ring-2 focus:border-brand-900">
                                        <option value="Delivery">Delivery</option>
                                        <option value="Guest" selected>Guest</option>
                                        <option value="Service">Service / Repair</option>
                                        <option value="Cab">Cab</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Quick Tags (Does not overwrite name)</label>
                                <div class="flex flex-wrap gap-1.5">
                                    <button type="button" onclick="selectQuickTag('Swiggy', 'Food Delivery', 'Delivery')" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-200 transition cursor-pointer">🍕 Swiggy</button>
                                    <button type="button" onclick="selectQuickTag('Zomato', 'Food Delivery', 'Delivery')" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-900 border border-rose-200 transition cursor-pointer">🍔 Zomato</button>
                                    <button type="button" onclick="selectQuickTag('Amazon', 'E-commerce', 'Delivery')" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-yellow-50 hover:bg-yellow-100 text-yellow-900 border border-yellow-200 transition cursor-pointer">📦 Amazon</button>
                                    <button type="button" onclick="selectQuickTag('Blinkit', 'Delivery', 'Delivery')" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-lime-50 hover:bg-lime-100 text-lime-900 border border-lime-200 transition cursor-pointer">⚡ Blinkit</button>
                                    <button type="button" onclick="selectQuickTag('Zepto', 'Delivery', 'Delivery')" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-purple-50 hover:bg-purple-100 text-purple-900 border border-purple-200 transition cursor-pointer">🛒 Zepto</button>
                                    <button type="button" onclick="selectQuickTag('Flipkart', 'E-commerce', 'Delivery')" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-900 border border-blue-200 transition cursor-pointer">🛍️ Flipkart</button>
                                    <button type="button" onclick="selectQuickTag('Cab', 'Cab / Taxi', 'Cab')" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-300 transition cursor-pointer">🚕 Cab</button>
                                    <button type="button" onclick="selectQuickTag('Guest', 'Guest', 'Guest')" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-900 border border-indigo-200 transition cursor-pointer">🤝 Guest</button>
                                    <button type="button" onclick="selectQuickTag('Service', 'Service Provider', 'Service')" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-900 border border-emerald-200 transition cursor-pointer">🔧 Service</button>
                                </div>
                            </div>

                            <div>
                                <label for="phone_number" class="block text-sm font-medium text-slate-700 mb-1">Phone Number <span class="text-rose-500">*</span></label>
                                <input type="tel" id="phone_number" required placeholder="10-digit mobile number (e.g. 9876543210)" class="w-full px-4 py-2 bg-white rounded-lg border border-slate-300 focus:ring-2 focus:border-brand-900">
                            </div>

                            <button type="submit" id="btn-submit" class="w-full px-6 py-2.5 text-sm font-bold text-white bg-brand-900 hover:bg-brand-800 rounded-lg shadow-sm mt-4 cursor-pointer">Log Entry</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="lg:col-span-2">
                <div class="flex flex-wrap items-center gap-2 mb-4">
                    <button type="button" id="tab-btn-visitors" onclick="switchGuardTab('visitors')" class="px-4 py-2 bg-slate-900 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center gap-1.5 cursor-pointer">
                        <span>👥 Visitors Log</span>
                    </button>
                    <button type="button" id="tab-btn-parcels" onclick="switchGuardTab('parcels')" class="px-4 py-2 bg-white text-slate-700 hover:bg-slate-100 border border-slate-200 font-bold text-xs rounded-xl shadow-2xs transition flex items-center gap-1.5 cursor-pointer">
                        <span>📦 Parcel Locker</span>
                        <span id="guard-parcels-badge" class="bg-amber-100 text-amber-900 text-[10px] font-black px-1.5 py-0.2 rounded-full">0</span>
                    </button>
                    <button type="button" id="tab-btn-handover" onclick="switchGuardTab('handover')" class="px-4 py-2 bg-white text-slate-700 hover:bg-slate-100 border border-slate-200 font-bold text-xs rounded-xl shadow-2xs transition flex items-center gap-1.5 cursor-pointer">
                        <span>🔄 Shift Handover</span>
                    </button>
                    <button type="button" id="tab-btn-incidents" onclick="switchGuardTab('incidents')" class="px-4 py-2 bg-white text-slate-700 hover:bg-slate-100 border border-slate-200 font-bold text-xs rounded-xl shadow-2xs transition flex items-center gap-1.5 cursor-pointer">
                        <span>⚠️ Incidents</span>
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
                    <!-- Mobile Touch-Friendly Visitor Cards (< md) -->
                    <div id="visitors-mobile-cards" class="md:hidden divide-y divide-slate-100">
                        <div class="p-6 text-center text-sm text-slate-500">Loading...</div>
                    </div>
                    <!-- Desktop Visitors Table (md+) -->
                    <div class="hidden md:block overflow-x-auto sg-scroll-thin">
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
                    <!-- Visitors Pagination (20 per page) -->
                    <div id="guard-visitors-pagination" class="p-3.5 px-6 border-t border-slate-100 flex flex-col sm:flex-row justify-between items-center gap-3 text-xs text-slate-600 bg-slate-50/50">
                        <div id="guard-pagination-info">Showing visitors</div>
                        <div id="guard-pagination-controls" class="flex items-center gap-1"></div>
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

                <!-- Tab 3: Shift Handover -->
                <div id="guard-tab-handover" class="hidden space-y-5">
                    <div id="incoming-handover-card" class="hidden bg-amber-50 border-2 border-amber-300 rounded-xl p-5 shadow-xs space-y-3 text-xs"></div>

                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-bold text-slate-900 font-serif">Start Gate Shift Handover</h2>
                                <p class="text-xs text-slate-500" id="handover-snapshot-line">Snapshot will automatically include pending approvals, parcels, and open incidents.</p>
                            </div>
                        </div>
                        <form onsubmit="event.preventDefault(); submitGuardHandover('start_handover');" class="space-y-3 text-xs">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block font-bold text-slate-700 mb-1">Ending Shift</label>
                                    <select id="ho_shift_name" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
                                        <option value="Morning">Morning Shift</option><option value="Afternoon">Afternoon Shift</option><option value="Night">Night Shift</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-700 mb-1">Next Incoming Guard (Optional)</label>
                                    <select id="ho_incoming_guard" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white"><option value="">Any Assigned Incoming Guard</option></select>
                                </div>
                            </div>
                            <textarea id="ho_shift_notes" rows="2" placeholder="Shift Summary Notes (traffic, special guests, deliveries)..." class="w-full px-3 py-2 border border-slate-300 rounded-lg"></textarea>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <input type="text" id="ho_suspicious" placeholder="Suspicious activity notes (if any)" class="px-3 py-2 border border-slate-300 rounded-lg">
                                <input type="text" id="ho_equipment" placeholder="Equipment/Barrier/Intercom issues" class="px-3 py-2 border border-slate-300 rounded-lg">
                            </div>
                            <textarea id="ho_instructions" rows="2" placeholder="Important instructions for next guard on duty..." class="w-full px-3 py-2 border border-slate-300 rounded-lg"></textarea>
                            <div class="flex flex-wrap gap-2">
                                <button type="submit" class="px-5 py-2 bg-brand-900 hover:bg-brand-800 text-white font-bold rounded-lg shadow cursor-pointer">Submit Shift Handover</button>
                                <button type="button" onclick="submitGuardHandover('emergency_handover')" class="px-4 py-2 bg-rose-700 hover:bg-rose-800 text-white font-bold rounded-lg cursor-pointer">🚨 Emergency Takeover (Outgoing Unavailable)</button>
                            </div>
                        </form>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="p-4 border-b border-slate-100 font-bold text-slate-900 font-serif text-sm">Recent Gate Handovers</div>
                        <div id="guard-handover-history" class="divide-y divide-slate-100 p-4 text-xs">Loading handover history...</div>
                    </div>
                </div>

                <!-- Tab 4: Security Incidents -->
                <div id="guard-tab-incidents" class="hidden space-y-5">
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-3 text-xs">
                        <h2 class="text-lg font-bold text-slate-900 font-serif">Report Gate Security Incident</h2>
                        <form onsubmit="event.preventDefault(); reportGateIncident();" class="space-y-3">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <select id="inc_type" class="px-3 py-2 border border-slate-300 rounded-lg bg-white">
                                    <option>Suspicious Activity</option><option>Unauthorized Entry Attempt</option><option>Equipment Failure</option><option>Traffic / Parking Dispute</option><option>Safety Hazard</option>
                                </select>
                                <select id="inc_severity" class="px-3 py-2 border border-slate-300 rounded-lg bg-white">
                                    <option value="Low">Low Severity</option><option value="Medium" selected>Medium Severity</option><option value="High">High Severity</option><option value="Critical">Critical Severity</option>
                                </select>
                            </div>
                            <input type="text" id="inc_title" required placeholder="Incident Headline (e.g., Boom barrier sensor fault / Unverified loitering)" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                            <textarea id="inc_desc" rows="2" placeholder="Full details for incoming guard & security manager..." class="w-full px-3 py-2 border border-slate-300 rounded-lg"></textarea>
                            <button type="submit" class="px-5 py-2 bg-rose-700 hover:bg-rose-800 text-white font-bold rounded-lg cursor-pointer">Log Incident</button>
                        </form>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="p-4 border-b border-slate-100 font-bold text-slate-900 font-serif text-sm">Active & Recent Gate Incidents (Unresolved Stay Highlighted)</div>
                        <div id="guard-incidents-list" class="divide-y divide-slate-100 p-4 text-xs">Loading incidents...</div>
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
            } else {
                document.getElementById('verified_resident_id').value = '';
            }
            renderUnitAutocomplete();
        }

        function renderUnitAutocomplete() {
            const input = document.getElementById('resident_search');
            const box = document.getElementById('unit-autocomplete-dropdown');
            if (!input || !box) return;
            const q = input.value.trim().toLowerCase();
            const opts = Array.from(document.querySelectorAll('#unit-list option'));
            const matches = opts.filter(o => !q || o.value.toLowerCase().includes(q)).slice(0, 12);
            if (!matches.length) {
                box.innerHTML = '<div class="p-3 text-xs text-slate-400 text-center">No matching flat or resident found</div>';
                box.classList.remove('hidden');
                return;
            }
            box.innerHTML = matches.map(o => `
                <button type="button" onclick="selectAutocompleteUnit('${escapeHtml(o.getAttribute('data-id'))}', '${escapeHtml(o.value)}')" class="w-full text-left px-4 py-2.5 text-xs sm:text-sm font-medium text-slate-800 hover:bg-brand-50 hover:text-brand-900 transition cursor-pointer flex items-center justify-between">
                    <span>${escapeHtml(o.value)}</span>
                    <span class="text-[10px] font-bold text-slate-400">Select</span>
                </button>
            `).join('');
            box.classList.remove('hidden');
        }

        function selectAutocompleteUnit(resId, label) {
            document.getElementById('resident_search').value = label;
            document.getElementById('verified_resident_id').value = resId;
            document.getElementById('unit-autocomplete-dropdown')?.classList.add('hidden');
        }

        document.addEventListener('click', (e) => {
            const box = document.getElementById('unit-autocomplete-dropdown');
            const inp = document.getElementById('resident_search');
            if (box && inp && !box.contains(e.target) && e.target !== inp) {
                box.classList.add('hidden');
            }
        });

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

            const badge = document.getElementById('photo-status-badge');
            if (badge) {
                badge.textContent = '✓ Photo Attached';
                badge.className = 'text-[11px] font-bold text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200';
            }

            showToast('Visitor photo attached successfully ✓', 'success');
        };

        let currentFacingMode = 'environment';
        let mediaStream = null;

        function triggerFilePicker() {
            const fileInput = document.getElementById('camera-file-input');
            if (fileInput) fileInput.click();
        }

        async function startCamera() {
            const promptBox = document.getElementById('camera-prompt-container');
            const video = document.getElementById('camera-stream');
            const overlay = document.getElementById('camera-controls-overlay');

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                showToast('Camera not supported in this browser. Please use HTTPS or upload a file.', 'warning');
                return;
            }

            try {
                stopCameraTracks();
                mediaStream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: { ideal: currentFacingMode },
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    },
                    audio: false
                });

                video.srcObject = mediaStream;
                video.classList.remove('hidden');
                if (promptBox) promptBox.classList.add('hidden');
                if (overlay) overlay.classList.remove('hidden');
                document.getElementById('photo-preview').classList.add('hidden');
                document.getElementById('retake-btn').classList.add('hidden');
            } catch (err) {
                console.error('Camera access error:', err);
                let msg = 'Could not access camera. Please allow camera permissions.';
                if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                    msg = 'Camera permission denied. Please allow camera access in your browser settings.';
                } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
                    msg = 'No camera device found.';
                } else if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
                    msg = 'In-app camera requires a secure HTTPS connection. Please use file upload instead.';
                }
                showToast(msg, 'error');
                stopCamera();
            }
        }

        function stopCameraTracks() {
            if (mediaStream) {
                mediaStream.getTracks().forEach(track => {
                    try { track.stop(); } catch (e) {}
                });
                mediaStream = null;
            }
            const video = document.getElementById('camera-stream');
            if (video) video.srcObject = null;
        }

        function stopCamera() {
            stopCameraTracks();
            const video = document.getElementById('camera-stream');
            if (video) video.classList.add('hidden');
            const promptBox = document.getElementById('camera-prompt-container');
            if (promptBox) promptBox.classList.remove('hidden');
            const overlay = document.getElementById('camera-controls-overlay');
            if (overlay) overlay.classList.add('hidden');
        }

        async function flipCamera() {
            currentFacingMode = (currentFacingMode === 'environment') ? 'user' : 'environment';
            await startCamera();
        }

        function handleNativePhotoCapture(event) {
            const file = event.target.files && event.target.files[0];
            if (!file) return;

            if (file.size > 10 * 1024 * 1024) {
                showToast('Image file exceeds the 10 MB limit.', 'error');
                event.target.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    const canvas = document.getElementById('camera-canvas');
                    const maxW = 960;
                    const scale = img.width > maxW ? (maxW / img.width) : 1;
                    canvas.width = Math.max(1, Math.round(img.width * scale));
                    canvas.height = Math.max(1, Math.round(img.height * scale));

                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

                    const base64Data = canvas.toDataURL('image/jpeg', 0.85);
                    document.getElementById('photo_base64').value = base64Data;

                    const preview = document.getElementById('photo-preview');
                    preview.src = base64Data;
                    preview.classList.remove('hidden');

                    stopCameraTracks();
                    const video = document.getElementById('camera-stream');
                    if (video) video.classList.add('hidden');
                    const overlay = document.getElementById('camera-controls-overlay');
                    if (overlay) overlay.classList.add('hidden');
                    const promptBox = document.getElementById('camera-prompt-container');
                    if (promptBox) promptBox.classList.add('hidden');
                    document.getElementById('retake-btn').classList.remove('hidden');

                    const badge = document.getElementById('photo-status-badge');
                    if (badge) {
                        badge.textContent = '✓ Photo Attached';
                        badge.className = 'text-[11px] font-bold text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200';
                    }

                    showToast('Visitor photo attached successfully ✓', 'success');
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }

        function capturePhoto() {
            const video = document.getElementById('camera-stream');
            const canvas = document.getElementById('camera-canvas');
            if (!video || !canvas || !video.videoWidth) {
                showToast('Camera video stream is not ready.', 'warning');
                return;
            }

            const maxW = 960;
            const scale = video.videoWidth > maxW ? (maxW / video.videoWidth) : 1;
            canvas.width = Math.max(1, Math.round(video.videoWidth * scale));
            canvas.height = Math.max(1, Math.round(video.videoHeight * scale));
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

            const base64Data = canvas.toDataURL('image/jpeg', 0.85);
            document.getElementById('photo_base64').value = base64Data;

            const preview = document.getElementById('photo-preview');
            preview.src = base64Data;
            preview.classList.remove('hidden');

            stopCameraTracks();
            video.classList.add('hidden');
            const overlay = document.getElementById('camera-controls-overlay');
            if (overlay) overlay.classList.add('hidden');
            document.getElementById('retake-btn').classList.remove('hidden');

            const badge = document.getElementById('photo-status-badge');
            if (badge) {
                badge.textContent = '✓ Photo Captured';
                badge.className = 'text-[11px] font-bold text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200';
            }
            showToast('Visitor photo captured ✓', 'success');
        }

        function retakePhoto() {
            stopCameraTracks();
            document.getElementById('photo_base64').value = '';
            document.getElementById('photo-preview').classList.add('hidden');
            const promptBox = document.getElementById('camera-prompt-container');
            if (promptBox) promptBox.classList.remove('hidden');
            document.getElementById('camera-stream').classList.add('hidden');
            const overlay = document.getElementById('camera-controls-overlay');
            if (overlay) overlay.classList.add('hidden');
            document.getElementById('retake-btn').classList.add('hidden');
            const fileInput = document.getElementById('camera-file-input');
            if (fileInput) fileInput.value = '';

            const badge = document.getElementById('photo-status-badge');
            if (badge) {
                badge.textContent = 'Optional / Tap to snap';
                badge.className = 'text-[11px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded border border-slate-200';
            }
        }

        let rawVisitorsData = [];

        function selectQuickTag(name, tag, category) {
            const tagSelect = document.getElementById('visitor_tag');
            const purposeSelect = document.getElementById('purpose');

            if (tagSelect && tag) tagSelect.value = tag;
            if (purposeSelect && category) purposeSelect.value = category;

            // Invariant: NEVER overwrite visitor_name! Focus visitor name if empty
            const vNameInput = document.getElementById('visitor_name');
            if (vNameInput && !vNameInput.value.trim()) {
                vNameInput.placeholder = `Name for ${name} visitor`;
                vNameInput.focus();
            }
            showToast(`Visitor Tag set to: ${tag}`, 'info');
        }

        let guardCurrentPage = 1;
        const guardPageSize = 20;

        function filterVisitors() {
            guardCurrentPage = 1;
            renderVisitorsTable(rawVisitorsData);
        }

        function guardChangePage(newPage) {
            guardCurrentPage = newPage;
            renderVisitorsTable(rawVisitorsData);
        }

        let knownVisitorStatuses = {};
        let isFirstVisitorFetch = true;
        let decisionAudioCtx = null;

        function playDecisionSound(isApproved) {
            try {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx) return;
                if (!decisionAudioCtx) decisionAudioCtx = new AudioCtx();
                if (decisionAudioCtx.state === 'suspended') decisionAudioCtx.resume();

                const now = decisionAudioCtx.currentTime;
                if (isApproved) {
                    // Cheerful two-tone chime (G5 784Hz -> C6 1046Hz)
                    const osc = decisionAudioCtx.createOscillator();
                    const gain = decisionAudioCtx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(783.99, now);
                    osc.frequency.setValueAtTime(1046.50, now + 0.16);
                    gain.gain.setValueAtTime(0.35, now);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.6);
                    osc.connect(gain);
                    gain.connect(decisionAudioCtx.destination);
                    osc.start(now);
                    osc.stop(now + 0.6);
                } else {
                    // Alert warning buzz (A4 440Hz -> Eb4 311Hz)
                    const osc = decisionAudioCtx.createOscillator();
                    const gain = decisionAudioCtx.createGain();
                    osc.type = 'sawtooth';
                    osc.frequency.setValueAtTime(440, now);
                    osc.frequency.setValueAtTime(311.13, now + 0.18);
                    gain.gain.setValueAtTime(0.3, now);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.55);
                    osc.connect(gain);
                    gain.connect(decisionAudioCtx.destination);
                    osc.start(now);
                    osc.stop(now + 0.55);
                }
            } catch (e) {
                // Audio autoplay policy or AudioContext unavailable
            }
        }

        function dismissDecisionPopup(el) {
            const popup = el.closest ? el.closest('.decision-popup') : el;
            if (!popup) return;
            popup.style.opacity = '0';
            popup.style.transform = 'translateY(-15px) scale(0.95)';
            setTimeout(() => {
                if (popup && popup.parentNode) popup.remove();
            }, 250);
        }

        function showGatekeeperDecisionPopup(vis, decision) {
            const container = document.getElementById('guard-decision-popup-container');
            if (!container) return;

            const isApproved = (decision === 'approved');
            const safeName = escapeHtml(vis.visitor_name || 'Visitor');
            const safeUnit = escapeHtml(vis.unit_number || 'Unit');
            const safePurpose = escapeHtml(vis.purpose || 'Visitor');
            const residentName = escapeHtml(((vis.first_name || '') + ' ' + (vis.last_name || '')).trim() || 'Resident');
            const initial = (vis.visitor_name || '?').charAt(0).toUpperCase();

            // Play audible chime for the gatekeeper
            playDecisionSound(isApproved);

            // Optional native browser notification if granted
            if (window.Notification && Notification.permission === 'granted') {
                try {
                    new Notification(isApproved ? `✓ Entry Approved: ${safeName}` : `✕ Entry Denied: ${safeName}`, {
                        body: `Flat ${safeUnit} • Resident ${residentName} ${isApproved ? 'allowed entry' : 'declined entry'}.`,
                        icon: '/assets/icon-192.png'
                    });
                } catch (e) {}
            }

            const avatarHtml = vis.photo_url
                ? `<img src="${escapeHtml(vis.photo_url)}" class="w-12 h-12 min-w-[48px] rounded-full object-cover border-2 ${isApproved ? 'border-emerald-400 ring-2 ring-emerald-500/30' : 'border-rose-400 ring-2 ring-rose-500/30'} shadow cursor-pointer" onclick="openPhotoLightbox('${escapeHtml(vis.photo_url)}', '${safeName}', '${safePurpose} | Flat ${safeUnit}')" alt="${safeName}" onerror="this.onerror=null; this.outerHTML='<div class=\\\'w-12 h-12 min-w-[48px] rounded-full flex items-center justify-center font-black text-lg ${isApproved ? 'bg-emerald-800 text-emerald-200 border-2 border-emerald-400' : 'bg-rose-900 text-rose-200 border-2 border-rose-400'} shadow\\\'>${escapeHtml(initial)}</div>';">`
                : `<div class="w-12 h-12 min-w-[48px] rounded-full flex items-center justify-center font-black text-lg ${isApproved ? 'bg-emerald-800 text-emerald-200 border-2 border-emerald-400' : 'bg-rose-900 text-rose-200 border-2 border-rose-400'} shadow">${escapeHtml(initial)}</div>`;

            const card = document.createElement('div');
            card.className = `decision-popup decision-popup-enter pointer-events-auto bg-slate-950/95 text-white border-2 ${isApproved ? 'border-emerald-400 shadow-emerald-950/50' : 'border-rose-500 shadow-rose-950/50'} rounded-2xl shadow-2xl p-4 sm:p-5 relative overflow-hidden backdrop-blur-md transition-all duration-300`;

            const topGlow = isApproved
                ? `<div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-emerald-500 via-teal-400 to-emerald-500"></div>`
                : `<div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-rose-500 via-red-400 to-rose-500"></div>`;

            const badge = isApproved
                ? `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 uppercase tracking-wide"><span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span> ✓ ENTRY APPROVED</span>`
                : `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-rose-500/20 text-rose-300 border border-rose-500/40 uppercase tracking-wide"><span class="w-2 h-2 rounded-full bg-rose-400 animate-ping"></span> ✕ ENTRY DENIED</span>`;

            const noteBox = isApproved
                ? `<div class="bg-emerald-950/70 border border-emerald-600/40 rounded-xl p-2.5 text-xs text-emerald-200 flex items-center gap-2"><span class="text-emerald-400 font-black text-base shrink-0">✓</span><div><strong>Resident ${residentName}</strong> has ALLOWED entry. Grant gate access.</div></div>`
                : `<div class="bg-rose-950/70 border border-rose-600/40 rounded-xl p-2.5 text-xs text-rose-200 flex items-center gap-2"><span class="text-rose-400 font-black text-base shrink-0">⛔</span><div><strong>Resident ${residentName}</strong> has DENIED entry. Do NOT allow into society.</div></div>`;

            const actionBtn = isApproved
                ? `<button type="button" onclick="dismissDecisionPopup(this)" class="w-full py-2.5 px-4 bg-emerald-600 hover:bg-emerald-500 active:scale-98 text-white font-bold text-xs rounded-xl shadow-lg shadow-emerald-900/40 transition-all cursor-pointer text-center">Allow In / Acknowledged</button>`
                : `<button type="button" onclick="dismissDecisionPopup(this)" class="w-full py-2.5 px-4 bg-rose-600 hover:bg-rose-500 active:scale-98 text-white font-bold text-xs rounded-xl shadow-lg shadow-rose-950/40 transition-all cursor-pointer text-center">Dismiss (Do Not Enter)</button>`;

            card.innerHTML = `
                ${topGlow}
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div class="flex flex-wrap items-center gap-2">
                        ${badge}
                        <span class="text-xs font-bold text-slate-300 bg-white/10 px-2 py-0.5 rounded">Flat ${safeUnit}</span>
                    </div>
                    <button type="button" onclick="dismissDecisionPopup(this)" class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-white/10 transition-colors cursor-pointer text-lg leading-none" aria-label="Close">✕</button>
                </div>
                <div class="flex items-center gap-3.5 mb-3">
                    ${avatarHtml}
                    <div class="min-w-0 flex-1">
                        <div class="text-base font-bold text-white truncate">${safeName}</div>
                        <div class="text-xs text-slate-300 flex flex-wrap items-center gap-1.5 mt-0.5">
                            <span class="font-semibold text-emerald-400">${safePurpose}</span>
                            ${vis.phone_number ? `<span>•</span><span class="font-mono text-slate-400">${escapeHtml(vis.phone_number)}</span>` : ''}
                        </div>
                    </div>
                </div>
                ${noteBox}
                <div class="mt-3">
                    ${actionBtn}
                </div>
            `;

            container.appendChild(card);

            // Auto dismiss after 12s (approved) or 16s (denied)
            setTimeout(() => {
                if (document.body.contains(card)) {
                    dismissDecisionPopup(card);
                }
            }, isApproved ? 12000 : 16000);
        }

        window.testDecisionPopup = function(type = 'approved') {
            showGatekeeperDecisionPopup({
                id: 9999,
                visitor_name: type === 'approved' ? 'Swiggy Partner (Ramesh)' : 'Unknown Salesperson',
                unit_number: '101',
                purpose: type === 'approved' ? 'Delivery' : 'Guest',
                first_name: 'John',
                last_name: 'Resident',
                phone_number: '9826500000'
            }, type);
        };

        async function fetchVisitors() {
            try {
                const activeGateId = getActiveGateId();
                const response = await fetch(appPath('/api/guard_get_visitors.php?gate_id=' + encodeURIComponent(activeGateId)));
                const result = await response.json();

                if (response.ok && result.status === 'success') {
                    const newVisitors = result.data || [];

                    // Check for resident decision on visitors that were pending
                    if (!isFirstVisitorFetch) {
                        newVisitors.forEach(vis => {
                            const prevStatus = knownVisitorStatuses[vis.id];
                            if (prevStatus === 'Pending') {
                                if (vis.status === 'Entered') {
                                    showGatekeeperDecisionPopup(vis, 'approved');
                                } else if (vis.status === 'Denied') {
                                    showGatekeeperDecisionPopup(vis, 'denied');
                                }
                            }
                        });
                    } else {
                        isFirstVisitorFetch = false;
                    }

                    // Update tracked visitor statuses
                    newVisitors.forEach(vis => {
                        knownVisitorStatuses[vis.id] = vis.status;
                    });

                    rawVisitorsData = newVisitors;
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
            const mobileCards = document.getElementById('visitors-mobile-cards');
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
                const emptyMsg = query ? 'No visitors matching "' + escapeHtml(query) + '"' : 'No visitors logged today.';
                tbody.innerHTML = `<tr><td colspan="4" class="text-center text-slate-500 py-8">${emptyMsg}</td></tr>`;
                if (mobileCards) mobileCards.innerHTML = `<div class="p-8 text-center text-sm text-slate-500">${emptyMsg}</div>`;
                renderGuardPagination(0, 0, 0, 1);
                return;
            }

            const now = Date.now();
            filtered.sort((a, b) => {
                if (a.status === 'Entered' && b.status !== 'Entered') return -1;
                if (a.status !== 'Entered' && b.status === 'Entered') return 1;
                return (parseMySqlDate(b.entry_time)?.getTime() || 0) - (parseMySqlDate(a.entry_time)?.getTime() || 0);
            });

            const totalItems = filtered.length;
            const totalPages = Math.max(1, Math.ceil(totalItems / guardPageSize));
            if (guardCurrentPage > totalPages) guardCurrentPage = totalPages;
            if (guardCurrentPage < 1) guardCurrentPage = 1;

            const startIndex = (guardCurrentPage - 1) * guardPageSize;
            const endIndex = Math.min(startIndex + guardPageSize, totalItems);
            const pagedVisitors = filtered.slice(startIndex, endIndex);

            const mobileHtml = [];
            tbody.innerHTML = pagedVisitors.map(vis => {
                let actionCell = '';
                let rowClass = 'hover:bg-slate-50 border-b border-slate-100 transition-colors';
                let cardBg = 'bg-white';
                let alertBadge = '';

                const safeName = escapeHtml(vis.visitor_name || 'Guest');
                const initial = (vis.visitor_name || '?').charAt(0).toUpperCase();
                const photoSrc = vis.photo_url || '';
                const avatarHTML = photoSrc
                    ? `<img src="${escapeHtml(photoSrc)}" class="w-10 h-10 min-w-[40px] rounded-full object-cover border border-slate-200 shadow-sm cursor-pointer hover:ring-2 hover:ring-brand-500 transition-all" alt="${safeName}" onclick="openPhotoLightbox('${escapeHtml(photoSrc)}', '${safeName}', '${escapeHtml(vis.purpose || 'Visitor')} | Flat ${escapeHtml(vis.unit_number || 'Unit')}')" onerror="this.onerror=null; this.outerHTML='<div class=\\\'w-10 h-10 min-w-[40px] rounded-full bg-slate-200 flex items-center justify-center text-slate-500 font-bold shadow-sm\\\'>${escapeHtml(initial)}</div>';">`
                    : `<div class="w-10 h-10 min-w-[40px] rounded-full bg-slate-200 flex items-center justify-center text-slate-500 font-bold shadow-sm">${escapeHtml(initial)}</div>`;

                if (vis.status === 'Entered') {
                    actionCell = `<button type="button" onclick="markExit(${Number(vis.id)}, this)" class="px-4 py-2 text-xs font-bold text-white bg-slate-800 hover:bg-slate-700 shadow-sm transition-all rounded-lg cursor-pointer">Mark Exit</button>`;

                    if (vis.purpose === 'Delivery' || vis.purpose === 'Cab' || vis.purpose === 'Service') {
                        const entryDt = parseMySqlDate(vis.entry_time);
                        const minutesPassed = entryDt ? Math.floor((now - entryDt.getTime()) / 60000) : 0;
                        if (minutesPassed > 45) {
                            rowClass = 'bg-red-50 hover:bg-red-100 border-b border-red-200 transition-colors';
                            cardBg = 'bg-rose-50/70';
                            alertBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-red-600 text-white animate-pulse">OVERSTAY: ${minutesPassed}m</span>`;
                        }
                    }
                } else if (vis.status === 'Pending') {
                    actionCell = `<span class="bg-amber-50 text-amber-800 px-2.5 py-1 rounded-full text-xs font-semibold border border-amber-200">Awaiting Approval</span>`;
                } else if (vis.status === 'Denied') {
                    actionCell = `<span class="bg-red-50 text-red-700 px-2.5 py-1 rounded-full text-xs font-semibold border border-red-200">Entry Denied</span>`;
                } else {
                    actionCell = `<span class="bg-slate-100 text-slate-600 px-2.5 py-1 rounded-full text-xs border border-slate-200">Exited ${escapeHtml(formatDisplayTime(vis.exit_time))}</span>`;
                }

                mobileHtml.push(`
                    <div class="p-4 space-y-2.5 ${cardBg}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                ${avatarHTML}
                                <div class="min-w-0">
                                    <div class="font-bold text-sm text-slate-900 truncate">${safeName}</div>
                                    <div class="text-xs text-slate-500">Unit ${escapeHtml(vis.unit_number || 'N/A')} • ${escapeHtml(formatDisplayTime(vis.entry_time))}</div>
                                </div>
                            </div>
                            <span class="text-[11px] font-bold text-slate-600 bg-slate-100 border border-slate-200 px-2 py-0.5 rounded shrink-0">${escapeHtml(vis.visitor_tag || vis.purpose || 'Guest')}</span>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                            <div class="text-xs text-slate-500">${escapeHtml(vis.phone_number || 'N/A')} ${alertBadge}</div>
                            <div>${actionCell}</div>
                        </div>
                    </div>
                `);

                return `
                    <tr class="${rowClass}">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                ${avatarHTML}
                                <div>
                                    <div class="font-bold text-slate-900 flex items-center flex-wrap gap-1.5">
                                        <span>${safeName}</span>
                                        <span class="text-[10px] font-bold text-slate-600 bg-slate-100 border border-slate-200 px-1.5 py-0.5 rounded">${escapeHtml(vis.visitor_tag || vis.purpose || 'Guest')}</span>
                                        ${alertBadge}
                                    </div>
                                    <div class="text-xs text-slate-500">${escapeHtml(vis.phone_number || 'N/A')} • ${escapeHtml(vis.purpose)}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4"><div class="font-semibold text-slate-800">Unit ${escapeHtml(vis.unit_number || 'N/A')}</div></td>
                        <td class="px-6 py-4 font-medium text-slate-900">${escapeHtml(formatDisplayTime(vis.entry_time))}</td>
                        <td class="px-6 py-4">${actionCell}</td>
                    </tr>
                `;
            }).join('');

            if (mobileCards) mobileCards.innerHTML = mobileHtml.join('');
            renderGuardPagination(totalItems, startIndex, endIndex, totalPages);
        }

        function renderGuardPagination(totalItems, startIndex, endIndex, totalPages) {
            const infoEl = document.getElementById('guard-pagination-info');
            const controlsEl = document.getElementById('guard-pagination-controls');
            if (!infoEl || !controlsEl) return;

            if (totalItems === 0) {
                infoEl.textContent = 'No visitors to display';
                controlsEl.innerHTML = '';
                return;
            }

            infoEl.innerHTML = `Showing <span class="font-bold text-slate-900">${startIndex + 1}</span> to <span class="font-bold text-slate-900">${endIndex}</span> of <span class="font-bold text-slate-900">${totalItems}</span> visitors`;

            if (totalPages <= 1) {
                controlsEl.innerHTML = '';
                return;
            }

            let buttonsHtml = '';
            const prevDisabled = guardCurrentPage <= 1;
            buttonsHtml += `<button type="button" ${prevDisabled ? 'disabled class="px-2.5 py-1 text-xs rounded border border-slate-200 text-slate-300 cursor-not-allowed"' : `onclick="guardChangePage(${guardCurrentPage - 1})" class="px-2.5 py-1 text-xs font-semibold rounded border border-slate-300 text-slate-700 bg-white hover:bg-slate-100 cursor-pointer shadow-sm transition-colors"`}>Prev</button>`;

            for (let p = 1; p <= totalPages; p++) {
                if (totalPages > 7) {
                    if (p !== 1 && p !== totalPages && Math.abs(p - guardCurrentPage) > 1) {
                        if (p === 2 || p === totalPages - 1) {
                            buttonsHtml += `<span class="px-1 text-slate-400">…</span>`;
                        }
                        continue;
                    }
                }
                if (p === guardCurrentPage) {
                    buttonsHtml += `<button type="button" class="px-2.5 py-1 text-xs font-bold rounded bg-brand-900 text-white shadow-sm">${p}</button>`;
                } else {
                    buttonsHtml += `<button type="button" onclick="guardChangePage(${p})" class="px-2.5 py-1 text-xs font-medium rounded border border-slate-300 text-slate-700 bg-white hover:bg-slate-100 cursor-pointer shadow-sm transition-colors">${p}</button>`;
                }
            }

            const nextDisabled = guardCurrentPage >= totalPages;
            buttonsHtml += `<button type="button" ${nextDisabled ? 'disabled class="px-2.5 py-1 text-xs rounded border border-slate-200 text-slate-300 cursor-not-allowed"' : `onclick="guardChangePage(${guardCurrentPage + 1})" class="px-2.5 py-1 text-xs font-semibold rounded border border-slate-300 text-slate-700 bg-white hover:bg-slate-100 cursor-pointer shadow-sm transition-colors"`}>Next</button>`;

            controlsEl.innerHTML = buttonsHtml;
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

            const visitorName = document.getElementById('visitor_name').value.trim();
            if (!visitorName) {
                showToast('Please enter the visitor\'s full name.', 'warning');
                btn.innerText = 'Log Entry';
                btn.disabled = false;
                document.getElementById('visitor_name').focus();
                return;
            }

            const rawPhone = document.getElementById('phone_number').value.replace(/[\s\-\+]/g, '');
            const cleanPhone = (rawPhone.startsWith('91') && rawPhone.length === 12) ? rawPhone.slice(2) : ((rawPhone.startsWith('0') && rawPhone.length === 11) ? rawPhone.slice(1) : rawPhone);
            if (!/^[6-9]\d{9}$/.test(cleanPhone)) {
                showToast('Please enter a valid 10-digit Indian mobile number (e.g., 9876543210)', 'warning');
                btn.innerText = 'Log Entry';
                btn.disabled = false;
                document.getElementById('phone_number').focus();
                return;
            }

            const payload = {
                gate_id: getActiveGateId(),
                resident_id: residentId,
                visitor_name: visitorName,
                visitor_tag: document.getElementById('visitor_tag') ? document.getElementById('visitor_tag').value : 'Guest',
                phone_number: cleanPhone,
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
                    const allAlerts = (result.alerts || (result.data ? result.data.alerts : [])) || [];
                    const activeAlerts = allAlerts.filter(a => a.status === 'Active' || a.status === 'Acknowledged' || a.status === 'Responding');

                    if (activeAlerts.length > 0) {
                        activeEmergencyAlert = activeAlerts[0];
                        document.getElementById('emergency-unit-title').textContent = `🚨 ${activeEmergencyAlert.alert_type.toUpperCase()} - FLAT ${activeEmergencyAlert.unit_number}`;
                        document.getElementById('emergency-details-desc').textContent = `Resident: ${activeEmergencyAlert.first_name || 'Resident'} ${activeEmergencyAlert.last_name || ''} • Phone: ${activeEmergencyAlert.phone_number || 'N/A'}`;
                        document.getElementById('emergency-notes-text').textContent = (activeEmergencyAlert.description || activeEmergencyAlert.notes) ? `Details: "${activeEmergencyAlert.description || activeEmergencyAlert.notes}"` : '';
                        document.getElementById('emergency-time-stamp').textContent = activeEmergencyAlert.created_at;

                        const badge = document.getElementById('emergency-status-badge');
                        if (badge) badge.textContent = activeEmergencyAlert.status.toUpperCase();

                        const mapLink = document.getElementById('emergency-map-link');
                        if (mapLink) {
                            if (activeEmergencyAlert.latitude && activeEmergencyAlert.longitude) {
                                mapLink.href = `https://maps.google.com/?q=${activeEmergencyAlert.latitude},${activeEmergencyAlert.longitude}`;
                                mapLink.classList.remove('hidden');
                            } else {
                                mapLink.classList.add('hidden');
                            }
                        }

                        const phoneLink = document.getElementById('emergency-phone-link');
                        if (phoneLink && activeEmergencyAlert.phone_number) {
                            phoneLink.href = `tel:${activeEmergencyAlert.phone_number}`;
                            phoneLink.classList.remove('hidden');
                        }

                        const ackBtn = document.getElementById('btn-ack-emergency');
                        const respBtn = document.getElementById('btn-responding-emergency');
                        const resolveBtn = document.getElementById('btn-resolve-emergency');

                        if (activeEmergencyAlert.status === 'Active') {
                            if (ackBtn) ackBtn.classList.remove('hidden');
                            if (respBtn) respBtn.classList.add('hidden');
                            if (resolveBtn) resolveBtn.classList.add('hidden');
                            playEmergencySiren();
                        } else if (activeEmergencyAlert.status === 'Acknowledged') {
                            if (ackBtn) ackBtn.classList.add('hidden');
                            if (respBtn) respBtn.classList.remove('hidden');
                            if (resolveBtn) resolveBtn.classList.remove('hidden');
                        } else if (activeEmergencyAlert.status === 'Responding') {
                            if (ackBtn) ackBtn.classList.add('hidden');
                            if (respBtn) respBtn.classList.add('hidden');
                            if (resolveBtn) resolveBtn.classList.remove('hidden');
                        }

                        banner.classList.remove('hidden');
                    } else {
                        activeEmergencyAlert = null;
                        banner.classList.add('hidden');
                    }
                }
            } catch (e) {}
        }

        async function updateEmergencyStatus(targetStatus) {
            if (!activeEmergencyAlert) return;

            try {
                const response = await fetch(appPath('/api/guard_acknowledge_emergency.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        alert_id: activeEmergencyAlert.id,
                        status: targetStatus,
                        csrf_token: csrfToken
                    })
                });
                const result = await response.json();
                if (response.ok) {
                    showToast(`Emergency alert updated to ${targetStatus}.`, 'success');
                    pollEmergencyAlerts();
                } else {
                    showToast(result.message || 'Error updating status.', 'error');
                }
            } catch (e) {
                showToast('Network error updating emergency.', 'error');
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

        // --- GUARD TABS, MULTI-GATE, HANDOVERS & INCIDENTS ---
        function getActiveGateId() {
            const sel = document.getElementById('guard_active_gate_id');
            return sel ? Number(sel.value || 1) : 1;
        }

        function onGuardGateChanged() {
            const sel = document.getElementById('guard_active_gate_id');
            const opt = sel?.options[sel.selectedIndex];
            const shiftBadge = document.getElementById('guard_shift_badge');
            if (opt && shiftBadge) {
                const sh = opt.getAttribute('data-shift') || 'Morning';
                shiftBadge.textContent = sh + ' Shift';
            }
            isFirstVisitorFetch = true;
            fetchVisitors();
            fetchGuardHandovers();
        }

        function switchGuardTab(tab) {
            const tabMap = {
                visitors: document.getElementById('guard-tab-visitors'),
                parcels: document.getElementById('guard-tab-parcels'),
                handover: document.getElementById('guard-tab-handover'),
                incidents: document.getElementById('guard-tab-incidents')
            };
            const btnMap = {
                visitors: document.getElementById('tab-btn-visitors'),
                parcels: document.getElementById('tab-btn-parcels'),
                handover: document.getElementById('tab-btn-handover'),
                incidents: document.getElementById('tab-btn-incidents')
            };
            Object.keys(tabMap).forEach(k => {
                if (tabMap[k]) tabMap[k].classList.toggle('hidden', k !== tab);
                if (btnMap[k]) {
                    btnMap[k].className = k === tab
                        ? 'px-4 py-2 bg-slate-900 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center gap-1.5 cursor-pointer'
                        : 'px-4 py-2 bg-white text-slate-700 hover:bg-slate-100 border border-slate-200 font-bold text-xs rounded-xl shadow-2xs transition flex items-center gap-1.5 cursor-pointer';
                }
            });
            if (tab === 'parcels') fetchGuardParcels();
            if (tab === 'handover') fetchGuardHandovers();
            if (tab === 'incidents') fetchGuardIncidents();
        }

        async function fetchGuardHandovers() {
            try {
                const res = await fetch(appPath('/api/guard_handover.php?gate_id=' + encodeURIComponent(getActiveGateId())));
                const j = await res.json();
                if (!res.ok || j.status !== 'success') return;

                const snap = j.snapshot || {};
                const line = document.getElementById('handover-snapshot-line');
                if (line) {
                    line.textContent = `Live Snapshot: ${snap.pending_visitors || 0} pending visitors • ${snap.pending_parcels || 0} parcels at gate • ${snap.open_incidents || 0} unresolved incidents`;
                }

                const peerSel = document.getElementById('ho_incoming_guard');
                if (peerSel && peerSel.options.length <= 1) {
                    (j.peer_guards || []).forEach(g => {
                        const o = document.createElement('option');
                        o.value = g.id;
                        o.textContent = `${g.first_name} ${g.last_name}`;
                        peerSel.appendChild(o);
                    });
                }

                const pend = j.pending_acknowledgments || [];
                const dot = document.getElementById('pending-handover-dot');
                if (dot) dot.classList.toggle('hidden', pend.length === 0);

                const incCard = document.getElementById('incoming-handover-card');
                if (incCard) {
                    if (pend.length > 0) {
                        const p = pend[0];
                        incCard.classList.remove('hidden');
                        incCard.innerHTML = `
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <span class="px-2 py-0.5 bg-amber-200 text-amber-950 font-black rounded uppercase">Incoming Handover #${Number(p.id)} • ${escapeHtml(p.handover_type)}</span>
                                    <h3 class="text-sm font-bold text-amber-950 mt-1">${escapeHtml(p.gate_name)} (${escapeHtml(p.shift_name)} Shift) from ${escapeHtml(p.outgoing_guard_name)}</h3>
                                </div>
                            </div>
                            <p class="text-amber-900"><strong>Notes:</strong> ${escapeHtml(p.shift_notes || 'None')} | <strong>Instructions:</strong> ${escapeHtml(p.next_guard_instructions || 'Standard vigilance')}</p>
                            <p class="text-amber-900"><strong>Equipment Issues:</strong> ${escapeHtml(p.equipment_issues || 'None')} | <strong>Suspicious:</strong> ${escapeHtml(p.suspicious_activity_notes || 'None')}</p>
                            <div class="flex flex-wrap gap-2 pt-2">
                                <button type="button" onclick="acknowledgeHandover(${Number(p.id)}, 'acknowledge_handover')" class="px-4 py-2 bg-emerald-700 hover:bg-emerald-800 text-white font-bold rounded-lg cursor-pointer">✓ Acknowledge Receipt</button>
                                <button type="button" onclick="acknowledgeHandover(${Number(p.id)}, 'report_discrepancy')" class="px-4 py-2 bg-rose-700 hover:bg-rose-800 text-white font-bold rounded-lg cursor-pointer">⚠️ Report Discrepancy</button>
                            </div>`;
                    } else {
                        incCard.classList.add('hidden');
                    }
                }

                const histEl = document.getElementById('guard-handover-history');
                if (histEl) {
                    const list = j.history || [];
                    histEl.innerHTML = list.length ? list.map(h => `
                        <div class="py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <div class="font-bold text-slate-900">${escapeHtml(h.gate_name)} (${escapeHtml(h.shift_name)}) • ${escapeHtml(h.outgoing_guard_name)} → ${escapeHtml(h.incoming_guard_name)}</div>
                                <div class="text-slate-500">${escapeHtml(formatDisplayDateTime(h.started_at))} • ${escapeHtml(h.shift_notes || h.next_guard_instructions || 'No notes')}</div>
                            </div>
                            <span class="px-2.5 py-0.5 rounded-full font-bold self-start ${h.status === 'Acknowledged' ? 'bg-emerald-50 text-emerald-800' : (h.status === 'Discrepancy_Reported' ? 'bg-rose-50 text-rose-800' : 'bg-amber-50 text-amber-800')}">${escapeHtml(h.status)}</span>
                        </div>
                    `).join('') : '<div class="text-slate-400 text-center py-4">No handovers recorded yet.</div>';
                }
            } catch (_) {}
        }

        async function submitGuardHandover(actionType) {
            const res = await fetch(appPath('/api/guard_handover.php'), {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: actionType,
                    gate_id: getActiveGateId(),
                    shift_name: document.getElementById('ho_shift_name').value,
                    incoming_guard_id: document.getElementById('ho_incoming_guard').value || null,
                    shift_notes: document.getElementById('ho_shift_notes').value,
                    suspicious_activity_notes: document.getElementById('ho_suspicious').value,
                    equipment_issues: document.getElementById('ho_equipment').value,
                    next_guard_instructions: document.getElementById('ho_instructions').value,
                    csrf_token: csrfToken
                })
            });
            const j = await res.json();
            showToast(j.message || 'Done', res.ok ? 'success' : 'error');
            if (res.ok) fetchGuardHandovers();
        }

        async function acknowledgeHandover(id, actionType) {
            let disc = '';
            if (actionType === 'report_discrepancy') {
                disc = (await showPromptDialog({
                    title: 'Report Handover Discrepancy',
                    message: 'Describe any missing equipment, open incident mismatch, or unlogged parcels:',
                    placeholder: 'Describe discrepancy details...',
                    confirmText: 'Submit Discrepancy'
                })) || '';
                if (!disc.trim()) return;
            }
            const res = await fetch(appPath('/api/guard_handover.php'), {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: actionType, handover_id: id, discrepancy_notes: disc, csrf_token: csrfToken })
            });
            const j = await res.json();
            showToast(j.message || 'Done', res.ok ? 'success' : 'error');
            if (res.ok) fetchGuardHandovers();
        }

        async function fetchGuardIncidents() {
            try {
                const res = await fetch(appPath('/api/guard_incidents.php'));
                const j = await res.json();
                if (!res.ok || j.status !== 'success') return;
                const badge = document.getElementById('open-incidents-badge');
                if (badge) badge.textContent = j.unresolved_count || 0;

                const listEl = document.getElementById('guard-incidents-list');
                if (!listEl) return;
                const items = j.incidents || [];
                listEl.innerHTML = items.length ? items.map(i => `
                    <div class="py-3 ${i.status !== 'Resolved' ? 'bg-rose-50/40 -mx-4 px-4' : ''} flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded font-bold ${i.severity === 'Critical' || i.severity === 'High' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800'}">${escapeHtml(i.severity)}</span>
                                <span class="font-bold text-slate-900">${escapeHtml(i.title)}</span>
                                <span class="text-slate-400">• ${escapeHtml(i.gate_name)}</span>
                            </div>
                            <p class="text-slate-600 mt-1">${escapeHtml(i.description || '')}</p>
                        </div>
                        <div class="flex gap-2 shrink-0">
                            ${i.status === 'Resolved'
                                ? '<span class="px-2.5 py-1 bg-emerald-50 text-emerald-800 rounded-full font-bold">Resolved</span>'
                                : `<button type="button" onclick="resolveGuardIncident(${Number(i.id)})" class="px-3 py-1 bg-emerald-700 text-white font-bold rounded-lg cursor-pointer">Resolve</button>`}
                        </div>
                    </div>
                `).join('') : '<div class="text-slate-400 text-center py-4">No security incidents recorded.</div>';
            } catch (_) {}
        }

        async function reportGateIncident() {
            const res = await fetch(appPath('/api/guard_incidents.php'), {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'report', gate_id: getActiveGateId(),
                    incident_type: document.getElementById('inc_type').value,
                    severity: document.getElementById('inc_severity').value,
                    title: document.getElementById('inc_title').value,
                    description: document.getElementById('inc_desc').value,
                    csrf_token: csrfToken
                })
            });
            const j = await res.json();
            showToast(j.message || 'Done', res.ok ? 'success' : 'error');
            if (res.ok) {
                document.getElementById('inc_title').value = '';
                document.getElementById('inc_desc').value = '';
                fetchGuardIncidents();
            }
        }

        async function resolveGuardIncident(id) {
            const notes = await showPromptDialog({
                title: 'Resolve Gate Security Incident',
                message: 'Enter summary notes of the resolution:',
                placeholder: 'e.g., Issue cleared / False alarm verified...',
                confirmText: 'Mark Resolved'
            });
            if (notes === null) return;
            const res = await fetch(appPath('/api/guard_incidents.php'), {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'update_status', incident_id: id, status: 'Resolved', resolution_notes: notes, csrf_token: csrfToken })
            });
            const j = await res.json();
            showToast(j.message || 'Done', res.ok ? 'success' : 'error');
            if (res.ok) fetchGuardIncidents();
        }

        document.addEventListener('DOMContentLoaded', () => {
            fetchGuardHandovers();
            fetchGuardIncidents();
        });

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
                                ${formatDisplayTime(p.received_at)}
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
            const ok = await showConfirmDialog({
                title: 'Confirm Parcel Handover',
                message: 'Confirm that this courier parcel has been collected by the resident?',
                confirmText: 'Mark Collected'
            });
            if (!ok) return;

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
