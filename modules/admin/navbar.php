<?php
// /modules/admin/navbar.php

$activeTab = $activeTab ?? 'pending';
$firstName = $_SESSION['first_name'] ?? 'Admin';
$navCsrfToken = generateCsrfToken();
$navBase = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');
?>

<nav class="bg-slate-900 shadow-md border-b border-slate-800 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center gap-6">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-brand-900 rounded flex items-center justify-center shadow-inner">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <span class="font-serif text-xl font-semibold text-white tracking-wide">Admin Portal</span>
                </div>

                <div class="hidden md:flex space-x-4 ml-4">
                    <a href="<?php echo $navBase; ?>/admin/dashboard" class="px-3 py-2 rounded-md text-sm font-medium transition-colors <?php echo $activeTab === 'pending' ? 'text-white bg-slate-800' : 'text-slate-400 hover:text-white'; ?>">Pending Requests</a>
                    <a href="<?php echo $navBase; ?>/admin/users" class="px-3 py-2 rounded-md text-sm font-medium transition-colors <?php echo $activeTab === 'users' ? 'text-white bg-slate-800' : 'text-slate-400 hover:text-white'; ?>">Resident Directory</a>
                    <a href="<?php echo $navBase; ?>/admin/notices" class="px-3 py-2 rounded-md text-sm font-medium transition-colors <?php echo $activeTab === 'notices' ? 'text-white bg-slate-800' : 'text-slate-400 hover:text-white'; ?>">Notice Board</a>
                    <a href="<?php echo $navBase; ?>/admin/financials" class="px-3 py-2 rounded-md text-sm font-medium transition-colors <?php echo $activeTab === 'financials' ? 'text-white bg-slate-800' : 'text-slate-400 hover:text-white'; ?>">Financials</a>
                    <a href="<?php echo $navBase; ?>/admin/guards" class="px-3 py-2 rounded-md text-sm font-medium transition-colors <?php echo $activeTab === 'guards' ? 'text-white bg-slate-800' : 'text-slate-400 hover:text-white'; ?>">Security Staff</a>
                    <a href="<?php echo $navBase; ?>/admin/complaints" class="px-3 py-2 rounded-md text-sm font-medium transition-colors <?php echo $activeTab === 'complaints' ? 'text-white bg-slate-800' : 'text-slate-400 hover:text-white'; ?>">Helpdesk</a>
                    <a href="<?php echo $navBase; ?>/admin/settings" class="px-3 py-2 rounded-md text-sm font-medium transition-colors <?php echo $activeTab === 'settings' ? 'text-white bg-slate-800' : 'text-slate-400 hover:text-white'; ?>">Settings</a>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <div class="text-right hidden sm:block">
                    <div class="text-sm font-medium text-white"><?php echo htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="text-xs text-slate-400">Committee Member</div>
                </div>
                <form action="<?php echo $navBase; ?>/api/logout.php" method="POST" class="hidden sm:block m-0">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($navCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" aria-label="Log Out" title="Log Out" class="p-2 text-slate-400 hover:text-white hover:bg-white/10 rounded-lg transition-colors cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    </button>
                </form>

                <div class="md:hidden flex items-center">
                    <button id="mobile-menu-btn" type="button" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="mobile-menu" class="text-slate-400 hover:text-white focus:outline-none p-2 rounded-md hover:bg-slate-800">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="mobile-menu" class="hidden md:hidden bg-slate-800 border-t border-slate-700 shadow-lg">
        <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
            <a href="<?php echo $navBase; ?>/admin/dashboard" class="block px-3 py-2 rounded-md text-base font-medium <?php echo $activeTab === 'pending' ? 'text-white bg-slate-900' : 'text-slate-300 hover:text-white hover:bg-slate-700'; ?>">Pending Requests</a>
            <a href="<?php echo $navBase; ?>/admin/users" class="block px-3 py-2 rounded-md text-base font-medium <?php echo $activeTab === 'users' ? 'text-white bg-slate-900' : 'text-slate-300 hover:text-white hover:bg-slate-700'; ?>">Resident Directory</a>
            <a href="<?php echo $navBase; ?>/admin/notices" class="block px-3 py-2 rounded-md text-base font-medium <?php echo $activeTab === 'notices' ? 'text-white bg-slate-900' : 'text-slate-300 hover:text-white hover:bg-slate-700'; ?>">Notice Board</a>
            <a href="<?php echo $navBase; ?>/admin/financials" class="block px-3 py-2 rounded-md text-base font-medium <?php echo $activeTab === 'financials' ? 'text-white bg-slate-900' : 'text-slate-300 hover:text-white hover:bg-slate-700'; ?>">Financials</a>
            <a href="<?php echo $navBase; ?>/admin/guards" class="block px-3 py-2 rounded-md text-base font-medium <?php echo $activeTab === 'guards' ? 'text-white bg-slate-900' : 'text-slate-300 hover:text-white hover:bg-slate-700'; ?>">Security Staff</a>
            <a href="<?php echo $navBase; ?>/admin/complaints" class="block px-3 py-2 rounded-md text-base font-medium <?php echo $activeTab === 'complaints' ? 'text-white bg-slate-900' : 'text-slate-300 hover:text-white hover:bg-slate-700'; ?>">Helpdesk</a>
            <a href="<?php echo $navBase; ?>/admin/settings" class="block px-3 py-2 rounded-md text-base font-medium <?php echo $activeTab === 'settings' ? 'text-white bg-slate-900' : 'text-slate-300 hover:text-white hover:bg-slate-700'; ?>">Settings</a>
            <form action="<?php echo $navBase; ?>/api/logout.php" method="POST" class="mt-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($navCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="w-full text-left block px-3 py-2 rounded-md text-base font-medium text-red-400 hover:text-red-300 hover:bg-slate-700">Log Out</button>
            </form>
        </div>
    </div>
</nav>

<script>
    document.getElementById('mobile-menu-btn').addEventListener('click', function () {
        const menu = document.getElementById('mobile-menu');
        const expanded = !menu.classList.contains('hidden');
        menu.classList.toggle('hidden');
        this.setAttribute('aria-expanded', String(!expanded));
    });
</script>
