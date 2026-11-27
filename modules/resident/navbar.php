<?php
// modules/resident/navbar.php
// Unified Responsive Navigation Bar for Resident Secondary Modules

$resActivePage = $resActivePage ?? 'dashboard';
$resPageTitle = $resPageTitle ?? 'Resident Portal';
$resNavBase = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');
$resNavCsrf = generateCsrfToken();
$resNavName = trim((string)($_SESSION['first_name'] ?? 'Resident') . ' ' . (string)($_SESSION['last_name'] ?? ''));
$resNavUnit = (string)($_SESSION['unit_number'] ?? '');

$resNavItems = [
    ['id' => 'dashboard', 'href' => '/resident/dashboard', 'label' => 'Dashboard'],
    ['id' => 'financials', 'href' => '/resident/financials', 'label' => 'My Dues'],
    ['id' => 'facilities', 'href' => '/resident/facilities', 'label' => 'Facilities'],
    ['id' => 'community', 'href' => '/resident/community', 'label' => 'Community'],
    ['id' => 'directory', 'href' => '/resident/directory', 'label' => 'Directory'],
    ['id' => 'services', 'href' => '/resident/services', 'label' => 'Services'],
    ['id' => 'documents', 'href' => '/resident/documents', 'label' => 'Office & NOC'],
];
?>
<a href="#main-content" class="sg-skip-link">Skip to main content</a>
<nav aria-label="Resident portal navigation" class="bg-brand-900 shadow-md border-b border-brand-800 sticky top-0 z-50 no-print" style="padding-top: env(safe-area-inset-top, 0px);">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16 items-center gap-3">
            <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
                <a href="<?php echo $resNavBase; ?>/resident/dashboard" aria-label="Back to Resident Dashboard" class="p-2 -ml-2 text-brand-100 hover:text-white hover:bg-white/10 rounded-lg transition shrink-0" title="Back to Dashboard">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div class="min-w-0">
                    <span class="font-serif text-base sm:text-xl font-semibold text-white tracking-wide truncate block"><?php echo htmlspecialchars($resPageTitle, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>

            <!-- Desktop Quick Module Switcher -->
            <div class="hidden lg:flex items-center gap-1">
                <?php foreach ($resNavItems as $item):
                    $isAct = ($resActivePage === $item['id']);
                ?>
                    <a href="<?php echo $resNavBase . $item['href']; ?>"
                       <?php echo $isAct ? 'aria-current="page"' : ''; ?>
                       class="px-2.5 py-1.5 rounded-lg text-xs font-semibold transition-colors <?php echo $isAct ? 'bg-white text-brand-900 font-bold shadow-2xs' : 'text-brand-100 hover:text-white hover:bg-white/10'; ?>">
                        <?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                <?php if (!empty($resNavExtraHtml)) echo $resNavExtraHtml; ?>
                <?php require_once __DIR__ . '/../../includes/notification_widget.php'; ?>
                <div class="text-right hidden sm:block leading-tight">
                    <div class="text-sm font-medium text-white truncate max-w-[130px]"><?php echo htmlspecialchars($resNavName, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php if ($resNavUnit !== ''): ?>
                        <div class="text-xs text-brand-100">Unit <?php echo htmlspecialchars($resNavUnit, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                </div>
                <form action="<?php echo $resNavBase; ?>/api/logout.php" method="POST" class="hidden sm:block m-0">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($resNavCsrf, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" aria-label="Log Out" title="Log Out" class="p-2 text-brand-100 hover:text-white hover:bg-white/10 rounded-lg transition-colors cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    </button>
                </form>
                <button type="button" id="res-mobile-nav-btn" aria-label="Open module menu" aria-expanded="false" aria-controls="res-mobile-nav" class="lg:hidden p-2 text-brand-100 hover:text-white hover:bg-white/10 rounded-lg transition cursor-pointer">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Dropdown Module Drawer -->
    <div id="res-mobile-nav" class="hidden lg:hidden bg-brand-950 border-t border-brand-800 px-3 py-3 space-y-1">
        <div class="px-3 py-2 mb-1 border-b border-brand-800/80 flex items-center justify-between sm:hidden text-white">
            <span class="text-sm font-semibold"><?php echo htmlspecialchars($resNavName, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php if ($resNavUnit !== ''): ?>
                <span class="text-xs px-2 py-0.5 rounded bg-brand-800 text-brand-100 font-bold">Unit <?php echo htmlspecialchars($resNavUnit, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
        </div>
        <div class="grid grid-cols-2 gap-1.5">
            <?php foreach ($resNavItems as $item):
                $isAct = ($resActivePage === $item['id']);
            ?>
                <a href="<?php echo $resNavBase . $item['href']; ?>"
                   <?php echo $isAct ? 'aria-current="page"' : ''; ?>
                   class="px-3 py-2 rounded-lg text-xs font-semibold <?php echo $isAct ? 'bg-white text-brand-900 font-bold' : 'text-brand-100 hover:bg-white/10 hover:text-white'; ?>">
                    <?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <form action="<?php echo $resNavBase; ?>/api/logout.php" method="POST" class="pt-2 mt-2 border-t border-brand-800/80 sm:hidden">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($resNavCsrf, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="w-full text-left px-3 py-2 rounded-lg text-xs font-bold text-rose-300 hover:bg-white/10 cursor-pointer">Log Out</button>
        </form>
    </div>
</nav>
<script>
    document.getElementById('res-mobile-nav-btn')?.addEventListener('click', function () {
        const m = document.getElementById('res-mobile-nav');
        if (!m) return;
        const open = m.classList.contains('hidden');
        m.classList.toggle('hidden');
        this.setAttribute('aria-expanded', String(open));
    });
    document.addEventListener('DOMContentLoaded', () => {
        const mainEl = document.querySelector('main');
        if (mainEl && !mainEl.id) mainEl.id = 'main-content';
    });
</script>
