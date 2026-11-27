<?php
// modules/admin/notices.php
// Enhanced Notice Board with Categories, Scheduling, Expiry, Pinning, Building Targets & Attachments
if (!isset($_SESSION['logged_in'])) { header('Location: ' . appBasePath() . '/login'); exit(); }
requirePermission('notices.manage');
$csrfToken = generateCsrfToken();
$base = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"><meta name="app-base" content="<?php echo $base; ?>">
    <title>Notice Board - Admin Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700&display=swap" rel="stylesheet">
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script><link rel="stylesheet" href="<?php echo $base; ?>/assets/css/style.css"><script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans flex flex-col min-h-screen">
<?php $activeTab = 'notices'; require_once __DIR__ . '/navbar.php'; ?>
<main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 font-serif">Notice Board &amp; Society Circulars</h1>
        <p class="text-sm text-slate-500 mt-1">Broadcast official announcements, target specific towers, schedule circulars, and monitor resident read views.</p>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 sticky top-24">
            <h2 class="text-xl font-bold text-slate-900 font-serif mb-4">Publish Notice</h2>
            <form id="notice-form" onsubmit="event.preventDefault(); submitNotice();" class="space-y-3 text-xs">
                <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <div>
                    <label for="title" class="block font-bold text-slate-700 mb-1">Notice Title *</label>
                    <input type="text" id="title" required class="w-full px-3 py-2 border border-slate-300 rounded-lg" placeholder="e.g., Scheduled Water Tank Cleaning">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Category</label>
                        <select id="category" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
                            <option>General</option><option>Maintenance</option><option>Security</option><option>Financial</option><option>Events</option><option>Emergency</option><option>Important Updates</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Target Tower</label>
                        <select id="target_building_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white"><option value="">All Towers</option></select>
                    </div>
                </div>
                <div>
                    <label for="content" class="block font-bold text-slate-700 mb-1">Announcement Content *</label>
                    <textarea id="content" required rows="4" class="w-full px-3 py-2 border border-slate-300 rounded-lg" placeholder="Write the full circular details..."></textarea>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="block font-bold text-slate-600 mb-1">Schedule At (Opt)</label><input type="datetime-local" id="scheduled_at" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg"></div>
                    <div><label class="block font-bold text-slate-600 mb-1">Expires At (Opt)</label><input type="datetime-local" id="expires_at" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg"></div>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Attachment (PDF or Image, max 10MB)</label>
                    <input type="file" id="attachment_file" accept="image/*,.pdf" class="w-full text-xs">
                </div>
                <div class="flex gap-4">
                    <label class="flex items-center gap-1.5 font-semibold"><input type="checkbox" id="is_important"> Mark Important</label>
                    <label class="flex items-center gap-1.5 font-semibold"><input type="checkbox" id="is_pinned"> Pin to Top</label>
                </div>
                <button type="submit" id="btn-publish" class="w-full py-2.5 font-bold text-white bg-brand-900 hover:bg-brand-800 rounded-lg shadow-sm cursor-pointer">Broadcast to Society</button>
            </form>
        </div>
    </div>

    <div class="lg:col-span-2">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
            <div><h2 class="text-xl font-bold text-slate-900 font-serif">Announcements &amp; Read Statistics</h2></div>
            <div class="flex flex-col sm:flex-row gap-2 text-xs w-full sm:w-auto">
                <input type="search" id="notice_q" oninput="fetchNotices()" aria-label="Search notices" placeholder="Search notices..." class="w-full sm:w-auto px-3 py-2 border border-slate-300 rounded-lg bg-white">
                <select id="notice_cat" aria-label="Filter notices by category" onchange="fetchNotices()" class="w-full sm:w-auto px-3 py-2 border border-slate-300 rounded-lg bg-white">
                    <option value="all">All Categories</option><option>General</option><option>Maintenance</option><option>Security</option><option>Financial</option><option>Events</option><option>Emergency</option><option>Important Updates</option>
                </select>
            </div>
        </div>
        <div id="notices-feed" class="space-y-4">Loading announcements...</div>
    </div>
    </div>
