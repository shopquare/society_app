<?php
// modules/admin/polls.php
if (!isset($_SESSION['logged_in'])) { header('Location: ' . appBasePath() . '/login'); exit(); }
requirePermission('polls.manage');
$csrfToken = generateCsrfToken();
$base = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="app-base" content="<?php echo $base; ?>">
    <title>Polls & Elections - Admin Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700&display=swap" rel="stylesheet">
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script><script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans flex flex-col min-h-screen">
<?php $activeTab = 'polls'; require_once __DIR__ . '/navbar.php'; ?>
<main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 grid grid-cols-1 lg:grid-cols-3 gap-6">
    <form onsubmit="event.preventDefault(); createPoll();" class="bg-white rounded-xl border border-slate-200 p-5 shadow-xs space-y-3 text-xs h-fit">
        <h2 class="text-lg font-bold font-serif text-slate-900">Create Poll / Society Election</h2>
        <input type="text" id="p_title" required placeholder="Poll Question / Election Title *" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
        <textarea id="p_desc" rows="2" placeholder="Description & governance details..." class="w-full px-3 py-2 border border-slate-300 rounded-lg"></textarea>
        <div class="grid grid-cols-2 gap-2">
            <select id="p_type" onchange="toggleElectionCheck()" class="px-3 py-2 border border-slate-300 rounded-lg bg-white">
                <option>General Survey</option><option>Society Improvement</option><option>Facility Preference</option><option>Festival Planning</option><option>Society Election</option><option>Committee Decision</option>
            </select>
            <select id="p_elig" class="px-3 py-2 border border-slate-300 rounded-lg bg-white">
                <option value="all">All Residents</option><option value="owners">Owners Only</option><option value="tenants">Tenants Only</option>
            </select>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div><label class="font-bold text-slate-600 block mb-1">Start</label><input type="datetime-local" id="p_start" required class="w-full px-2 py-1.5 border border-slate-300 rounded-lg"></div>
            <div><label class="font-bold text-slate-600 block mb-1">End</label><input type="datetime-local" id="p_end" required class="w-full px-2 py-1.5 border border-slate-300 rounded-lg"></div>
        </div>
        <div>
            <label class="font-bold text-slate-600 block mb-1">Options (one per line, minimum 2)</label>
            <textarea id="p_options" rows="4" required placeholder="Option A&#10;Option B&#10;Option C" class="w-full px-3 py-2 border border-slate-300 rounded-lg"></textarea>
        </div>
        <select id="p_vis" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
            <option value="Immediate">Results Visible Immediately</option>
            <option value="After_Close">Results Hidden Until Poll Closes</option>
            <option value="Admin_Only">Admin Only Until Official Publication</option>
        </select>
        <label class="flex items-center gap-2 font-semibold"><input type="checkbox" id="p_anon" checked> Enable Confidential / Anonymous Ballot</label>
        <div id="election-cert-box" class="hidden p-3 bg-amber-50 border border-amber-300 rounded-lg text-amber-950">
            <label class="flex items-start gap-2 font-bold cursor-pointer"><input type="checkbox" id="p_cert"> <span>I confirm this Society Election follows society bylaws and voter verification rules.</span></label>
        </div>
        <button type="submit" class="w-full py-2.5 bg-brand-900 hover:bg-brand-800 text-white font-bold rounded-lg cursor-pointer">Launch Poll</button>
    </form>

    <div class="lg:col-span-2 space-y-4">
        <h1 class="text-2xl font-bold font-serif text-slate-900">Active & Past Polls</h1>
        <div id="polls-list" class="space-y-4 text-xs">Loading polls...</div>
    </div>
