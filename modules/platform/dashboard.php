<?php
// modules/platform/dashboard.php
require_once __DIR__ . '/../../config/db.php';
requirePermission('saas.platform');
if (!isPlatformSuperAdmin()) {
    http_response_code(403);
    exit('Forbidden: Platform Super Admin required.');
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
    <title>SaaS Platform Command Center - SecureGate</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script>
    <script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 font-sans min-h-screen flex flex-col">
    <?php $activeTab = 'platform'; require_once __DIR__ . '/../admin/navbar.php'; ?>
    <main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 font-serif">Multi-Society SaaS Platform Console</h1>
                <p class="text-sm text-slate-500 mt-1">Manage tenant societies, subscription tiers, quotas, SaaS invoices, and support escalations.</p>
            </div>
            <a href="<?php echo $base; ?>/register-society" target="_blank" class="px-4 py-2 text-xs font-bold bg-emerald-700 hover:bg-emerald-800 text-white rounded-lg">+ Onboard New Society</a>
        </div>

        <div id="saas-kpis" class="grid grid-cols-2 sm:grid-cols-4 gap-4"></div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-xl border border-slate-200 p-6 h-fit">
                <h2 class="text-lg font-bold text-slate-900 font-serif mb-4">Assign / Renew Society Plan</h2>
                <form onsubmit="event.preventDefault(); assignSub();" class="space-y-3 text-sm">
                    <select id="sub-soc" required class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white"></select>
                    <select id="sub-plan" required class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white"></select>
                    <div class="grid grid-cols-2 gap-2">
                        <select id="sub-cycle" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
                            <option value="Monthly">Monthly</option>
                            <option value="Annual">Annual</option>
                        </select>
                        <select id="sub-status" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
                            <option value="Active">Active</option>
                            <option value="Trial">14d Trial</option>
                            <option value="Grace_Period">Grace Period</option>
                            <option value="Expired">Expired (Read-Only)</option>
                        </select>
                    </div>
                    <label class="inline-flex items-center gap-1.5 text-xs"><input type="checkbox" id="sub-gen-inv" checked> Generate SaaS Billing Invoice</label>
                    <button type="submit" class="w-full py-2.5 bg-brand-900 text-white font-semibold rounded-lg cursor-pointer">Update Society Subscription</button>
                </form>
            </div>

            <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="p-5 border-b border-slate-100"><h2 class="text-lg font-bold text-slate-900 font-serif">Registered Tenant Societies</h2></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs whitespace-nowrap">
                        <thead class="bg-slate-50 border-b border-slate-200 text-slate-600">
                            <tr>
                                <th class="px-4 py-3">Society &amp; Code</th>
                                <th class="px-4 py-3">Plan &amp; Cycle</th>
                                <th class="px-4 py-3">Usage (Flats/Users)</th>
                                <th class="px-4 py-3">Sub Status</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="saas-socs-tbody" class="divide-y divide-slate-100"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h2 class="font-bold text-slate-900 mb-3">SaaS Platform Billing Invoices</h2>
                <div id="saas-inv-list" class="space-y-2 text-xs">Loading...</div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h2 class="font-bold text-slate-900 mb-3">Society Support Tickets</h2>
                <div id="saas-tkt-list" class="space-y-2 text-xs">Loading...</div>
            </div>
        </div>
    </main>

    <script>
        const csrfToken = document.getElementById('csrf_token').value;
        document.addEventListener('DOMContentLoaded', loadPlatform);

        async function loadPlatform() {
            const res = await fetch(appPath('/api/platform_admin.php'));
            const r = await res.json();
            if (!res.ok || r.status !== 'success') return;

            const k = r.data.kpis || {};
            document.getElementById('saas-kpis').innerHTML = `
                <div class="bg-white p-4 rounded-xl border border-slate-200"><span class="text-xs font-bold text-slate-400 uppercase">Total Societies</span><div class="text-2xl font-black text-slate-900 mt-1">${k.total_societies || 0}</div></div>
                <div class="bg-white p-4 rounded-xl border border-slate-200"><span class="text-xs font-bold text-slate-400 uppercase">Active Tenants</span><div class="text-2xl font-black text-emerald-700 mt-1">${k.active_societies || 0}</div></div>
                <div class="bg-white p-4 rounded-xl border border-slate-200"><span class="text-xs font-bold text-slate-400 uppercase">On Trial</span><div class="text-2xl font-black text-amber-600 mt-1">${k.trial_societies || 0}</div></div>
                <div class="bg-white p-4 rounded-xl border border-slate-200"><span class="text-xs font-bold text-slate-400 uppercase">Estimated MRR</span><div class="text-2xl font-black text-brand-900 mt-1">₹${parseFloat(k.estimated_mrr || 0).toFixed(0)}</div></div>`;

            const socs = r.data.societies || [];
            const plans = r.data.plans || [];
            document.getElementById('sub-soc').innerHTML = socs.map(s => `<option value="${s.id}">${escapeHtml(s.society_name)} (${escapeHtml(s.society_code)})</option>`).join('');
            document.getElementById('sub-plan').innerHTML = plans.map(p => `<option value="${p.id}">${escapeHtml(p.plan_name)} (₹${parseFloat(p.monthly_price).toFixed(0)}/mo)</option>`).join('');

            document.getElementById('saas-socs-tbody').innerHTML = socs.map(s => `
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3"><div class="font-bold text-slate-900">${escapeHtml(s.society_name)}</div><div class="font-mono text-[11px] text-slate-400">${escapeHtml(s.society_code)} • ${escapeHtml(s.city || '')}</div></td>
                    <td class="px-4 py-3"><strong>${escapeHtml(s.plan_name || 'Enterprise')}</strong><div class="text-slate-400">${escapeHtml(s.billing_cycle || 'Annual')}</div></td>
                    <td class="px-4 py-3">${s.total_flats || 0} / ${s.max_flats || 5000} Flats • ${s.total_users || 0} Users</td>
                    <td class="px-4 py-3"><span class="px-2 py-0.5 rounded font-semibold ${s.status === 'Suspended' || s.subscription_status === 'Expired' ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700'}">${escapeHtml(s.subscription_status || s.status)}</span></td>
                    <td class="px-4 py-3 text-right">${s.id > 1 ? `<button onclick="toggleSuspend(${s.id}, '${s.status === 'Suspended' ? 'Active' : 'Suspended'}')" class="px-2.5 py-1 rounded border border-slate-300 font-semibold cursor-pointer">${s.status === 'Suspended' ? 'Activate' : 'Suspend'}</button>` : '<span class="text-slate-400">Primary</span>'}</td>
                </tr>`).join('');

            const invs = r.data.saas_invoices || [];
            document.getElementById('saas-inv-list').innerHTML = invs.length ? invs.map(i => `
                <div class="p-3 border border-slate-100 rounded-lg flex justify-between items-center">
                    <div><strong>${escapeHtml(i.invoice_number)}</strong> • ${escapeHtml(i.society_name)} (${escapeHtml(i.billing_period)})</div>
                    <div class="flex items-center gap-2"><span class="font-bold">₹${parseFloat(i.amount).toFixed(0)}</span>${i.status === 'Unpaid' ? `<button onclick="markPaid(${i.id})" class="px-2 py-1 bg-emerald-700 text-white rounded cursor-pointer">Mark Paid</button>` : `<span class="text-emerald-700 font-bold">${escapeHtml(i.status)}</span>`}</div>
                </div>`).join('') : '<p class="text-slate-400">No SaaS billing invoices generated yet.</p>';

            const tkts = r.data.tickets || [];
            document.getElementById('saas-tkt-list').innerHTML = tkts.length ? tkts.map(t => `
                <div class="p-3 border border-slate-100 rounded-lg flex justify-between items-center">
                    <div><strong>${escapeHtml(t.subject)}</strong> • ${escapeHtml(t.society_name)} (${escapeHtml(t.status)})</div>
                    ${t.status !== 'Resolved' ? `<button onclick="resolveTkt(${t.id})" class="px-2 py-1 bg-brand-900 text-white rounded cursor-pointer">Resolve</button>` : '<span class="text-emerald-700">Resolved</span>'}
                </div>`).join('') : '<p class="text-slate-400">Zero open platform support tickets.</p>';
        }

        async function assignSub() {
            const res = await fetch(appPath('/api/platform_admin.php'), {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ csrf_token: csrfToken, action: 'assign_subscription', society_id: document.getElementById('sub-soc').value, plan_id: document.getElementById('sub-plan').value, billing_cycle: document.getElementById('sub-cycle').value, status: document.getElementById('sub-status').value, generate_invoice: document.getElementById('sub-gen-inv').checked })
            });
            const r = await res.json();
            showToast(r.message || 'Updated', res.ok ? 'success' : 'error');
            if (res.ok) loadPlatform();
        }

        async function toggleSuspend(societyId, status) {
            const res = await fetch(appPath('/api/platform_admin.php'), { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ csrf_token: csrfToken, action: 'toggle_society_suspend', society_id: societyId, status }) });
            const r = await res.json();
            showToast(r.message || 'Updated', res.ok ? 'success' : 'error');
            loadPlatform();
        }

        async function markPaid(invoiceId) {
            const res = await fetch(appPath('/api/platform_admin.php'), { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ csrf_token: csrfToken, action: 'mark_saas_invoice_paid', invoice_id: invoiceId }) });
            if (res.ok) loadPlatform();
        }

        async function resolveTkt(ticketId) {
            const res = await fetch(appPath('/api/platform_admin.php'), { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ csrf_token: csrfToken, action: 'update_ticket', ticket_id: ticketId, status: 'Resolved', platform_response: 'Resolved by Platform Operations' }) });
            if (res.ok) loadPlatform();
        }
    </script>
</body>
</html>
