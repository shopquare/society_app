<?php
// modules/admin/navbar.php
// Permission-aware Responsive Admin Navbar + Accessible Dropdowns + Centralized Notification Widget

require_once __DIR__ . '/../../includes/permissions.php';

$activeTab = $activeTab ?? 'pending';
$firstName = $_SESSION['first_name'] ?? 'Admin';
$roleLabel = $_SESSION['custom_role_name'] ?? (((int)($_SESSION['role_id'] ?? 2) === 1) ? 'Super Admin' : 'Committee Member');
$navCsrfToken = generateCsrfToken();
$navBase = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');

$navGroups = [
    [
        'id' => 'ops',
        'label' => 'Operations',
        'tabs' => ['pending', 'users', 'gates', 'guards', 'staff', 'buildings'],
        'items' => [
            ['tab' => 'pending', 'href' => '/admin/dashboard', 'label' => 'Pending Requests', 'perm' => 'residents.manage'],
            ['tab' => 'users', 'href' => '/admin/users', 'label' => 'Resident Directory', 'perm' => 'residents.manage'],
            ['tab' => 'gates', 'href' => '/admin/gates', 'label' => 'Gates & Handovers', 'perm' => 'gates.view'],
            ['tab' => 'guards', 'href' => '/admin/guards', 'label' => 'Security Staff', 'perm' => 'guards.manage'],
            ['tab' => 'staff', 'href' => '/admin/staff', 'label' => 'Daily Staff & Attendance', 'perm' => 'guards.manage'],
            ['tab' => 'buildings', 'href' => '/admin/buildings', 'label' => 'Buildings & Flats', 'perm' => 'buildings.manage'],
        ]
    ],
    [
        'id' => 'comm',
        'label' => 'Community',
        'tabs' => ['notices', 'polls', 'events', 'community', 'moderation', 'complaints', 'services'],
        'items' => [
            ['tab' => 'notices', 'href' => '/admin/notices', 'label' => 'Notice Board', 'perm' => 'notices.manage'],
            ['tab' => 'polls', 'href' => '/admin/polls', 'label' => 'Polls & Voting', 'perm' => 'polls.manage'],
            ['tab' => 'events', 'href' => '/admin/events', 'label' => 'Events & Calendar', 'perm' => 'events.manage'],
            ['tab' => 'community', 'href' => '/admin/community', 'label' => 'Forums & Lost/Found', 'perm' => 'community.moderate'],
            ['tab' => 'moderation', 'href' => '/admin/moderation', 'label' => 'Moderation & Tenant KYC', 'perm' => 'community.moderate'],
            ['tab' => 'complaints', 'href' => '/admin/complaints', 'label' => 'Helpdesk', 'perm' => 'community.moderate'],
            ['tab' => 'services', 'href' => '/admin/services', 'label' => 'Local Services', 'perm' => 'services.manage'],
        ]
    ],
    [
        'id' => 'fin',
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
        'id' => 'gov',
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
<a href="#main-content" class="sg-skip-link sr-only focus-visible:not-sr-only">Skip to main content</a>
<nav aria-label="Admin portal navigation" class="bg-slate-900 shadow-md border-b border-slate-800 sticky top-0 z-50" style="padding-top: env(safe-area-inset-top, 0px);">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16 gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <a href="<?php echo $navBase; ?>/admin/dashboard" class="flex items-center gap-2.5 shrink-0 rounded-lg focus:outline-none">
                    <div class="w-8 h-8 bg-brand-900 rounded-lg flex items-center justify-center shadow-inner border border-white/10">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <div class="leading-tight">
                        <span class="font-serif text-base sm:text-lg font-bold text-white tracking-wide block">SecureGate</span>
                        <span class="text-[10px] uppercase tracking-wider text-slate-400 font-semibold hidden sm:block">Admin Portal</span>
                    </div>
                </a>

                <div class="hidden lg:flex items-center space-x-1 ml-2">
                    <?php foreach ($navGroups as $grp):
                        $visibleItems = array_filter($grp['items'], fn($it) => hasPermission($it['perm']));
                        if (empty($visibleItems)) continue;
                        $isGroupActive = in_array($activeTab, $grp['tabs'], true);
                    ?>
                    <div class="relative sg-admin-dd">
                        <button type="button"
                                onclick="toggleAdminDropdown('dd-<?php echo $grp['id']; ?>', this)"
                                aria-expanded="false"
                                aria-haspopup="true"
                                aria-controls="dd-<?php echo $grp['id']; ?>"
                                class="px-3 py-2 rounded-lg text-sm font-medium inline-flex items-center gap-1.5 transition-colors cursor-pointer <?php echo $isGroupActive ? 'text-white bg-slate-800 ring-1 ring-white/10' : 'text-slate-300 hover:text-white hover:bg-slate-800/70'; ?>">
                            <span><?php echo htmlspecialchars($grp['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <svg class="w-3.5 h-3.5 opacity-70 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div id="dd-<?php echo $grp['id']; ?>" class="sg-admin-dd-menu hidden absolute left-0 top-full pt-1.5 w-60 z-50">
                            <div class="bg-white rounded-xl shadow-xl border border-slate-200 py-1.5 text-slate-800 sg-modal-panel" role="menu">
                                <?php foreach ($visibleItems as $it): ?>
                                    <a href="<?php echo $navBase . $it['href']; ?>"
                                       role="menuitem"
                                       <?php echo $activeTab === $it['tab'] ? 'aria-current="page"' : ''; ?>
                                       class="block px-4 py-2 text-xs font-semibold transition-colors <?php echo $activeTab === $it['tab'] ? 'bg-brand-50 text-brand-900 font-bold border-l-2 border-brand-900' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900'; ?>">
                                        <?php echo htmlspecialchars($it['label'], ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (hasPermission('ads.manage')): ?>
                        <a href="<?php echo $navBase; ?>/admin/advertisements"
                           <?php echo $activeTab === 'advertisements' ? 'aria-current="page"' : ''; ?>
                           class="px-2.5 py-1.5 rounded-lg text-xs font-bold text-amber-300 hover:text-amber-200 hover:bg-slate-800/70 transition-colors <?php echo $activeTab === 'advertisements' ? 'bg-slate-800 ring-1 ring-amber-400/30' : ''; ?>">Company Ads</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                <?php require_once __DIR__ . '/../../includes/notification_widget.php'; ?>
                <div class="text-right hidden sm:block leading-tight">
                    <div class="text-sm font-medium text-white truncate max-w-[140px]"><?php echo htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="text-[11px] text-slate-400 truncate max-w-[140px]"><?php echo htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <form action="<?php echo $navBase; ?>/api/logout.php" method="POST" class="hidden sm:block m-0">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($navCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" aria-label="Log Out" title="Log Out" class="p-2 text-slate-300 hover:text-white hover:bg-white/10 rounded-lg transition-colors cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    </button>
                </form>

                <div class="lg:hidden flex items-center">
                    <button id="mobile-menu-btn" type="button" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="mobile-menu" class="text-slate-300 hover:text-white p-2 rounded-lg hover:bg-slate-800 cursor-pointer transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="mobile-menu" class="hidden lg:hidden bg-slate-800 border-t border-slate-700 shadow-xl max-h-96 overflow-y-auto sg-scroll-thin">
        <div class="px-4 py-3 border-b border-slate-700/80 flex items-center justify-between sm:hidden">
            <div>
                <div class="text-sm font-semibold text-white"><?php echo htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="text-xs text-slate-400"><?php echo htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <span class="px-2 py-0.5 text-[11px] font-bold rounded bg-slate-900 text-slate-300 border border-slate-700">Admin Session</span>
        </div>
        <div class="px-3 pt-2 pb-4 space-y-3">
            <?php foreach ($navGroups as $grp):
                $visibleItems = array_filter($grp['items'], fn($it) => hasPermission($it['perm']));
                if (empty($visibleItems)) continue;
            ?>
                <div>
                    <div class="px-2 py-1 text-[11px] font-black uppercase tracking-wider text-slate-400"><?php echo htmlspecialchars($grp['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="space-y-0.5">
                        <?php foreach ($visibleItems as $it): ?>
                            <a href="<?php echo $navBase . $it['href']; ?>"
                               <?php echo $activeTab === $it['tab'] ? 'aria-current="page"' : ''; ?>
                               class="block px-3 py-2 rounded-lg text-sm font-medium transition-colors <?php echo $activeTab === $it['tab'] ? 'text-white bg-slate-900 ring-1 ring-white/10' : 'text-slate-200 hover:text-white hover:bg-slate-700'; ?>">
                                <?php echo htmlspecialchars($it['label'], ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <form action="<?php echo $navBase; ?>/api/logout.php" method="POST" class="pt-3 border-t border-slate-700">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($navCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="w-full text-left block px-3 py-2.5 rounded-lg text-sm font-semibold text-rose-300 hover:text-rose-200 hover:bg-slate-700 cursor-pointer">Log Out</button>
            </form>
        </div>
    </div>
</nav>

<script>
    function closeAllAdminDropdowns() {
        document.querySelectorAll('.sg-admin-dd-menu').forEach(el => {
            el.classList.add('hidden');
            el.removeAttribute('data-pinned');
        });
        document.querySelectorAll('.sg-admin-dd > button').forEach(b => b.setAttribute('aria-expanded', 'false'));
    }

    function toggleAdminDropdown(menuId, btn) {
        const menu = document.getElementById(menuId);
        if (!menu) return;
        const isPinned = menu.getAttribute('data-pinned') === 'true';
        const isOpen = !menu.classList.contains('hidden');
        closeAllAdminDropdowns();
        if (!isOpen || !isPinned) {
            menu.classList.remove('hidden');
            menu.setAttribute('data-pinned', 'true');
            btn?.setAttribute('aria-expanded', 'true');
        }
    }

    document.querySelectorAll('.sg-admin-dd').forEach(dd => {
        let hoverTimer = null;
        dd.addEventListener('mouseenter', () => {
            if (window.innerWidth < 1024) return;
            clearTimeout(hoverTimer);
            const menu = dd.querySelector('.sg-admin-dd-menu');
            const btn = dd.querySelector('button');
            document.querySelectorAll('.sg-admin-dd-menu').forEach(el => {
                if (el !== menu && el.getAttribute('data-pinned') !== 'true') {
                    el.classList.add('hidden');
                    el.closest('.sg-admin-dd')?.querySelector('button')?.setAttribute('aria-expanded', 'false');
                }
            });
            menu?.classList.remove('hidden');
            btn?.setAttribute('aria-expanded', 'true');
        });
        dd.addEventListener('mouseleave', () => {
            if (window.innerWidth < 1024) return;
            hoverTimer = setTimeout(() => {
                const menu = dd.querySelector('.sg-admin-dd-menu');
                const btn = dd.querySelector('button');
                if (menu && menu.getAttribute('data-pinned') !== 'true') {
                    menu.classList.add('hidden');
                    btn?.setAttribute('aria-expanded', 'false');
                }
            }, 140);
        });
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.sg-admin-dd')) {
            closeAllAdminDropdowns();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeAllAdminDropdowns();
            const mm = document.getElementById('mobile-menu');
            if (mm && !mm.classList.contains('hidden')) {
                mm.classList.add('hidden');
                document.getElementById('mobile-menu-btn')?.setAttribute('aria-expanded', 'false');
            }
        }
    });

    document.getElementById('mobile-menu-btn')?.addEventListener('click', function () {
        const menu = document.getElementById('mobile-menu');
        const expanded = !menu.classList.contains('hidden');
        menu.classList.toggle('hidden');
        this.setAttribute('aria-expanded', String(!expanded));
    });

    // Ensure main element supports Skip-to-Content anchor across all Admin views
    document.addEventListener('DOMContentLoaded', () => {
        const mainEl = document.querySelector('main');
        if (mainEl && !mainEl.id) mainEl.id = 'main-content';
    });
</script>