</main>
<script>
const csrf = <?php echo json_encode($csrfToken); ?>;
document.addEventListener('DOMContentLoaded', () => {
    const now = new Date(); const week = new Date(now.getTime() + 7*86400000);
    document.getElementById('p_start').value = now.toISOString().slice(0,16);
    document.getElementById('p_end').value = week.toISOString().slice(0,16);
    loadPolls();
});
function toggleElectionCheck() {
    document.getElementById('election-cert-box').classList.toggle('hidden', document.getElementById('p_type').value !== 'Society Election');
}
async function loadPolls() {
    const res = await fetch(appPath('/api/community_polls.php'));
    const j = await res.json();
    if (!res.ok) return;
    const list = j.polls || [];
    document.getElementById('polls-list').innerHTML = list.length ? list.map(p => {
        const total = Math.max(1, Number(p.total_votes || 0));
        return `<div class="bg-white rounded-xl border border-slate-200 p-5 shadow-2xs space-y-3">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <span class="px-2 py-0.5 rounded font-bold bg-blue-50 text-blue-800">${escapeHtml(p.poll_type)}</span>
                    <span class="px-2 py-0.5 rounded font-bold ${p.status==='Active'?'bg-emerald-50 text-emerald-800':'bg-slate-100 text-slate-600'}">${escapeHtml(p.status)}</span>
                    ${Number(p.is_anonymous) ? '<span class="px-2 py-0.5 rounded font-bold bg-purple-50 text-purple-800">🔒 Anonymous</span>' : ''}
                    <h3 class="text-base font-bold text-slate-900 mt-1">${escapeHtml(p.title)}</h3>
                </div>
                <div class="flex gap-1.5">
                    <a href="${appPath('/api/community_polls.php?export=csv&poll_id=' + Number(p.id))}" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 rounded font-bold">CSV</a>
                    ${p.status === 'Active' ? `<button type="button" onclick="pollAction('send_reminder', ${Number(p.id)})" class="px-2.5 py-1 bg-amber-50 text-amber-800 rounded font-bold cursor-pointer">Remind</button>
                    <button type="button" onclick="pollAction('close_poll', ${Number(p.id)})" class="px-2.5 py-1 bg-rose-50 text-rose-800 rounded font-bold cursor-pointer">Close</button>` : ''}
                </div>
            </div>
            <p class="text-slate-600">${escapeHtml(p.description || '')}</p>
            <div class="space-y-2">${(p.options||[]).map(o => {
                const vc = Number(o.vote_count || 0); const pct = Math.round((vc / total)*100);
                return `<div><div class="flex justify-between font-semibold"><span>${escapeHtml(o.option_text)}</span><span>${vc} (${pct}%)</span></div><div class="h-2 bg-slate-100 rounded-full overflow-hidden"><div class="h-full bg-brand-900" style="width:${pct}%"></div></div></div>`;
            }).join('')}</div>
            <div class="text-slate-400">Total Ballots: ${Number(p.total_votes)} • Closes: ${escapeHtml(formatDisplayDateTime(p.end_at))}</div>
        </div>`;
    }).join('') : '<div class="bg-white p-8 rounded-xl border text-center text-slate-400">No polls created yet.</div>';
}
async function createPoll() {
    const opts = document.getElementById('p_options').value.split('\n').map(s=>s.trim()).filter(Boolean);
    const res = await fetch(appPath('/api/community_polls.php'), {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
            action:'create_poll', title:document.getElementById('p_title').value, description:document.getElementById('p_desc').value,
            poll_type:document.getElementById('p_type').value, eligible_voters:document.getElementById('p_elig').value,
            start_at:document.getElementById('p_start').value, end_at:document.getElementById('p_end').value,
            results_visibility:document.getElementById('p_vis').value, is_anonymous:document.getElementById('p_anon').checked,
            is_governance_certified:document.getElementById('p_cert').checked, options:opts, csrf_token:csrf
        })
    });
    const j = await res.json();
    showToast(j.message || 'Done', res.ok ? 'success' : 'error');
    if (res.ok) loadPolls();
}
async function pollAction(action, pollId) {
    const res = await fetch(appPath('/api/community_polls.php'), {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action, poll_id:pollId, csrf_token:csrf})
    });
    const j = await res.json();
    showToast(j.message || 'Done', res.ok ? 'success' : 'error');
    if (res.ok) loadPolls();
}
</script>
</body></html>
