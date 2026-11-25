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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

    <nav class="bg-slate-900 shadow-md border-b border-slate-800 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between h-16 items-center">
            <span class="font-serif text-xl font-semibold text-white tracking-wide">Gatekeeper Terminal</span>
            <div class="flex items-center gap-4">
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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                    <h2 class="text-lg font-bold text-slate-900 font-serif mb-4">New Entry</h2>

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

                                    <button type="button" id="start-cam-btn" onclick="startCamera()" class="z-10 bg-white/90 hover:bg-white text-slate-800 px-5 py-2.5 rounded shadow text-sm font-medium transition-all">Start Camera</button>
                                    <button type="button" id="capture-btn" onclick="capturePhoto()" class="hidden z-10 absolute bottom-3 bg-brand-900 text-white px-5 py-2.5 rounded shadow text-sm font-bold">Take Photo</button>
                                    <button type="button" id="retake-btn" onclick="retakePhoto()" class="hidden z-10 absolute bottom-3 bg-slate-800 text-white px-5 py-2.5 rounded shadow text-sm font-bold">Retake</button>
                                </div>
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

                            <button type="submit" id="btn-submit" class="w-full px-6 py-2.5 text-sm font-bold text-white bg-brand-900 hover:bg-brand-800 rounded-lg shadow-sm mt-4">Log Entry</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="p-4 sm:p-6 border-b border-slate-100 flex justify-between items-center">
                        <h2 class="text-lg font-bold text-slate-900 font-serif">Today's Visitors</h2>
                        <span class="text-xs text-slate-400">Auto-refreshes every 5s</span>
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
            </div>
        </div>
    </main>

    <script>
        const csrfToken = document.getElementById('csrf_token').value;
        let stream = null;
        let visitorRefreshTimer = null;

        document.addEventListener('DOMContentLoaded', () => {
            fetchVisitors();
            startVisitorPolling();
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    stopVisitorPolling();
                } else {
                    fetchVisitors();
                    startVisitorPolling();
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

        async function startCamera() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                showToast('Camera API requires HTTPS or localhost.', 'error');
                return;
            }
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                const video = document.getElementById('camera-stream');
                video.srcObject = stream;
                video.classList.remove('hidden');
                document.getElementById('start-cam-btn').classList.add('hidden');
                document.getElementById('capture-btn').classList.remove('hidden');
                document.getElementById('photo-preview').classList.add('hidden');
            } catch (err) {
                showToast('Camera access denied or unavailable.', 'error');
            }
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
            document.getElementById('capture-btn').classList.add('hidden');
            document.getElementById('retake-btn').classList.remove('hidden');
        }

        function retakePhoto() {
            document.getElementById('photo_base64').value = '';
            document.getElementById('photo-preview').classList.add('hidden');
            if (stream) {
                document.getElementById('camera-stream').classList.remove('hidden');
                document.getElementById('capture-btn').classList.remove('hidden');
            } else {
                document.getElementById('start-cam-btn').classList.remove('hidden');
            }
            document.getElementById('retake-btn').classList.add('hidden');
        }

        async function fetchVisitors() {
            const tbody = document.getElementById('visitors-tbody');
            try {
                const response = await fetch(appPath('/api/guard_get_visitors.php'));
                const result = await response.json();

                if (response.ok && result.status === 'success') {
                    if (result.data.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="4" class="text-center text-slate-500 py-8">No visitors logged today.</td></tr>`;
                        return;
                    }

                    const now = Date.now();

                    result.data.sort((a, b) => {
                        if (a.status === 'Entered' && b.status !== 'Entered') return -1;
                        if (a.status !== 'Entered' && b.status === 'Entered') return 1;
                        return (parseMySqlDate(b.entry_time)?.getTime() || 0) - (parseMySqlDate(a.entry_time)?.getTime() || 0);
                    });

                    tbody.innerHTML = result.data.map(vis => {
                        let actionCell = '';
                        let rowClass = 'hover:bg-slate-50 border-b border-slate-100 transition-colors';
                        let alertBadge = '';

                        const safeName = escapeHtml(vis.visitor_name || 'Guest');
                        const initial = (vis.visitor_name || '?').charAt(0).toUpperCase();
                        const photoSrc = vis.photo_url || '';
                        const avatarHTML = photoSrc
                            ? `<img src="${escapeHtml(photoSrc)}" class="w-10 h-10 min-w-[40px] rounded-full object-cover border border-slate-200 shadow-sm" alt="${safeName}">`
                            : `<div class="w-10 h-10 min-w-[40px] rounded-full bg-slate-200 flex items-center justify-center text-slate-500 font-bold shadow-sm">${escapeHtml(initial)}</div>`;

                        if (vis.status === 'Entered') {
                            actionCell = `<button type="button" onclick="markExit(${Number(vis.id)}, this)" class="px-4 py-1.5 text-xs font-bold text-white bg-slate-800 hover:bg-slate-700 shadow-sm transition-all rounded">Mark Exit</button>`;

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
            } catch (error) {
                tbody.innerHTML = `<tr><td colspan="4" class="text-center text-red-500 py-8">Error loading log.</td></tr>`;
            }
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
    </script>
</body>
</html>
