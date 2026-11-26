<?php
// modules/admin/navbar.php
// Permission-aware Responsive Admin Navbar + Centralized Notification Widget

require_once __DIR__ . '/../../includes/permissions.php';

$activeTab = $activeTab ?? 'pending';
$firstName = $_SESSION['first_name'] ?? 'Admin';
$roleLabel = $_SESSION['custom_role_name'] ?? (((int)($_SESSION['role_id'] ?? 2) === 1) ? 'Super Admin' : 'Committee Member');
$navCsrfToken = generateCsrfToken();
$navBase = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');

$navGroups = [
    [
        'label' => 'Operations',
        'tabs' => ['pending', 'users', 'gates', 'guards', 'buildings'],
        'items' => [
            ['tab' => 'pending', 'href' => '/admin/dashboard', 'label' => 'Pending Requests', 'perm' => 'residents.manage'],
            ['tab' => 'users', 'href' => '/admin/users', 'label' => 'Resident Directory', 'perm' => 'residents.manage'],
            ['tab' => 'gates', 'href' => '/admin/gates', 'label' => 'Gates & Handovers', 'perm' => 'gates.view'],
            ['tab' => 'guards', 'href' => '/admin/guards', 'label' => 'Security Staff', 'perm' => 'guards.manage'],
            ['tab' => 'buildings', 'href' => '/admin/buildings', 'label' => 'Buildings & Flats', 'perm' => 'buildings.manage'],
        ]
    ],
    [
        'label' => 'Community',
        'tabs' => ['notices', 'polls', 'events', 'community', 'complaints', 'services'],
        'items' => [
            ['tab' => 'notices', 'href' => '/admin/notices', 'label' => 'Notice Board', 'perm' => 'notices.manage'],
            ['tab' => 'polls', 'href' => '/admin/polls', 'label' => 'Polls & Voting', 'perm' => 'polls.manage'],
            ['tab' => 'events', 'href' => '/admin/events', 'label' => 'Events & Calendar', 'perm' => 'events.manage'],
            ['tab' => 'community', 'href' => '/admin/community', 'label' => 'Moderation & Lost/Found', 'perm' => 'community.moderate'],
            ['tab' => 'complaints', 'href' => '/admin/complaints', 'label' => 'Helpdesk', 'perm' => 'community.moderate'],
            ['tab' => 'services', 'href' => '/admin/services', 'label' => 'Local Services', 'perm' => 'services.manage'],
        ]
    ],
    [
        'label' => 'Finance & Facilities',
        'tabs' => ['financials', 'vendors', 'facilities', 'advertisements'],
        'items' => [
            ['tab' => 'financials', 'href' => '/admin/financials', 'label' => 'Financials & Budgets', 'perm' => 'finance.view'],
            ['tab' => 'vendors', 'href' => '/admin/vendors', 'label' => 'Vendors & Expenses', 'perm' => 'vendors.manage'],
            ['tab' => 'facilities', 'href' => '/admin/facilities', 'label' => 'Facilities & Bookings', 'perm' => 'facilities.manage'],
            ['tab' => 'advertisements', 'href' => '/admin/advertisements', 'label' => 'Company Ads', 'perm' => 'ads.manage'],
        ]
    ],
    [
        'label' => 'Governance',
        'tabs' => ['roles', 'documents', 'notifications', 'reports', 'settings', 'platform'],
        'items' => [
            ['tab' => 'roles', 'href' => '/admin/roles', 'label' => 'Roles & Admins', 'perm' => 'roles.manage'],
            ['tab' => 'documents', 'href' => '/admin/documents', 'label' => 'Digital Office & NOC', 'perm' => 'documents.manage'],
            ['tab' => 'notifications', 'href' => '/admin/notifications', 'label' => 'Broadcast & Logs', 'perm' => 'notifications.broadcast'],
            ['tab' => 'reports', 'href' => '/admin/reports', 'label' => 'Reports & Audit', 'perm' => 'reports.view'],
            ['tab' => 'settings', 'href' => '/admin/settings', 'label' => 'Society Settings', 'perm' => 'buildings.manage'],
            ['tab' => 'platform', 'href' => '/platform/dashboard', 'label' => 'SaaS Platform Console', 'perm' => 'saas.platform'],
        ]
    ]
];
?>
<nav class="bg-slate-900 shadow-md border-b border-slate-800 sticky top-0 z-50" style="padding-top: env(safe-area-inset-top, 0px);">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center gap-4">
                <a href="<?php echo $navBase; ?>/admin/dashboard" class="flex items-center gap-2.5 shrink-0">
                    <div class="w-8 h-8 bg-brand-900 rounded flex items-center justify-center shadow-inner">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <span class="font-serif text-lg sm:text-xl font-semibold text-white tracking-wide">Admin Portal</span>
                </a>

                <div class="hidden lg:flex items-center space-x-1 ml-2">
                    <?php foreach ($navGroups as $grp):
                        $visibleItems = array_filter($grp['items'], fn($it) => hasPermission($it['perm']));
                        if (empty($visibleItems)) continue;
                        $isGroupActive = in_array($activeTab, $grp['tabs'], true);
                    ?>
                    <div class="relative group">
                        <button type="button" class="px-3 py-2 rounded-md text-sm font-medium inline-flex items-center gap-1 transition-colors <?php echo $isGroupActive ? 'text-white bg-slate-800' : 'text-slate-300 hover:text-white hover:bg-slate-800/60'; ?>">
                            <span><?php echo htmlspecialchars($grp['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <svg class="w-3.5 h-3.5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="hidden group-hover:block absolute left-0 top-full pt-1 w-56 z-50">
                            <div class="bg-white rounded-xl shadow-xl border border-slate-200 py-1.5 text-slate-800">
                                <?php foreach ($visibleItems as $it): ?>
                                    <a href="<?php echo $navBase . $it['href']; ?>" class="block px-4 py-2 text-xs font-semibold <?php echo $activeTab === $it['tab'] ? 'bg-brand-50 text-brand-900 font-bold' : 'text-slate-700 hover:bg-slate-50'; ?>">
                                        <?php echo htmlspecialchars($it['label'], ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <a href="<?php echo $navBase; ?>/admin/advertisements" class="px-2.5 py-1.5 rounded-md text-xs font-bold text-amber-300 hover:text-amber-200 <?php echo $activeTab === 'advertisements' ? 'bg-slate-800' : ''; ?>">Company Ads</a>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <?php require_once __DIR__ . '/../../includes/notification_widget.php'; ?>
                <div class="text-right hidden sm:block">
                    <div class="text-sm font-medium text-white"><?php echo htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="text-[11px] text-slate-400"><?php echo htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <form action="<?php echo $navBase; ?>/api/logout.php" method="POST" class="hidden sm:block m-0">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($navCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" aria-label="Log Out" title="Log Out" class="p-2 text-slate-400 hover:text-white hover:bg-white/10 rounded-lg transition-colors cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    </button>
                </form>

                <div class="lg:hidden flex items-center">
                    <button id="mobile-menu-btn" type="button" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="mobile-menu" class="text-slate-400 hover:text-white focus:outline-none p-2 rounded-md hover:bg-slate-800">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="mobile-menu" class="hidden lg:hidden bg-slate-800 border-t border-slate-700 shadow-lg max-h-[75vh] overflow-y-auto">
        <div class="px-3 pt-2 pb-4 space-y-3">
            <?php foreach ($navGroups as $grp):
                $visibleItems = array_filter($grp['items'], fn($it) => hasPermission($it['perm']));
                if (empty($visibleItems)) continue;
            ?>
                <div>
                    <div class="px-2 py-1 text-[10px] font-black uppercase tracking-wider text-slate-400"><?php echo htmlspecialchars($grp['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php foreach ($visibleItems as $it): ?>
                        <a href="<?php echo $navBase . $it['href']; ?>" class="block px-3 py-1.5 rounded-md text-sm font-medium <?php echo $activeTab === $it['tab'] ? 'text-white bg-slate-900' : 'text-slate-300 hover:text-white hover:bg-slate-700'; ?>">
                            <?php echo htmlspecialchars($it['label'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <form action="<?php echo $navBase; ?>/api/logout.php" method="POST" class="pt-2 border-t border-slate-700">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($navCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="w-full text-left block px-3 py-2 rounded-md text-sm font-medium text-red-400 hover:text-red-300 hover:bg-slate-700">Log Out</button>
            </form>
        </div>
    </div>
</nav>

<script>
    document.getElementById('mobile-menu-btn')?.addEventListener('click', function () {
        const menu = document.getElementById('mobile-menu');
        const expanded = !menu.classList.contains('hidden');
        menu.classList.toggle('hidden');
        this.setAttribute('aria-expanded', String(!expanded));
    });
</script>
