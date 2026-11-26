<?php
// modules/resident/facilities.php
// Interactive Resident Amenity Discovery, Real-Time Slot Grid, Booking & Cancellation Portal
if (!isset($_SESSION['logged_in']) || !hasRole([1, 2, 3])) { header('Location: ' . appBasePath() . '/login'); exit(); }
$csrfToken = generateCsrfToken();
$base = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"><meta name="app-base" content="<?php echo $base; ?>">
    <title>Amenities & Facility Booking - SecureGate</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700&display=swap" rel="stylesheet">
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script><script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans flex flex-col min-h-screen">
<nav class="bg-brand-900 shadow-md border-b border-brand-800 sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex justify-between items-center">
        <div class="flex items-center gap-3"><a href="<?php echo $base; ?>/resident/dashboard" class="text-white/80 hover:text-white font-bold text-sm">← Dashboard</a><span class="font-serif text-lg sm:text-xl font-bold text-white">Facilities & Booking</span></div>
        <?php require_once __DIR__ . '/../../includes/notification_widget.php'; ?>
    </div>
</nav>

<main class="flex-grow max-w-6xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-6 grid grid-cols-1 lg:grid-cols-3 gap-6 text-xs">
    <div class="lg:col-span-2 space-y-5">
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-xs space-y-4">
            <h2 class="text-base font-bold font-serif text-slate-900">1. Select Facility & Check Live Slot Availability</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <select id="res_fac_select" onchange="loadSlots()" class="px-3 py-2 border border-slate-300 rounded-lg bg-white font-semibold"></select>
                <input type="date" id="res_date_select" onchange="loadSlots()" min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>" class="px-3 py-2 border border-slate-300 rounded-lg">
            </div>
            <div id="fac-info-banner" class="p-3 bg-slate-50 border border-slate-200 rounded-lg text-slate-600"></div>
            <div id="slots-grid" class="grid grid-cols-2 sm:grid-cols-3 gap-2"></div>
            <div id="booking-confirm-box" class="hidden p-4 bg-brand-50 border border-brand-200 rounded-xl space-y-2.5">
                <div class="font-bold text-brand-900" id="selected-slot-label"></div>
                <input type="text" id="res_notes" placeholder="Purpose / Notes (e.g., Family Evening Session)" class="w-full px-3 py-2 bg-white border rounded-lg">
                <input type="text" id="res_pay_ref" placeholder="UPI Ref (if paid facility)" class="hidden w-full px-3 py-2 bg-white border rounded-lg">
                <button type="button" onclick="confirmBooking()" class="px-5 py-2 bg-brand-900 text-white font-bold rounded-lg cursor-pointer">Confirm Reservation</button>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-xs space-y-3 h-fit">
        <h2 class="text-base font-bold font-serif text-slate-900">My Booking History</h2>
        <div id="my-bookings-list" class="divide-y divide-slate-100">Loading...</div>
    </div>
</main>

<script>
const csrf = <?php echo json_encode($csrfToken); ?>;
let facs = []; let chosenSlot = null;
document.addEventListener('DOMContentLoaded', loadInitial);
async function loadInitial() {
    const j = await (await fetch(appPath('/api/resident_facility_booking.php'))).json();
    facs = j.facilities || [];
    document.getElementById('res_fac_select').innerHTML = facs.map(f=>`<option value="${Number(f.id)}">${escapeHtml(f.facility_name)} (${Number(f.booking_cost_per_slot)>0 ? '₹'+Number(f.booking_cost_per_slot) : 'Free'})</option>`).join('');
    renderBookings(j.my_bookings || []);
    if (facs.length) loadSlots();
}
async function loadSlots() {
    const fid = document.getElementById('res_fac_select').value;
    const dt = document.getElementById('res_date_select').value;
    chosenSlot = null; document.getElementById('booking-confirm-box').classList.add('hidden');
    const j = await (await fetch(appPath(`/api/resident_facility_booking.php?action=availability&facility_id=${fid}&date=${dt}`))).json();
    if (!j.facility) return;
    const f = j.facility;
    document.getElementById('fac-info-banner').innerHTML = `<strong>${escapeHtml(f.facility_name)}</strong> (${escapeHtml(f.location||'Campus')}) • Cap: ${Number(f.capacity)} • Fee: ${Number(f.booking_cost_per_slot)>0?'₹'+Number(f.booking_cost_per_slot):'Free'} • ${Number(f.requires_approval)?'Admin Approval Required':'Instant Confirmation'}`;
    document.getElementById('res_pay_ref').classList.toggle('hidden', Number(f.booking_cost_per_slot) <= 0);
    document.getElementById('slots-grid').innerHTML = (j.slots||[]).map(s => {
        const isOpen = s.status === 'available';
        return `<button type="button" ${!isOpen?'disabled':''} onclick="pickSlot('${s.start_time}','${s.end_time}',${Number(f.booking_cost_per_slot)})" class="p-2.5 rounded-lg border text-left transition ${isOpen?'bg-emerald-50 border-emerald-300 hover:bg-emerald-100 text-emerald-900 cursor-pointer font-bold':'bg-slate-100 border-slate-200 text-slate-400 cursor-not-allowed'}">
            <div>${s.start_time} – ${s.end_time}</div>
            <div class="text-[10px]">${isOpen ? 'Available' : escapeHtml(s.reason || s.status)}</div>
        </button>`;
    }).join('');
}
function pickSlot(st, et, fee) {
    chosenSlot = { start_time: st, end_time: et };
    document.getElementById('selected-slot-label').textContent = `Selected: ${st} – ${et}` + (fee>0 ? ` (Fee: ₹${fee})` : ' (Free)');
    document.getElementById('booking-confirm-box').classList.remove('hidden');
}
async function confirmBooking() {
    if (!chosenSlot) return;
    const res = await fetch(appPath('/api/resident_facility_booking.php'), {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action:'book', facility_id:document.getElementById('res_fac_select').value, booking_date:document.getElementById('res_date_select').value, start_time:chosenSlot.start_time, end_time:chosenSlot.end_time, notes:document.getElementById('res_notes').value, payment_ref:document.getElementById('res_pay_ref').value, csrf_token:csrf })
    });
    const j = await res.json();
    showToast(j.message||'Booked', res.ok?'success':'error');
    if (res.ok) loadInitial();
}
function renderBookings(list) {
    document.getElementById('my-bookings-list').innerHTML = list.length ? list.map(b => `
        <div class="py-3 flex justify-between items-start gap-2">
            <div><div class="font-bold text-slate-900">${escapeHtml(b.facility_name)}</div><div class="text-slate-500">${escapeHtml(b.booking_date)} • ${escapeHtml(b.start_time.slice(0,5))}-${escapeHtml(b.end_time.slice(0,5))}</div><span class="inline-block mt-1 px-2 py-0.5 rounded font-bold bg-slate-100">${escapeHtml(b.status)}</span></div>
            ${(b.status==='Confirmed'||b.status==='Pending_Approval') ? `<button onclick="cancelBooking(${Number(b.id)})" class="text-rose-600 font-bold hover:underline cursor-pointer">Cancel</button>` : ''}
        </div>
    `).join('') : '<div class="py-6 text-center text-slate-400">No bookings yet.</div>';
}
async function cancelBooking(id) {
    if (!confirm('Cancel this booking?')) return;
    const res = await fetch(appPath('/api/resident_facility_booking.php'), { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'cancel', booking_id:id, csrf_token:csrf}) });
    showToast((await res.json()).message||'Cancelled', res.ok?'success':'error');
    loadInitial();
}
</script>
</body></html>
