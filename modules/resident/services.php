<?php
// modules/resident/services.php
require_once __DIR__ . '/../../config/db.php';
requireLogin();
if (!hasRole([1, 2, 3])) {
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
    <title>Local Services Directory - SecureGate</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script>
    <script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 font-sans min-h-screen flex flex-col">
    <nav class="bg-brand-900 shadow-md border-b border-brand-800 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between h-16 items-center">
            <div class="flex items-center gap-3">
                <a href="<?php echo $base; ?>/resident/dashboard" class="text-white/80 hover:text-white">← Back</a>
                <span class="font-serif text-xl font-semibold text-white">Verified Local Services</span>
            </div>
            <button type="button" onclick="document.getElementById('suggest-modal').classList.remove('hidden')" class="px-3.5 py-1.5 text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg cursor-pointer">+ Suggest Service</button>
        </div>
    </nav>

    <main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

        <!-- Search & Filters -->
        <div class="bg-white rounded-xl border border-slate-200 p-4 flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between">
            <input type="text" id="svc-search" oninput="loadServices()" placeholder="Search plumber, electrician, WiFi, clinic..." class="flex-grow px-4 py-2 border border-slate-300 rounded-lg text-sm">
            <div class="flex flex-wrap gap-2 items-center text-xs font-semibold">
                <label class="px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 inline-flex items-center gap-1.5 cursor-pointer"><input type="checkbox" id="flt-verified" onchange="loadServices()"> ✓ Verified Only</label>
                <label class="px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 inline-flex items-center gap-1.5 cursor-pointer"><input type="checkbox" id="flt-fav" onchange="loadServices()"> ❤ Favorites</label>
                <select id="svc-sort" onchange="loadServices()" class="px-3 py-2 border border-slate-200 rounded-lg bg-white">
                    <option value="verified">Featured &amp; Verified</option>
                    <option value="rating">Highest Rated</option>
                    <option value="newest">Recently Added</option>
                </select>
            </div>
        </div>

        <!-- Category Chips -->
        <div id="category-chips" class="flex flex-wrap gap-2"></div>

        <!-- Providers Grid -->
        <div id="providers-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5"></div>
    </main>

    <!-- Suggest Provider Modal -->
    <div id="suggest-modal" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold font-serif">Suggest a Local Service</h3>
                <button onclick="document.getElementById('suggest-modal').classList.add('hidden')" class="text-slate-400">✕</button>
            </div>
            <form onsubmit="event.preventDefault(); submitSuggestion();" class="space-y-3 text-sm">
                <select id="sug-cat" required class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white"></select>
                <input type="text" id="sug-name" required placeholder="Business / Worker Name" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                <input type="text" id="sug-phone" required placeholder="10-Digit Phone Number" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                <input type="text" id="sug-area" placeholder="Location / Area" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                <textarea id="sug-desc" rows="2" placeholder="Why do you recommend this service?" class="w-full px-3 py-2 border border-slate-300 rounded-lg"></textarea>
                <button type="submit" class="w-full py-2.5 bg-brand-900 text-white font-semibold rounded-lg cursor-pointer">Submit for Verification</button>
            </form>
        </div>
    </div>

    <!-- Review & Details Modal -->
    <div id="review-modal" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold font-serif" id="rev-title">Service Reviews</h3>
                <button onclick="document.getElementById('review-modal').classList.add('hidden')" class="text-slate-400">✕</button>
            </div>
            <div id="rev-list" class="space-y-2 text-sm max-h-48 overflow-y-auto"></div>
            <form onsubmit="event.preventDefault(); submitReview();" class="border-t pt-3 space-y-2 text-sm">
                <input type="hidden" id="rev-provider-id">
                <label class="font-semibold block">Rate this Provider (1 per resident)</label>
                <select id="rev-rating" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
                    <option value="5">★★★★★ (5 - Excellent)</option>
                    <option value="4">★★★★ (4 - Good)</option>
                    <option value="3">★★★ (3 - Average)</option>
                    <option value="2">★★ (2 - Below Average)</option>
                    <option value="1">★ (1 - Poor)</option>
                </select>
                <textarea id="rev-text" rows="2" placeholder="Share your experience..." class="w-full px-3 py-2 border border-slate-300 rounded-lg"></textarea>
                <button type="submit" class="w-full py-2 bg-brand-900 text-white font-semibold rounded-lg cursor-pointer">Post Review</button>
            </form>
            <div class="border-t pt-3 text-right">
                <button type="button" onclick="reportCurrentProvider()" class="text-xs text-rose-700 hover:underline cursor-pointer">🚩 Report Wrong Number / Misconduct</button>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = document.getElementById('csrf_token').value;
        let selectedCategory = 0;

        document.addEventListener('DOMContentLoaded', loadServices);

        function selectCategory(id) {
            selectedCategory = id;
            loadServices();
        }

        async function loadServices() {
            const q = encodeURIComponent(document.getElementById('svc-search').value.trim());
            const v = document.getElementById('flt-verified').checked ? 1 : 0;
            const f = document.getElementById('flt-fav').checked ? 1 : 0;
            const s = document.getElementById('svc-sort').value;
            const res = await fetch(appPath(`/api/services_directory.php?category_id=${selectedCategory}&q=${q}&verified_only=${v}&favorites_only=${f}&sort=${s}`));
            const r = await res.json();
            if (!res.ok || r.status !== 'success') return;

            const cats = r.data.categories || [];
            document.getElementById('sug-cat').innerHTML = cats.map(c => `<option value="${c.id}">${escapeHtml(c.icon + ' ' + c.name)}</option>`).join('');
            document.getElementById('category-chips').innerHTML =
                `<button onclick="selectCategory(0)" class="px-3 py-1.5 rounded-full text-xs font-semibold border cursor-pointer ${selectedCategory === 0 ? 'bg-brand-900 text-white border-brand-900' : 'bg-white text-slate-700 border-slate-200'}">All Categories</button>` +
                cats.map(c => `<button onclick="selectCategory(${c.id})" class="px-3 py-1.5 rounded-full text-xs font-semibold border cursor-pointer ${selectedCategory === Number(c.id) ? 'bg-brand-900 text-white border-brand-900' : 'bg-white text-slate-700 border-slate-200'}">${escapeHtml(c.icon)} ${escapeHtml(c.name)}</button>`).join('');

            const provs = r.data.providers || [];
            document.getElementById('providers-grid').innerHTML = provs.length ? provs.map(p => {
                const waNum = (p.whatsapp_number || p.phone_number || '').replace(/\D/g, '');
                const waHref = `https://wa.me/${waNum.length === 10 ? '91' + waNum : waNum}?text=${encodeURIComponent('Hello ' + p.business_name + ', found your contact on SecureGate Society Directory.')}`;
                return `
                <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-2xs flex flex-col justify-between gap-4">
                    <div>
                        <div class="flex justify-between items-start gap-2">
                            <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700">${escapeHtml(p.category_icon + ' ' + p.category_name)}</span>
                            <button onclick="toggleFav(${p.id})" class="text-lg cursor-pointer" title="Save Favorite">${Number(p.is_favorite) ? '❤️' : '🤍'}</button>
                        </div>
                        <h3 class="text-base font-bold text-slate-900 mt-2 flex items-center gap-1.5">${escapeHtml(p.business_name)} ${Number(p.is_verified) ? '<span class="text-xs px-1.5 py-0.5 bg-emerald-100 text-emerald-800 rounded">✓ Verified</span>' : ''}</h3>
                        <p class="text-xs text-slate-500 mt-1">${escapeHtml(p.description || 'Verified society neighborhood service.')}</p>
                        <div class="text-xs text-slate-600 mt-2 space-y-0.5">
                            <div>🕒 ${escapeHtml(p.timing_hours || '8 AM - 8 PM')} ${Number(p.is_emergency_service) ? '<span class="text-rose-700 font-bold">• 24x7 Emergency</span>' : ''}</div>
                            <div>📍 ${escapeHtml(p.service_area || 'Society Campus')}</div>
                            <div class="font-semibold text-amber-600">★ ${parseFloat(p.avg_rating || 0).toFixed(1)} (${p.review_count || 0} reviews)</div>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 pt-2 border-t border-slate-100">
                        <a href="tel:${escapeHtml(p.phone_number)}" class="flex-1 text-center py-2 text-xs font-bold bg-brand-900 text-white rounded-lg hover:bg-brand-800">📞 Call</a>
                        <a href="${waHref}" target="_blank" rel="noopener noreferrer" class="flex-1 text-center py-2 text-xs font-bold bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">💬 WhatsApp</a>
                        <button onclick="openReviews(${p.id}, '${escapeHtml(p.business_name).replace(/'/g, '')}')" class="px-3 py-2 text-xs font-bold bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 cursor-pointer">★ Reviews</button>
                    </div>
                </div>`;
            }).join('') : '<div class="col-span-full text-center py-12 bg-white rounded-xl border border-slate-200 text-slate-400">No service providers matched your filter.</div>';
        }

        async function toggleFav(pid) {
            const res = await fetch(appPath('/api/services_directory.php'), { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ csrf_token: csrfToken, action: 'toggle_favorite', provider_id: pid }) });
            if (res.ok) loadServices();
        }

        async function openReviews(pid, name) {
            document.getElementById('rev-provider-id').value = pid;
            document.getElementById('rev-title').innerText = name + ' — Reviews';
            document.getElementById('review-modal').classList.remove('hidden');
            const res = await fetch(appPath('/api/services_directory.php?provider_id=' + pid));
            const r = await res.json();
            const list = (r.data && r.data.reviews) ? r.data.reviews : [];
            document.getElementById('rev-list').innerHTML = list.length ? list.map(rv => `
                <div class="p-2.5 bg-slate-50 rounded-lg border border-slate-100">
                    <div class="flex justify-between text-xs font-bold"><span>${escapeHtml(rv.reviewer_name)}</span><span class="text-amber-600">${'★'.repeat(rv.rating)}</span></div>
                    <p class="text-xs text-slate-600 mt-1">${escapeHtml(rv.review_text || '')}</p>
                </div>`).join('') : '<p class="text-slate-400 text-xs">No reviews yet. Be the first resident to review!</p>';
        }

        async function submitReview() {
            const pid = document.getElementById('rev-provider-id').value;
            const res = await fetch(appPath('/api/services_directory.php'), {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ csrf_token: csrfToken, action: 'submit_review', provider_id: pid, rating: document.getElementById('rev-rating').value, review_text: document.getElementById('rev-text').value })
            });
            const r = await res.json();
            showToast(r.message || 'Done', res.ok ? 'success' : 'error');
            if (res.ok) { document.getElementById('rev-text').value = ''; openReviews(pid, document.getElementById('rev-title').innerText.replace(' — Reviews', '')); loadServices(); }
        }

        async function reportCurrentProvider() {
            const pid = document.getElementById('rev-provider-id').value;
            const reason = prompt('Reason for reporting (e.g., Wrong Phone Number, Overcharging, Misconduct):');
            if (!reason) return;
            const res = await fetch(appPath('/api/services_directory.php'), {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ csrf_token: csrfToken, action: 'report_provider', provider_id: pid, reason })
            });
            const r = await res.json();
            showToast(r.message || 'Reported', res.ok ? 'success' : 'error');
        }

        async function submitSuggestion() {
            const res = await fetch(appPath('/api/services_directory.php'), {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    csrf_token: csrfToken, action: 'submit_provider',
                    category_id: document.getElementById('sug-cat').value,
                    business_name: document.getElementById('sug-name').value.trim(),
                    phone_number: document.getElementById('sug-phone').value.trim(),
                    service_area: document.getElementById('sug-area').value.trim(),
                    description: document.getElementById('sug-desc').value.trim()
                })
            });
            const r = await res.json();
            showToast(r.message || 'Submitted', res.ok ? 'success' : 'error');
            if (res.ok) { document.getElementById('suggest-modal').classList.add('hidden'); loadServices(); }
        }
    </script>
</body>
</html>
