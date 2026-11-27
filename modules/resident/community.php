<?php
// modules/resident/community.php
// Interactive Resident Community Hub: Polls, Events Calendar, Lost & Found, Suggestions, Discussions, Notices
if (!isset($_SESSION['logged_in']) || !hasRole([1, 2, 3])) {
    header('Location: ' . appBasePath() . '/login');
    exit();
}
$csrfToken = generateCsrfToken();
$base = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');
$initTab = htmlspecialchars((string)($_GET['tab'] ?? 'polls'), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"><meta name="app-base" content="<?php echo $base; ?>">
    <title>Community Hub - SecureGate</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700&display=swap" rel="stylesheet">
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script><link rel="stylesheet" href="<?php echo $base; ?>/assets/css/style.css"><script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans flex flex-col min-h-screen">
<?php $resActivePage = 'community'; $resPageTitle = 'Community Hub'; require_once __DIR__ . '/navbar.php'; ?>

<main id="main-content" class="flex-grow max-w-6xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 font-serif">Society Community Hub</h1>
        <p class="text-sm text-slate-500 mt-1">Participate in society polls, view upcoming events, browse circulars, and engage with neighbors.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <?php foreach ([
            'polls' => '🗳️ Polls & Voting',
            'events' => '🎉 Events Calendar',
            'lostfound' => '🔍 Lost & Found',
            'suggestions' => '💡 Suggestions',
            'discussions' => '💬 Discussions',
            'notices' => '📢 Circulars'
        ] as $k => $lbl): ?>
            <button type="button" id="ctab-btn-<?php echo $k; ?>" onclick="switchCommTab('<?php echo $k; ?>')" class="px-4 py-2 rounded-xl text-xs font-bold border transition cursor-pointer bg-white text-slate-700 border-slate-200"><?php echo $lbl; ?></button>
        <?php endforeach; ?>
    </div>

    <!-- TAB 1: POLLS -->
    <section id="ctab-polls" class="hidden space-y-4 text-xs">
        <div id="res-polls-list" class="grid grid-cols-1 md:grid-cols-2 gap-4">Loading polls...</div>
    </section>

    <!-- TAB 2: EVENTS & MONTHLY CALENDAR -->
    <section id="ctab-events" class="hidden space-y-5 text-xs">
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-2xs">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-bold text-base font-serif text-slate-900" id="cal-month-title">Event Calendar</h2>
                <div class="flex gap-1">
                    <button type="button" onclick="shiftCalendarMonth(-1)" class="px-2.5 py-1 bg-slate-100 rounded font-bold cursor-pointer">‹</button>
                    <button type="button" onclick="shiftCalendarMonth(1)" class="px-2.5 py-1 bg-slate-100 rounded font-bold cursor-pointer">›</button>
                </div>
            </div>
            <div class="grid grid-cols-7 gap-1 text-center text-[10px] font-bold text-slate-400 mb-1">
                <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>
            </div>
            <div id="cal-days-grid" class="grid grid-cols-7 gap-1 text-[11px]"></div>
        </div>
        <div id="res-events-list" class="grid grid-cols-1 md:grid-cols-2 gap-4">Loading events...</div>
    </section>

    <!-- TAB 3: LOST & FOUND -->
    <section id="ctab-lostfound" class="hidden grid grid-cols-1 lg:grid-cols-3 gap-6 text-xs">
        <form onsubmit="event.preventDefault(); submitLostFound();" class="bg-white rounded-xl border border-slate-200 p-5 shadow-xs space-y-3 h-fit">
            <h2 class="text-base font-bold font-serif text-slate-900">Report Lost or Found Item</h2>
            <div class="grid grid-cols-2 gap-2">
                <select id="lf_type" class="px-3 py-2 border border-slate-300 rounded-lg bg-white"><option value="Lost">Lost Item</option><option value="Found">Found Item</option></select>
                <select id="lf_cat" class="px-3 py-2 border border-slate-300 rounded-lg bg-white"><option>Keys / Wallet</option><option>Electronics</option><option>Documents</option><option>Pet</option><option>Jewelry / Watch</option><option>Personal Item</option></select>
            </div>
            <input type="text" id="lf_name" required placeholder="Item Name *" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
            <input type="text" id="lf_loc" required placeholder="Last Seen / Found Location *" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
            <input type="date" id="lf_date" required value="<?php echo date('Y-m-d'); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
            <textarea id="lf_desc" required rows="3" placeholder="Identifying details (your personal phone/email remains hidden)..." class="w-full px-3 py-2 border border-slate-300 rounded-lg"></textarea>
            <input type="file" id="lf_photo" accept="image/*" class="w-full">
            <button type="submit" class="w-full py-2.5 bg-brand-900 text-white font-bold rounded-lg cursor-pointer">Submit Report</button>
        </form>
        <div id="res-lf-list" class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4 h-fit">Loading items...</div>
    </section>

    <!-- TAB 4: SUGGESTIONS BOX -->
    <section id="ctab-suggestions" class="hidden grid grid-cols-1 lg:grid-cols-3 gap-6 text-xs">
        <form onsubmit="event.preventDefault(); submitSuggestion();" class="bg-white rounded-xl border border-slate-200 p-5 shadow-xs space-y-3 h-fit">
            <h2 class="text-base font-bold font-serif text-slate-900">Society Suggestion Box</h2>
            <select id="sg_cat" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
                <option>Society Improvement</option><option>Security</option><option>Cleanliness & Maintenance</option><option>Amenities</option><option>Green Initiatives</option>
            </select>
            <input type="text" id="sg_title" required placeholder="Suggestion Title *" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
            <textarea id="sg_desc" required rows="4" placeholder="Describe your idea for the managing committee..." class="w-full px-3 py-2 border border-slate-300 rounded-lg"></textarea>
            <label class="flex items-center gap-2 font-semibold"><input type="checkbox" id="sg_anon"> Submit Anonymously</label>
            <button type="submit" class="w-full py-2.5 bg-brand-900 text-white font-bold rounded-lg cursor-pointer">Submit Suggestion</button>
        </form>
        <div id="res-sugg-list" class="lg:col-span-2 space-y-3">Loading your suggestions...</div>
    </section>

    <!-- TAB 5: DISCUSSIONS -->
    <section id="ctab-discussions" class="hidden grid grid-cols-1 lg:grid-cols-3 gap-6 text-xs">
        <form onsubmit="event.preventDefault(); createDiscussion();" class="bg-white rounded-xl border border-slate-200 p-5 shadow-xs space-y-3 h-fit">
            <h2 class="text-base font-bold font-serif text-slate-900">Start Society Discussion</h2>
            <select id="dc_cat" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
                <option>General Discussion</option><option>Society Improvements</option><option>Events</option><option>Recommendations</option><option>Local Services</option><option>Community Help</option>
            </select>
            <input type="text" id="dc_title" required placeholder="Topic Headline *" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
            <textarea id="dc_body" required rows="4" placeholder="Share respectfully with neighbors..." class="w-full px-3 py-2 border border-slate-300 rounded-lg"></textarea>
            <button type="submit" class="w-full py-2.5 bg-brand-900 text-white font-bold rounded-lg cursor-pointer">Post Topic</button>
        </form>
        <div class="lg:col-span-2 space-y-4">
            <div class="flex flex-col sm:flex-row gap-2">
                <input type="search" id="dc_search" aria-label="Search discussions" oninput="loadDiscussions()" placeholder="Search topics..." class="flex-1 px-3 py-2 border border-slate-300 rounded-lg bg-white">
                <select id="dc_filter" aria-label="Filter discussions by category" onchange="loadDiscussions()" class="px-3 py-2 border border-slate-300 rounded-lg bg-white">
                    <option value="all">All Categories</option><option>General Discussion</option><option>Society Improvements</option><option>Events</option><option>Recommendations</option><option>Local Services</option><option>Community Help</option>
                </select>
            </div>
            <div id="res-disc-list" class="space-y-3">Loading discussions...</div>
        </div>
    </section>

    <!-- TAB 6: NOTICES ARCHIVE -->
    <section id="ctab-notices" class="hidden space-y-4 text-xs">
        <div class="flex flex-col sm:flex-row gap-2">
            <input type="search" id="nt_q" aria-label="Search circulars" oninput="loadNotices()" placeholder="Search circulars..." class="flex-1 px-3 py-2 border border-slate-300 rounded-lg bg-white">
            <select id="nt_cat" aria-label="Filter circulars by category" onchange="loadNotices()" class="px-3 py-2 border border-slate-300 rounded-lg bg-white">
                <option value="all">All Categories</option><option>General</option><option>Maintenance</option><option>Security</option><option>Financial</option><option>Events</option><option>Emergency</option><option>Important Updates</option>
            </select>
        </div>
        <div id="res-notices-list" class="space-y-3">Loading notices...</div>
    </section>
</main>

<script>
const csrf = <?php echo json_encode($csrfToken); ?>;
let calDate = new Date();
let eventsCache = [];

document.addEventListener('DOMContentLoaded', () => switchCommTab(<?php echo json_encode($initTab); ?>));

function switchCommTab(tab) {
    const valid = ['polls','events','lostfound','suggestions','discussions','notices'];
    if (!valid.includes(tab)) tab = 'polls';
    valid.forEach(t => {
        document.getElementById('ctab-' + t).classList.toggle('hidden', t !== tab);
        document.getElementById('ctab-btn-' + t).className = t === tab
            ? 'px-4 py-2 rounded-xl text-xs font-bold border transition cursor-pointer bg-slate-900 text-white border-slate-900 shadow-xs'
            : 'px-4 py-2 rounded-xl text-xs font-bold border transition cursor-pointer bg-white text-slate-700 border-slate-200 hover:bg-slate-100';
    });
    if (tab === 'polls') loadPolls();
    if (tab === 'events') loadEvents();
    if (tab === 'lostfound') loadLostFound();
    if (tab === 'suggestions') loadSuggestions();
    if (tab === 'discussions') loadDiscussions();
    if (tab === 'notices') loadNotices();
}

function toBase64(file) {
    return new Promise(r => { if (!file) return r(''); const fr = new FileReader(); fr.onload = () => r(fr.result); fr.readAsDataURL(file); });
}

async function loadPolls() {
    const j = await (await fetch(appPath('/api/community_polls.php'))).json();
    const list = j.polls || [];
    document.getElementById('res-polls-list').innerHTML = list.length ? list.map(p => {
        const total = Math.max(1, Number(p.total_votes || 0));
        return `<div class="bg-white rounded-xl border border-slate-200 p-5 shadow-2xs space-y-3">
            <div class="flex justify-between items-start gap-2">
                <div><span class="px-2 py-0.5 bg-blue-50 text-blue-800 rounded font-bold">${escapeHtml(p.poll_type)}</span> <span class="px-2 py-0.5 rounded font-bold ${p.status==='Active'?'bg-emerald-50 text-emerald-800':'bg-slate-100 text-slate-500'}">${escapeHtml(p.status)}</span></div>
                <span class="text-slate-400">Ends ${escapeHtml(formatDisplayDate(p.end_at))}</span>
            </div>
            <h3 class="text-base font-bold text-slate-900">${escapeHtml(p.title)}</h3>
            <p class="text-slate-600">${escapeHtml(p.description||'')}</p>
            <div class="space-y-2">${(p.options||[]).map(o => {
                if (Number(p.has_voted) || p.status !== 'Active') {
                    const cnt = o.vote_count === null ? 'Hidden' : `${Number(o.vote_count)} (${Math.round((Number(o.vote_count)/total)*100)}%)`;
                    return `<div class="p-2.5 bg-slate-50 rounded-lg border border-slate-200 flex justify-between font-semibold"><span>${escapeHtml(o.option_text)}</span><span class="text-brand-900">${cnt}</span></div>`;
                }
                return `<button type="button" ${!p.is_eligible?'disabled':''} onclick="castVote(${Number(p.id)}, ${Number(o.id)})" class="w-full text-left p-2.5 rounded-lg border border-slate-300 hover:border-brand-900 hover:bg-brand-50 font-semibold transition cursor-pointer">${escapeHtml(o.option_text)}</button>`;
            }).join('')}</div>
            ${!p.is_eligible ? '<div class="text-amber-700 font-semibold">Restricted to: '+escapeHtml(p.eligible_voters)+'</div>' : ''}
            ${Number(p.has_voted) ? '<div class="text-emerald-700 font-bold">✓ Your ballot is recorded</div>' : ''}
        </div>`;
    }).join('') : '<div class="col-span-2 bg-white p-8 rounded-xl border text-center text-slate-400">No active polls at the moment.</div>';
}

async function castVote(pollId, optionId) {
    const res = await fetch(appPath('/api/community_polls.php'), { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'cast_vote', poll_id:pollId, option_id:optionId, csrf_token:csrf}) });
    const j = await res.json();
    showToast(j.message||'Vote cast', res.ok?'success':'error');
    if (res.ok) loadPolls();
}

