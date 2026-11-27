<?php
// modules/admin/facilities.php
if (!isset($_SESSION['logged_in'])) { header('Location: ' . appBasePath() . '/login'); exit(); }
requirePermission('facilities.manage');
$csrfToken = generateCsrfToken();
$base = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"><meta name="app-base" content="<?php echo $base; ?>">
    <title>Facility Management - Admin Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700&display=swap" rel="stylesheet">
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script><link rel="stylesheet" href="<?php echo $base; ?>/assets/css/style.css"><script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans flex flex-col min-h-screen">
<?php $activeTab = 'facilities'; require_once __DIR__ . '/navbar.php'; ?>
<main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 space-y-6 text-xs">
    <div class="flex flex-wrap justify-between items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold font-serif text-slate-900">Society Facilities & Slot Approvals</h1>
            <p class="text-sm text-slate-500">Configure operating hours, booking fees, limits, maintenance blocks, and approve requests.</p>
        </div>
        <button type="button" onclick="openFacModal()" class="px-4 py-2 bg-brand-900 text-white font-bold rounded-lg shadow cursor-pointer">+ Add Facility</button>
    </div>

    <div id="admin-facilities-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4"></div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <form onsubmit="event.preventDefault(); blockMaintenanceDate();" class="bg-white rounded-xl border border-slate-200 p-5 shadow-xs space-y-3 h-fit">
            <h2 class="font-bold text-base font-serif text-slate-900">Block Date / Maintenance Closure</h2>
            <select id="blk_fac" required class="w-full px-3 py-2 border rounded-lg bg-white"></select>
            <input type="date" id="blk_date" required value="<?php echo date('Y-m-d'); ?>" class="w-full px-3 py-2 border rounded-lg">
            <input type="text" id="blk_reason" required placeholder="Reason (e.g., Pool Chlorination)" class="w-full px-3 py-2 border rounded-lg">
            <button type="submit" class="w-full py-2 bg-rose-700 text-white font-bold rounded-lg cursor-pointer">Block Selected Date</button>
            <div id="blocked-dates-list" class="pt-2 space-y-1.5 border-t border-slate-100"></div>
        </form>

        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="p-4 border-b border-slate-100 font-bold text-base font-serif text-slate-900">Reservations & Approvals Queue</div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-slate-50 border-b text-slate-600">
                        <tr><th class="px-4 py-3">Facility</th><th class="px-4 py-3">Resident</th><th class="px-4 py-3">Slot</th><th class="px-4 py-3">Fee / Status</th><th class="px-4 py-3 text-right">Action</th></tr>
                    </thead>
                    <tbody id="admin-bookings-tbody" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Facility Modal -->
<div id="fac-modal" role="dialog" aria-modal="true" aria-labelledby="fac-modal-title" onclick="if (event.target === this) this.classList.add('hidden')" class="hidden fixed inset-0 z-[130] bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl max-w-lg w-full max-h-full flex flex-col shadow-2xl overflow-hidden sg-modal-panel my-auto">
        <div class="p-4 bg-slate-900 text-white flex justify-between items-center"><h3 class="font-bold font-serif text-sm" id="fac-modal-title">Facility Settings</h3><button type="button" onclick="document.getElementById('fac-modal').classList.add('hidden')" aria-label="Close facility modal" class="p-1.5 text-slate-300 hover:text-white rounded-lg hover:bg-white/10 cursor-pointer">✕</button></div>
        <form onsubmit="event.preventDefault(); saveFacility();" class="p-5 overflow-y-auto space-y-3">
            <input type="hidden" id="f_id" value="0">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <input type="text" id="f_name" aria-label="Facility Name" required placeholder="Facility Name *" class="px-3 py-2 border rounded-lg">
                <select id="f_type" aria-label="Facility Type" class="px-3 py-2 border rounded-lg bg-white"><option>Clubhouse</option><option>Gym</option><option>Swimming Pool</option><option>Banquet Hall</option><option>Sports Court</option><option>Guest Room</option><option>Community Hall</option><option>Parking</option></select>
            </div>
            <input type="text" id="f_loc" aria-label="Location" placeholder="Location" class="w-full px-3 py-2 border rounded-lg">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                <div><label for="f_open" class="font-bold text-slate-500">Open</label><input type="time" id="f_open" value="06:00" class="w-full px-2 py-1.5 border rounded-lg"></div>
                <div><label for="f_close" class="font-bold text-slate-500">Close</label><input type="time" id="f_close" value="22:00" class="w-full px-2 py-1.5 border rounded-lg"></div>
                <div><label for="f_dur" class="font-bold text-slate-500">Slot Mins</label><input type="number" id="f_dur" value="60" min="30" class="w-full px-2 py-1.5 border rounded-lg"></div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                <div><label for="f_cap" class="font-bold text-slate-500">Capacity</label><input type="number" id="f_cap" value="20" class="w-full px-2 py-1.5 border rounded-lg"></div>
                <div><label for="f_fee" class="font-bold text-slate-500">Fee (₹)</label><input type="number" id="f_fee" value="0" min="0" class="w-full px-2 py-1.5 border rounded-lg"></div>
                <div><label for="f_lim" class="font-bold text-slate-500">Max/Month</label><input type="number" id="f_lim" value="6" min="1" class="w-full px-2 py-1.5 border rounded-lg"></div>
            </div>
            <textarea id="f_desc" rows="2" placeholder="Description & rules..." class="w-full px-3 py-2 border rounded-lg"></textarea>
            <div class="flex gap-4"><label class="flex items-center gap-1.5 font-bold"><input type="checkbox" id="f_appr"> Requires Approval</label><label class="flex items-center gap-1.5 font-bold"><input type="checkbox" id="f_active" checked> Active</label></div>
            <button type="submit" class="w-full py-2.5 bg-brand-900 text-white font-bold rounded-lg cursor-pointer">Save Facility</button>
        </form>
    </div>