</main>
<script>
const csrfToken = document.getElementById('csrf_token').value;
document.addEventListener('DOMContentLoaded', () => { loadBuildings(); fetchNotices(); });
async function loadBuildings() {
    try {
        const res = await fetch(appPath('/api/admin_manage_buildings.php'));
        const j = await res.json();
        if (res.ok && j.buildings) {
            const sel = document.getElementById('target_building_id');
            j.buildings.forEach(b => { const o = document.createElement('option'); o.value = b.id; o.textContent = b.building_name; sel.appendChild(o); });
        }
    } catch (_) {}
}
async function fetchNotices() {
    const qs = new URLSearchParams({ category: document.getElementById('notice_cat').value, q: document.getElementById('notice_q').value });
    const response = await fetch(appPath('/api/get_notices.php?' + qs.toString()));
    const result = await response.json();
    const feed = document.getElementById('notices-feed');
    if (response.ok && result.status === 'success') {
        const notices = result.data || [];
        feed.innerHTML = notices.length ? notices.map(n => `
            <div class="bg-white rounded-xl shadow-sm border ${Number(n.is_pinned)?'border-amber-400 ring-1 ring-amber-200':'border-slate-200'} p-5 text-xs">
                <div class="flex flex-wrap justify-between items-start gap-2 mb-2">
                    <div class="flex flex-wrap items-center gap-1.5">
                        ${Number(n.is_pinned)?'<span class="px-2 py-0.5 bg-amber-100 text-amber-900 rounded font-bold">📌 Pinned</span>':''}
                        ${Number(n.is_important)?'<span class="px-2 py-0.5 bg-rose-100 text-rose-800 rounded font-bold">Important</span>':''}
                        <span class="px-2 py-0.5 bg-blue-50 text-blue-800 rounded font-bold">${escapeHtml(n.category||'General')}</span>
                        ${n.target_building_name?`<span class="px-2 py-0.5 bg-slate-100 text-slate-700 rounded font-bold">${escapeHtml(n.target_building_name)}</span>`:''}
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-slate-500">👁️ ${Number(n.views_count||0)} views</span>
                        <button type="button" onclick="togglePin(${Number(n.id)})" class="px-2 py-0.5 bg-slate-100 hover:bg-slate-200 rounded font-bold cursor-pointer">${Number(n.is_pinned)?'Unpin':'Pin'}</button>
                    </div>
                </div>
                <h3 class="text-base font-bold text-slate-900">${escapeHtml(n.title)}</h3>
                <p class="text-slate-600 whitespace-pre-wrap mt-1">${escapeHtml(n.content)}</p>
                ${n.attachment_path ? `<div class="mt-2"><a href="${appPath('/uploads/' + escapeHtml(n.attachment_path))}" target="_blank" class="text-brand-900 font-bold underline">📎 View Attachment (${escapeHtml(n.attachment_type||'file')})</a></div>` : ''}
                <div class="text-slate-400 mt-3">Posted by ${escapeHtml(n.first_name)} ${escapeHtml(n.last_name)} • ${escapeHtml(formatDisplayDateTime(n.created_at))}</div>
            </div>
        `).join('') : '<div class="bg-white rounded-xl p-8 text-center text-slate-500">No announcements matching filter.</div>';
    }
}
async function togglePin(id) {
    await fetch(appPath('/api/admin_create_notice.php'), { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'toggle_pin', id, csrf_token:csrfToken}) });
    fetchNotices();
}
function toBase64(file) {
    return new Promise(r => { if (!file) return r(''); const fr = new FileReader(); fr.onload = () => r(fr.result); fr.readAsDataURL(file); });
}
async function submitNotice() {
    const attach = await toBase64(document.getElementById('attachment_file').files[0]);
    const res = await fetch(appPath('/api/admin_create_notice.php'), {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({
            title: document.getElementById('title').value, content: document.getElementById('content').value,
            category: document.getElementById('category').value, target_building_id: document.getElementById('target_building_id').value || null,
            scheduled_at: document.getElementById('scheduled_at').value || null, expires_at: document.getElementById('expires_at').value || null,
            is_important: document.getElementById('is_important').checked, is_pinned: document.getElementById('is_pinned').checked,
            attachment_base64: attach, csrf_token: csrfToken
        })
    });
    const j = await res.json();
    showToast(j.message||'Published', res.ok?'success':'error');
    if (res.ok) { document.getElementById('notice-form').reset(); fetchNotices(); }
}
</script>
</body></html>
