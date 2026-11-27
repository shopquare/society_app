<?php
// /society_app/modules/admin/guards.php

if (!isset($_SESSION['logged_in']) || !hasRole([1, 2])) {
    header('Location: ' . appBasePath() . '/login');
    exit();
}
$csrfToken = generateCsrfToken();
$base = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="app-base" content="<?php echo $base; ?>">
    <title>Security Staff - Admin Portal</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script>
    <link rel="stylesheet" href="<?php echo $base; ?>/assets/css/style.css">
    <script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans flex flex-col min-h-screen">

    <?php
    $activeTab = 'guards';
    require_once __DIR__ . '/navbar.php';
    ?>

    <main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8">

        <div class="mb-8">
            <h1 class="text-2xl font-bold text-slate-900 font-serif">Security Guard Management</h1>
            <p class="text-sm text-slate-500 mt-1">Provision and manage gate security personnel accounts.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 sticky top-24">
                    <h2 class="text-lg font-bold text-slate-900 font-serif mb-4">Add New Guard</h2>
                    <form id="guard-form" onsubmit="event.preventDefault(); createGuard();">
                        <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="space-y-4">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-slate-700 mb-1">First Name</label>
                                <input type="text" id="first_name" required class="w-full px-4 py-2 bg-white rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:border-brand-900" placeholder="e.g., Ramesh">
                            </div>
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-slate-700 mb-1">Last Name</label>
                                <input type="text" id="last_name" required class="w-full px-4 py-2 bg-white rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:border-brand-900" placeholder="e.g., Kumar">
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email Address</label>
                                <input type="email" id="email" required class="w-full px-4 py-2 bg-white rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:border-brand-900" placeholder="guard@society.com">
                            </div>
                            <div>
                                <label for="phone_number" class="block text-sm font-medium text-slate-700 mb-1">Phone Number</label>
                                <input type="text" id="phone_number" required class="w-full px-4 py-2 bg-white rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:border-brand-900" placeholder="9876543210">
                            </div>
                            <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 text-xs text-slate-500">
                                A unique random temporary password will be generated and displayed once upon creation.
                            </div>
                            <button type="submit" id="btn-submit" class="w-full px-6 py-2.5 text-sm font-medium text-white bg-brand-900 hover:bg-brand-800 rounded-lg shadow-sm transition-all">
                                Create Guard Account
                            </button>
                        </div>
                    </form>

                    <div id="temp-password-box" class="hidden mt-4 p-4 bg-emerald-50 border border-emerald-200 rounded-lg text-sm">
                        <div class="text-xs font-semibold text-emerald-800 uppercase tracking-wider mb-1">Temporary Guard Password</div>
                        <div id="temp-password-value" class="font-mono font-bold text-lg text-emerald-900 select-all"></div>
                        <p class="text-xs text-emerald-700 mt-1">Share this securely with the guard. It will not be shown again.</p>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2">
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-slate-900 font-serif">Registered Guards</h2>
                    <p class="text-sm text-slate-500">Active security staff assigned to gate operations.</p>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm whitespace-nowrap">
                            <thead class="bg-slate-50 text-slate-600 font-medium border-b border-slate-200">
                                <tr>
                                    <th class="px-6 py-4">Guard Name</th>
                                    <th class="px-6 py-4">Contact Info</th>
                                    <th class="px-6 py-4">Status</th>
                                    <th class="px-6 py-4">Added On</th>
                                </tr>
                            </thead>
                            <tbody id="guards-tbody" class="divide-y divide-slate-100 text-slate-700">
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center text-slate-500">
                                        <svg class="animate-spin h-6 w-6 mx-auto text-brand-900 mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        Loading security staff...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', fetchGuards);
        const csrfToken = document.getElementById('csrf_token').value;

        async function fetchGuards() {
            const tbody = document.getElementById('guards-tbody');
            try {
                const response = await fetch(appPath('/api/admin_get_guards.php'));
                const result = await response.json();

                if (response.ok && result.status === 'success') {
                    const guards = result.data;

                    if (guards.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="4" class="px-6 py-12 text-center text-slate-500 font-medium">No security guards registered yet.</td></tr>`;
                        return;
                    }

                    tbody.innerHTML = guards.map(guard => `
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 font-semibold text-slate-900">
                                ${escapeHtml(guard.first_name)} ${escapeHtml(guard.last_name)}
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-slate-900">${escapeHtml(guard.email)}</div>
                                <div class="text-xs text-slate-500 mt-0.5">${escapeHtml(guard.phone_number)}</div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                    <div class="w-1.5 h-1.5 rounded-full bg-emerald-600"></div> ${escapeHtml(guard.status || 'active')}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-xs font-medium text-slate-500">
                                ${escapeHtml(formatDisplayDate(guard.created_at))}
                            </td>
                        </tr>
                    `).join('');
                } else {
                    tbody.innerHTML = `<tr><td colspan="4" class="px-6 py-12 text-center text-red-500 font-medium">${escapeHtml(result.message || 'Failed to load guards.')}</td></tr>`;
                }
            } catch (error) {
                tbody.innerHTML = `<tr><td colspan="4" class="px-6 py-12 text-center text-red-500 font-medium">Network error occurred.</td></tr>`;
            }
        }

        async function createGuard() {
            const btn = document.getElementById('btn-submit');
            btn.innerText = 'Creating...';
            btn.disabled = true;

            const payload = {
                first_name: document.getElementById('first_name').value.trim(),
                last_name: document.getElementById('last_name').value.trim(),
                email: document.getElementById('email').value.trim(),
                phone_number: document.getElementById('phone_number').value.trim(),
                csrf_token: csrfToken
            };

            try {
                const response = await fetch(appPath('/api/admin_create_guard.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();

                if (response.ok) {
                    document.getElementById('guard-form').reset();
                    if (result.temporary_password) {
                        document.getElementById('temp-password-value').textContent = result.temporary_password;
                        document.getElementById('temp-password-box').classList.remove('hidden');
                    }
                    fetchGuards();
                    showToast(result.message || 'Guard created.', 'success');
                } else {
                    showToast(result.message || 'Unable to create guard.', 'error');
                }
            } catch (error) {
                showToast('An error occurred while creating the guard account.', 'error');
            } finally {
                btn.innerText = 'Create Guard Account';
                btn.disabled = false;
            }
        }
    </script>
</body>
</html>