async function loadEvents() {
    const j = await (await fetch(appPath('/api/community_events.php'))).json();
    eventsCache = j.events || [];
    renderCalendar();
    document.getElementById('res-events-list').innerHTML = eventsCache.length ? eventsCache.map(ev => `
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-2xs space-y-3">
            ${ev.poster_path ? `<img src="${appPath('/uploads/' + escapeHtml(ev.poster_path))}" class="w-full h-36 object-cover rounded-lg border">` : ''}
            <div class="flex justify-between items-start">
                <div><span class="px-2 py-0.5 bg-indigo-50 text-indigo-800 rounded font-bold">${escapeHtml(ev.category)}</span><h3 class="text-base font-bold text-slate-900 mt-1">${escapeHtml(ev.title)}</h3></div>
                <span class="font-bold text-brand-900">${Number(ev.is_paid)?'₹'+Number(ev.registration_fee):'Free'}</span>
            </div>
            <p class="text-slate-600">${escapeHtml(ev.description||'')}</p>
            <div class="text-slate-500">📍 ${escapeHtml(ev.event_location)} • 🗓️ ${escapeHtml(formatDisplayDateTime(ev.start_datetime))} • 👥 ${Number(ev.registered_count)}/${Number(ev.max_participants)}</div>
            <div class="pt-2 border-t border-slate-100 flex justify-between items-center">
                ${ev.my_rsvp_status === 'Registered'
                    ? `<button onclick="eventRsvp('cancel_registration', ${Number(ev.id)})" class="px-3 py-1.5 bg-rose-50 text-rose-700 font-bold rounded-lg cursor-pointer">Cancel RSVP</button>`
                    : `<button onclick="eventRsvp('register', ${Number(ev.id)})" class="px-4 py-1.5 bg-brand-900 text-white font-bold rounded-lg cursor-pointer">Register Now</button>`}
            </div>
        </div>
    `).join('') : '<div class="col-span-2 bg-white p-8 rounded-xl border text-center text-slate-400">No upcoming events.</div>';
}