</div>
<script>
const csrf = <?php echo json_encode($csrfToken); ?>;
let facList = [];
document.addEventListener('DOMContentLoaded', loadFacData);
async function loadFacData() {
    const j = await (await fetch(appPath('/api/admin_manage_facilities.php'))).json();
    facList = j.facilities || [];
    document.getElementById('blk_fac').innerHTML = facList.map(f=>`<option value="${Number(f.id)}">${escapeHtml(f.facility_name)}</option>`).join('');
    document.getElementById('admin-facilities-grid').innerHTML = facList.map(f => `
        <div class="bg-white rounded-xl border ${Number(f.is_active)?'border-slate-200':'border-rose-200 opacity-75'} p-4 shadow-2xs flex flex-col justify-between">
            <div>
                <div class="flex justify-between items-start"><span class="px-2 py-0.5 bg-blue-50 text-blue-800 rounded font-bold">${escapeHtml(f.facility_type||'Amenity')}</span><button onclick="openFacModal(${Number(f.id)})" class="font-bold text-brand-900 hover:underline cursor-pointer">Edit</button></div>
                <h3 class="font-bold text-sm text-slate-900 mt-1">${escapeHtml(f.facility_name)}</h3>
                <div class="text-slate-500 mt-1">${escapeHtml(f.open_time.slice(0,5))} – ${escapeHtml(f.close_time.slice(0,5))} • ${Number(f.slot_duration_mins)}m slots</div>
            </div>
            <div class="pt-2 mt-2 border-t border-slate-100 flex justify-between font-bold">
                <span>${Number(f.booking_cost_per_slot)>0 ? '₹'+Number(f.booking_cost_per_slot) : 'Free'}</span>
                <span class="text-slate-500">Cap: ${Number(f.capacity)}</span>
            </div>
        </div>
    `).join('');
    document.getElementById('blocked-dates-list').innerHTML = (j.blocked_dates||[]).map(b => `
        <div class="flex justify-between items-center bg-rose-50 px-2.5 py-1.5 rounded text-rose-900"><span><strong>${escapeHtml(b.facility_name)}</strong>: ${escapeHtml(b.blocked_date)} (${escapeHtml(b.reason)})</span><button onclick="unblockDate(${Number(b.id)})" class="font-bold underline cursor-pointer">Unblock</button></div>
    `).join('');
    document.getElementById('admin-bookings-tbody').innerHTML = (j.bookings||[]).map(b => `
        <tr>
            <td class="px-4 py-2.5 font-bold">${escapeHtml(b.facility_name)}</td>
            <td class="px-4 py-2.5">${escapeHtml(b.resident_name)} (Unit ${escapeHtml(b.unit_number||'N/A')})</td>
            <td class="px-4 py-2.5">${escapeHtml(b.booking_date)} • ${escapeHtml(b.start_time.slice(0,5))}-${escapeHtml(b.end_time.slice(0,5))}</td>
            <td class="px-4 py-2.5"><span class="px-2 py-0.5 rounded font-bold ${b.status==='Confirmed'?'bg-emerald-50 text-emerald-800':(b.status==='Pending_Approval'?'bg-amber-50 text-amber-800':'bg-slate-100 text-slate-600')}">${escapeHtml(b.status)}</span></td>
            <td class="px-4 py-2.5 text-right">${b.status==='Pending_Approval' ? `<button onclick="decideBooking(${Number(b.id)},'Confirmed')" class="px-2 py-1 bg-emerald-700 text-white rounded font-bold mr-1 cursor-pointer">Approve</button><button onclick="decideBooking(${Number(b.id)},'Rejected')" class="px-2 py-1 bg-rose-700 text-white rounded font-bold cursor-pointer">Reject</button>` : ''}</td>
        </tr>
    `).join('') || '<tr><td colspan="5" class="p-6 text-center text-slate-400">No bookings yet.</td></tr>';
}
function openFacModal(id = 0) {
    const f = facList.find(x => Number(x.id) === Number(id));
    document.getElementById('f_id').value = f ? f.id : 0;
    document.getElementById('f_name').value = f ? f.facility_name : '';
    document.getElementById('f_type').value = f ? (f.facility_type||'Clubhouse') : 'Clubhouse';
    document.getElementById('f_loc').value = f ? (f.location||'') : '';
    document.getElementById('f_open').value = f ? f.open_time.slice(0,5) : '06:00';
    document.getElementById('f_close').value = f ? f.close_time.slice(0,5) : '22:00';
    document.getElementById('f_dur').value = f ? f.slot_duration_mins : 60;
    document.getElementById('f_cap').value = f ? f.capacity : 20;
    document.getElementById('f_fee').value = f ? f.booking_cost_per_slot : 0;
    document.getElementById('f_lim').value = f ? (f.max_bookings_per_unit_month||6) : 6;
    document.getElementById('f_desc').value = f ? (f.description||'') : '';
    document.getElementById('f_appr').checked = f ? Boolean(Number(f.requires_approval)) : false;
    document.getElementById('f_active').checked = f ? Boolean(Number(f.is_active)) : true;
    document.getElementById('fac-modal').classList.remove('hidden');
}
async function saveFacility() {
    const res = await fetch(appPath('/api/admin_manage_facilities.php'), {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({
            action:'save_facility', id:document.getElementById('f_id').value, facility_name:document.getElementById('f_name').value,
            facility_type:document.getElementById('f_type').value, location:document.getElementById('f_loc').value,
            open_time:document.getElementById('f_open').value+':00', close_time:document.getElementById('f_close').value+':00',
            slot_duration_mins:document.getElementById('f_dur').value, capacity:document.getElementById('f_cap').value,
            booking_cost_per_slot:document.getElementById('f_fee').value, max_bookings_per_unit_month:document.getElementById('f_lim').value,
            description:document.getElementById('f_desc').value, requires_approval:document.getElementById('f_appr').checked,
            is_active:document.getElementById('f_active').checked, csrf_token:csrf
        })
    });
    showToast((await res.json()).message||'Saved', res.ok?'success':'error');
    if (res.ok) { document.getElementById('fac-modal').classList.add('hidden'); loadFacData(); }
}
async function blockMaintenanceDate() {
    const res = await fetch(appPath('/api/admin_manage_facilities.php'), { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'block_date', facility_id:document.getElementById('blk_fac').value, blocked_date:document.getElementById('blk_date').value, reason:document.getElementById('blk_reason').value, csrf_token:csrf}) });
    showToast((await res.json()).message||'Blocked', res.ok?'success':'error'); loadFacData();
}
async function unblockDate(id) { await fetch(appPath('/api/admin_manage_facilities.php'), { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'unblock_date', id, csrf_token:csrf}) }); loadFacData(); }
async function decideBooking(bookingId, decision) {
    await fetch(appPath('/api/admin_manage_facilities.php'), { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'booking_decision', booking_id:bookingId, decision, csrf_token:csrf}) });
    showToast('Updated booking', 'success'); loadFacData();
}
</script>
</body></html>
