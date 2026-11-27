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
        'SELECT id, visitor_name, pin_code, valid_date, valid_until, status, created_at
         FROM sms_visitor_passes
         WHERE resident_id = :user_id AND society_id = :society_id
         ORDER BY created_at DESC LIMIT 8'
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script>
    <link rel="stylesheet" href="<?php echo $base; ?>/assets/css/style.css?v=20261127">
    <script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans flex flex-col min-h-screen">

    <a href="#main-content" class="sg-skip-link sr-only focus-visible:not-sr-only">Skip to main content</a>
    <nav aria-label="Resident primary navigation" class="bg-brand-900 shadow-md border-b border-brand-800 sticky top-0 z-40" style="padding-top: env(safe-area-inset-top, 0px);">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 gap-2">
                <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                    <button type="button" onclick="toggleMobileSidebar()" class="lg:hidden p-2 -ml-1 text-brand-100 hover:text-white hover:bg-white/10 rounded-lg transition-colors cursor-pointer shrink-0" aria-label="Toggle navigation drawer">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                    <button type="button" onclick="toggleDesktopSidebar()" aria-label="Collapse or expand sidebar" class="hidden lg:flex p-2 text-brand-100 hover:text-white hover:bg-white/10 rounded-lg transition-colors cursor-pointer shrink-0" title="Collapse / Expand Sidebar">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16"></path></svg>
                    </button>
                    <div class="w-8 h-8 bg-white/10 rounded-lg hidden sm:flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    </div>
                    <span class="font-serif text-base sm:text-xl font-semibold text-white tracking-wide truncate"><?php echo htmlspecialchars($societyName, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>

                <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                    <button type="button" onclick="openEmergencyModal()" class="px-2.5 sm:px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs rounded-lg shadow-sm flex items-center gap-1.5 transition-colors cursor-pointer animate-pulse" title="Trigger Emergency Guard SOS" aria-label="Emergency SOS">
                        <svg class="w-4 h-4 text-white shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                        <span>SOS</span>
                    </button>
                    <a href="<?php echo $base; ?>/resident/financials" class="hidden sm:inline-flex text-sm font-medium text-brand-50 hover:text-white px-3 py-1.5 rounded-lg hover:bg-white/10 transition-colors">My Dues</a>
                    <?php require_once __DIR__ . '/../../includes/notification_widget.php'; ?>
                    <div class="text-right hidden sm:block leading-tight">
                        <div class="text-sm font-medium text-white truncate max-w-[140px]"><?php echo htmlspecialchars(trim($firstName . ' ' . $lastName), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="text-xs text-brand-100">Unit <?php echo htmlspecialchars((string)$unitNumber, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <form action="<?php echo $base; ?>/api/logout.php" method="POST" class="m-0">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" aria-label="Log Out" title="Log Out" class="p-2 text-brand-50 hover:text-white hover:bg-white/10 rounded-lg transition-colors cursor-pointer">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <!-- App Layout Container with Sidebar Navigation -->
    <div class="flex-grow flex">
        <!-- Collapsible Desktop Sidebar Navigation -->
        <aside id="resident-desktop-sidebar" class="hidden lg:flex flex-col w-64 bg-slate-900 text-slate-300 border-r border-slate-800 transition-all duration-300 select-none sticky top-16 h-[calc(100vh-4rem)] overflow-y-auto z-30">
            <div class="p-4 space-y-6 flex-1 text-xs">
                <div>
                    <div class="sidebar-label uppercase text-[10px] font-black tracking-wider text-slate-400 mb-2 px-3">Main</div>
                    <a href="#dashboard" onclick="scrollToTopView(); return false;" class="flex items-center gap-3 px-3 py-2 rounded-lg bg-brand-900 text-white font-bold transition shadow-xs">
                        <span class="text-base">📊</span>
                        <span class="sidebar-text">Dashboard</span>
                    </a>
                </div>

                <div>
                    <div class="sidebar-label uppercase text-[10px] font-black tracking-wider text-slate-400 mb-2 px-3">My Society</div>
                    <div class="space-y-1">
                        <a href="<?php echo $base; ?>/resident/facilities" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-white transition text-left">
                            <span class="text-base">🏊</span>
                            <span class="sidebar-text">Facilities & Slot Booking</span>
                        </a>
                        <button type="button" onclick="openRulesModal()" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-white transition text-left cursor-pointer">
                            <span class="text-base">📜</span>
                            <span class="sidebar-text">Rules & Guidelines</span>
                        </button>
                        <button type="button" onclick="openDirectoryModal()" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-white transition text-left cursor-pointer">
                            <span class="text-base">📞</span>
                            <span class="sidebar-text">Gate Intercom</span>
                        </button>
                    </div>
                </div>

                <div>
                    <div class="sidebar-label uppercase text-[10px] font-black tracking-wider text-slate-400 mb-2 px-3">Security & Access</div>
                    <div class="space-y-1">
                        <button type="button" onclick="scrollToPassForm()" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-white transition text-left cursor-pointer">
                            <span class="text-base">🔑</span>
                            <span class="sidebar-text">Guest Passes (48h)</span>
                        </button>
                        <button type="button" onclick="openDeliveryModal()" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-white transition text-left cursor-pointer">
                            <span class="text-base">📦</span>
                            <span class="sidebar-text">Delivery Pre-Approve</span>
                        </button>
                        <button type="button" onclick="scrollToVisitorHistory()" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-white transition text-left cursor-pointer">
                            <span class="text-base">👥</span>
                            <span class="sidebar-text">Visitor History</span>
                        </button>
                        <button type="button" onclick="openParcelsModal()" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-white transition text-left cursor-pointer">
                            <span class="text-base">📬</span>
                            <span class="sidebar-text">Gate Parcel Locker</span>
                        </button>
                    </div>
                </div>

                <div>
                    <div class="sidebar-label uppercase text-[10px] font-black tracking-wider text-slate-400 mb-2 px-3">Community</div>
                    <div class="space-y-1">
                        <a href="<?php echo $base; ?>/resident/community?tab=polls" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-white transition text-left">
                            <span class="text-base">🗳️</span>
                            <span class="sidebar-text">Polls & Voting</span>
                        </a>
                        <a href="<?php echo $base; ?>/resident/community?tab=events" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-white transition text-left">
                            <span class="text-base">🎉</span>
                            <span class="sidebar-text">Events & Calendar</span>
                        </a>
                        <a href="<?php echo $base; ?>/resident/community?tab=discussions" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-white transition text-left">
                            <span class="text-base">💬</span>
                            <span class="sidebar-text">Discussions & Forum</span>
                        </a>
                        <a href="<?php echo $base; ?>/resident/community?tab=lostfound" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-white transition text-left">
                            <span class="text-base">🔍</span>
                            <span class="sidebar-text">Lost & Found</span>
                        </a>
                        <a href="<?php echo $base; ?>/resident/community?tab=suggestions" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-white transition text-left">
                            <span class="text-base">💡</span>
                            <span class="sidebar-text">Suggestions Box</span>
                        </a>
                        <a href="<?php echo $base; ?>/resident/directory" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-white transition text-left">
                            <span class="text-base">📖</span>
                            <span class="sidebar-text">Resident Directory</span>
                        </a>
                        <a href="<?php echo $base; ?>/resident/services" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-white transition text-left">
                            <span class="text-base">🔧</span>
                            <span class="sidebar-text">Local Services</span>
                        </a>
                        <a href="<?php echo $base; ?>/resident/documents" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-white transition text-left">
                            <span class="text-base">📄</span>
                            <span class="sidebar-text">Digital Office &amp; NOC</span>
                        </a>
                        <button type="button" onclick="openOffersModal()" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 text-amber-300 hover:text-white transition text-left cursor-pointer font-bold">
                            <span class="text-base">🏷️</span>
                            <span class="sidebar-text">Offers & Deals</span>
                        </button>
                        <button type="button" onclick="openMarketplaceModal()" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-white transition text-left cursor-pointer">
                            <span class="text-base">🛍️</span>
                            <span class="sidebar-text">Buy & Sell Marketplace</span>
                        </button>
                        <button type="button" onclick="scrollToNotices()" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-white transition text-left cursor-pointer">
                            <span class="text-base">📢</span>
                            <span class="sidebar-text">Notice Board</span>
                        </button>
                        <button type="button" onclick="toggleModal('complaint-modal')" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-white transition text-left cursor-pointer">
                            <span class="text-base">✍️</span>
                            <span class="sidebar-text">Helpdesk Complaints</span>
                        </button>
                    </div>
                </div>

                <div>
                    <div class="sidebar-label uppercase text-[10px] font-black tracking-wider text-slate-400 mb-2 px-3">Services</div>
                    <div class="space-y-1">
                        <button type="button" onclick="openStaffModal()" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-white transition text-left cursor-pointer">
                            <span class="text-base">🧹</span>
                            <span class="sidebar-text">Daily Help Roster</span>
                        </button>
                        <button type="button" onclick="openVehiclesModal()" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-white transition text-left cursor-pointer">
                            <span class="text-base">🚗</span>
                            <span class="sidebar-text">Vehicles & Parking</span>
                        </button>
                    </div>
                </div>

                <div>
                    <div class="sidebar-label uppercase text-[10px] font-black tracking-wider text-slate-400 mb-2 px-3">Emergency</div>
                    <button type="button" onclick="openEmergencyModal()" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg bg-rose-900/60 hover:bg-rose-900 text-rose-200 border border-rose-800 transition text-left cursor-pointer font-bold">
                        <span class="text-base">🚨</span>
                        <span class="sidebar-text">Emergency SOS</span>
                    </button>
                </div>

                <div>
                    <div class="sidebar-label uppercase text-[10px] font-black tracking-wider text-slate-400 mb-2 px-3">Account</div>
                    <div class="space-y-1">
                        <button type="button" onclick="openFamilyModal()" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-white transition text-left cursor-pointer">
                            <span class="text-base">👨‍👩‍👧‍👦</span>
                            <span class="sidebar-text">Family Members</span>
                        </button>
                        <a href="<?php echo $base; ?>/resident/financials" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-white transition text-left">
                            <span class="text-base">💳</span>
                            <span class="sidebar-text">Maintenance Invoices</span>
                        </a>
                        <button type="button" onclick="openAdvertiseModal()" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-white transition text-left cursor-pointer text-amber-300">
                            <span class="text-base">📣</span>
                            <span class="sidebar-text">Advertise With Us</span>
                        </button>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Mobile Off-Canvas Drawer Backdrop -->
        <div id="resident-mobile-backdrop" onclick="toggleMobileSidebar()" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs hidden lg:hidden transition-opacity"></div>

        <!-- Mobile Off-Canvas Slide Drawer -->
        <aside id="resident-mobile-drawer" aria-label="Resident mobile navigation" class="fixed top-0 bottom-0 left-0 z-50 w-72 bg-slate-900 text-slate-300 border-r border-slate-800 -translate-x-full transition-transform duration-300 flex flex-col lg:hidden overflow-y-auto sg-scroll-thin">
            <div class="p-4 flex items-center justify-between border-b border-slate-800">
                <div>
                    <span class="font-serif text-lg font-bold text-white block"><?php echo htmlspecialchars($societyName, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="text-xs text-slate-400"><?php echo htmlspecialchars(trim($firstName . ' ' . $lastName), ENT_QUOTES, 'UTF-8'); ?> • Unit <?php echo htmlspecialchars((string)$unitNumber, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <button type="button" onclick="toggleMobileSidebar()" aria-label="Close navigation menu" class="p-2 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg cursor-pointer">✕</button>
            </div>
            <div class="p-4 space-y-5 flex-1 text-xs">
                <div>
                    <div class="uppercase text-[10px] font-black tracking-wider text-slate-400 mb-2 px-2">Main</div>
                    <button type="button" onclick="toggleMobileSidebar(); scrollToTopView();" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg bg-brand-900 text-white font-bold text-left cursor-pointer">
                        <span class="text-base">📊</span><span>Dashboard</span>
                    </button>
                </div>
                <div>
                    <div class="uppercase text-[10px] font-black tracking-wider text-slate-400 mb-2 px-2">My Society</div>
                    <div class="space-y-1">
                        <a href="<?php echo $base; ?>/resident/facilities" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 text-left">
                            <span class="text-base">🏊</span><span>Facilities &amp; Slot Booking</span>
                        </a>
                        <button type="button" onclick="toggleMobileSidebar(); openRulesModal();" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 text-left cursor-pointer">
                            <span class="text-base">📜</span><span>Rules &amp; Guidelines</span>
                        </button>
                        <button type="button" onclick="toggleMobileSidebar(); openDirectoryModal();" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 text-left cursor-pointer">
                            <span class="text-base">📞</span><span>Gate Intercom</span>
                        </button>
                    </div>
                </div>
                <div>
                    <div class="uppercase text-[10px] font-black tracking-wider text-slate-400 mb-2 px-2">Security & Access</div>
                    <div class="space-y-1">
                        <button type="button" onclick="toggleMobileSidebar(); scrollToPassForm();" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 text-left cursor-pointer">
                            <span class="text-base">🔑</span><span>Guest Passes (48h)</span>
                        </button>
                        <button type="button" onclick="toggleMobileSidebar(); openDeliveryModal();" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 text-left cursor-pointer">
                            <span class="text-base">📦</span><span>Delivery Pre-Approve</span>
                        </button>
                        <button type="button" onclick="toggleMobileSidebar(); scrollToVisitorHistory();" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 text-left cursor-pointer">
                            <span class="text-base">👥</span><span>Visitor History</span>
                        </button>
                        <button type="button" onclick="toggleMobileSidebar(); openParcelsModal();" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 text-left cursor-pointer">
                            <span class="text-base">📬</span><span>Parcel Locker</span>
                        </button>
                    </div>
                </div>
                <div>
                    <div class="uppercase text-[10px] font-black tracking-wider text-slate-400 mb-2 px-2">Community</div>
                    <div class="space-y-1">
                        <a href="<?php echo $base; ?>/resident/community?tab=polls" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 text-left">
                            <span class="text-base">🗳️</span><span>Polls, Events & Forum</span>
                        </a>
                        <a href="<?php echo $base; ?>/resident/directory" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 text-left">
                            <span class="text-base">📖</span><span>Resident Directory</span>
                        </a>
                        <a href="<?php echo $base; ?>/resident/services" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 text-left">
                            <span class="text-base">🔧</span><span>Local Services</span>
                        </a>
                        <a href="<?php echo $base; ?>/resident/documents" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 text-left">
                            <span class="text-base">📄</span><span>Digital Office &amp; NOC</span>
                        </a>
                        <button type="button" onclick="toggleMobileSidebar(); openOffersModal();" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 text-amber-300 hover:text-white text-left cursor-pointer font-bold">
                            <span class="text-base">🏷️</span><span>Offers & Deals</span>
                        </button>
                        <button type="button" onclick="toggleMobileSidebar(); openMarketplaceModal();" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 text-left cursor-pointer">
                            <span class="text-base">🛍️</span><span>Marketplace (Buy & Sell)</span>
                        </button>
                        <button type="button" onclick="toggleMobileSidebar(); scrollToNotices();" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 text-left cursor-pointer">
                            <span class="text-base">📢</span><span>Notice Board</span>
                        </button>
                        <button type="button" onclick="toggleMobileSidebar(); toggleModal('complaint-modal');" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 text-left cursor-pointer">
                            <span class="text-base">✍️</span><span>Helpdesk Complaints</span>
                        </button>
                    </div>
                </div>
                <div>
                    <div class="uppercase text-[10px] font-black tracking-wider text-slate-400 mb-2 px-2">Services</div>
                    <div class="space-y-1">
                        <button type="button" onclick="toggleMobileSidebar(); openStaffModal();" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 text-left cursor-pointer">
                            <span class="text-base">🧹</span><span>Daily Help Roster</span>
                        </button>
                        <button type="button" onclick="toggleMobileSidebar(); openVehiclesModal();" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 text-left cursor-pointer">
                            <span class="text-base">🚗</span><span>Vehicles & Parking</span>
                        </button>
                    </div>
                </div>
                <div>
                    <div class="uppercase text-[10px] font-black tracking-wider text-slate-400 mb-2 px-2">Emergency</div>
                    <button type="button" onclick="toggleMobileSidebar(); openEmergencyModal();" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg bg-rose-900/70 text-rose-200 border border-rose-800 text-left cursor-pointer font-bold">
                        <span class="text-base">🚨</span><span>Emergency SOS</span>
                    </button>
                </div>
                <div>
                    <div class="uppercase text-[10px] font-black tracking-wider text-slate-400 mb-2 px-2">Account</div>
                    <div class="space-y-1">
                        <button type="button" onclick="toggleMobileSidebar(); openFamilyModal();" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 text-left cursor-pointer">
                            <span class="text-base">👨‍👩‍👧‍👦</span><span>Family Members</span>
                        </button>
                        <a href="<?php echo $base; ?>/resident/financials" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 text-left">
                            <span class="text-base">💳</span><span>Maintenance Invoices</span>
                        </a>
                        <button type="button" onclick="toggleMobileSidebar(); openAdvertiseModal();" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800 text-left cursor-pointer text-amber-300">
                            <span class="text-base">📣</span><span>Advertise With Us</span>
                        </button>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main id="main-content" class="flex-1 min-w-0 px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
            <!-- Sponsored Brands Carousel (MyGate-Style) -->
            <div id="company-carousel-section" class="hidden mb-6 bg-white rounded-2xl p-4 shadow-sm border border-slate-200">
                <div class="flex items-center justify-between mb-3 px-1">
                    <div class="flex items-center gap-2">
                        <span class="text-base">🏷️</span>
                        <span class="font-bold text-xs uppercase tracking-wider text-slate-800 font-serif">Featured Brands & Offers</span>
                        <span class="bg-amber-100 text-amber-800 text-[10px] font-black px-2 py-0.5 rounded-full uppercase tracking-wider border border-amber-200">Sponsored</span>
                    </div>
                    <button type="button" onclick="openOffersModal()" class="text-xs font-bold text-brand-900 hover:text-brand-800 hover:underline flex items-center gap-1 cursor-pointer">
                        Explore All Offers →
                    </button>
                </div>
                <div id="company-carousel-track" class="flex items-center gap-3 overflow-x-auto pb-1 scrollbar-none snap-x">
                    <!-- Loaded dynamically -->
                </div>
            </div>

            <!-- Hero Company Advertisement Banner -->
            <div id="resident-ad-banner" class="hidden mb-6 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 rounded-2xl p-4 text-white border border-indigo-500/30 shadow-md relative overflow-hidden">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl" id="ad-banner-icon">📢</span>
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-indigo-300 flex items-center gap-2">
                                <span id="ad-banner-company">Community Sponsor</span>
                                <span id="ad-banner-badge" class="hidden bg-amber-400 text-slate-950 text-[9px] font-black px-1.5 py-0.2 rounded-full uppercase"></span>
                            </div>
                            <div id="ad-banner-title" class="font-bold text-white text-sm">Welcome to SecureGate Marketplace</div>
                        </div>
                    </div>
                    <a id="ad-banner-link" href="#" target="_blank" rel="noopener noreferrer" class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-lg shadow transition">Learn More →</a>
                </div>
            </div>

            <div id="active-sos-banner-container" class="hidden"></div>

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
                <button type="button" onclick="requestPushPermission()" id="btn-push-perm" class="px-3 py-2 bg-brand-900 text-white text-xs font-bold rounded-lg shadow-2xs hover:bg-brand-800 transition-colors cursor-pointer">Enable Push Alerts</button>
                <button type="button" onclick="testLoudAlert()" class="px-3 py-2 bg-rose-700 hover:bg-rose-800 text-white text-xs font-bold rounded-lg shadow-2xs flex items-center gap-1.5 transition-colors cursor-pointer"><span>🚨</span> Test Loud Gate Alert</button>
                <a href="<?php echo $base; ?>/resident/financials" class="sm:hidden px-3 py-2 bg-emerald-700 text-white text-xs font-bold rounded-lg shadow-2xs hover:bg-emerald-800 transition-colors">My Dues</a>
            </div>
        </div>

        <!-- Pending Action & Unit Summary Strip -->
        <?php
        $unpaidBillsCount = 0;
        $unpaidBillsTotal = 0.0;
        foreach ($invoices as $invRow) {
            if (($invRow['status'] ?? '') !== 'Paid') {
                $unpaidBillsCount++;
                $unpaidBillsTotal += (float)($invRow['amount'] ?? 0);
            }
        }
        $activePassCount = 0;
        foreach ($passes as $passRow) {
            $vu = !empty($passRow['valid_until']) ? strtotime($passRow['valid_until']) : 0;
            if (($passRow['status'] ?? '') === 'Active' && $vu > time()) {
                $activePassCount++;
            }
        }
        ?>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5 mb-8">
            <a href="<?php echo $base; ?>/resident/financials" class="sg-kpi-card p-4 block hover:border-brand-300">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Unpaid Dues</span>
                <div class="text-xl sm:text-2xl font-black <?php echo $unpaidBillsCount > 0 ? 'text-rose-700' : 'text-emerald-700'; ?> mt-1">₹<?php echo number_format($unpaidBillsTotal, 0); ?></div>
                <span class="text-xs text-slate-500 font-medium"><?php echo $unpaidBillsCount; ?> pending bill(s) →</span>
            </a>
            <button type="button" onclick="openParcelsModal()" class="sg-kpi-card p-4 text-left hover:border-amber-300 cursor-pointer">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Gate Parcels</span>
                <div class="text-xl sm:text-2xl font-black <?php echo count($waitingParcels) > 0 ? 'text-amber-600' : 'text-slate-800'; ?> mt-1"><?php echo count($waitingParcels); ?></div>
                <span class="text-xs text-slate-500 font-medium">Ready at security →</span>
            </button>
            <button type="button" onclick="scrollToPassForm()" class="sg-kpi-card p-4 text-left hover:border-brand-300 cursor-pointer">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Active Passes</span>
                <div class="text-xl sm:text-2xl font-black text-brand-900 mt-1"><?php echo $activePassCount; ?></div>
                <span class="text-xs text-slate-500 font-medium">48h Guest PINs →</span>
            </button>
            <button type="button" onclick="openVehiclesModal()" class="sg-kpi-card p-4 text-left hover:border-brand-300 cursor-pointer">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">My Vehicles</span>
                <div class="text-xl sm:text-2xl font-black text-slate-900 mt-1"><?php echo count($registeredVehicles); ?></div>
                <span class="text-xs text-slate-500 font-medium">Registered slots →</span>
            </button>
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

                            <div class="p-3 bg-amber-50 rounded-lg border border-amber-200 text-xs text-amber-800 flex items-start gap-2">
                                <svg class="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <span><strong>48-Hour Auto-Expiry:</strong> Passes expire automatically 48 hours from generation. Passes cannot be reused once entered.</span>
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
                                <button type="button" onclick="openOffersModal()" class="flex flex-col items-center justify-center p-3 bg-amber-50 hover:bg-amber-100 text-amber-900 rounded-lg border border-amber-200 transition-colors group cursor-pointer">
                                    <span class="text-xl mb-1">🏷️</span>
                                    <span class="text-xs font-bold text-center">Offers & Deals</span>
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
                                    <th class="px-5 py-3.5">Guest Name</th>
                                    <th class="px-5 py-3.5">Pass / PIN</th>
                                    <th class="px-5 py-3.5">Generated</th>
                                    <th class="px-5 py-3.5">Expires At</th>
                                    <th class="px-5 py-3.5">Validity</th>
                                    <th class="px-5 py-3.5">Status</th>
                                    <th class="px-5 py-3.5 text-center">Share</th>
                                </tr>
                            </thead>
                            <tbody id="guest-passes-tbody" class="divide-y divide-slate-100 text-slate-700">
                                <?php if (empty($passes)): ?>
                                    <tr id="empty-passes-row"><td colspan="7" class="px-6 py-8 text-center text-slate-500">No guest passes generated yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($passes as $p): ?>
                                        <?php
                                        $createdAtTs = !empty($p['created_at']) ? strtotime($p['created_at']) : time();
                                        $validUntilTs = !empty($p['valid_until']) ? strtotime($p['valid_until']) : ($createdAtTs + 48 * 3600);
                                        $nowTs = time();
                                        $diffSec = $validUntilTs - $nowTs;
                                        $isExpired = ($diffSec <= 0) || ($p['status'] === 'Expired');
                                        $effectiveStatus = $p['status'];
                                        if ($p['status'] === 'Active' && $isExpired) {
                                            $effectiveStatus = 'Expired';
                                        }

                                        if ($effectiveStatus === 'Used') {
                                            $remainingLabel = 'Pass Redeemed';
                                            $badgeClass = 'bg-emerald-50 text-emerald-700 border border-emerald-200';
                                        } elseif ($effectiveStatus === 'Expired') {
                                            $remainingLabel = 'Expired';
                                            $badgeClass = 'bg-slate-100 text-slate-600 border border-slate-200';
                                        } else {
                                            $remHours = floor($diffSec / 3600);
                                            $remMins = floor(($diffSec % 3600) / 60);
                                            $remainingLabel = $remHours > 0 ? "{$remHours}h {$remMins}m left" : "{$remMins}m left";
                                            $badgeClass = 'bg-blue-50 text-blue-700 border border-blue-200';
                                        }
                                        $formattedExpiry = date('d M Y, h:i A', $validUntilTs);
                                        $formattedGen = date('d M, h:i A', $createdAtTs);
                                        ?>
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-5 py-3.5 font-semibold text-slate-900"><?php echo htmlspecialchars($p['visitor_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="px-5 py-3.5 font-mono font-bold text-brand-900 tracking-wider"><?php echo htmlspecialchars($p['pin_code'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="px-5 py-3.5 text-xs text-slate-500"><?php echo htmlspecialchars($formattedGen, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="px-5 py-3.5 text-xs text-slate-500"><?php echo htmlspecialchars($formattedExpiry, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="px-5 py-3.5 text-xs font-medium <?php echo $isExpired ? 'text-slate-400' : 'text-amber-700 font-semibold'; ?>">
                                                <?php echo htmlspecialchars($remainingLabel, ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                            <td class="px-5 py-3.5">
                                                <span class="<?php echo $badgeClass; ?> px-2.5 py-1 rounded-full text-xs font-semibold">
                                                    <?php echo htmlspecialchars($effectiveStatus, ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            </td>
                                            <td class="px-5 py-3.5 text-center">
                                                <div class="flex items-center justify-center gap-1.5">
                                                    <button type="button" onclick="sharePassRowWhatsApp('<?php echo addslashes($p['visitor_name']); ?>', '<?php echo $p['pin_code']; ?>', '<?php echo addslashes($formattedExpiry); ?>')" class="p-1.5 bg-[#25D366] hover:bg-[#20bd5a] text-white rounded-md shadow-sm transition cursor-pointer" title="Share via WhatsApp">
                                                        <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                                                    </button>
                                                    <button type="button" onclick="copyPassRowText('<?php echo addslashes($p['visitor_name']); ?>', '<?php echo $p['pin_code']; ?>', '<?php echo addslashes($formattedExpiry); ?>')" class="p-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-md transition cursor-pointer" title="Copy Invite Text">
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
    </div>

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
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2" id="emergency-type-selector">
                            <label class="flex flex-col items-center justify-center p-2.5 rounded-xl border-2 border-rose-200 bg-rose-50/50 hover:bg-rose-100 cursor-pointer transition has-[:checked]:border-rose-600 has-[:checked]:bg-rose-100">
                                <input type="radio" name="sos_type" value="Medical Emergency" class="hidden">
                                <span class="text-xl mb-0.5">🩺</span>
                                <span class="text-[11px] font-bold text-rose-900 text-center">Medical</span>
                                <span class="text-[9px] text-slate-500 text-center">Doctor, Ambulance</span>
                            </label>
                            <label class="flex flex-col items-center justify-center p-2.5 rounded-xl border-2 border-rose-200 bg-rose-50/50 hover:bg-rose-100 cursor-pointer transition has-[:checked]:border-rose-600 has-[:checked]:bg-rose-100">
                                <input type="radio" name="sos_type" value="Fire Emergency" class="hidden">
                                <span class="text-xl mb-0.5">🔥</span>
                                <span class="text-[11px] font-bold text-rose-900 text-center">Fire</span>
                                <span class="text-[9px] text-slate-500 text-center">Smoke, Gas Leak</span>
                            </label>
                            <label class="flex flex-col items-center justify-center p-2.5 rounded-xl border-2 border-rose-200 bg-rose-50/50 hover:bg-rose-100 cursor-pointer transition has-[:checked]:border-rose-600 has-[:checked]:bg-rose-100">
                                <input type="radio" name="sos_type" value="Security Threat" checked class="hidden">
                                <span class="text-xl mb-0.5">🛡️</span>
                                <span class="text-[11px] font-bold text-rose-900 text-center">Security</span>
                                <span class="text-[9px] text-slate-500 text-center">Intruder, Threat</span>
                            </label>
                            <label class="flex flex-col items-center justify-center p-2.5 rounded-xl border-2 border-rose-200 bg-rose-50/50 hover:bg-rose-100 cursor-pointer transition has-[:checked]:border-rose-600 has-[:checked]:bg-rose-100">
                                <input type="radio" name="sos_type" value="Accident" class="hidden">
                                <span class="text-xl mb-0.5">💥</span>
                                <span class="text-[11px] font-bold text-rose-900 text-center">Accident</span>
                                <span class="text-[9px] text-slate-500 text-center">Fall, Injury</span>
                            </label>
                            <label class="flex flex-col items-center justify-center p-2.5 rounded-xl border-2 border-rose-200 bg-rose-50/50 hover:bg-rose-100 cursor-pointer transition has-[:checked]:border-rose-600 has-[:checked]:bg-rose-100">
                                <input type="radio" name="sos_type" value="Theft / Suspicious Activity" class="hidden">
                                <span class="text-xl mb-0.5">🦹</span>
                                <span class="text-[11px] font-bold text-rose-900 text-center">Theft / Suspicious</span>
                                <span class="text-[9px] text-slate-500 text-center">Robbery, Stalking</span>
                            </label>
                            <label class="flex flex-col items-center justify-center p-2.5 rounded-xl border-2 border-rose-200 bg-rose-50/50 hover:bg-rose-100 cursor-pointer transition has-[:checked]:border-rose-600 has-[:checked]:bg-rose-100">
                                <input type="radio" name="sos_type" value="Other Emergency" class="hidden">
                                <span class="text-xl mb-0.5">⚠️</span>
                                <span class="text-[11px] font-bold text-rose-900 text-center">Other SOS</span>
                                <span class="text-[9px] text-slate-500 text-center">Lift Stuck, Flood</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label for="sos_notes" class="block text-xs font-bold text-slate-700 mb-1">Emergency Description (Optional)</label>
                        <textarea id="sos_notes" rows="2" placeholder="Describe the emergency details to guide responding security staff..." class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-rose-500 resize-none"></textarea>
                    </div>

                    <!-- Confirmation safeguard against accidental trigger -->
                    <div class="p-3 bg-rose-50/70 border border-rose-200 rounded-xl space-y-2">
                        <label class="flex items-start gap-2.5 cursor-pointer">
                            <input type="checkbox" id="sos_confirm_check" required class="w-4 h-4 mt-0.5 text-rose-600 rounded border-rose-300 focus:ring-rose-500">
                            <span class="text-xs font-semibold text-rose-950 leading-tight">
                                I confirm this is an active emergency requiring immediate security & committee dispatch.
                            </span>
                        </label>
                        <div class="text-[10px] text-rose-700 leading-relaxed pl-6.5">
                            Notice: Triggering SOS sounds real-time audible alarms on all security guard terminals and alerts committee administrators. It does not automatically contact municipal 112/100/108 dispatch.
                        </div>
                    </div>

                    <div class="flex gap-3 pt-1">
                        <button type="button" onclick="closeEmergencyModal()" class="w-1/3 py-2.5 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg transition cursor-pointer">Cancel</button>
                        <button type="submit" id="btn-submit-sos" class="w-2/3 py-2.5 px-4 bg-rose-600 hover:bg-rose-700 text-white text-xs font-extrabold uppercase tracking-wider rounded-lg shadow-lg shadow-rose-200 transition animate-pulse cursor-pointer flex items-center justify-center gap-1.5">
                            <span>🚨 DISPATCH SOS ALARM</span>
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
            <div class="relative max-h-80 sm:max-h-96 flex items-center justify-center bg-slate-950 rounded-xl overflow-hidden border border-slate-800 p-2">
                <img id="lightbox-img" src="" alt="Visitor Photo" class="max-h-72 sm:max-h-96 w-auto max-w-full object-contain rounded-lg shadow-lg">
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

    <!-- 1. Society Facilities & Amenities Booking Modal -->
    <div id="facilities-modal" role="dialog" aria-modal="true" onclick="if (event.target === this) closeFacilitiesModal()" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex justify-center items-center p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl max-w-2xl w-full p-5 sm:p-6 shadow-2xl border border-slate-100 max-h-full overflow-y-auto my-auto sg-modal-panel sg-scroll-thin">
            <div class="flex justify-between items-center pb-4 border-b border-slate-100 mb-5">
                <div class="flex items-center gap-2.5">
                    <span class="text-2xl">🏊</span>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 font-serif">Society Facilities & Booking</h3>
                        <p class="text-xs text-slate-500">Reserve clubhouse, swimming pool, gym, and sports courts</p>
                    </div>
                </div>
                <button type="button" onclick="closeFacilitiesModal()" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg cursor-pointer">✕</button>
            </div>

            <div class="flex border-b border-slate-200 mb-5 gap-4 text-xs font-bold">
                <button type="button" id="fac-tab-list-btn" onclick="switchFacTab('list')" class="pb-2 border-b-2 border-brand-900 text-brand-900 cursor-pointer">Available Amenities</button>
                <button type="button" id="fac-tab-my-btn" onclick="switchFacTab('my')" class="pb-2 border-b-2 border-transparent text-slate-500 hover:text-slate-700 cursor-pointer">My Bookings</button>
            </div>

            <div id="fac-tab-list-content">
                <div id="facilities-list-container" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                    <div class="p-8 text-center text-slate-400 col-span-2">Loading amenities...</div>
                </div>

                <div id="fac-booking-form-card" class="hidden p-5 bg-slate-50 rounded-xl border border-slate-200">
                    <h4 id="fac-booking-form-title" class="font-bold text-sm text-slate-900 mb-3">Reserve Amenity</h4>
                    <form id="fac-booking-form" onsubmit="event.preventDefault(); submitFacilityBooking();" class="space-y-3">
                        <input type="hidden" id="fac_book_id">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Date</label>
                                <input type="date" id="fac_book_date" required class="w-full px-3 py-1.5 text-xs bg-white border border-slate-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Start Time</label>
                                <input type="time" id="fac_book_start" required class="w-full px-3 py-1.5 text-xs bg-white border border-slate-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">End Time</label>
                                <input type="time" id="fac_book_end" required class="w-full px-3 py-1.5 text-xs bg-white border border-slate-300 rounded-lg">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Notes / Occasion (Optional)</label>
                            <input type="text" id="fac_book_notes" placeholder="e.g. Birthday celebration, Family gathering" class="w-full px-3 py-1.5 text-xs bg-white border border-slate-300 rounded-lg">
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" onclick="cancelFacBookingForm()" class="px-3.5 py-1.5 text-xs text-slate-600 bg-white border border-slate-300 rounded-lg cursor-pointer">Cancel</button>
                            <button type="submit" id="fac_book_submit_btn" class="px-4 py-1.5 text-xs font-bold text-white bg-brand-900 hover:bg-brand-800 rounded-lg shadow-sm cursor-pointer">Confirm Reservation</button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="fac-tab-my-content" class="hidden space-y-3 max-h-80 overflow-y-auto">
                <div id="my-bookings-container">Loading bookings...</div>
            </div>
        </div>
    </div>

    <!-- 2. Family Members Management Modal -->
    <div id="family-modal" role="dialog" aria-modal="true" onclick="if (event.target === this) closeFamilyModal()" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex justify-center items-center p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl max-w-lg w-full p-5 sm:p-6 shadow-2xl border border-slate-100 max-h-full overflow-y-auto my-auto sg-modal-panel sg-scroll-thin">
            <div class="flex justify-between items-center pb-4 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2.5">
                    <span class="text-2xl">👨‍👩‍👧‍👦</span>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 font-serif">Unit Family Members</h3>
                        <p class="text-xs text-slate-500">Manage occupants living in Flat <?php echo htmlspecialchars((string)$unitNumber, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
                <button type="button" onclick="closeFamilyModal()" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg cursor-pointer">✕</button>
            </div>

            <form id="family-form" onsubmit="event.preventDefault(); submitAddFamilyMember();" class="p-4 bg-slate-50 rounded-xl border border-slate-200 mb-5 space-y-3">
                <div class="font-bold text-xs text-slate-800">Add New Member</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <div>
                        <label class="block text-[11px] font-medium text-slate-600 mb-1">Full Name *</label>
                        <input type="text" id="fam_name" required placeholder="e.g. Priya Sharma" class="w-full px-3 py-1.5 text-xs bg-white border border-slate-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-slate-600 mb-1">Relationship *</label>
                        <select id="fam_rel" required class="w-full px-3 py-1.5 text-xs bg-white border border-slate-300 rounded-lg">
                            <option value="Spouse">Spouse</option>
                            <option value="Child">Child / Son / Daughter</option>
                            <option value="Parent">Parent / Father / Mother</option>
                            <option value="Sibling">Sibling</option>
                            <option value="Relative">Other Relative</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <div>
                        <label class="block text-[11px] font-medium text-slate-600 mb-1">Phone Number (Optional)</label>
                        <input type="tel" id="fam_phone" placeholder="10-digit mobile number" class="w-full px-3 py-1.5 text-xs bg-white border border-slate-300 rounded-lg">
                    </div>
                    <div class="flex items-center pt-5">
                        <label class="flex items-center gap-2 cursor-pointer text-xs text-slate-700">
                            <input type="checkbox" id="fam_emergency" class="rounded text-brand-900 focus:ring-brand-900 w-4 h-4">
                            <span>Emergency Contact</span>
                        </label>
                    </div>
                </div>
                <button type="submit" id="fam_submit_btn" class="w-full py-2 bg-brand-900 hover:bg-brand-800 text-white font-bold text-xs rounded-lg shadow-sm transition cursor-pointer">
                    + Add Family Member
                </button>
            </form>

            <div class="font-bold text-xs text-slate-700 mb-2">Registered Occupants</div>
            <div id="family-list-container" class="space-y-2 max-h-56 overflow-y-auto">Loading...</div>
        </div>
    </div>

    <!-- 3. Domestic Daily Help Roster Modal -->
    <div id="staff-modal" role="dialog" aria-modal="true" onclick="if (event.target === this) closeStaffModal()" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex justify-center items-center p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl max-w-xl w-full p-5 sm:p-6 shadow-2xl border border-slate-100 max-h-full flex flex-col overflow-hidden my-auto sg-modal-panel">
            <div class="flex justify-between items-center pb-4 border-b border-slate-100 mb-4 shrink-0">
                <div class="flex items-center gap-2.5">
                    <span class="text-2xl">🧹</span>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 font-serif">Domestic Daily Help</h3>
                        <p class="text-xs text-slate-500">Verified society maids, drivers, cooks, and daily staff</p>
                    </div>
                </div>
                <button type="button" onclick="closeStaffModal()" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg cursor-pointer">✕</button>
            </div>

            <div class="text-xs text-slate-600 mb-3 bg-indigo-50 border border-indigo-200 p-2.5 rounded-lg flex items-center gap-2 shrink-0">
                <span>🛡️</span>
                <span>All domestic staff listed below undergo gate background verification by society management.</span>
            </div>

            <div id="staff-list-container" class="space-y-3 flex-auto min-h-0 overflow-y-auto sg-scroll-thin pr-1">
                <div class="p-6 text-center text-slate-400">Loading daily help directory...</div>
            </div>
        </div>
    </div>

    <!-- 4. Society Rules & Handbook Modal -->
    <div id="rules-modal" role="dialog" aria-modal="true" onclick="if (event.target === this) closeRulesModal()" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex justify-center items-center p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl max-w-xl w-full p-5 sm:p-6 shadow-2xl border border-slate-100 max-h-full overflow-y-auto my-auto sg-modal-panel sg-scroll-thin">
            <div class="flex justify-between items-center pb-4 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2.5">
                    <span class="text-2xl">📜</span>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 font-serif">Rules & Regulations</h3>
                        <p class="text-xs text-slate-500">Official Society Bylaws & Code of Conduct for <?php echo htmlspecialchars($societyName, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
                <button type="button" onclick="closeRulesModal()" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg cursor-pointer">✕</button>
            </div>

            <div class="space-y-4 text-xs text-slate-700">
                <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl">
                    <div class="font-bold text-slate-900 mb-1 flex items-center gap-2"><span>🔇</span> 1. Quiet Hours Policy</div>
                    <p class="text-slate-600">Quiet hours are strictly enforced between <strong>10:00 PM and 7:00 AM</strong> daily. High-volume music, party noise, and loud instruments are prohibited during these hours.</p>
                </div>
                <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl">
                    <div class="font-bold text-slate-900 mb-1 flex items-center gap-2"><span>🐶</span> 2. Pet Etiquette & Leashing</div>
                    <p class="text-slate-600">Pets must be kept on a leash at all times in common corridors, elevators, and gardens. Pet waste must be promptly bagged and disposed of in designated bins.</p>
                </div>
                <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl">
                    <div class="font-bold text-slate-900 mb-1 flex items-center gap-2"><span>🚗</span> 3. Parking Regulations</div>
                    <p class="text-slate-600">Park only in designated bay slots. Visitor cars must be registered at the security gate and parked in visitor bays for a maximum continuous stay of 24 hours.</p>
                </div>
                <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl">
                    <div class="font-bold text-slate-900 mb-1 flex items-center gap-2"><span>🔨</span> 4. Renovation & Carpentry Hours</div>
                    <p class="text-slate-600">Noisy drill work and carpentry are permitted only Monday to Friday, <strong>9:00 AM to 6:00 PM</strong>. No renovation work is allowed on Sundays or public holidays without committee consent.</p>
                </div>
                <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl">
                    <div class="font-bold text-slate-900 mb-1 flex items-center gap-2"><span>🗑️</span> 5. Waste Disposal & Segregation</div>
                    <p class="text-slate-600">Dry and wet waste must be segregated. E-waste and large discarded furniture must be scheduled with society housekeeping.</p>
                </div>
            </div>

            <button type="button" onclick="closeRulesModal()" class="w-full mt-5 py-2.5 bg-brand-900 hover:bg-brand-800 text-white text-xs font-bold rounded-xl transition cursor-pointer">Understood</button>
        </div>
    </div>

    <!-- 5. Society Marketplace (Buy & Sell) Modal -->
    <div id="marketplace-modal" role="dialog" aria-modal="true" onclick="if (event.target === this) closeMarketplaceModal()" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex justify-center items-center p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl max-w-4xl w-full p-5 sm:p-6 shadow-2xl border border-slate-100 max-h-full my-auto flex flex-col overflow-hidden sg-modal-panel">
            <div class="flex justify-between items-center pb-4 border-b border-slate-100 mb-4 shrink-0">
                <div class="flex items-center gap-2.5">
                    <span class="text-2xl">🛍️</span>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 font-serif">Society Marketplace (Buy & Sell)</h3>
                        <p class="text-xs text-slate-500">Exclusively for residents of <?php echo htmlspecialchars($societyName, ENT_QUOTES, 'UTF-8'); ?> • Intra-Society Verified</p>
                    </div>
                </div>
                <button type="button" onclick="closeMarketplaceModal()" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg cursor-pointer">✕</button>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 mb-4 border-b border-slate-200 pb-2 shrink-0">
                <div class="flex gap-4 text-xs font-bold">
                    <button type="button" id="mkt-tab-browse-btn" onclick="switchMktTab('browse')" class="pb-2 border-b-2 border-brand-900 text-brand-900 cursor-pointer">All Items</button>
                    <button type="button" id="mkt-tab-sell-btn" onclick="switchMktTab('sell')" class="pb-2 border-b-2 border-transparent text-slate-500 hover:text-slate-800 cursor-pointer">+ Post an Item</button>
                    <button type="button" id="mkt-tab-my-btn" onclick="switchMktTab('my')" class="pb-2 border-b-2 border-transparent text-slate-500 hover:text-slate-800 cursor-pointer">My Listings</button>
                </div>
                <div id="mkt-search-bar" class="w-full sm:w-64">
                    <input type="search" id="mkt-search-input" oninput="loadMarketplaceListings()" placeholder="Search items, furniture..." class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-900">
                </div>
            </div>

            <div id="mkt-tab-browse" class="flex-auto min-h-0 overflow-y-auto sg-scroll-thin pr-1">
                <div class="flex flex-wrap gap-1.5 mb-4 text-xs">
                    <button type="button" onclick="filterMktCategory('All', this)" class="mkt-cat-btn px-2.5 py-1 rounded-full font-bold bg-brand-900 text-white cursor-pointer">All</button>
                    <button type="button" onclick="filterMktCategory('Furniture', this)" class="mkt-cat-btn px-2.5 py-1 rounded-full font-bold bg-slate-100 text-slate-700 hover:bg-slate-200 cursor-pointer">🛋️ Furniture</button>
                    <button type="button" onclick="filterMktCategory('Electronics', this)" class="mkt-cat-btn px-2.5 py-1 rounded-full font-bold bg-slate-100 text-slate-700 hover:bg-slate-200 cursor-pointer">💻 Electronics</button>
                    <button type="button" onclick="filterMktCategory('Vehicles', this)" class="mkt-cat-btn px-2.5 py-1 rounded-full font-bold bg-slate-100 text-slate-700 hover:bg-slate-200 cursor-pointer">🚗 Vehicles</button>
                    <button type="button" onclick="filterMktCategory('Home Appliances', this)" class="mkt-cat-btn px-2.5 py-1 rounded-full font-bold bg-slate-100 text-slate-700 hover:bg-slate-200 cursor-pointer">🍳 Appliances</button>
                    <button type="button" onclick="filterMktCategory('Books', this)" class="mkt-cat-btn px-2.5 py-1 rounded-full font-bold bg-slate-100 text-slate-700 hover:bg-slate-200 cursor-pointer">📚 Books</button>
                    <button type="button" onclick="filterMktCategory('Kids & Toys', this)" class="mkt-cat-btn px-2.5 py-1 rounded-full font-bold bg-slate-100 text-slate-700 hover:bg-slate-200 cursor-pointer">🧸 Kids</button>
                </div>

                <div id="mkt-listings-grid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    <div class="col-span-3 text-center py-10 text-slate-400">Loading marketplace items...</div>
                </div>
            </div>

            <div id="mkt-tab-sell" class="hidden flex-auto min-h-0 overflow-y-auto sg-scroll-thin pr-1">
                <form id="mkt-sell-form" onsubmit="event.preventDefault(); submitMarketplaceListing();" class="space-y-4 max-w-xl mx-auto p-4 bg-slate-50 border border-slate-200 rounded-xl">
                    <h4 class="font-bold text-sm text-slate-900 border-b border-slate-200 pb-2">List an Item for Society Neighbors</h4>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Item Title *</label>
                        <input type="text" id="mkt_title" required placeholder="e.g. Solid Oak Dining Table (6 Seater)" class="w-full px-3 py-2 text-xs bg-white border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-900">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Category *</label>
                            <select id="mkt_category" required class="w-full px-3 py-2 text-xs bg-white border border-slate-300 rounded-lg">
                                <option value="Furniture">Furniture</option>
                                <option value="Electronics">Electronics</option>
                                <option value="Vehicles">Vehicles</option>
                                <option value="Home Appliances">Home Appliances</option>
                                <option value="Books">Books</option>
                                <option value="Sports & Fitness">Sports & Fitness</option>
                                <option value="Kids & Toys">Kids & Toys</option>
                                <option value="Fashion">Fashion</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Price (₹ INR) *</label>
                            <input type="number" id="mkt_price" required min="0" step="1" placeholder="e.g. 4500" class="w-full px-3 py-2 text-xs bg-white border border-slate-300 rounded-lg">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Item Condition</label>
                            <select id="mkt_condition" class="w-full px-3 py-2 text-xs bg-white border border-slate-300 rounded-lg">
                                <option value="Like New">Like New</option>
                                <option value="Good" selected>Good Condition</option>
                                <option value="Used">Used / Functional</option>
                                <option value="New">Brand New / Unopened</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Contact Phone</label>
                            <input type="tel" id="mkt_phone" placeholder="10-digit mobile number" class="w-full px-3 py-2 text-xs bg-white border border-slate-300 rounded-lg">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Item Photos (Up to 5 images)</label>
                        <input type="file" id="mkt_photos" multiple accept="image/*" class="w-full text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-slate-200 file:text-slate-800 hover:file:bg-slate-300 cursor-pointer">
                        <span class="text-[10px] text-slate-400">JPEG, PNG, WebP up to 10 MB per photo</span>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Description</label>
                        <textarea id="mkt_desc" rows="3" placeholder="Condition details, reason for selling, dimensions..." class="w-full px-3 py-2 text-xs bg-white border border-slate-300 rounded-lg"></textarea>
                    </div>
                    <button type="submit" id="mkt_submit_btn" class="w-full py-2.5 bg-brand-900 hover:bg-brand-800 text-white font-bold text-xs rounded-lg shadow-sm transition cursor-pointer">
                        Post Listing
                    </button>
                </form>
            </div>

            <div id="mkt-tab-my" class="hidden flex-auto min-h-0 overflow-y-auto sg-scroll-thin pr-1">
                <div id="mkt-my-listings-grid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    <div class="col-span-3 text-center py-10 text-slate-400">Loading your listings...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 6. Advertise With Us Modal -->
    <div id="advertise-modal" role="dialog" aria-modal="true" onclick="if (event.target === this) closeAdvertiseModal()" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex justify-center items-center p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl max-w-xl w-full p-5 sm:p-6 shadow-2xl border border-slate-100 max-h-full overflow-y-auto my-auto sg-modal-panel sg-scroll-thin">
            <div class="flex justify-between items-center pb-4 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2.5">
                    <span class="text-2xl">📣</span>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 font-serif">Advertise With Us</h3>
                        <p class="text-xs text-slate-500">Promote your business or service to society residents</p>
                    </div>
                </div>
                <button type="button" onclick="closeAdvertiseModal()" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg cursor-pointer">✕</button>
            </div>

            <form id="ad-submit-form" onsubmit="event.preventDefault(); submitResidentAd();" class="space-y-4 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 mb-1.5">Select Promotion Package</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="p-3 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer hover:border-brand-900 flex items-start gap-2">
                            <input type="radio" name="ad_package" value="1" checked class="mt-1">
                            <div>
                                <div class="font-bold text-slate-900">Weekly Spotlight (7 Days)</div>
                                <div class="text-brand-900 font-black text-sm">₹499</div>
                                <div class="text-[10px] text-slate-500">Banner on resident dashboard</div>
                            </div>
                        </label>
                        <label class="p-3 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer hover:border-brand-900 flex items-start gap-2">
                            <input type="radio" name="ad_package" value="2" class="mt-1">
                            <div>
                                <div class="font-bold text-slate-900">Monthly Campaign (30 Days)</div>
                                <div class="text-brand-900 font-black text-sm">₹1,499</div>
                                <div class="text-[10px] text-slate-500">Featured banner on dashboard & marketplace</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Campaign Headline / Business Name *</label>
                    <input type="text" id="ad_title" required placeholder="e.g. Home Baker Delicious Cakes & Catering" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg">
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Website or WhatsApp Link (Optional)</label>
                    <input type="url" id="ad_url" placeholder="https://wa.me/919876543210 or your website" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg">
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Banner Image (Optional)</label>
                    <input type="file" id="ad_banner_file" accept="image/*" class="w-full text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-slate-200 file:text-slate-800 hover:file:bg-slate-300 cursor-pointer">
                </div>

                <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-amber-900">
                    <div class="font-bold mb-1">Manual Payment Instructions</div>
                    <div class="text-[11px] space-y-1">
                        <div>Pay via UPI to: <strong class="font-mono text-slate-900">securegate@upi</strong> (or pay at society office).</div>
                        <div>Enter the UPI Transaction ID or Cheque reference below for verification:</div>
                    </div>
                    <input type="text" id="ad_payment_ref" required placeholder="Enter UPI Ref ID (e.g. 432189765421)" class="w-full mt-2 px-3 py-1.5 text-xs bg-white border border-amber-300 rounded-lg">
                </div>

                <button type="submit" id="ad_submit_btn" class="w-full py-2.5 bg-brand-900 hover:bg-brand-800 text-white font-bold text-xs rounded-xl shadow transition cursor-pointer">
                    Submit Campaign for Admin Verification
                </button>
            </form>
        </div>
    </div>

    <!-- 7. Offers & Deals Modal (MyGate-Style Company Advertisements) -->
    <div id="offers-deals-modal" role="dialog" aria-modal="true" onclick="if (event.target === this) closeOffersModal()" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex justify-center items-center p-3 sm:p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl max-w-4xl w-full p-4 sm:p-6 shadow-2xl border border-slate-100 max-h-full my-auto flex flex-col overflow-hidden sg-modal-panel">
            <div class="flex justify-between items-center pb-4 border-b border-slate-100 mb-4 shrink-0">
                <div class="flex items-center gap-2.5">
                    <span class="text-2xl">🏷️</span>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 font-serif">Offers & Deals for Residents</h3>
                        <p class="text-xs text-slate-500">Curated discounts, food delivery perks & neighborhood brand partnerships</p>
                    </div>
                </div>
                <button type="button" onclick="closeOffersModal()" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg cursor-pointer">✕</button>
            </div>

            <!-- Category Filter Pills -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-2 mb-4 scrollbar-none text-xs shrink-0">
                <button type="button" onclick="filterResidentOffers('all')" id="offer-pill-all" class="offer-pill-btn px-3 py-1.5 rounded-full font-bold bg-brand-900 text-white cursor-pointer transition whitespace-nowrap shrink-0">All Offers</button>
                <button type="button" onclick="filterResidentOffers('Food')" id="offer-pill-Food" class="offer-pill-btn px-3 py-1.5 rounded-full font-medium bg-slate-100 text-slate-700 hover:bg-slate-200 cursor-pointer transition whitespace-nowrap shrink-0">🍕 Food & Dining</button>
                <button type="button" onclick="filterResidentOffers('Grocery')" id="offer-pill-Grocery" class="offer-pill-btn px-3 py-1.5 rounded-full font-medium bg-slate-100 text-slate-700 hover:bg-slate-200 cursor-pointer transition whitespace-nowrap shrink-0">🥦 10-Min Grocery</button>
                <button type="button" onclick="filterResidentOffers('Shopping')" id="offer-pill-Shopping" class="offer-pill-btn px-3 py-1.5 rounded-full font-medium bg-slate-100 text-slate-700 hover:bg-slate-200 cursor-pointer transition whitespace-nowrap shrink-0">🛍️ E-Commerce</button>
                <button type="button" onclick="filterResidentOffers('Healthcare')" id="offer-pill-Healthcare" class="offer-pill-btn px-3 py-1.5 rounded-full font-medium bg-slate-100 text-slate-700 hover:bg-slate-200 cursor-pointer transition whitespace-nowrap shrink-0">🏥 Healthcare</button>
                <button type="button" onclick="filterResidentOffers('Services')" id="offer-pill-Services" class="offer-pill-btn px-3 py-1.5 rounded-full font-medium bg-slate-100 text-slate-700 hover:bg-slate-200 cursor-pointer transition whitespace-nowrap shrink-0">🔧 Home Services</button>
            </div>

            <!-- Offers Cards Grid -->
            <div id="resident-offers-grid" class="grid grid-cols-1 md:grid-cols-2 gap-4 flex-auto min-h-0 overflow-y-auto pr-1 auto-rows-max content-start sg-scroll-thin">
                <div class="col-span-full py-10 text-center text-slate-400 text-xs">Loading offers...</div>
            </div>
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

        // --- RESPONSIVE SIDEBAR NAVIGATION ---
        let isDesktopSidebarCollapsed = false;
        function toggleDesktopSidebar() {
            const sidebar = document.getElementById('resident-desktop-sidebar');
            if (!sidebar) return;
            isDesktopSidebarCollapsed = !isDesktopSidebarCollapsed;
            if (isDesktopSidebarCollapsed) {
                sidebar.classList.remove('w-64');
                sidebar.classList.add('w-16');
                sidebar.querySelectorAll('.sidebar-text, .sidebar-label').forEach(el => el.classList.add('hidden'));
            } else {
                sidebar.classList.remove('w-16');
                sidebar.classList.add('w-64');
                sidebar.querySelectorAll('.sidebar-text, .sidebar-label').forEach(el => el.classList.remove('hidden'));
            }
        }

        function toggleMobileSidebar() {
            const drawer = document.getElementById('resident-mobile-drawer');
            const backdrop = document.getElementById('resident-mobile-backdrop');
            if (!drawer || !backdrop) return;
            const isClosed = drawer.classList.contains('-translate-x-full');
            if (isClosed) {
                drawer.classList.remove('-translate-x-full');
                backdrop.classList.remove('hidden');
            } else {
                drawer.classList.add('-translate-x-full');
                backdrop.classList.add('hidden');
            }
        }

        function scrollToTopView() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        function scrollToPassForm() {
            const el = document.getElementById('guest-pass-card') || document.getElementById('pass-form');
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        function scrollToVisitorHistory() {
            const el = document.getElementById('visitor-history-table') || document.getElementById('recent-visitors-section');
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        function scrollToNotices() {
            const el = document.getElementById('notices-container');
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        // --- COMPANY ADVERTISEMENTS & OFFERS (MYGATE-STYLE) ---
        let allResidentOffers = [];
        let currentOfferCategory = 'all';

        async function loadResidentAdBanner() {
            try {
                const res = await fetch(appPath('/api/get_advertisements.php?page=resident_dashboard'));
                const json = await res.json();
                if (res.ok && json.status === 'success') {
                    const adPayload = json.data || json;
                    const carouselAds = adPayload.carousel || [];
                    const banners = adPayload.banners || adPayload.ads || [];
                    allResidentOffers = adPayload.offers || adPayload.ads || [];

                    // 1. Render Carousel
                    renderCompanyCarousel(carouselAds);

                    // 2. Render Hero Banner
                    if (banners.length > 0) {
                        const topAd = banners[0];
                        const bannerEl = document.getElementById('resident-ad-banner');
                        const titleEl = document.getElementById('ad-banner-title');
                        const compEl = document.getElementById('ad-banner-company');
                        const badgeEl = document.getElementById('ad-banner-badge');
                        const linkEl = document.getElementById('ad-banner-link');

                        if (bannerEl && titleEl && linkEl) {
                            titleEl.textContent = topAd.headline || topAd.title || (topAd.company_name + ' Deals');
                            if (compEl) compEl.textContent = topAd.company_name || topAd.advertiser_name || 'Sponsored';
                            if (badgeEl) {
                                if (topAd.offer_badge) {
                                    badgeEl.textContent = topAd.offer_badge;
                                    badgeEl.classList.remove('hidden');
                                } else {
                                    badgeEl.classList.add('hidden');
                                }
                            }
                            linkEl.textContent = `${topAd.cta_text || 'View Offer'} →`;
                            linkEl.onclick = (e) => {
                                e.preventDefault();
                                recordAdClickAndOpen(topAd.id, topAd.target_url, 'dashboard_banner');
                            };
                            bannerEl.classList.remove('hidden');

                            // Record impression
                            recordAdImpression(topAd.id, 'dashboard_banner');
                        }
                    }
                }
            } catch (e) {
                console.warn('Ad loader notice:', e);
            }
        }

        function renderCompanyCarousel(items) {
            const container = document.getElementById('company-carousel-section');
            const track = document.getElementById('company-carousel-track');
            if (!container || !track) return;

            if (!items || items.length === 0) {
                container.classList.add('hidden');
                return;
            }

            track.innerHTML = items.map(item => {
                const logoSrc = item.logo_url || `${appPath('/assets/img/app-logo.svg')}`;
                const badge = item.offer_badge ? `<span class="bg-amber-100 text-amber-900 text-[9px] font-black px-1.5 py-0.2 rounded-full border border-amber-200 uppercase">${escapeHtml(item.offer_badge)}</span>` : '';

                return `
                    <div onclick="recordAdClickAndOpen(${item.id}, '${escapeHtml(item.target_url || '#')}', 'carousel')" class="flex items-center gap-2.5 p-2 pr-3.5 bg-slate-50 hover:bg-slate-100 rounded-xl border border-slate-200 shadow-2xs transition shrink-0 cursor-pointer snap-start hover:border-brand-900 group">
                        <div class="w-8 h-8 rounded-lg bg-white border border-slate-200 flex items-center justify-center p-0.5 overflow-hidden shadow-2xs shrink-0 group-hover:scale-105 transition">
                            <img src="${escapeHtml(logoSrc)}" alt="${escapeHtml(item.company_name)}" class="w-full h-full object-contain">
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="font-bold text-xs text-slate-900 block truncate group-hover:text-brand-900">${escapeHtml(item.company_name)}</span>
                                ${badge}
                            </div>
                            <span class="text-[10px] text-slate-500 block truncate">${escapeHtml(item.cta_text || 'View Offer')}</span>
                        </div>
                    </div>
                `;
            }).join('');

            container.classList.remove('hidden');

            // Record impressions for carousel items
            items.forEach(it => recordAdImpression(it.id, 'carousel'));
        }

        function openOffersModal() {
            const m = document.getElementById('offers-deals-modal');
            if (m) m.classList.remove('hidden');
            renderOffersGrid(allResidentOffers);
        }

        function closeOffersModal() {
            const m = document.getElementById('offers-deals-modal');
            if (m) m.classList.add('hidden');
        }

        function filterResidentOffers(category) {
            currentOfferCategory = category;
            document.querySelectorAll('.offer-pill-btn').forEach(btn => {
                btn.classList.remove('bg-brand-900', 'text-white', 'font-bold');
                btn.classList.add('bg-slate-100', 'text-slate-700', 'font-medium');
            });
            const activeBtn = document.getElementById(`offer-pill-${category}`) || document.getElementById('offer-pill-all');
            if (activeBtn) {
                activeBtn.classList.remove('bg-slate-100', 'text-slate-700', 'font-medium');
                activeBtn.classList.add('bg-brand-900', 'text-white', 'font-bold');
            }

            if (category === 'all') {
                renderOffersGrid(allResidentOffers);
            } else {
                const filtered = allResidentOffers.filter(o => o.category === category);
                renderOffersGrid(filtered);
            }
        }

        function renderOffersGrid(offers) {
            const grid = document.getElementById('resident-offers-grid');
            if (!grid) return;

            if (!offers || offers.length === 0) {
                grid.innerHTML = `
                    <div class="col-span-full py-12 text-center text-slate-400">
                        <span class="text-3xl block mb-2">🏷️</span>
                        <span class="font-bold text-slate-600 block text-xs">No active promotions in this category</span>
                        <span class="text-[11px] text-slate-400 mt-1 block">Check back soon for exclusive society deals.</span>
                    </div>
                `;
                return;
            }

            grid.innerHTML = offers.map(ad => {
                const logoSrc = ad.logo_url || `${appPath('/assets/img/app-logo.svg')}`;
                const bannerSrc = ad.banner_url || '';
                const badge = ad.offer_badge ? `<span class="bg-amber-400 text-amber-950 font-black text-[10px] px-2 py-0.5 rounded-full shadow uppercase">${escapeHtml(ad.offer_badge)}</span>` : '';
                const phoneBtn = ad.contact_phone ? `
                    <a href="tel:${escapeHtml(ad.contact_phone)}" onclick="event.stopPropagation(); recordAdClickAndOpen(${ad.id}, 'tel:${escapeHtml(ad.contact_phone)}', 'phone_call')" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-lg transition flex items-center gap-1 cursor-pointer">
                        <span>📞</span> Call
                    </a>
                ` : '';

                return `
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition overflow-hidden flex flex-col justify-between shrink-0">
                        ${bannerSrc ? `
                            <div class="relative w-full h-36 bg-slate-900 overflow-hidden">
                                <img src="${escapeHtml(bannerSrc)}" alt="${escapeHtml(ad.headline)}" class="w-full h-full object-cover">
                                ${badge ? `<div class="absolute top-2.5 left-2.5">${badge}</div>` : ''}
                                <span class="absolute top-2.5 right-2.5 bg-black/60 backdrop-blur-xs text-white text-[9px] font-semibold px-1.5 py-0.5 rounded">
                                    Sponsored
                                </span>
                            </div>
                        ` : ''}

                        <div class="p-4 flex-1 flex flex-col justify-between space-y-3">
                            <div class="space-y-2">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg bg-slate-100 border border-slate-200 flex items-center justify-center p-0.5 overflow-hidden shrink-0">
                                        <img src="${escapeHtml(logoSrc)}" alt="${escapeHtml(ad.company_name)}" class="w-full h-full object-contain">
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <span class="font-bold text-xs text-slate-900 block truncate">${escapeHtml(ad.company_name)}</span>
                                        <span class="text-[10px] text-slate-500 block">${escapeHtml(ad.category || 'Deals')}</span>
                                    </div>
                                    ${(!bannerSrc && badge) ? badge : ''}
                                </div>

                                <h4 class="font-bold text-xs text-slate-900 leading-snug">${escapeHtml(ad.headline)}</h4>
                                <p class="text-[11px] text-slate-600 line-clamp-2">${escapeHtml(ad.body_text || '')}</p>
                            </div>

                            <div class="pt-2 border-t border-slate-100 flex items-center justify-between gap-2">
                                <span class="text-[10px] text-slate-400">Valid until ${escapeHtml(ad.end_date)}</span>
                                <div class="flex items-center gap-1.5">
                                    ${phoneBtn}
                                    <button type="button" onclick="recordAdClickAndOpen(${ad.id}, '${escapeHtml(ad.target_url || '#')}', 'offers_modal')" class="px-3.5 py-1.5 bg-brand-900 hover:bg-brand-800 text-white font-bold text-xs rounded-lg shadow-2xs transition flex items-center gap-1 cursor-pointer">
                                        <span>${escapeHtml(ad.cta_text || 'View Offer')}</span>
                                        <span>→</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function recordAdImpression(adId, placement) {
            try {
                navigator.sendBeacon(appPath('/api/track_ad_event.php'), JSON.stringify({
                    ad_id: adId,
                    event_type: 'impression',
                    placement: placement
                }));
            } catch (e) {
                fetch(appPath('/api/track_ad_event.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ad_id: adId, event_type: 'impression', placement: placement })
                }).catch(() => {});
            }
        }

        function recordAdClickAndOpen(adId, targetUrl, placement) {
            try {
                fetch(appPath('/api/track_ad_event.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ad_id: adId, event_type: 'click', placement: placement })
                }).catch(() => {});
            } catch (e) {}

            if (targetUrl && targetUrl !== '#' && !targetUrl.startsWith('tel:')) {
                window.open(targetUrl, '_blank', 'noopener,noreferrer');
            } else if (targetUrl && targetUrl.startsWith('tel:')) {
                window.location.href = targetUrl;
            }
        }

        // --- FACILITIES & AMENITIES ---
        function openFacilitiesModal() {
            const m = document.getElementById('facilities-modal');
            if (m) m.classList.remove('hidden');
            loadFacilities();
        }
        function closeFacilitiesModal() {
            const m = document.getElementById('facilities-modal');
            if (m) m.classList.add('hidden');
            cancelFacBookingForm();
        }
        function switchFacTab(tab) {
            const listContent = document.getElementById('fac-tab-list-content');
            const myContent = document.getElementById('fac-tab-my-content');
            const listBtn = document.getElementById('fac-tab-list-btn');
            const myBtn = document.getElementById('fac-tab-my-btn');
            if (tab === 'list') {
                listContent.classList.remove('hidden');
                myContent.classList.add('hidden');
                listBtn.className = 'pb-2 border-b-2 border-brand-900 text-brand-900 cursor-pointer';
                myBtn.className = 'pb-2 border-b-2 border-transparent text-slate-500 hover:text-slate-700 cursor-pointer';
            } else {
                listContent.classList.add('hidden');
                myContent.classList.remove('hidden');
                listBtn.className = 'pb-2 border-b-2 border-transparent text-slate-500 hover:text-slate-700 cursor-pointer';
                myBtn.className = 'pb-2 border-b-2 border-brand-900 text-brand-900 cursor-pointer';
            }
        }
        async function loadFacilities() {
            try {
                const res = await fetch(appPath('/api/resident_facility_booking.php'));
                const json = await res.json();
                if (res.ok && json.status === 'success') {
                    const fPayload = json.data || json;
                    renderFacilities(fPayload.facilities || []);
                    renderMyBookings(fPayload.my_bookings || []);
                }
            } catch (e) {
                document.getElementById('facilities-list-container').innerHTML = '<div class="col-span-2 text-center text-red-500 py-6">Failed to load facilities.</div>';
            }
        }
        function renderFacilities(facilities) {
            const c = document.getElementById('facilities-list-container');
            if (!facilities.length) {
                c.innerHTML = '<div class="col-span-2 text-center text-slate-400 py-6">No facilities available.</div>';
                return;
            }
            c.innerHTML = facilities.map(f => `
                <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <h4 class="font-bold text-slate-900 text-sm">${escapeHtml(f.name)}</h4>
                            <span class="text-[10px] font-black px-2 py-0.5 rounded-full ${Number(f.booking_cost_per_slot) > 0 ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-900'}">${Number(f.booking_cost_per_slot) > 0 ? '₹' + Number(f.booking_cost_per_slot) : 'Free'}</span>
                        </div>
                        <p class="text-[11px] text-slate-600 mb-2">${escapeHtml(f.description || '')}</p>
                        <div class="text-[10px] text-slate-500 space-y-0.5 mb-3">
                            <div>👥 Capacity: <strong>${f.capacity || 'Open'}</strong> persons</div>
                            ${f.rules ? `<div>⚠️ ${escapeHtml(f.rules)}</div>` : ''}
                        </div>
                    </div>
                    <button type="button" onclick="showFacBookingForm(${f.id}, '${escapeHtml(f.name)}')" class="w-full py-1.5 bg-brand-900 hover:bg-brand-800 text-white font-bold text-xs rounded-lg shadow-xs transition cursor-pointer">
                        Reserve Slot
                    </button>
                </div>
            `).join('');
        }
        function renderMyBookings(bookings) {
            const c = document.getElementById('my-bookings-container');
            if (!bookings.length) {
                c.innerHTML = '<div class="text-center text-slate-400 py-6 text-xs">You have no upcoming facility bookings.</div>';
                return;
            }
            c.innerHTML = bookings.map(b => `
                <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between text-xs">
                    <div>
                        <div class="font-bold text-slate-900">${escapeHtml(b.facility_name)}</div>
                        <div class="text-slate-500 text-[11px]">📅 ${escapeHtml(b.booking_date)} • ⏰ ${escapeHtml(b.start_time.slice(0,5))} - ${escapeHtml(b.end_time.slice(0,5))}</div>
                        ${b.notes ? `<div class="text-slate-400 text-[10px] italic">${escapeHtml(b.notes)}</div>` : ''}
                    </div>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-black ${b.status === 'Approved' ? 'bg-emerald-100 text-emerald-800' : (b.status === 'Pending' ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-700')}">${escapeHtml(b.status)}</span>
                </div>
            `).join('');
        }
        function showFacBookingForm(id, name) {
            document.getElementById('fac_book_id').value = id;
            document.getElementById('fac-booking-form-title').textContent = `Reserve: ${name}`;
            document.getElementById('fac-booking-form-card').classList.remove('hidden');
            const dInput = document.getElementById('fac_book_date');
            if (dInput && !dInput.value) {
                dInput.value = new Date().toISOString().split('T')[0];
            }
            document.getElementById('fac-booking-form-card').scrollIntoView({ behavior: 'smooth' });
        }
        function cancelFacBookingForm() {
            const card = document.getElementById('fac-booking-form-card');
            if (card) card.classList.add('hidden');
            const form = document.getElementById('fac-booking-form');
            if (form) form.reset();
        }
        async function submitFacilityBooking() {
            const btn = document.getElementById('fac_book_submit_btn');
            btn.disabled = true;
            btn.textContent = 'Reserving...';
            const payload = {
                facility_id: parseInt(document.getElementById('fac_book_id').value, 10),
                booking_date: document.getElementById('fac_book_date').value,
                start_time: document.getElementById('fac_book_start').value,
                end_time: document.getElementById('fac_book_end').value,
                notes: document.getElementById('fac_book_notes').value.trim(),
                csrf_token: csrfToken
            };
            try {
                const res = await fetch(appPath('/api/resident_facility_booking.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const json = await res.json();
                if (res.ok && json.status === 'success') {
                    showToast(json.message || 'Facility booked successfully!', 'success');
                    cancelFacBookingForm();
                    loadFacilities();
                    switchFacTab('my');
                } else {
                    showToast(json.message || 'Booking conflict or error.', 'error');
                }
            } catch (e) {
                showToast('Network error while booking facility.', 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Confirm Reservation';
            }
        }

        // --- FAMILY MEMBERS ---
        function openFamilyModal() {
            const m = document.getElementById('family-modal');
            if (m) m.classList.remove('hidden');
            loadFamilyMembers();
        }
        function closeFamilyModal() {
            const m = document.getElementById('family-modal');
            if (m) m.classList.add('hidden');
        }
        async function loadFamilyMembers() {
            const c = document.getElementById('family-list-container');
            try {
                const res = await fetch(appPath('/api/resident_manage_family.php'));
                const json = await res.json();
                if (res.ok && json.status === 'success') {
                    const family = json.data || [];
                    if (!family.length) {
                        c.innerHTML = '<div class="text-center text-slate-400 py-4 text-xs">No family members registered yet.</div>';
                        return;
                    }
                    c.innerHTML = family.map(f => `
                        <div class="p-3 bg-white border border-slate-200 rounded-xl flex items-center justify-between text-xs shadow-2xs">
                            <div class="flex items-center gap-2.5">
                                <span class="text-lg">👤</span>
                                <div>
                                    <div class="font-bold text-slate-900 flex items-center gap-1.5">
                                        <span>${escapeHtml(f.name)}</span>
                                        ${Number(f.is_emergency_contact) ? '<span class="bg-rose-100 text-rose-800 text-[10px] font-bold px-1.5 py-0.2 rounded">🚨 SOS Contact</span>' : ''}
                                    </div>
                                    <div class="text-[11px] text-slate-500">${escapeHtml(f.relationship)} ${f.phone_number ? '• ' + escapeHtml(f.phone_number) : ''}</div>
                                </div>
                            </div>
                            <button type="button" onclick="deleteFamilyMember(${f.id})" class="text-rose-600 hover:text-rose-800 text-xs font-bold px-2 py-1 rounded hover:bg-rose-50 cursor-pointer">Remove</button>
                        </div>
                    `).join('');
                }
            } catch (e) {
                c.innerHTML = '<div class="text-center text-red-500 py-4 text-xs">Error loading family members.</div>';
            }
        }
        async function submitAddFamilyMember() {
            const btn = document.getElementById('fam_submit_btn');
            btn.disabled = true;
            const payload = {
                name: document.getElementById('fam_name').value.trim(),
                relationship: document.getElementById('fam_rel').value,
                phone_number: document.getElementById('fam_phone').value.trim(),
                is_emergency_contact: document.getElementById('fam_emergency').checked ? 1 : 0,
                csrf_token: csrfToken
            };
            try {
                const res = await fetch(appPath('/api/resident_manage_family.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const json = await res.json();
                if (res.ok && json.status === 'success') {
                    showToast(json.message || 'Family member added.', 'success');
                    document.getElementById('family-form').reset();
                    loadFamilyMembers();
                } else {
                    showToast(json.message || 'Failed to add member.', 'error');
                }
            } catch (e) {
                showToast('Network error.', 'error');
            } finally {
                btn.disabled = false;
            }
        }
        async function deleteFamilyMember(id) {
            const ok = await showConfirmDialog({
                title: 'Remove Family Member',
                message: 'Are you sure you want to remove this person from your household directory?',
                confirmText: 'Remove',
                danger: true
            });
            if (!ok) return;
            try {
                const res = await fetch(appPath('/api/resident_manage_family.php'), {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, csrf_token: csrfToken })
                });
                const json = await res.json();
                if (res.ok && json.status === 'success') {
                    showToast(json.message || 'Member removed.', 'success');
                    loadFamilyMembers();
                } else {
                    showToast(json.message || 'Error removing member.', 'error');
                }
            } catch (e) {
                showToast('Network error.', 'error');
            }
        }

        // --- DOMESTIC DAILY HELP ROSTER ---
        function openStaffModal() {
            const m = document.getElementById('staff-modal');
            if (m) m.classList.remove('hidden');
            loadStaffRoster();
        }
        function closeStaffModal() {
            const m = document.getElementById('staff-modal');
            if (m) m.classList.add('hidden');
        }
        async function loadStaffRoster() {
            const c = document.getElementById('staff-list-container');
            try {
                const res = await fetch(appPath('/api/resident_manage_staff.php'));
                const json = await res.json();
                if (res.ok && json.status === 'success') {
                    const staff = json.data || [];
                    if (!staff.length) {
                        c.innerHTML = '<div class="text-center text-slate-400 py-6 text-xs">No daily staff directory entries available.</div>';
                        return;
                    }
                    c.innerHTML = staff.map(s => `
                        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-10 h-10 rounded-full bg-slate-200 text-slate-700 flex items-center justify-center font-bold text-sm shrink-0">
                                    ${escapeHtml((s.name || '?').charAt(0).toUpperCase())}
                                </div>
                                <div class="min-w-0">
                                    <div class="font-bold text-slate-900 flex flex-wrap items-center gap-1.5">
                                        <span>${escapeHtml(s.name)}</span>
                                        <span class="bg-indigo-100 text-indigo-900 text-[10px] font-bold px-1.5 py-0.5 rounded">${escapeHtml(s.role_type)}</span>
                                        ${Number(s.is_verified) ? '<span class="text-emerald-600 font-bold" title="Gate Background Verified">✓ Verified</span>' : ''}
                                    </div>
                                    <div class="text-[11px] text-slate-500 mt-0.5">
                                        ⭐ ${Number(s.rating || 4.8).toFixed(1)} rating • 📞 <a href="tel:${escapeHtml(s.phone_number)}" class="text-brand-900 font-medium hover:underline">${escapeHtml(s.phone_number)}</a>
                                    </div>
                                </div>
                            </div>
                            <div class="shrink-0">
                                ${Number(s.is_my_staff) 
                                    ? `<button type="button" onclick="toggleStaffAssignment(${s.id}, 'remove')" class="w-full sm:w-auto whitespace-nowrap px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 font-bold text-xs rounded-lg transition cursor-pointer">Remove</button>`
                                    : `<button type="button" onclick="toggleStaffAssignment(${s.id}, 'assign')" class="w-full sm:w-auto whitespace-nowrap px-3 py-1.5 bg-brand-900 hover:bg-brand-800 text-white font-bold text-xs rounded-lg shadow-xs transition cursor-pointer">+ Add to My Unit</button>`
                                }
                            </div>
                        </div>
                    `).join('');
                }
            } catch (e) {
                c.innerHTML = '<div class="text-center text-red-500 py-6 text-xs">Failed to load staff roster.</div>';
            }
        }
        async function toggleStaffAssignment(id, action) {
            try {
                const res = await fetch(appPath('/api/resident_manage_staff.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ staff_id: id, action: action, csrf_token: csrfToken })
                });
                const json = await res.json();
                if (res.ok && json.status === 'success') {
                    showToast(json.message || 'Staff roster updated.', 'success');
                    loadStaffRoster();
                } else {
                    showToast(json.message || 'Error updating staff.', 'error');
                }
            } catch (e) {
                showToast('Network error.', 'error');
            }
        }

        // --- SOCIETY RULES HANDBOOK ---
        function openRulesModal() {
            const m = document.getElementById('rules-modal');
            if (m) m.classList.remove('hidden');
        }
        function closeRulesModal() {
            const m = document.getElementById('rules-modal');
            if (m) m.classList.add('hidden');
        }

        // --- SOCIETY MARKETPLACE (BUY & SELL) ---
        let currentMktCategory = 'All';

        function openMarketplaceModal() {
            const m = document.getElementById('marketplace-modal');
            if (m) m.classList.remove('hidden');
            loadMarketplaceListings();
        }
        function closeMarketplaceModal() {
            const m = document.getElementById('marketplace-modal');
            if (m) m.classList.add('hidden');
        }
        function switchMktTab(tab) {
            const browseTab = document.getElementById('mkt-tab-browse');
            const sellTab = document.getElementById('mkt-tab-sell');
            const myTab = document.getElementById('mkt-tab-my');
            const searchBar = document.getElementById('mkt-search-bar');
            const browseBtn = document.getElementById('mkt-tab-browse-btn');
            const sellBtn = document.getElementById('mkt-tab-sell-btn');
            const myBtn = document.getElementById('mkt-tab-my-btn');

            [browseTab, sellTab, myTab].forEach(el => el.classList.add('hidden'));
            [browseBtn, sellBtn, myBtn].forEach(el => {
                el.className = 'pb-2 border-b-2 border-transparent text-slate-500 hover:text-slate-800 cursor-pointer';
            });

            if (tab === 'browse') {
                browseTab.classList.remove('hidden');
                if (searchBar) searchBar.classList.remove('hidden');
                browseBtn.className = 'pb-2 border-b-2 border-brand-900 text-brand-900 cursor-pointer';
                loadMarketplaceListings();
            } else if (tab === 'sell') {
                sellTab.classList.remove('hidden');
                if (searchBar) searchBar.classList.add('hidden');
                sellBtn.className = 'pb-2 border-b-2 border-brand-900 text-brand-900 cursor-pointer';
            } else if (tab === 'my') {
                myTab.classList.remove('hidden');
                if (searchBar) searchBar.classList.add('hidden');
                myBtn.className = 'pb-2 border-b-2 border-brand-900 text-brand-900 cursor-pointer';
                loadMyMarketplaceListings();
            }
        }
        function filterMktCategory(cat, btn) {
            currentMktCategory = cat;
            document.querySelectorAll('.mkt-cat-btn').forEach(b => {
                b.className = 'mkt-cat-btn px-2.5 py-1 rounded-full font-bold bg-slate-100 text-slate-700 hover:bg-slate-200 cursor-pointer';
            });
            if (btn) btn.className = 'mkt-cat-btn px-2.5 py-1 rounded-full font-bold bg-brand-900 text-white cursor-pointer';
            loadMarketplaceListings();
        }
        async function loadMarketplaceListings() {
            const grid = document.getElementById('mkt-listings-grid');
            const search = (document.getElementById('mkt-search-input')?.value || '').trim();
            let url = appPath(`/api/marketplace_get_listings.php?category=${encodeURIComponent(currentMktCategory)}&search=${encodeURIComponent(search)}`);
            try {
                const res = await fetch(url);
                const json = await res.json();
                if (res.ok && json.status === 'success') {
                    const items = json.data || [];
                    if (!items.length) {
                        grid.innerHTML = '<div class="col-span-3 text-center py-10 text-slate-400 text-xs">No items currently listed in this category.</div>';
                        return;
                    }
                    grid.innerHTML = items.map(item => `
                        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-2xs hover:shadow-md transition flex flex-col justify-between">
                            <div>
                                <div class="relative bg-slate-100 aspect-video flex items-center justify-center overflow-hidden">
                                    ${item.image_url 
                                        ? `<img src="${escapeHtml(item.image_url)}" alt="${escapeHtml(item.title)}" class="w-full h-full object-cover">`
                                        : `<span class="text-3xl text-slate-300">📦</span>`
                                    }
                                    <span class="absolute top-2 left-2 bg-slate-900/80 text-white text-[10px] font-bold px-2 py-0.5 rounded backdrop-blur-xs">${escapeHtml(item.category)}</span>
                                    <span class="absolute top-2 right-2 bg-emerald-600 text-white text-[10px] font-black px-2 py-0.5 rounded shadow">${escapeHtml(item.condition_type)}</span>
                                </div>
                                <div class="p-3">
                                    <div class="text-brand-900 font-black text-sm mb-0.5">${escapeHtml(item.formatted_price)}</div>
                                    <h4 class="font-bold text-slate-900 text-xs line-clamp-1 mb-1">${escapeHtml(item.title)}</h4>
                                    <p class="text-[11px] text-slate-500 line-clamp-2 mb-2">${escapeHtml(item.description || 'No description provided.')}</p>
                                    <div class="text-[10px] text-slate-400">Seller: Flat ${escapeHtml(item.unit_number || 'Resident')} • ${escapeHtml(item.seller_name || '')}</div>
                                </div>
                            </div>
                            <div class="p-3 pt-0 flex items-center gap-1.5">
                                ${item.contact_phone 
                                    ? `<a href="https://wa.me/91${escapeHtml(item.contact_phone.replace(/\D/g, ''))}?text=${encodeURIComponent('Hi neighbor! I am interested in your listing: ' + item.title)}" target="_blank" class="flex-1 py-1.5 bg-[#25D366] hover:bg-[#20bd5a] text-white font-bold text-[11px] rounded-lg text-center transition">Chat</a>`
                                    : ''
                                }
                                ${item.contact_phone 
                                    ? `<a href="tel:${escapeHtml(item.contact_phone)}" class="py-1.5 px-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[11px] rounded-lg text-center transition">📞</a>`
                                    : ''
                                }
                                <button type="button" onclick="saveMarketplaceListing(${item.id}, this)" class="py-1.5 px-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] rounded-lg cursor-pointer" title="Save Favorite">
                                    ${Number(item.is_saved) ? '❤️' : '🤍'}
                                </button>
                                <button type="button" onclick="reportMarketplaceListing(${item.id})" class="py-1.5 px-2 bg-slate-50 hover:bg-rose-50 text-slate-400 hover:text-rose-600 text-[11px] rounded-lg cursor-pointer" title="Report Inappropriate">
                                    🚩
                                </button>
                            </div>
                        </div>
                    `).join('');
                }
            } catch (e) {
                grid.innerHTML = '<div class="col-span-3 text-center py-10 text-red-500 text-xs">Failed to load marketplace listings.</div>';
            }
        }
        async function loadMyMarketplaceListings() {
            const grid = document.getElementById('mkt-my-listings-grid');
            try {
                const res = await fetch(appPath('/api/marketplace_get_listings.php?my_listings=1'));
                const json = await res.json();
                if (res.ok && json.status === 'success') {
                    const items = json.data || [];
                    if (!items.length) {
                        grid.innerHTML = '<div class="col-span-3 text-center py-10 text-slate-400 text-xs">You have no active listings. Post your first item!</div>';
                        return;
                    }
                    grid.innerHTML = items.map(item => `
                        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-2xs flex flex-col justify-between">
                            <div class="p-3">
                                <div class="flex items-center justify-between gap-2 mb-1">
                                    <span class="text-brand-900 font-black text-sm">${escapeHtml(item.formatted_price)}</span>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ${item.status === 'Sold' ? 'bg-slate-200 text-slate-700' : 'bg-emerald-100 text-emerald-800'}">${escapeHtml(item.status)}</span>
                                </div>
                                <h4 class="font-bold text-slate-900 text-xs line-clamp-1">${escapeHtml(item.title)}</h4>
                            </div>
                            <div class="p-3 pt-0 flex gap-2">
                                ${item.status !== 'Sold' 
                                    ? `<button type="button" onclick="markMarketplaceSold(${item.id})" class="flex-1 py-1.5 bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs rounded-lg cursor-pointer">Mark Sold</button>`
                                    : ''
                                }
                                <button type="button" onclick="deleteMarketplaceListing(${item.id})" class="py-1.5 px-3 bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold text-xs rounded-lg cursor-pointer">Delete</button>
                            </div>
                        </div>
                    `).join('');
                }
            } catch (e) {
                grid.innerHTML = '<div class="col-span-3 text-center py-10 text-red-500 text-xs">Error loading your listings.</div>';
            }
        }
        async function submitMarketplaceListing() {
            const btn = document.getElementById('mkt_submit_btn');
            btn.disabled = true;
            btn.textContent = 'Posting...';

            const photoFiles = document.getElementById('mkt_photos').files;
            const photoPromises = [];
            for (let i = 0; i < Math.min(photoFiles.length, 5); i++) {
                const f = photoFiles[i];
                photoPromises.push(new Promise((resolve) => {
                    const reader = new FileReader();
                    reader.onload = (e) => resolve(e.target.result);
                    reader.readAsDataURL(f);
                }));
            }
            const base64Photos = await Promise.all(photoPromises);

            const payload = {
                title: document.getElementById('mkt_title').value.trim(),
                category: document.getElementById('mkt_category').value,
                price: parseFloat(document.getElementById('mkt_price').value),
                condition_type: document.getElementById('mkt_condition').value,
                contact_phone: document.getElementById('mkt_phone').value.trim(),
                description: document.getElementById('mkt_desc').value.trim(),
                photos: base64Photos,
                csrf_token: csrfToken
            };

            try {
                const res = await fetch(appPath('/api/marketplace_create_listing.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const json = await res.json();
                if (res.ok && json.status === 'success') {
                    showToast(json.message || 'Item listed on Society Marketplace!', 'success');
                    document.getElementById('mkt-sell-form').reset();
                    switchMktTab('browse');
                } else {
                    showToast(json.message || 'Failed to post listing.', 'error');
                }
            } catch (e) {
                showToast('Network error while posting item.', 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Post Listing';
            }
        }
        async function markMarketplaceSold(id) {
            try {
                const res = await fetch(appPath('/api/marketplace_action.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ listing_id: id, action: 'mark_sold', csrf_token: csrfToken })
                });
                const json = await res.json();
                if (res.ok && json.status === 'success') {
                    showToast(json.message || 'Item marked as sold.', 'success');
                    loadMyMarketplaceListings();
                }
            } catch (e) {
                showToast('Error marking item as sold.', 'error');
            }
        }
        async function deleteMarketplaceListing(id) {
            const ok = await showConfirmDialog({
                title: 'Delete Marketplace Listing',
                message: 'Are you sure you want to permanently remove this listing?',
                confirmText: 'Delete',
                danger: true
            });
            if (!ok) return;
            try {
                const res = await fetch(appPath('/api/marketplace_action.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ listing_id: id, action: 'delete', csrf_token: csrfToken })
                });
                const json = await res.json();
                if (res.ok && json.status === 'success') {
                    showToast(json.message || 'Listing removed.', 'success');
                    loadMyMarketplaceListings();
                }
            } catch (e) {
                showToast('Error removing listing.', 'error');
            }
        }
        async function saveMarketplaceListing(id, btn) {
            try {
                const res = await fetch(appPath('/api/marketplace_action.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ listing_id: id, action: 'save', csrf_token: csrfToken })
                });
                const json = await res.json();
                if (res.ok && json.status === 'success') {
                    showToast(json.message, 'success');
                    if (btn) btn.textContent = json.data?.saved ? '❤️' : '🤍';
                }
            } catch (e) {
                showToast('Error saving item.', 'error');
            }
        }
        async function reportMarketplaceListing(id) {
            const reason = await showPromptDialog({
                title: 'Report Marketplace Listing',
                message: 'Please specify why this listing is being reported (e.g., inappropriate content, prohibited item, misleading price):',
                placeholder: 'Enter reason for society committee review...',
                confirmText: 'Submit Report'
            });
            if (!reason) return;
            try {
                const res = await fetch(appPath('/api/marketplace_action.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ listing_id: id, action: 'report', reason: reason, csrf_token: csrfToken })
                });
                const json = await res.json();
                if (res.ok && json.status === 'success') {
                    showToast(json.message || 'Report submitted to administration.', 'info');
                }
            } catch (e) {
                showToast('Error reporting listing.', 'error');
            }
        }

        // --- RESIDENT PAID ADVERTISEMENT ("ADVERTISE WITH US") ---
        function openAdvertiseModal() {
            const m = document.getElementById('advertise-modal');
            if (m) m.classList.remove('hidden');
        }
        function closeAdvertiseModal() {
            const m = document.getElementById('advertise-modal');
            if (m) m.classList.add('hidden');
        }
        async function submitResidentAd() {
            const btn = document.getElementById('ad_submit_btn');
            btn.disabled = true;
            btn.textContent = 'Submitting Campaign...';

            const file = document.getElementById('ad_banner_file').files[0];
            let bannerBase64 = '';
            if (file) {
                bannerBase64 = await new Promise((resolve) => {
                    const reader = new FileReader();
                    reader.onload = (e) => resolve(e.target.result);
                    reader.readAsDataURL(file);
                });
            }

            const packageRadio = document.querySelector('input[name="ad_package"]:checked');
            const payload = {
                package_id: packageRadio ? parseInt(packageRadio.value, 10) : 1,
                title: document.getElementById('ad_title').value.trim(),
                target_url: document.getElementById('ad_url').value.trim(),
                payment_method: 'UPI',
                payment_reference: document.getElementById('ad_payment_ref').value.trim(),
                banner_base64: bannerBase64,
                csrf_token: csrfToken
            };

            try {
                const res = await fetch(appPath('/api/resident_submit_ad.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const json = await res.json();
                if (res.ok && json.status === 'success') {
                    showToast(json.message || 'Campaign submitted! Management will verify your payment.', 'success');
                    document.getElementById('ad-submit-form').reset();
                    closeAdvertiseModal();
                } else {
                    showToast(json.message || 'Submission error.', 'error');
                }
            } catch (e) {
                showToast('Network error while submitting ad.', 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Submit Campaign for Admin Verification';
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const validDateEl = document.getElementById('valid_date');
            if (validDateEl) validDateEl.value = new Date().toISOString().split('T')[0];
            fetchNotices();
            fetchMyTickets();
            checkPendingVisitors();
            startVisitorPolling();
            registerPwaServiceWorker();
            loadResidentAdBanner();

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
                        const verifiedUnit = vis.unit_number || residentUnitFallback;
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
                    if (document.getElementById('valid_date')) {
                        document.getElementById('valid_date').value = new Date().toISOString().split('T')[0];
                    }

                    lastGeneratedPass = {
                        visitor_name: result.visitor_name || payload.visitor_name,
                        pin_code: result.pin_code,
                        formatted_expiry: result.formatted_expiry || '48 hours from generation',
                        created_at: result.created_at || new Date().toISOString()
                    };

                    document.getElementById('generated-pin-display').textContent = result.pin_code;
                    document.getElementById('pin-result-box').classList.remove('hidden');

                    prependGuestPassRow(lastGeneratedPass.visitor_name, lastGeneratedPass.pin_code, lastGeneratedPass.formatted_expiry);
                    showToast('Guest pass generated! Ready to share on WhatsApp (48-hr validity).', 'success');
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

        function prependGuestPassRow(visitorName, pinCode, formattedExpiry) {
            const tbody = document.getElementById('guest-passes-tbody');
            const emptyRow = document.getElementById('empty-passes-row');
            if (emptyRow) emptyRow.remove();

            const tr = document.createElement('tr');
            tr.className = 'hover:bg-slate-50';
            tr.innerHTML = `
                <td class="px-5 py-3.5 font-semibold text-slate-900">${escapeHtml(visitorName)}</td>
                <td class="px-5 py-3.5 font-mono font-bold text-brand-900 tracking-wider">${escapeHtml(pinCode)}</td>
                <td class="px-5 py-3.5 text-xs text-slate-500">Just Now</td>
                <td class="px-5 py-3.5 text-xs text-slate-500">${escapeHtml(formattedExpiry)}</td>
                <td class="px-5 py-3.5 text-xs text-amber-700 font-semibold">48h 0m left</td>
                <td class="px-5 py-3.5"><span class="bg-blue-50 text-blue-700 px-2.5 py-1 rounded-full text-xs font-semibold border border-blue-200">Active</span></td>
                <td class="px-5 py-3.5 text-center">
                    <div class="flex items-center justify-center gap-1.5">
                        <button type="button" onclick="sharePassRowWhatsApp('${escapeHtml(visitorName)}', '${escapeHtml(pinCode)}', '${escapeHtml(formattedExpiry)}')" class="p-1.5 bg-[#25D366] hover:bg-[#20bd5a] text-white rounded-md shadow-sm transition cursor-pointer" title="Share via WhatsApp">
                            <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                        </button>
                        <button type="button" onclick="copyPassRowText('${escapeHtml(visitorName)}', '${escapeHtml(pinCode)}', '${escapeHtml(formattedExpiry)}')" class="p-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-md transition cursor-pointer" title="Copy Invite Text">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                        </button>
                    </div>
                </td>
            `;
            tbody.prepend(tr);
            while (tbody.rows.length > 8) {
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
        const societyNameFallback = "<?php echo htmlspecialchars($societyName ?? 'Sunshine Residency', ENT_QUOTES, 'UTF-8'); ?>";

        function buildPassInviteMessage(visitorName, pinCode, expiryStr) {
            const soc = societyNameFallback || 'Sunshine Residency';
            const expText = expiryStr ? `at ${expiryStr}` : 'after 48 hours from generation';
            return `Hello! You have been invited to visit ${soc}.\n\n` +
                   `Guest Pass: ${pinCode}\n\n` +
                   `This guest pass is valid for 48 hours from the time of generation and will automatically expire ${expText}.\n\n` +
                   `Please share this pass with security at the entrance.\n\n` +
                   `Thank you!`;
        }

        function sharePassRowWhatsApp(visitorName, pinCode, expiryStr) {
            const text = buildPassInviteMessage(visitorName, pinCode, expiryStr);
            const waUrl = `https://api.whatsapp.com/send?text=${encodeURIComponent(text)}`;
            window.open(waUrl, '_blank');
        }

        function copyPassRowText(visitorName, pinCode, expiryStr) {
            const text = buildPassInviteMessage(visitorName, pinCode, expiryStr);
            copyToClipboard(text, 'Guest pass invite copied to clipboard!');
        }

        function shareGeneratedPassWhatsApp() {
            if (!lastGeneratedPass) return;
            sharePassRowWhatsApp(lastGeneratedPass.visitor_name, lastGeneratedPass.pin_code, lastGeneratedPass.formatted_expiry);
        }

        function copyGeneratedPassText() {
            if (!lastGeneratedPass) return;
            copyPassRowText(lastGeneratedPass.visitor_name, lastGeneratedPass.pin_code, lastGeneratedPass.formatted_expiry);
        }

        function copyCurrentPin() {
            if (!lastGeneratedPass) return;
            copyToClipboard(lastGeneratedPass.pin_code, `PIN ${lastGeneratedPass.pin_code} copied!`);
        }

        async function shareGeneratedPassNative() {
            if (!lastGeneratedPass) return;
            const text = buildPassInviteMessage(lastGeneratedPass.visitor_name, lastGeneratedPass.pin_code, lastGeneratedPass.formatted_expiry);
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
            const confirmCheck = document.getElementById('sos_confirm_check');
            if (confirmCheck && !confirmCheck.checked) {
                showToast('Please confirm the emergency confirmation checkbox before dispatching.', 'error');
                return;
            }

            const btn = document.getElementById('btn-submit-sos');
            btn.innerText = 'DISPATCHING ALARM...';
            btn.disabled = true;

            const selectedRadio = document.querySelector('input[name="sos_type"]:checked');
            const alertType = selectedRadio ? selectedRadio.value : 'Security Threat';
            const notes = document.getElementById('sos_notes').value.trim();

            let coords = { latitude: null, longitude: null };

            // Quick non-blocking geolocation lookup (max 1.5s timeout)
            if (navigator.geolocation) {
                try {
                    const pos = await new Promise((resolve) => {
                        navigator.geolocation.getCurrentPosition(resolve, () => resolve(null), { timeout: 1500, maximumAge: 60000 });
                    });
                    if (pos && pos.coords) {
                        coords.latitude = pos.coords.latitude;
                        coords.longitude = pos.coords.longitude;
                    }
                } catch (e) {}
            }

            try {
                const response = await fetch(appPath('/api/resident_trigger_emergency.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        alert_type: alertType,
                        description: notes,
                        notes: notes,
                        latitude: coords.latitude,
                        longitude: coords.longitude,
                        csrf_token: csrfToken
                    })
                });

                const result = await response.json();
                if (response.ok && result.status === 'success') {
                    closeEmergencyModal();
                    document.getElementById('emergency-form').reset();
                    showToast('🚨 SOS Dispatched! Security guards have been alerted with siren.', 'warning');
                    checkActiveEmergencyStatus();
                } else {
                    showToast(result.message || 'Failed to dispatch SOS.', 'error');
                }
            } catch (e) {
                showToast('Network error triggering emergency alert.', 'error');
            } finally {
                btn.innerText = '🚨 DISPATCH SOS ALARM';
                btn.disabled = false;
            }
        }

        async function checkActiveEmergencyStatus() {
            try {
                const res = await fetch(appPath('/api/resident_get_emergency_status.php'));
                const data = await res.json();
                const container = document.getElementById('active-sos-banner-container');
                if (!container) return;

                if (data.status === 'success' && data.has_active && data.alert) {
                    const a = data.alert;
                    let statusColor = 'bg-rose-600 text-white';
                    let statusText = 'ACTIVE ALARM - Siren ringing at gate terminal';
                    if (a.status === 'Acknowledged') {
                        statusColor = 'bg-amber-600 text-white';
                        statusText = `ACKNOWLEDGED by Security${a.guard_first_name ? ' (' + a.guard_first_name + ')' : ''} - Help on the way`;
                    } else if (a.status === 'Responding') {
                        statusColor = 'bg-blue-600 text-white';
                        statusText = 'RESPONDING - Guard is proceeding to your flat';
                    }

                    container.innerHTML = `
                        <div class="${statusColor} p-4 rounded-xl shadow-lg mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-3 animate-pulse">
                            <div class="flex items-center gap-3">
                                <span class="text-2xl">🚨</span>
                                <div>
                                    <div class="font-extrabold text-sm uppercase tracking-wide">Emergency SOS: ${escapeHtml(a.alert_type)}</div>
                                    <div class="text-xs opacity-95">${statusText}</div>
                                </div>
                            </div>
                            <div class="text-xs font-semibold bg-white/20 px-3 py-1.5 rounded-lg text-center self-start sm:self-auto">
                                Status: ${escapeHtml(a.status)}
                            </div>
                        </div>
                    `;
                    container.classList.remove('hidden');
                } else {
                    container.classList.add('hidden');
                    container.innerHTML = '';
                }
            } catch (e) {}
        }

        // Poll for emergency status updates every 10 seconds
        setInterval(checkActiveEmergencyStatus, 10000);
        document.addEventListener('DOMContentLoaded', checkActiveEmergencyStatus);

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
            const ok = await showConfirmDialog({
                title: 'Cancel Delivery Instruction',
                message: 'Are you sure you want to cancel this delivery pre-approval at the gate?',
                confirmText: 'Cancel Instruction',
                danger: true
            });
            if (!ok) return;
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
            const ok = await showConfirmDialog({
                title: 'Remove Vehicle',
                message: 'Are you sure you want to remove this vehicle from your flat registration?',
                confirmText: 'Remove Vehicle',
                danger: true
            });
            if (!ok) return;

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