function shiftCalendarMonth(delta) { calDate = new Date(calDate.getFullYear(), calDate.getMonth() + delta, 1); renderCalendar(); }
function renderCalendar() {
    const y = calDate.getFullYear(); const m = calDate.getMonth();
    document.getElementById('cal-month-title').textContent = calDate.toLocaleDateString('en-IN', { month: 'long', year: 'numeric' });
    const firstDay = new Date(y, m, 1).getDay(); const daysInMonth = new Date(y, m + 1, 0).getDate();
    let html = '';
    for (let i = 0; i < firstDay; i++) html += '<div class="p-2 bg-slate-50 rounded"></div>';
    for (let d = 1; d <= daysInMonth; d++) {
        const dStr = `${y}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const dayEvs = eventsCache.filter(e => (e.start_datetime||'').startsWith(dStr));
        html += `<div class="p-1.5 min-h-[46px] rounded border ${dayEvs.length?'border-brand-300 bg-brand-50':'border-slate-100 bg-white'}">
            <div class="font-bold text-slate-700">${d}</div>
            ${dayEvs.map(e=>`<div class="truncate text-[9px] font-bold text-brand-900 bg-white px-1 rounded mt-0.5">${escapeHtml(e.title)}</div>`).join('')}
        </div>`;
    }
    document.getElementById('cal-days-grid').innerHTML = html;
}

async function eventRsvp(action, eventId) {
    const res = await fetch(appPath('/api/community_events.php'), { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action, event_id:eventId, guests_count:1, csrf_token:csrf}) });
    showToast((await res.json()).message||'Done', res.ok?'success':'error');
    if (res.ok) loadEvents();
}

async function loadLostFound() {
    const j = await (await fetch(appPath('/api/community_lost_found.php'))).json();
    document.getElementById('res-lf-list').innerHTML = (j.items||[]).map(i => `
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-2xs space-y-2">
            ${i.photo_path ? `<img src="${appPath('/uploads/'+escapeHtml(i.photo_path))}" class="w-full h-32 object-cover rounded-lg">` : ''}
            <div class="flex justify-between"><span class="px-2 py-0.5 rounded font-bold ${i.report_type==='Found'?'bg-emerald-50 text-emerald-800':'bg-rose-50 text-rose-800'}">${escapeHtml(i.report_type)}</span><span class="text-slate-500 font-semibold">${escapeHtml(i.status)}</span></div>
            <h3 class="font-bold text-slate-900">${escapeHtml(i.item_name)}</h3>
            <p class="text-slate-600">${escapeHtml(i.description)}</p>
            <div class="text-slate-400">📍 ${escapeHtml(i.location)} • ${escapeHtml(i.event_date)}</div>
            ${!Number(i.is_mine) && (i.status==='New'||i.status==='Under Review') ? `<button onclick="claimItem(${Number(i.id)})" class="w-full py-1.5 bg-slate-900 text-white font-bold rounded-lg cursor-pointer">Submit Ownership Claim</button>` : ''}
        </div>
    `).join('') || '<div class="col-span-2 bg-white p-8 rounded-xl border text-center text-slate-400">No Lost & Found reports.</div>';
}
async function submitLostFound() {
    const photo = await toBase64(document.getElementById('lf_photo').files[0]);
    const res = await fetch(appPath('/api/community_lost_found.php'), {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action:'report_item', report_type:document.getElementById('lf_type').value, category:document.getElementById('lf_cat').value, item_name:document.getElementById('lf_name').value, location:document.getElementById('lf_loc').value, event_date:document.getElementById('lf_date').value, description:document.getElementById('lf_desc').value, photo_base64:photo, csrf_token:csrf })
    });
    showToast((await res.json()).message||'Submitted', res.ok?'success':'error');
    if (res.ok) loadLostFound();
}
async function claimItem(itemId) {
    const proof = await showPromptDialog({
        title: 'Submit Ownership Claim',
        message: 'Enter identifying details or proof of ownership. Your personal contact remains private with the society committee.',
        placeholder: 'Describe unique marks, contents, or serial details...',
        confirmText: 'Submit Claim'
    });
    if (!proof) return;
    const res = await fetch(appPath('/api/community_lost_found.php'), { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'submit_claim', item_id:itemId, proof_details:proof, csrf_token:csrf}) });
    showToast((await res.json()).message||'Submitted', res.ok?'success':'error');
    loadLostFound();
}

async function loadSuggestions() {
    const j = await (await fetch(appPath('/api/community_suggestions.php'))).json();
    document.getElementById('res-sugg-list').innerHTML = (j.suggestions||[]).map(s => `
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-2xs space-y-2">
            <div class="flex justify-between"><span class="px-2 py-0.5 bg-blue-50 text-blue-800 rounded font-bold">${escapeHtml(s.category)}</span><span class="px-2 py-0.5 bg-slate-100 rounded font-bold">${escapeHtml(s.status)}</span></div>
            <h3 class="font-bold text-slate-900">${escapeHtml(s.title)}</h3>
            <p class="text-slate-600">${escapeHtml(s.description)}</p>
            ${s.admin_response ? `<div class="p-2.5 bg-emerald-50 border border-emerald-200 rounded-lg text-emerald-900"><strong>Committee Response:</strong> ${escapeHtml(s.admin_response)}</div>` : ''}
        </div>
    `).join('') || '<div class="bg-white p-8 rounded-xl border text-center text-slate-400">You have not submitted any suggestions yet.</div>';
}
async function submitSuggestion() {
    const res = await fetch(appPath('/api/community_suggestions.php'), { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'submit', category:document.getElementById('sg_cat').value, title:document.getElementById('sg_title').value, description:document.getElementById('sg_desc').value, is_anonymous:document.getElementById('sg_anon').checked, csrf_token:csrf }) });
    showToast((await res.json()).message||'Submitted', res.ok?'success':'error');
    if (res.ok) { document.getElementById('sg_title').value=''; document.getElementById('sg_desc').value=''; loadSuggestions(); }
}

async function loadDiscussions() {
    const qs = new URLSearchParams({ category: document.getElementById('dc_filter').value, q: document.getElementById('dc_search').value });
    const j = await (await fetch(appPath('/api/community_discussions.php?'+qs.toString()))).json();
    document.getElementById('res-disc-list').innerHTML = (j.posts||[]).map(p => `
        <div class="bg-white rounded-xl border ${Number(p.is_pinned)?'border-amber-400':'border-slate-200'} p-4 shadow-2xs space-y-2">
            <div class="flex justify-between items-center">
                <span class="px-2 py-0.5 bg-slate-100 rounded font-bold">${escapeHtml(p.category)}</span>
                <span class="text-slate-400">${escapeHtml(p.author_name)} (Unit ${escapeHtml(p.unit_number||'N/A')}) • ${escapeHtml(formatDisplayDate(p.created_at))}</span>
            </div>
            <h3 class="text-sm font-bold text-slate-900">${Number(p.is_pinned)?'📌 ':''}${escapeHtml(p.title)}</h3>
            <p class="text-slate-600">${escapeHtml(p.content)}</p>
            <div class="flex items-center gap-3 pt-2 border-t border-slate-100">
                <button onclick="likePost(${Number(p.id)})" class="font-bold ${Number(p.liked_by_me)?'text-rose-600':'text-slate-600'} cursor-pointer">❤️ ${Number(p.likes_count)}</button>
                <button onclick="toggleComments(${Number(p.id)})" class="font-bold text-brand-900 cursor-pointer">💬 Comments (${Number(p.comments_count)})</button>
                <button onclick="reportPost(${Number(p.id)})" class="text-slate-400 hover:text-rose-600 ml-auto cursor-pointer">⚑ Report</button>
            </div>
            <div id="comments-box-${Number(p.id)}" class="hidden pt-2 space-y-2"></div>
        </div>
    `).join('') || '<div class="bg-white p-8 rounded-xl border text-center text-slate-400">No discussions yet. Start the first topic!</div>';
}
async function createDiscussion() {
    const res = await fetch(appPath('/api/community_discussions.php'), { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'create_post', category:document.getElementById('dc_cat').value, title:document.getElementById('dc_title').value, content:document.getElementById('dc_body').value, csrf_token:csrf }) });
    showToast((await res.json()).message||'Posted', res.ok?'success':'error');
    if (res.ok) { document.getElementById('dc_title').value=''; document.getElementById('dc_body').value=''; loadDiscussions(); }
}
async function likePost(id) {
    await fetch(appPath('/api/community_discussions.php'), { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'toggle_like', discussion_id:id, csrf_token:csrf}) });
    loadDiscussions();
}
async function toggleComments(id) {
    const box = document.getElementById('comments-box-' + id);
    if (!box.classList.contains('hidden')) return box.classList.add('hidden');
    box.classList.remove('hidden');
    const j = await (await fetch(appPath('/api/community_discussions.php?discussion_id=' + id))).json();
    box.innerHTML = `<div class="space-y-1.5 pl-3 border-l-2 border-slate-200">${(j.comments||[]).map(c=>`<div><strong>${escapeHtml(c.author_name)}</strong>: ${escapeHtml(c.comment_text)}</div>`).join('')}</div>
        <div class="flex gap-2 mt-2"><input id="c_in_${id}" placeholder="Write reply..." class="flex-1 px-2.5 py-1 border rounded"><button onclick="postComment(${id})" class="px-3 py-1 bg-brand-900 text-white rounded font-bold cursor-pointer">Reply</button></div>`;
}
async function postComment(id) {
    const txt = document.getElementById('c_in_' + id).value;
    if (!txt.trim()) return;
    await fetch(appPath('/api/community_discussions.php'), { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'add_comment', discussion_id:id, comment_text:txt, csrf_token:csrf}) });
    document.getElementById('comments-box-' + id).classList.add('hidden');
    loadDiscussions();
}
async function reportPost(id) {
    const rsn = await showPromptDialog({
        title: 'Report Discussion Post',
        message: 'Let the managing committee know why this post violates community guidelines:',
        placeholder: 'e.g., Spam, harassment, misleading information...',
        confirmText: 'Submit Report'
    });
    if (!rsn) return;
    const res = await fetch(appPath('/api/community_discussions.php'), { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'report', target_type:'discussion', target_id:id, reason:rsn, csrf_token:csrf}) });
    showToast((await res.json()).message||'Reported', res.ok?'success':'error');
}

async function loadNotices() {
    const qs = new URLSearchParams({ category: document.getElementById('nt_cat').value, q: document.getElementById('nt_q').value });
    const j = await (await fetch(appPath('/api/get_notices.php?' + qs.toString()))).json();
    document.getElementById('res-notices-list').innerHTML = (j.data||[]).map(n => `
        <div class="bg-white rounded-xl border ${Number(n.is_pinned)?'border-amber-400':'border-slate-200'} p-4 shadow-2xs">
            <div class="flex justify-between items-center mb-1">
                <span class="px-2 py-0.5 bg-blue-50 text-blue-800 rounded font-bold">${escapeHtml(n.category||'General')}</span>
                <span class="text-slate-400">${escapeHtml(formatDisplayDate(n.created_at))}</span>
            </div>
            <h3 class="text-sm font-bold text-slate-900">${escapeHtml(n.title)}</h3>
            <p class="text-slate-600 mt-1 whitespace-pre-wrap">${escapeHtml(n.content)}</p>
            ${n.attachment_path ? `<a href="${appPath('/uploads/' + escapeHtml(n.attachment_path))}" target="_blank" class="inline-block mt-2 text-brand-900 font-bold underline">📎 Download Attachment</a>` : ''}
        </div>
    `).join('');
}
</script>
</body></html>
