<?php
// /society_app/modules/admin/settings.php

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="app-base" content="<?php echo $base; ?>">
    <title>Society Settings - Admin Portal</title>

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
    $activeTab = 'settings';
    require_once __DIR__ . '/navbar.php';
    ?>

    <main class="flex-grow max-w-5xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8">

        <div class="mb-8">
            <h1 class="text-2xl font-bold text-slate-900 font-serif">Society Settings</h1>
            <p class="text-sm text-slate-500 mt-1">Manage core community details and security access.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

            <div class="lg:col-span-3 bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-lg font-bold text-slate-900 font-serif">Society Profile</h2>
                    <span class="text-xs font-medium text-slate-400">ID: #<?php echo (int)$_SESSION['society_id']; ?></span>
                </div>

                <form id="profile-form" onsubmit="event.preventDefault(); updateSocietyProfile();">
                    <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="space-y-4">
                        <div>
                            <label for="society_name" class="block text-sm font-medium text-slate-700 mb-1">Society Name *</label>
                            <input type="text" id="society_name" required class="w-full px-4 py-2 bg-white rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:border-brand-900">
                        </div>

                        <div>
                            <label for="address" class="block text-sm font-medium text-slate-700 mb-1">Street Address</label>
                            <input type="text" id="address" class="w-full px-4 py-2 bg-white rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:border-brand-900" placeholder="e.g., 123 Main St">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label for="city" class="block text-sm font-medium text-slate-700 mb-1">City *</label>
                                <input type="text" id="city" required class="w-full px-4 py-2 bg-white rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:border-brand-900">
                            </div>
                            <div>
                                <label for="state" class="block text-sm font-medium text-slate-700 mb-1">State</label>
                                <input type="text" id="state" class="w-full px-4 py-2 bg-white rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:border-brand-900">
                            </div>
                            <div>
                                <label for="zip_code" class="block text-sm font-medium text-slate-700 mb-1">Zip Code</label>
                                <input type="text" id="zip_code" class="w-full px-4 py-2 bg-white rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:border-brand-900">
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-100 flex justify-end">
                            <button type="submit" id="btn-save" class="px-6 py-2 text-sm font-medium text-white bg-brand-900 hover:bg-brand-800 rounded-lg shadow-sm transition-all">
                                Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 p-6 h-fit">
                <h2 class="text-lg font-bold text-slate-900 font-serif mb-2">Access Control</h2>
                <p class="text-sm text-slate-500 mb-6">New residents require this code during registration.</p>

                <div class="bg-slate-50 p-4 rounded-lg border border-slate-200 mb-6 text-center">
                    <span class="text-xs text-slate-500 font-medium uppercase tracking-wider block mb-1">Current Invite Code</span>
                    <span class="text-2xl font-mono font-bold text-brand-900 tracking-widest" id="invite-code">------</span>
                </div>

                <button type="button" onclick="resetInviteCode()" id="btn-reset" class="w-full flex items-center justify-center gap-2 px-6 py-2.5 text-sm font-medium text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 rounded-lg transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    Regenerate Code
                </button>
            </div>

        </div>
    </main>

    <script>
        const csrfToken = document.getElementById('csrf_token').value;

        document.addEventListener('DOMContentLoaded', fetchSocietyInfo);

        async function fetchSocietyInfo() {
            try {
                const response = await fetch(appPath('/api/admin_get_society.php'));
                const result = await response.json();

                if (response.ok && result.status === 'success') {
                    document.getElementById('society_name').value = result.data.society_name || '';
                    document.getElementById('address').value = result.data.address || '';
                    document.getElementById('city').value = result.data.city || '';
                    document.getElementById('state').value = result.data.state || '';
                    document.getElementById('zip_code').value = result.data.zip_code || '';
                    document.getElementById('invite-code').textContent = result.data.society_code || 'NOT GENERATED';
                } else {
                    showToast(result.message || 'Unable to load society settings.', 'error');
                }
            } catch (error) {
                showToast('Network error loading society profile.', 'error');
            }
        }

        async function updateSocietyProfile() {
            const btn = document.getElementById('btn-save');
            btn.innerText = 'Saving...';
            btn.disabled = true;

            const payload = {
                society_name: document.getElementById('society_name').value.trim(),
                address: document.getElementById('address').value.trim(),
                city: document.getElementById('city').value.trim(),
                state: document.getElementById('state').value.trim(),
                zip_code: document.getElementById('zip_code').value.trim(),
                csrf_token: csrfToken
            };

            try {
                const response = await fetch(appPath('/api/admin_update_society.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();
                showToast(result.message || 'Saved.', response.ok ? 'success' : 'error');
            } catch (error) {
                showToast('An error occurred while saving.', 'error');
            } finally {
                btn.innerText = 'Save Changes';
                btn.disabled = false;
            }
        }

        async function resetInviteCode() {
            if (!confirm('Are you sure? The old code will stop working immediately.')) return;

            const btn = document.getElementById('btn-reset');
            btn.innerText = 'Regenerating...';
            btn.disabled = true;

            try {
                const response = await fetch(appPath('/api/admin_reset_invite.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ csrf_token: csrfToken })
                });

                const result = await response.json();

                if (response.ok) {
                    document.getElementById('invite-code').textContent = result.new_code;
                    const codeBox = document.getElementById('invite-code').parentElement;
                    codeBox.classList.add('bg-emerald-50', 'border-emerald-200');
                    setTimeout(() => codeBox.classList.remove('bg-emerald-50', 'border-emerald-200'), 1000);
                    showToast(result.message || 'Invite code regenerated.', 'success');
                } else {
                    showToast(result.message || 'Failed to regenerate code.', 'error');
                }
            } catch (error) {
                showToast('An error occurred.', 'error');
            } finally {
                btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> Regenerate Code';
                btn.disabled = false;
            }
        }
    </script>
</body>
</html>
