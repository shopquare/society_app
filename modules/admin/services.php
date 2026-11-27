<?php
// modules/admin/services.php
require_once __DIR__ . '/../../config/db.php';
requirePermission('services.view');
$csrfToken = generateCsrfToken();
$base = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="app-base" content="<?php echo $base; ?>">
    <title>Local Service Directory Management - SecureGate</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script>
    <link rel="stylesheet" href="<?php echo $base; ?>/assets/css/style.css">
    <script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 font-sans min-h-screen flex flex-col">
    <?php $activeTab = 'services'; require_once __DIR__ . '/navbar.php'; ?>
    <main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8">
        <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 font-serif">Local Services &amp; Verified Businesses</h1>
                <p class="text-sm text-slate-500 mt-1">Approve resident suggestions, award Verified badges, feature providers, and resolve service reports.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="bg-white rounded-xl border border-slate-200 p-6 h-fit">
                <h2 class="text-lg font-bold text-slate-900 font-serif mb-4">Add / Edit Verified Provider</h2>
                <form onsubmit="event.preventDefault(); saveProvider();" class="space-y-3 text-sm">
                    <input type="hidden" id="sp-id" value="">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Category</label>
                        <select id="sp-cat" required class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white"></select>
                    </div>
                    <input type="text" id="sp-name" required placeholder="Business / Provider Name" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <input type="text" id="sp-contact" aria-label="Contact Person" placeholder="Contact Person" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                        <input type="text" id="sp-phone" aria-label="Phone Number" required placeholder="Phone (10 digits)" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <input type="text" id="sp-area" aria-label="Service Area" placeholder="Service Area" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                        <input type="text" id="sp-hours" aria-label="Service Timings" value="8:00 AM - 8:00 PM" placeholder="Timings" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    </div>
                    <textarea id="sp-desc" rows="2" placeholder="Services offered, pricing notes..." class="w-full px-3 py-2 border border-slate-300 rounded-lg"></textarea>
                    <div class="flex flex-wrap gap-3 text-xs pt-1">
                        <label class="inline-flex items-center gap-1"><input type="checkbox" id="sp-verified" checked> Society Verified</label>
                        <label class="inline-flex items-center gap-1"><input type="checkbox" id="sp-featured"> Featured</label>
                        <label class="inline-flex items-center gap-1 text-rose-700"><input type="checkbox" id="sp-emergency"> 24x7 Emergency</label>
                    </div>
                    <button type="submit" class="w-full py-2.5 bg-brand-900 hover:bg-brand-800 text-white font-semibold rounded-lg cursor-pointer">Save Service Provider</button>
                </form>
            </div>

            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-xl border border-slate-200 p-6">
                    <h2 class="text-lg font-bold text-slate-900 font-serif mb-4">Directory &amp; Approval Queue</h2>
                    <div id="admin-providers-list" class="space-y-3 text-sm">Loading providers...</div>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-6">
                    <h2 class="text-lg font-bold text-slate-900 font-serif mb-4">Service Issue Reports</h2>
                    <div id="admin-reports-list" class="space-y-3 text-sm">Loading reports...</div>
                </div>
            </div>
        </div>
    </main>

    <script>
        const csrfToken = document.getElementById('csrf_token').value;
        document.addEventListener('DOMContentLoaded', loadAdminServices);

        async function loadAdminServices() {
            const res = await fetch(appPath('/api/admin_manage_services.php'));
            const r = await res.json();
            if (!res.ok || r.status !== 'success') return;
            const d = r.data || r;

            document.getElementById('sp-cat').innerHTML = (d.categories || []).map(c => `<option value="${c.id}">${escapeHtml(c.icon + ' ' + c.name)}</option>`).join('');

            const provs = d.providers || [];
            document.getElementById('admin-providers-list').innerHTML = provs.length ? provs.map(p => `
                <div class="p-4 border border-slate-200 rounded-xl flex flex-col sm:flex-row justify-between gap-3 bg-slate-50/50">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-bold text-slate-900">${escapeHtml(p.business_name)}</span>
                            <span class="text-xs px-2 py-0.5 rounded bg-slate-200 text-slate-700">${escapeHtml(p.category_name)}</span>
                            ${Number(p.is_verified) ? '<span class="text-xs px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-semibold">✓ Verified</span>' : ''}
                            ${Number(p.is_featured) ? '<span class="text-xs px-2 py-0.5 rounded bg-amber-100 text-amber-800 font-semibold">★ Featured</span>' : ''}
                            <span class="text-xs px-2 py-0.5 rounded ${p.approval_status === 'Approved' ? 'bg-emerald-50 text-emerald-700' : (p.approval_status === 'Pending' ? 'bg-amber-50 text-amber-700 font-bold' : 'bg-rose-50 text-rose-700')}">${escapeHtml(p.approval_status)}</span>
                        </div>
                        <div class="text-xs text-slate-500 mt-1">📞 ${escapeHtml(p.phone_number)} • ★ ${p.avg_rating} (${p.review_count} reviews) • ${escapeHtml(p.timing_hours || '')}</div>
                        <p class="text-xs text-slate-600 mt-1">${escapeHtml(p.description || '')}</p>
                    </div>
                    <div class="flex flex-wrap gap-1.5 items-start shrink-0">
                        ${p.approval_status !== 'Approved' ? `<button onclick="modProvider(${p.id},'approve')" class="px-2.5 py-1 text-xs font-semibold bg-emerald-600 text-white rounded-lg cursor-pointer">Approve</button>` : ''}
                        ${p.approval_status === 'Pending' ? `<button onclick="modProvider(${p.id},'reject')" class="px-2.5 py-1 text-xs font-semibold bg-rose-600 text-white rounded-lg cursor-pointer">Reject</button>` : ''}
                        <button onclick="modProvider(${p.id},'toggle_verified')" class="px-2.5 py-1 text-xs font-semibold bg-white border border-slate-300 rounded-lg cursor-pointer">${Number(p.is_verified) ? 'Unverify' : 'Verify'}</button>
                        <button onclick="modProvider(${p.id},'toggle_featured')" class="px-2.5 py-1 text-xs font-semibold bg-white border border-slate-300 rounded-lg cursor-pointer">${Number(p.is_featured) ? 'Unfeature' : 'Feature'}</button>
                        <button onclick="modProvider(${p.id},'toggle_active')" class="px-2.5 py-1 text-xs font-semibold ${Number(p.is_active) ? 'text-rose-700' : 'text-emerald-700'} bg-white border border-slate-300 rounded-lg cursor-pointer">${Number(p.is_active) ? 'Disable' : 'Enable'}</button>
                    </div>
                </div>`).join('') : '<p class="text-slate-400">No providers found.</p>';

            const reps = d.reports || [];
            document.getElementById('admin-reports-list').innerHTML = reps.length ? reps.map(rp => `
                <div class="p-3 border border-slate-200 rounded-lg flex justify-between items-center gap-3">
                    <div>
                        <div class="font-semibold text-slate-900">${escapeHtml(rp.business_name)} <span class="text-xs px-2 py-0.5 rounded bg-rose-50 text-rose-700">${escapeHtml(rp.status)}</span></div>
                        <div class="text-xs text-slate-600">${escapeHtml(rp.reason)}: ${escapeHtml(rp.details || '')} — Reported by ${escapeHtml(rp.reporter_name || 'Resident')}</div>
                    </div>
                    ${rp.status === 'Open' ? `<div class="flex gap-1"><button onclick="resolveReport(${rp.id},'Resolved',false)" class="px-2.5 py-1 text-xs bg-emerald-600 text-white rounded cursor-pointer">Resolve</button><button onclick="resolveReport(${rp.id},'Resolved',true)" class="px-2.5 py-1 text-xs bg-rose-600 text-white rounded cursor-pointer">Resolve &amp; Disable</button></div>` : ''}
                </div>`).join('') : '<p class="text-slate-400">No open service reports.</p>';
        }

        async function saveProvider() {
            const res = await fetch(appPath('/api/admin_manage_services.php'), {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    csrf_token: csrfToken, action: 'save_provider',
                    id: document.getElementById('sp-id').value || 0,
                    category_id: document.getElementById('sp-cat').value,
                    business_name: document.getElementById('sp-name').value.trim(),
                    contact_person: document.getElementById('sp-contact').value.trim(),
                    phone_number: document.getElementById('sp-phone').value.trim(),
                    service_area: document.getElementById('sp-area').value.trim(),
                    timing_hours: document.getElementById('sp-hours').value.trim(),
                    description: document.getElementById('sp-desc').value.trim(),
                    is_verified: document.getElementById('sp-verified').checked,
                    is_featured: document.getElementById('sp-featured').checked,
                    is_emergency_service: document.getElementById('sp-emergency').checked
                })
            });
            const r = await res.json();
            showToast(r.message || 'Saved', res.ok ? 'success' : 'error');
            if (res.ok) { document.getElementById('sp-name').value = ''; document.getElementById('sp-phone').value = ''; loadAdminServices(); }
        }

        async function modProvider(id, operation) {
            const res = await fetch(appPath('/api/admin_manage_services.php'), {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ csrf_token: csrfToken, action: 'moderate_provider', id, operation })
            });
            const r = await res.json();
            showToast(r.message || 'Updated', res.ok ? 'success' : 'error');
            if (res.ok) loadAdminServices();
        }

        async function resolveReport(reportId, status, deactivate) {
            const res = await fetch(appPath('/api/admin_manage_services.php'), {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ csrf_token: csrfToken, action: 'resolve_report', report_id: reportId, status, deactivate_provider: deactivate })
            });
            const r = await res.json();
            showToast(r.message || 'Updated', res.ok ? 'success' : 'error');
            if (res.ok) loadAdminServices();
        }
    </script>
</body>
</html>
