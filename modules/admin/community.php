<?php
// modules/admin/community.php
// Unified Admin Console for Lost & Found Claims, Suggestions Box & Discussion Moderation
if (!isset($_SESSION['logged_in'])) { header('Location: ' . appBasePath() . '/login'); exit(); }
requirePermission('community.moderate');
$csrfToken = generateCsrfToken();
$base = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"><meta name="app-base" content="<?php echo $base; ?>">
    <title>Community Moderation - Admin Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700&display=swap" rel="stylesheet">
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script><link rel="stylesheet" href="<?php echo $base; ?>/assets/css/style.css"><script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans flex flex-col min-h-screen">
<?php $activeTab = 'community'; require_once __DIR__ . '/navbar.php'; ?>
<main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <div>
        <h1 class="text-2xl font-bold font-serif text-slate-900">Community Moderation, Claims & Suggestions</h1>
        <p class="text-sm text-slate-500 mt-1">Verify Lost & Found claims, triage resident suggestions, and moderate discussions.</p>
    </div>

    <!-- Section 1: Suggestions Box Triage -->
    <section class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="p-4 border-b border-slate-100 font-bold font-serif text-slate-900">Resident Suggestions Box</div>
        <div id="admin-suggestions-list" class="divide-y divide-slate-100 p-4 text-xs">Loading suggestions...</div>
    </section>

    <!-- Section 2: Lost & Found & Claims -->
    <section class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="p-4 border-b border-slate-100 font-bold font-serif text-slate-900">Lost & Found Items & Ownership Claims</div>
        <div id="admin-claims-list" class="divide-y divide-slate-100 p-4 text-xs">Loading claims...</div>
        <div id="admin-lf-list" class="divide-y divide-slate-100 p-4 border-t border-slate-100 text-xs"></div>
    </section>

    <!-- Section 3: Discussion Reports -->
    <section class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="p-4 border-b border-slate-100 font-bold font-serif text-slate-900">Forum Moderation & Reported Posts</div>
        <div id="admin-reports-list" class="divide-y divide-slate-100 p-4 text-xs">Loading reports...</div>
    </section>
</main>
<script>
const csrf = <?php echo json_encode($csrfToken); ?>;
document.addEventListener('DOMContentLoaded', () => { loadSuggestions(); loadLostFound(); loadReports(); });

async function loadSuggestions() {
    const res = await fetch(appPath('/api/community_suggestions.php'));
    const j = await res.json();
    const list = j.suggestions || [];
    document.getElementById('admin-suggestions-list').innerHTML = list.length ? list.map(s => `
        <div class="py-3 space-y-2">
            <div class="flex flex-wrap justify-between gap-2">
                <div><span class="px-2 py-0.5 bg-blue-50 text-blue-800 rounded font-bold">${escapeHtml(s.category)}</span> <strong class="text-sm text-slate-900">${escapeHtml(s.title)}</strong> <span class="text-slate-400">by ${escapeHtml(s.resident_name)} (${escapeHtml(s.unit_number||'')})</span></div>
                <select id="sugg_st_${Number(s.id)}" class="px-2 py-1 border rounded font-bold bg-white">
                    ${['Submitted','Under Review','Approved','In Progress','Implemented','Rejected'].map(st=>`<option ${s.status===st?'selected':''}>${st}</option>`).join('')}
                </select>
            </div>
            <p class="text-slate-600">${escapeHtml(s.description)}</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                <input type="text" id="sugg_resp_${Number(s.id)}" value="${escapeHtml(s.admin_response||'')}" placeholder="Public response to resident" class="px-2.5 py-1.5 border rounded">
                <input type="text" id="sugg_int_${Number(s.id)}" value="${escapeHtml(s.internal_notes||'')}" placeholder="Internal committee note (hidden)" class="px-2.5 py-1.5 border rounded bg-amber-50">
                <button type="button" onclick="updateSuggestion(${Number(s.id)})" class="px-3 py-1.5 bg-brand-900 text-white font-bold rounded cursor-pointer">Save Response</button>
            </div>
        </div>
    `).join('') : '<div class="text-center text-slate-400 py-4">No suggestions submitted.</div>';
}

