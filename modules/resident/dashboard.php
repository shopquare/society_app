<?php
// /society_app/modules/resident/dashboard.php

if (!isset($_SESSION['logged_in']) || !hasRole(3)) {
    header('Location: ' . appBasePath() . '/login');
    exit();
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/csrf.php';
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
        'SELECT visitor_name, pin_code, valid_date, status
         FROM sms_visitor_passes
         WHERE resident_id = :user_id AND society_id = :society_id
         ORDER BY created_at DESC LIMIT 5'
    );
    $passStmt->execute([':user_id' => $user_id, ':society_id' => $society_id]);
    $passes = $passStmt->fetchAll();
} catch (PDOException $e) {
    error_log('Resident dashboard passes load error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="manifest" href="<?php echo $base; ?>/manifest.json">
    <meta name="theme-color" content="#1E3A8A">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

    <nav class="bg-brand-900 shadow-md border-b border-brand-800 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-white/10 rounded flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    </div>
                    <span class="font-serif text-xl font-semibold text-white tracking-wide"><?php echo htmlspecialchars($societyName, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>

                <div class="flex items-center gap-4">
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

                    <div id="pin-result-box" class="hidden mt-6 p-4 bg-brand-50 border border-brand-200 rounded-lg text-center">
                        <div class="text-xs text-slate-500 uppercase tracking-wider font-semibold">Your Guest PIN</div>
                        <div id="generated-pin-display" class="text-3xl font-black text-brand-900 tracking-widest my-2">------</div>
                        <div class="text-xs text-emerald-700 font-medium">Pass created successfully! Share this code with your visitor.</div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2 space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 flex flex-col justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-800 mb-4 border-b border-slate-100 pb-2">Quick Actions</h2>
                            <div class="grid grid-cols-2 gap-3">
                                <button type="button" onclick="toggleModal('complaint-modal')" class="flex flex-col items-center justify-center p-4 bg-slate-50 hover:bg-brand-50 hover:text-brand-900 rounded-lg border border-slate-100 transition-colors group">
                                    <svg class="w-6 h-6 text-slate-400 group-hover:text-brand-900 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                                    <span class="text-xs font-medium text-center">Raise Complaint</span>
                                </button>
                                <button type="button" onclick="scrollToPassForm()" class="flex flex-col items-center justify-center p-4 bg-slate-50 hover:bg-brand-50 hover:text-brand-900 rounded-lg border border-slate-100 transition-colors group">
                                    <svg class="w-6 h-6 text-slate-400 group-hover:text-brand-900 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                                    <span class="text-xs font-medium text-center">Pre-approve Visitor</span>
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
                    <div class="p-4 sm:p-6 border-b border-slate-100"><h2 class="text-lg font-bold text-slate-900 font-serif">My Guest Passes</h2></div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm whitespace-nowrap">
                            <thead class="bg-slate-50 text-slate-600 font-medium border-b border-slate-200">
                                <tr>
                                    <th class="px-6 py-4">Visitor Name</th>
                                    <th class="px-6 py-4">PIN Code</th>
                                    <th class="px-6 py-4">Valid Date</th>
                                    <th class="px-6 py-4">Status</th>
                                </tr>
                            </thead>
                            <tbody id="guest-passes-tbody" class="divide-y divide-slate-100 text-slate-700">
                                <?php if (empty($passes)): ?>
                                    <tr id="empty-passes-row"><td colspan="4" class="px-6 py-8 text-center text-slate-500">No guest passes generated yet.</td></tr>
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

    <script>
        const csrfToken = document.getElementById('csrf_token').value;
        const vapidPublicKey = <?php echo json_encode((string)$vapidPublicKey); ?>;
        const residentUnitFallback = <?php echo json_encode((string)$unitNumber); ?>;

        let lastPendingCount = 0;
        let alertInterval = null;
        let visitorPollTimer = null;
        let isChimeMuted = false;

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

            if ('Notification' in window && Notification.permission === 'granted') {
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
            if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
                showToast('Push notifications are not supported by this browser.', 'warning');
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
                            ? `<img src="${escapeHtml(photoSrc)}" alt="${safeName}" class="w-16 h-16 min-w-[64px] rounded-lg object-cover border border-amber-300 shadow-sm">`
                            : `<div class="w-16 h-16 min-w-[64px] rounded-lg bg-amber-200 flex items-center justify-center font-bold text-amber-800 text-xl">${escapeHtml(initial)}</div>`;

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
                                    <button type="button" onclick="handleVisitorApproval(${Number(vis.id)}, 'Approve')" class="flex-1 sm:flex-none px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-lg shadow transition-all cursor-pointer">Approve Entry</button>
                                    <button type="button" onclick="handleVisitorApproval(${Number(vis.id)}, 'Decline')" class="flex-1 sm:flex-none px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded-lg shadow transition-all cursor-pointer">Deny</button>
                                </div>
                            </div>
                        `;
                    }).join('');
                } else {
                    stopContinuousAlert();
                    lastPendingCount = 0;
                    section.classList.add('hidden');
                    container.innerHTML = '';
                }
            } catch (err) {
                console.error('Polling error', err);
            }
        }

        async function handleVisitorApproval(visitorId, action) {
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
                    showToast(result.message || 'Decision recorded.', 'success');
                } else {
                    showToast(result.message || 'Unable to process decision.', 'error');
                }
            } catch (err) {
                showToast('Error connecting to server.', 'error');
            }
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

                    document.getElementById('generated-pin-display').textContent = result.pin_code;
                    document.getElementById('pin-result-box').classList.remove('hidden');

                    prependGuestPassRow(result.visitor_name || payload.visitor_name, result.pin_code, result.valid_date || payload.valid_date);
                    showToast('Guest pass generated!', 'success');
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
    </script>
</body>
</html>
