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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
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

        <!-- Google AdSense & Resident Paid Advertisements Section -->
        <div class="mt-8 mb-4 bg-gradient-to-r from-slate-900 to-brand-900 text-white rounded-2xl p-6 shadow-md flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-white/10 backdrop-blur-xs flex items-center justify-center text-2xl shrink-0">
                    📢
                </div>
                <div>
                    <h3 class="font-bold font-serif text-lg text-white">Company Advertisements (MyGate-Style)</h3>
                    <p class="text-xs text-slate-200 mt-0.5">Manage external brand campaigns (Zomato, Swiggy, Blinkit, Amazon, local shops) and resident app promotional placements.</p>
                </div>
            </div>
            <a href="<?php echo $base; ?>/admin/advertisements" class="shrink-0 px-5 py-2.5 bg-amber-400 hover:bg-amber-300 text-slate-950 font-bold text-xs rounded-xl shadow transition flex items-center gap-1.5">
                <span>⚡</span> Manage Brand Ads →
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Google AdSense Settings -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">💰</span>
                        <h2 class="text-lg font-bold text-slate-900 font-serif">Google AdSense</h2>
                    </div>
                    <span class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded border border-slate-200 font-medium">Monetization</span>
                </div>
                <p class="text-xs text-slate-500 mb-4">Display Google AdSense banner advertisements on resident dashboard and marketplace. Advertisements are strictly excluded from all emergency, login, and security gate screens.</p>

                <form id="adsense-form" onsubmit="event.preventDefault(); saveAdsenseSettings();" class="space-y-4 text-xs">
                    <div class="flex items-center gap-2 p-3 bg-slate-50 border border-slate-200 rounded-lg">
                        <input type="checkbox" id="adsense_enabled" class="rounded text-brand-900 focus:ring-brand-900 w-4 h-4">
                        <label for="adsense_enabled" class="font-bold text-slate-800 cursor-pointer">Enable Google AdSense</label>
                    </div>

                    <div>
                        <label for="adsense_pub_id" class="block font-medium text-slate-700 mb-1">Publisher ID</label>
                        <input type="text" id="adsense_pub_id" placeholder="ca-pub-XXXXXXXXXXXXXXXX" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg">
                    </div>

                    <div>
                        <label for="adsense_slot_id" class="block font-medium text-slate-700 mb-1">Ad Slot ID</label>
                        <input type="text" id="adsense_slot_id" placeholder="1234567890" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg">
                    </div>

                    <button type="submit" id="btn-save-adsense" class="w-full py-2 bg-brand-900 hover:bg-brand-800 text-white font-bold rounded-lg shadow-sm transition cursor-pointer">
                        Save AdSense Settings
                    </button>
                </form>
            </div>

            <!-- Resident Paid Advertisements & Payment Verification -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">📣</span>
                            <h2 class="text-lg font-bold text-slate-900 font-serif">Resident Paid Campaigns</h2>
                        </div>
                        <span class="text-xs bg-amber-100 text-amber-900 px-2 py-0.5 rounded border border-amber-200 font-bold">Manual Verification</span>
                    </div>
                    <p class="text-xs text-slate-500 mb-4">Review resident advertising campaigns and verify their manual UPI / bank payments before activating.</p>

                    <div id="admin-ads-container" class="space-y-3 max-h-72 overflow-y-auto">
                        <div class="text-center py-6 text-slate-400 text-xs">Loading advertisement campaigns...</div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        const csrfToken = document.getElementById('csrf_token').value;

        document.addEventListener('DOMContentLoaded', () => {
            fetchSocietyInfo();
            fetchAdSettings();
        });

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
            const ok = await showConfirmDialog({
                title: 'Regenerate Society Invite Code',
                message: 'Are you sure? The previous invite code will stop working immediately for new resident signups.',
                confirmText: 'Regenerate Code',
                danger: true
            });
            if (!ok) return;

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

        async function fetchAdSettings() {
            try {
                const res = await fetch(appPath('/api/admin_manage_ads.php'));
                const json = await res.json();
                if (res.ok && json.status === 'success') {
                    const s = json.data.settings;
                    if (s) {
                        document.getElementById('adsense_enabled').checked = Boolean(Number(s.adsense_enabled));
                        document.getElementById('adsense_pub_id').value = s.adsense_publisher_id || '';
                        document.getElementById('adsense_slot_id').value = s.adsense_slot_id || '';
                    }
                    renderAdminAds(json.data.ads || []);
                }
            } catch (e) {
                console.warn('Ad settings load error:', e);
            }
        }

        function renderAdminAds(ads) {
            const c = document.getElementById('admin-ads-container');
            if (!ads.length) {
                c.innerHTML = '<div class="text-center py-6 text-slate-400 text-xs">No resident advertisement campaigns submitted yet.</div>';
                return;
            }
            c.innerHTML = ads.map(a => `
                <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs flex flex-col gap-2">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="font-bold text-slate-900">${escapeHtml(a.title)}</div>
                            <div class="text-[11px] text-slate-500">${escapeHtml(a.first_name || '')} ${escapeHtml(a.last_name || '')} (${escapeHtml(a.user_email || '')})</div>
                            <div class="text-[10px] text-slate-400">Package: ${escapeHtml(a.package_name || 'Standard')} • Amount: ₹${Number(a.amount || 0)}</div>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold ${a.status === 'Approved' ? 'bg-emerald-100 text-emerald-800' : (a.status === 'Pending_Approval' ? 'bg-amber-100 text-amber-900' : 'bg-slate-200 text-slate-700')}">${escapeHtml(a.status)}</span>
                    </div>
                    <div class="bg-white p-2 rounded border border-slate-200 text-[11px]">
                        <strong>Payment Ref:</strong> <span class="font-mono text-slate-900">${escapeHtml(a.payment_reference || 'N/A')}</span>
                        <span class="ml-2 text-[10px] ${a.payment_status === 'Verified' ? 'text-emerald-700 font-bold' : 'text-amber-700'}">(${escapeHtml(a.payment_status || 'Pending')})</span>
                    </div>
                    ${a.status === 'Pending_Approval' ? `
                        <div class="flex gap-2 pt-1">
                            <button type="button" onclick="verifyAdPayment(${a.id}, 'Approved')" class="flex-1 py-1 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded transition cursor-pointer">✓ Verify & Approve</button>
                            <button type="button" onclick="verifyAdPayment(${a.id}, 'Rejected')" class="px-3 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 font-bold text-xs rounded transition cursor-pointer">Reject</button>
                        </div>
                    ` : ''}
                </div>
            `).join('');
        }

        async function saveAdsenseSettings() {
            const btn = document.getElementById('btn-save-adsense');
            btn.disabled = true;
            btn.innerText = 'Saving...';
            try {
                const res = await fetch(appPath('/api/admin_manage_ads.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update_adsense',
                        adsense_enabled: document.getElementById('adsense_enabled').checked ? 1 : 0,
                        adsense_publisher_id: document.getElementById('adsense_pub_id').value.trim(),
                        adsense_slot_id: document.getElementById('adsense_slot_id').value.trim(),
                        csrf_token: csrfToken
                    })
                });
                const json = await res.json();
                showToast(json.message || 'AdSense settings updated.', res.ok ? 'success' : 'error');
            } catch (e) {
                showToast('Failed to save settings.', 'error');
            } finally {
                btn.disabled = false;
                btn.innerText = 'Save AdSense Settings';
            }
        }

        async function verifyAdPayment(adId, decision) {
            try {
                const res = await fetch(appPath('/api/admin_manage_ads.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'verify_payment',
                        ad_id: adId,
                        decision: decision,
                        csrf_token: csrfToken
                    })
                });
                const json = await res.json();
                if (res.ok && json.status === 'success') {
                    showToast(json.message || 'Ad status updated.', 'success');
                    fetchAdSettings();
                } else {
                    showToast(json.message || 'Verification error.', 'error');
                }
            } catch (e) {
                showToast('Network error.', 'error');
            }
        }
    </script>
</body>
</html>
