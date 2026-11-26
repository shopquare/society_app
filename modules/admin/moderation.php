<?php
// modules/admin/moderation.php
require_once __DIR__ . '/../../config/db.php';
requirePermission('community.moderate');
$csrfToken = generateCsrfToken();
$base = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="app-base" content="<?php echo $base; ?>">
    <title>Moderation Center &amp; Tenant KYC - SecureGate</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script>
    <script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 font-sans min-h-screen flex flex-col">
    <?php $activeTab = 'moderation'; require_once __DIR__ . '/navbar.php'; ?>
    <main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 font-serif">Moderation Center &amp; Tenant KYC Lifecycle</h1>
            <p class="text-sm text-slate-500 mt-1">Review flagged marketplace/forum/service content and verify resident move-in, move-out &amp; lease dates.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h2 class="font-bold text-slate-900 mb-3">Marketplace Reports</h2>
                <div id="mkt-reports" class="space-y-3 text-sm">Loading...</div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h2 class="font-bold text-slate-900 mb-3">Discussion Forum Reports</h2>
                <div id="disc-reports" class="space-y-3 text-sm">Loading...</div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h2 class="font-bold text-slate-900 mb-3">Local Service Reports</h2>
                <div id="svc-reports" class="space-y-3 text-sm">Loading...</div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="p-5 border-b border-slate-100">
                <h2 class="text-lg font-bold text-slate-900 font-serif">Resident &amp; Tenant Move-In / Move-Out / Lease Verification</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600">
                        <tr>
                            <th class="px-5 py-3">Unit &amp; Type</th>
                            <th class="px-5 py-3">Resident / Tenant</th>
                            <th class="px-5 py-3">Move-In / Lease End</th>
                            <th class="px-5 py-3">KYC Status</th>
                            <th class="px-5 py-3 text-right">Update</th>
                        </tr>
                    </thead>
                    <tbody id="tenancy-tbody" class="divide-y divide-slate-100">
                        <tr><td colspan="5" class="px-5 py-8 text-center text-slate-400">Loading occupancy records...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        const csrfToken = document.getElementById('csrf_token').value;
        document.addEventListener('DOMContentLoaded', loadModeration);

        async function loadModeration() {
            const res = await fetch(appPath('/api/admin_moderation_center.php'));
            const r = await res.json();
            if (!res.ok || r.status !== 'success') return;

            const mr = r.data.marketplace_reports || [];
            document.getElementById('mkt-reports').innerHTML = mr.length ? mr.map(x => `
                <div class="p-3 border border-slate-100 rounded-lg bg-slate-50">
                    <div class="font-semibold text-slate-900">${escapeHtml(x.listing_title)} (${escapeHtml(x.unit_number)})</div>
                    <div class="text-xs text-slate-500">Reason: ${escapeHtml(x.reason)} • Status: ${escapeHtml(x.status)}</div>
                    ${x.status === 'Pending' ? `<div class="flex gap-2 mt-2"><button onclick="modAct('moderate_marketplace',${x.id},'archive_listing')" class="px-2 py-1 text-xs bg-rose-600 text-white rounded cursor-pointer">Archive Listing</button><button onclick="modAct('moderate_marketplace',${x.id},'dismiss')" class="px-2 py-1 text-xs bg-slate-200 text-slate-700 rounded cursor-pointer">Dismiss</button></div>` : ''}
                </div>`).join('') : '<p class="text-slate-400 text-xs">Zero marketplace reports.</p>';

            const dr = r.data.discussion_reports || [];
            document.getElementById('disc-reports').innerHTML = dr.length ? dr.map(x => `
                <div class="p-3 border border-slate-100 rounded-lg bg-slate-50">
                    <div class="font-semibold text-slate-900">${escapeHtml(x.discussion_title)}</div>
                    <div class="text-xs text-slate-500">${escapeHtml(x.reason)} • ${escapeHtml(x.status)}</div>
                    ${x.status === 'Open' ? `<div class="flex gap-2 mt-2"><button onclick="modAct('moderate_discussion_report',${x.id},'remove_post')" class="px-2 py-1 text-xs bg-rose-600 text-white rounded cursor-pointer">Remove Post</button><button onclick="modAct('moderate_discussion_report',${x.id},'dismiss')" class="px-2 py-1 text-xs bg-slate-200 text-slate-700 rounded cursor-pointer">Dismiss</button></div>` : ''}
                </div>`).join('') : '<p class="text-slate-400 text-xs">Zero forum reports.</p>';

            const sr = r.data.service_reports || [];
            document.getElementById('svc-reports').innerHTML = sr.length ? sr.map(x => `
                <div class="p-3 border border-slate-100 rounded-lg bg-slate-50">
                    <div class="font-semibold text-slate-900">${escapeHtml(x.business_name)}</div>
                    <div class="text-xs text-slate-500">${escapeHtml(x.reason)} • ${escapeHtml(x.status)}</div>
                    ${x.status === 'Open' ? `<div class="flex gap-2 mt-2"><button onclick="modAct('moderate_service_report',${x.id},'disable_provider')" class="px-2 py-1 text-xs bg-rose-600 text-white rounded cursor-pointer">Disable Service</button><button onclick="modAct('moderate_service_report',${x.id},'resolve')" class="px-2 py-1 text-xs bg-slate-200 text-slate-700 rounded cursor-pointer">Dismiss</button></div>` : ''}
                </div>`).join('') : '<p class="text-slate-400 text-xs">Zero service reports.</p>';

            const tens = r.data.tenancies || [];
            document.getElementById('tenancy-tbody').innerHTML = tens.length ? tens.map(t => `
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3 font-bold">${escapeHtml(t.unit_number)} <span class="text-xs px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 capitalize">${escapeHtml(t.occupancy_type)}</span></td>
                    <td class="px-5 py-3"><div class="font-semibold">${escapeHtml(t.resident_name)}</div><div class="text-xs text-slate-400">${escapeHtml(t.phone_number)}</div></td>
                    <td class="px-5 py-3 text-xs">In: ${escapeHtml(t.move_in_date)} ${t.lease_end_date ? `<br>Lease End: <strong>${escapeHtml(t.lease_end_date)}</strong>` : ''}</td>
                    <td class="px-5 py-3"><select id="kyc-st-${t.id}" class="px-2 py-1 border border-slate-300 rounded text-xs bg-white"><option value="Pending" ${t.verification_status === 'Pending' ? 'selected' : ''}>Pending</option><option value="Verified" ${t.verification_status === 'Verified' ? 'selected' : ''}>Verified</option><option value="Expired" ${t.verification_status === 'Expired' ? 'selected' : ''}>Expired</option><option value="Moved_Out" ${t.verification_status === 'Moved_Out' ? 'selected' : ''}>Moved Out</option></select></td>
                    <td class="px-5 py-3 text-right"><button onclick="updateKyc(${t.id})" class="px-3 py-1 text-xs font-semibold bg-brand-900 text-white rounded cursor-pointer">Save</button></td>
                </tr>`).join('') : '<tr><td colspan="5" class="px-5 py-8 text-center text-slate-400">No unit occupancies found.</td></tr>';
        }

        async function modAct(action, reportId, operation) {
            const res = await fetch(appPath('/api/admin_moderation_center.php'), {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ csrf_token: csrfToken, action, report_id: reportId, operation })
            });
            const r = await res.json();
            showToast(r.message || 'Done', res.ok ? 'success' : 'error');
            if (res.ok) loadModeration();
        }

        async function updateKyc(id) {
            const st = document.getElementById('kyc-st-' + id).value;
            const res = await fetch(appPath('/api/admin_moderation_center.php'), {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ csrf_token: csrfToken, action: 'update_tenancy_verification', unit_link_id: id, verification_status: st })
            });
            const r = await res.json();
            showToast(r.message || 'Saved', res.ok ? 'success' : 'error');
            if (res.ok) loadModeration();
        }
    </script>
</body>
</html>
