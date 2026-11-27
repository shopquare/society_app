<?php
// modules/admin/events.php
if (!isset($_SESSION['logged_in'])) { header('Location: ' . appBasePath() . '/login'); exit(); }
requirePermission('events.manage');
$csrfToken = generateCsrfToken();
$base = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"><meta name="app-base" content="<?php echo $base; ?>">
    <title>Events & Celebrations - Admin Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700&display=swap" rel="stylesheet">
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script><link rel="stylesheet" href="<?php echo $base; ?>/assets/css/style.css"><script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans flex flex-col min-h-screen">
<?php $activeTab = 'events'; require_once __DIR__ . '/navbar.php'; ?>
<main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 grid grid-cols-1 lg:grid-cols-3 gap-6">
    <form onsubmit="event.preventDefault(); saveEvent();" class="bg-white rounded-xl border border-slate-200 p-5 shadow-xs space-y-3 text-xs h-fit">
        <h2 class="text-lg font-bold font-serif text-slate-900">Create Society Event</h2>
        <input type="text" id="ev_title" required placeholder="Event Title *" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
        <select id="ev_cat" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
            <option>Festivals</option><option>Cultural Programs</option><option>Society Meetings</option><option>Birthday Celebrations</option>
            <option>Sports Events</option><option>Children's Activities</option><option>Community Gatherings</option><option>Annual Functions</option><option>Workshops</option>
        </select>
        <input type="text" id="ev_loc" required placeholder="Venue / Location (e.g., Central Lawn)" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
        <div class="grid grid-cols-2 gap-2">
            <div><label class="font-bold text-slate-600 block mb-1">Starts</label><input type="datetime-local" id="ev_start" required class="w-full px-2 py-1.5 border border-slate-300 rounded-lg"></div>
            <div><label class="font-bold text-slate-600 block mb-1">Ends</label><input type="datetime-local" id="ev_end" required class="w-full px-2 py-1.5 border border-slate-300 rounded-lg"></div>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div><label class="font-bold text-slate-600 block mb-1">Capacity</label><input type="number" id="ev_cap" value="100" min="1" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg"></div>
            <div><label class="font-bold text-slate-600 block mb-1">Paid Fee (₹)</label><input type="number" id="ev_fee" value="0" min="0" step="1" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg"></div>
        </div>
        <textarea id="ev_desc" rows="3" placeholder="Event schedule & details..." class="w-full px-3 py-2 border border-slate-300 rounded-lg"></textarea>
        <div><label class="font-bold text-slate-600 block mb-1">Poster Image (Optional)</label><input type="file" id="ev_poster" accept="image/*" class="w-full text-xs"></div>
        <button type="submit" class="w-full py-2.5 bg-brand-900 hover:bg-brand-800 text-white font-bold rounded-lg cursor-pointer">Publish Event</button>
    </form>

    <div class="lg:col-span-2 space-y-4">
        <h1 class="text-2xl font-bold font-serif text-slate-900">Society Events & Registrations</h1>
        <div id="events-list" class="space-y-4 text-xs">Loading events...</div>
    </div>
</main>
<script>
const csrf = <?php echo json_encode($csrfToken); ?>;
document.addEventListener('DOMContentLoaded', () => {
    const s = new Date(Date.now() + 86400000); const e = new Date(s.getTime() + 10800000);
    document.getElementById('ev_start').value = s.toISOString().slice(0,16);
    document.getElementById('ev_end').value = e.toISOString().slice(0,16);
    loadEvents();
});
function readFileBase64(file) {
    return new Promise((resolve) => {
        if (!file) return resolve('');
        const r = new FileReader(); r.onload = () => resolve(r.result); r.readAsDataURL(file);
    });
}
async function loadEvents() {
    const res = await fetch(appPath('/api/community_events.php'));
    const j = await res.json();
    if (!res.ok) return;
    document.getElementById('events-list').innerHTML = (j.events||[]).map(ev => `
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-2xs space-y-2">
            <div class="flex justify-between items-start gap-2">
                <div>
                    <span class="px-2 py-0.5 rounded font-bold bg-indigo-50 text-indigo-800">${escapeHtml(ev.category)}</span>
                    <span class="px-2 py-0.5 rounded font-bold ${ev.status==='Upcoming'?'bg-emerald-50 text-emerald-800':'bg-rose-50 text-rose-800'}">${escapeHtml(ev.status)}</span>
                    <h3 class="text-base font-bold text-slate-900 mt-1">${escapeHtml(ev.title)}</h3>
                </div>
                <div class="text-right font-bold text-brand-900">${Number(ev.registered_count)} / ${Number(ev.max_participants)} RSVP</div>
            </div>
            <p class="text-slate-600">${escapeHtml(ev.description||'')}</p>
            <div class="text-slate-500">📍 ${escapeHtml(ev.event_location)} • 🗓️ ${escapeHtml(formatDisplayDateTime(ev.start_datetime))} ${Number(ev.is_paid) ? '• Entry ₹'+Number(ev.registration_fee) : '• Free Entry'}</div>
            ${ev.poster_path ? `<img src="${appPath('/uploads/' + escapeHtml(ev.poster_path))}" class="max-h-40 rounded-lg border border-slate-200 mt-2">` : ''}
        </div>
    `).join('') || '<div class="bg-white p-8 rounded-xl border text-center text-slate-400">No events scheduled yet.</div>';
}
async function saveEvent() {
    const fee = Number(document.getElementById('ev_fee').value || 0);
    const posterBase64 = await readFileBase64(document.getElementById('ev_poster').files[0]);
    const res = await fetch(appPath('/api/community_events.php'), {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({
            action:'save_event', title:document.getElementById('ev_title').value, category:document.getElementById('ev_cat').value,
            event_location:document.getElementById('ev_loc').value, start_datetime:document.getElementById('ev_start').value,
            end_datetime:document.getElementById('ev_end').value, max_participants:document.getElementById('ev_cap').value,
            is_paid: fee > 0 ? 1 : 0, registration_fee: fee, description:document.getElementById('ev_desc').value,
            poster_base64: posterBase64, csrf_token:csrf
        })
    });
    const j = await res.json();
    showToast(j.message || 'Done', res.ok ? 'success' : 'error');
    if (res.ok) loadEvents();
}
</script>
</body></html>