async function updateSuggestion(id) {
    const res = await fetch(appPath('/api/community_suggestions.php'), {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({
            action:'admin_update', id, status:document.getElementById('sugg_st_'+id).value,
            admin_response:document.getElementById('sugg_resp_'+id).value,
            internal_notes:document.getElementById('sugg_int_'+id).value, csrf_token:csrf
        })
    });
    const j = await res.json();
    showToast(j.message||'Saved', res.ok?'success':'error');
    if (res.ok) loadSuggestions();
}

async function loadLostFound() {
    const res = await fetch(appPath('/api/community_lost_found.php'));
    const j = await res.json();
    const claims = j.claims || [];
    document.getElementById('admin-claims-list').innerHTML = claims.length ? '<div class="font-bold text-amber-800 mb-2">Pending Ownership Claims</div>' + claims.map(c => `
        <div class="py-2 flex flex-wrap justify-between items-center gap-2">
            <div><strong>${escapeHtml(c.item_name)}</strong> claimed by ${escapeHtml(c.claimant_name)} (Unit ${escapeHtml(c.unit_number||'N/A')}) — Proof: <em>${escapeHtml(c.proof_details)}</em></div>
            <div class="flex gap-2">
                ${c.status==='Pending' ? `<button onclick="verifyClaim(${Number(c.id)},'Verified')" class="px-3 py-1 bg-emerald-700 text-white rounded font-bold cursor-pointer">Verify</button><button onclick="verifyClaim(${Number(c.id)},'Rejected')" class="px-3 py-1 bg-rose-700 text-white rounded font-bold cursor-pointer">Reject</button>` : `<span class="font-bold">${escapeHtml(c.status)}</span>`}
            </div>
        </div>
    `).join('') : '<div class="text-slate-400">No ownership claims submitted yet.</div>';

    document.getElementById('admin-lf-list').innerHTML = (j.items||[]).map(i => `
        <div class="py-2 flex justify-between items-center">
            <div><span class="px-2 py-0.5 rounded font-bold ${i.report_type==='Found'?'bg-emerald-50 text-emerald-800':'bg-rose-50 text-rose-800'}">${escapeHtml(i.report_type)}</span> <strong>${escapeHtml(i.item_name)}</strong> @ ${escapeHtml(i.location)} (${escapeHtml(i.status)})</div>
            <select onchange="updateLfStatus(${Number(i.id)}, this.value)" class="px-2 py-1 border rounded bg-white">
                ${['New','Under Review','Claimed','Returned','Closed'].map(st=>`<option ${i.status===st?'selected':''}>${st}</option>`).join('')}
            </select>
        </div>
    `).join('');
}

async function verifyClaim(claimId, decision) {
    const res = await fetch(appPath('/api/community_lost_found.php'), {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'admin_verify_claim', claim_id:claimId, decision, csrf_token:csrf})
    });
    showToast((await res.json()).message||'Done', res.ok?'success':'error');
    loadLostFound();
}
async function updateLfStatus(itemId, status) {
    await fetch(appPath('/api/community_lost_found.php'), {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'admin_update_item', item_id:itemId, status, csrf_token:csrf})
    });
    showToast('Status updated', 'success');
}
async function loadReports() {
    const res = await fetch(appPath('/api/community_discussions.php'));
    const j = await res.json();
    const reps = j.reports || [];
    document.getElementById('admin-reports-list').innerHTML = reps.length ? reps.map(r => `
        <div class="py-2 flex justify-between items-center">
            <div>Reported ${escapeHtml(r.target_type)} #${Number(r.target_id)} by ${escapeHtml(r.reporter_name)}: <em>${escapeHtml(r.reason)}</em> (${escapeHtml(r.status)})</div>
            ${r.status==='Pending' ? `<div class="flex gap-2">
                <button onclick="modForum('remove_post', ${Number(r.target_id)})" class="px-2.5 py-1 bg-rose-700 text-white rounded font-bold cursor-pointer">Remove Post</button>
                <button onclick="modForum('dismiss_report', ${Number(r.id)})" class="px-2.5 py-1 bg-slate-200 rounded font-bold cursor-pointer">Dismiss</button>
            </div>` : ''}
        </div>
    `).join('') : '<div class="text-slate-400">No discussion reports pending.</div>';
}
async function modForum(modAction, targetId) {
    await fetch(appPath('/api/community_discussions.php'), {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'moderate', mod_action:modAction, target_id:targetId, csrf_token:csrf})
    });
    showToast('Moderation action logged', 'success');
    loadReports();
}
</script>
</body></html>
