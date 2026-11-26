<?php
// modules/resident/directory.php
// Privacy-First Searchable Society Resident Directory
if (!isset($_SESSION['logged_in']) || !hasRole([1, 2, 3])) {
    header('Location: ' . appBasePath() . '/login');
    exit();
}
$csrfToken = generateCsrfToken();
$base = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"><meta name="app-base" content="<?php echo $base; ?>">
    <title>Resident Directory - SecureGate</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700&display=swap" rel="stylesheet">
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script><script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans flex flex-col min-h-screen">
<nav class="bg-brand-900 shadow-md border-b border-brand-800 sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex justify-between items-center">
        <div class="flex items-center gap-3">
            <a href="<?php echo $base; ?>/resident/dashboard" class="text-white/80 hover:text-white font-bold text-sm">← Dashboard</a>
            <span class="font-serif text-lg sm:text-xl font-bold text-white">Resident Directory</span>
        </div>
        <?php require_once __DIR__ . '/../../includes/notification_widget.php'; ?>
    </div>
</nav>

<main class="flex-grow max-w-6xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-6 grid grid-cols-1 lg:grid-cols-4 gap-6 text-xs">
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-xs space-y-3 h-fit">
        <h2 class="text-base font-bold font-serif text-slate-900">My Directory Privacy</h2>
        <p class="text-slate-500">Phone numbers and emails are hidden by default to protect your privacy.</p>
        <label class="flex items-center gap-2 font-semibold"><input type="checkbox" id="priv_dir" checked> List me in society directory</label>
        <label class="flex items-center gap-2 font-semibold"><input type="checkbox" id="priv_phone"> Share phone number with neighbors</label>
        <label class="flex items-center gap-2 font-semibold"><input type="checkbox" id="priv_email"> Share email address</label>
        <label class="flex items-center gap-2 font-semibold"><input type="checkbox" id="priv_family"> Share family member names</label>
        <button type="button" onclick="savePrivacy()" class="w-full py-2 bg-brand-900 text-white font-bold rounded-lg cursor-pointer">Save Privacy Settings</button>
    </div>

    <div class="lg:col-span-3 space-y-4">
        <div class="flex flex-col sm:flex-row gap-2">
            <input type="search" id="dir_q" oninput="loadDirectory(1)" placeholder="Search resident name or flat number..." class="flex-1 px-3 py-2 border border-slate-300 rounded-lg bg-white">
            <select id="dir_bldg" onchange="loadDirectory(1)" class="px-3 py-2 border border-slate-300 rounded-lg bg-white"><option value="all">All Towers</option></select>
        </div>
        <div id="dir-grid" class="grid grid-cols-1 sm:grid-cols-2 gap-3">Loading directory...</div>
    </div>
</main>

<script>
const csrf = <?php echo json_encode($csrfToken); ?>;
document.addEventListener('DOMContentLoaded', () => { loadPrivacy(); loadDirectory(1); });
async function loadPrivacy() {
    const j = await (await fetch(appPath('/api/resident_directory.php?action=my_privacy'))).json();
    if (j.privacy) {
        document.getElementById('priv_dir').checked = Boolean(j.privacy.show_in_directory);
        document.getElementById('priv_phone').checked = Boolean(j.privacy.show_phone);
        document.getElementById('priv_email').checked = Boolean(j.privacy.show_email);
        document.getElementById('priv_family').checked = Boolean(j.privacy.show_family);
    }
}
async function savePrivacy() {
    const res = await fetch(appPath('/api/resident_directory.php'), {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({
            show_in_directory: document.getElementById('priv_dir').checked,
            show_phone: document.getElementById('priv_phone').checked,
            show_email: document.getElementById('priv_email').checked,
            show_family: document.getElementById('priv_family').checked,
            csrf_token: csrf
        })
    });
    showToast((await res.json()).message||'Saved', res.ok?'success':'error');
    loadDirectory(1);
}
async function loadDirectory(page) {
    const qs = new URLSearchParams({ q: document.getElementById('dir_q').value, building: document.getElementById('dir_bldg').value, page });
    const j = await (await fetch(appPath('/api/resident_directory.php?' + qs.toString()))).json();
    const sel = document.getElementById('dir_bldg');
    if (sel.options.length <= 1 && j.buildings) {
        j.buildings.forEach(b => { const o = document.createElement('option'); o.value = b.building_code; o.textContent = b.building_name; sel.appendChild(o); });
    }
    const list = j.residents || [];
    document.getElementById('dir-grid').innerHTML = list.length ? list.map(r => `
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs space-y-1.5">
            <div class="flex justify-between items-start">
                <div class="font-bold text-sm text-slate-900">${escapeHtml(r.first_name + ' ' + r.last_name)}</div>
                <span class="px-2 py-0.5 bg-brand-50 text-brand-900 font-bold rounded">Unit ${escapeHtml(r.unit_number||'N/A')}</span>
            </div>
            <div class="text-slate-500">${escapeHtml(r.building_name||'Tower')} • ${escapeHtml(r.flat_type||'Residence')} (${escapeHtml(r.occupancy_type||'Resident')})</div>
            ${r.phone_number ? `<div class="text-emerald-700 font-semibold">📞 ${escapeHtml(r.phone_number)}</div>` : '<div class="text-slate-400">🔒 Phone private</div>'}
            ${r.email ? `<div class="text-slate-600">✉️ ${escapeHtml(r.email)}</div>` : ''}
            ${(r.family_members&&r.family_members.length) ? `<div class="pt-1 text-[11px] text-slate-500">Family: ${r.family_members.map(f=>escapeHtml(f.full_name)+' ('+escapeHtml(f.relation)+')').join(', ')}</div>` : ''}
        </div>
    `).join('') : '<div class="col-span-2 bg-white p-8 rounded-xl border text-center text-slate-400">No visible residents found.</div>';
}
</script>
</body></html>
