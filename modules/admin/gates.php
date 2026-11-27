<?php
// modules/admin/gates.php
if (!isset($_SESSION['logged_in'])) {
    header('Location: ' . appBasePath() . '/login');
    exit();
}
requirePermission('gates.view');
$csrfToken = generateCsrfToken();
$base = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="app-base" content="<?php echo $base; ?>">
    <title>Multi-Gate & Handover Console - SecureGate</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700&display=swap" rel="stylesheet">
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script>
    <link rel="stylesheet" href="<?php echo $base; ?>/assets/css/style.css">
    <script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans flex flex-col min-h-screen">
<?php $activeTab = 'gates'; require_once __DIR__ . '/navbar.php'; ?>

<main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 font-serif">Multi-Gate Security & Shift Handovers</h1>
            <p class="text-sm text-slate-500 mt-1">Manage multiple society entrances, guard shift rosters, handover compliance, and gate incidents.</p>
        </div>
        <div class="flex flex-wrap gap-2 text-xs">
            <a href="<?php echo $base; ?>/api/admin_manage_gates.php?export=handovers_csv" class="px-3.5 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-800 font-bold rounded-lg shadow-2xs">⬇ Handover CSV</a>
            <a href="<?php echo $base; ?>/api/admin_manage_gates.php?export=gates_csv" class="px-3.5 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-800 font-bold rounded-lg shadow-2xs">⬇ Gate Traffic CSV</a>
            <button type="button" onclick="openGateModal()" class="px-4 py-2 bg-brand-900 hover:bg-brand-800 text-white font-bold rounded-lg shadow cursor-pointer">+ Add Gate</button>
        </div>
    </div>

    <!-- Missing Handover Alert Banner -->
    <div id="missing-handovers-alert" class="hidden bg-amber-50 border border-amber-300 rounded-xl p-4 text-xs text-amber-950 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <div class="font-bold text-amber-900">⚠️ Missing Shift Handovers Detected (Last 24h)</div>
            <div id="missing-handovers-text" class="mt-0.5 text-amber-800"></div>
        </div>
    </div>

    <!-- Gates Grid -->
    <div id="gates-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4"></div>

    <!-- Guard Shift Assignments + Emergency Handover -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-xs space-y-4">
            <h2 class="font-bold text-slate-900 font-serif text-base">Assign Guard to Gate</h2>
            <form onsubmit="event.preventDefault(); assignGuard();" class="space-y-3 text-xs">
                <select id="assign_gate_id" required class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white"></select>
                <select id="assign_guard_id" required class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white"></select>
                <select id="assign_shift" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
                    <option value="Morning">Morning Shift (06:00 - 14:00)</option>
                    <option value="Afternoon">Afternoon Shift (14:00 - 22:00)</option>
                    <option value="Night">Night Shift (22:00 - 06:00)</option>
                </select>
                <button type="submit" class="w-full py-2 bg-emerald-700 hover:bg-emerald-800 text-white font-bold rounded-lg cursor-pointer">Save Assignment</button>
            </form>

            <hr class="border-slate-100">
            <h3 class="font-bold text-slate-900 text-xs uppercase tracking-wider">Emergency Admin Handover</h3>
            <p class="text-[11px] text-slate-500">Use if outgoing guard is unavailable to initiate shift transfer.</p>
            <form onsubmit="event.preventDefault(); createEmergencyHandover();" class="space-y-2 text-xs">
                <select id="eh_gate_id" required class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white"></select>
                <select id="eh_shift" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
                    <option>Morning</option><option>Afternoon</option><option>Night</option>
                </select>
                <input type="text" id="eh_notes" required placeholder="Emergency handover reason / instructions" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                <button type="submit" class="w-full py-2 bg-rose-700 hover:bg-rose-800 text-white font-bold rounded-lg cursor-pointer">Trigger Emergency Handover</button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="p-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="font-bold text-slate-900 font-serif text-base">Active Guard Assignments & Attendance</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600">
                        <tr><th class="px-4 py-3">Guard</th><th class="px-4 py-3">Assigned Gate</th><th class="px-4 py-3">Shift</th><th class="px-4 py-3">Entries Today</th><th class="px-4 py-3 text-right">Action</th></tr>
                    </thead>
                    <tbody id="assignments-tbody" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Handover Audit Log -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <h2 class="font-bold text-slate-900 font-serif text-base">Shift Handover History</h2>
            <div class="flex flex-wrap gap-2 text-xs">
                <input type="search" id="ho_guard_search" oninput="loadGatesData()" placeholder="Search guard name..." class="px-3 py-1.5 border border-slate-300 rounded-lg">
                <input type="date" id="ho_date_filter" onchange="loadGatesData()" class="px-3 py-1.5 border border-slate-300 rounded-lg">
                <select id="ho_shift_filter" onchange="loadGatesData()" class="px-3 py-1.5 border border-slate-300 rounded-lg bg-white">
                    <option value="">All Shifts</option><option>Morning</option><option>Afternoon</option><option>Night</option>
                </select>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-600">
                    <tr><th class="px-4 py-3">Gate & Shift</th><th class="px-4 py-3">Outgoing → Incoming</th><th class="px-4 py-3">Snapshot</th><th class="px-4 py-3">Notes / Issues</th><th class="px-4 py-3">Status</th></tr>
                </thead>
                <tbody id="handovers-tbody" class="divide-y divide-slate-100"></tbody>
            </table>
        </div>
    </div>

    <!-- Security Incidents -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="p-4 border-b border-slate-100">
            <h2 class="font-bold text-slate-900 font-serif text-base">Gate Security Incidents</h2>
        </div>
        <div id="incidents-list" class="divide-y divide-slate-100 p-4 text-xs">Loading incidents...</div>
    </div>
</main>

<!-- Gate Create/Edit Modal -->
<div id="gate-modal" role="dialog" aria-modal="true" aria-labelledby="gate-modal-title" onclick="if (event.target === this) this.classList.add('hidden')" class="hidden fixed inset-0 z-[130] bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl overflow-hidden text-xs sg-modal-panel my-auto">
        <div class="p-4 bg-slate-900 text-white flex justify-between items-center">
            <h3 class="font-bold font-serif text-sm" id="gate-modal-title">Add Gate</h3>
            <button type="button" onclick="document.getElementById('gate-modal').classList.add('hidden')" aria-label="Close modal" class="text-slate-300 hover:text-white p-1.5 rounded-lg hover:bg-white/10 cursor-pointer">✕</button>
        </div>
        <form onsubmit="event.preventDefault(); saveGate();" class="p-5 space-y-3">
            <input type="hidden" id="edit_gate_id" value="0">
            <div>
                <label class="block font-bold text-slate-700 mb-1">Gate Name *</label>
                <input type="text" id="g_name" required placeholder="e.g., Pedestrian Back Gate" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Gate Type</label>
                    <select id="g_type" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
                        <option>Main</option><option>Back</option><option>Tower</option><option>Service</option><option>Pedestrian</option><option>Parking</option><option>Delivery</option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Building (Optional)</label>
                    <select id="g_building" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white"><option value="">All / Complex</option></select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block font-bold text-slate-700 mb-1">Open Time</label><input type="time" id="g_open" value="00:00" class="w-full px-3 py-2 border border-slate-300 rounded-lg"></div>
                <div><label class="block font-bold text-slate-700 mb-1">Close Time</label><input type="time" id="g_close" value="23:59" class="w-full px-3 py-2 border border-slate-300 rounded-lg"></div>
            </div>
            <label class="flex items-center gap-2 font-bold text-slate-700"><input type="checkbox" id="g_active" checked> Gate is Active</label>
            <button type="submit" class="w-full py-2.5 bg-brand-900 hover:bg-brand-800 text-white font-bold rounded-lg cursor-pointer">Save Gate</button>
        </form>
    </div>
</div>

<script>
const csrf = <?php echo json_encode($csrfToken); ?>;
let gatesCache = [];

document.addEventListener('DOMContentLoaded', loadGatesData);

async function loadGatesData() {
    const qs = new URLSearchParams({
        guard_search: document.getElementById('ho_guard_search').value || '',
        date: document.getElementById('ho_date_filter').value || '',
        shift: document.getElementById('ho_shift_filter').value || ''
    });
    const res = await fetch(appPath('/api/admin_manage_gates.php?' + qs.toString()));
    const j = await res.json();
    if (!res.ok || j.status !== 'success') return showToast(j.message || 'Load failed', 'error');

    gatesCache = j.gates || [];
    const missing = j.missing_handovers || [];
    const alertBox = document.getElementById('missing-handovers-alert');
    if (missing.length > 0) {
        alertBox.classList.remove('hidden');
        document.getElementById('missing-handovers-text').textContent = missing.map(m => m.gate_name).join(', ') + ' had no handover logged in the past 24 hours.';
    } else {
        alertBox.classList.add('hidden');
    }

    document.getElementById('g_building').innerHTML = '<option value="">All / Complex</option>' + (j.buildings || []).map(b => `<option value="${Number(b.id)}">${escapeHtml(b.building_name)}</option>`).join('');
    const gateOpts = gatesCache.filter(g => Number(g.is_active) === 1).map(g => `<option value="${Number(g.id)}">${escapeHtml(g.gate_name)} (${escapeHtml(g.gate_type)})</option>`).join('');
    document.getElementById('assign_gate_id').innerHTML = gateOpts;
    document.getElementById('eh_gate_id').innerHTML = gateOpts;
    document.getElementById('assign_guard_id').innerHTML = (j.guards || []).map(g => `<option value="${Number(g.id)}">${escapeHtml(g.first_name + ' ' + g.last_name)}</option>`).join('');

    document.getElementById('gates-grid').innerHTML = gatesCache.map(g => `
        <div class="bg-white p-4 rounded-xl border ${Number(g.is_active) ? 'border-slate-200' : 'border-rose-200 opacity-75'} shadow-2xs flex flex-col justify-between">
            <div>
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h3 class="font-bold text-slate-900">${escapeHtml(g.gate_name)}</h3>
                        <span class="text-[10px] font-bold px-2 py-0.5 bg-blue-50 text-blue-800 rounded">${escapeHtml(g.gate_type)}</span>
                    </div>
                    <button type="button" onclick="openGateModal(${Number(g.id)})" class="text-xs font-bold text-brand-900 hover:underline cursor-pointer">Edit</button>
                </div>
                <div class="text-[11px] text-slate-500 mt-2">Hours: ${escapeHtml(g.open_time.slice(0,5))} – ${escapeHtml(g.close_time.slice(0,5))}</div>
            </div>
            <div class="grid grid-cols-3 gap-2 pt-3 mt-3 border-t border-slate-100 text-center text-[11px]">
                <div><div class="font-black text-slate-900">${Number(g.visitors_today)}</div><div class="text-slate-400">Visitors</div></div>
                <div><div class="font-black text-amber-700">${Number(g.deliveries_today)}</div><div class="text-slate-400">Deliveries</div></div>
                <div><div class="font-black ${Number(g.open_incidents) > 0 ? 'text-rose-600' : 'text-emerald-700'}">${Number(g.open_incidents)}</div><div class="text-slate-400">Incidents</div></div>
            </div>
        </div>
    `).join('');

    document.getElementById('assignments-tbody').innerHTML = (j.assignments || []).map(a => `
        <tr>
            <td class="px-4 py-2.5 font-bold text-slate-900">${escapeHtml(a.first_name + ' ' + a.last_name)}</td>
            <td class="px-4 py-2.5">${escapeHtml(a.gate_name)}</td>
            <td class="px-4 py-2.5"><span class="px-2 py-0.5 bg-slate-100 rounded font-semibold">${escapeHtml(a.shift_name)}</span></td>
            <td class="px-4 py-2.5 font-bold text-emerald-700">${Number(a.entries_today)} logged today</td>
            <td class="px-4 py-2.5 text-right"><button type="button" onclick="removeAssignment(${Number(a.id)})" class="text-rose-600 hover:underline font-bold cursor-pointer">Remove</button></td>
        </tr>
    `).join('') || '<tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">No guard assignments configured.</td></tr>';

    document.getElementById('handovers-tbody').innerHTML = (j.handovers || []).map(h => `
        <tr>
            <td class="px-4 py-3"><div class="font-bold text-slate-900">${escapeHtml(h.gate_name)}</div><div class="text-slate-500">${escapeHtml(h.shift_name)} • ${escapeHtml(formatDisplayDateTime(h.started_at))}</div></td>
            <td class="px-4 py-3">${escapeHtml(h.outgoing_guard_name)} → <strong>${escapeHtml(h.incoming_guard_name)}</strong></td>
            <td class="px-4 py-3">Visitors: ${Number(h.pending_visitors_count)} | Parcels: ${Number(h.pending_parcels_count)} | Incidents: ${Number(h.unresolved_incidents_count)}</td>
            <td class="px-4 py-3 max-w-xs truncate">${escapeHtml(h.shift_notes || h.next_guard_instructions || h.discrepancy_notes || '—')}</td>
            <td class="px-4 py-3"><span class="px-2 py-0.5 rounded font-bold ${h.status === 'Acknowledged' ? 'bg-emerald-50 text-emerald-800' : (h.status === 'Discrepancy_Reported' ? 'bg-rose-50 text-rose-800' : 'bg-amber-50 text-amber-800')}">${escapeHtml(h.status)}</span></td>
        </tr>
    `).join('') || '<tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">No handovers logged yet.</td></tr>';

    document.getElementById('incidents-list').innerHTML = (j.incidents || []).map(i => `
        <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 rounded font-bold ${i.severity === 'Critical' || i.severity === 'High' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800'}">${escapeHtml(i.severity)}</span>
                    <span class="font-bold text-slate-900">${escapeHtml(i.title)}</span>
                    <span class="text-slate-400">@ ${escapeHtml(i.gate_name)}</span>
                </div>
                <p class="text-slate-600 mt-1">${escapeHtml(i.description || '')}</p>
            </div>
            <div>
                ${i.status === 'Resolved' ? '<span class="px-2.5 py-1 bg-emerald-50 text-emerald-800 rounded-full font-bold">Resolved</span>' : `<button type="button" onclick="resolveIncident(${Number(i.id)})" class="px-3 py-1 bg-brand-900 text-white font-bold rounded-lg cursor-pointer">Mark Resolved</button>`}
            </div>
        </div>
    `).join('') || '<div class="text-center text-slate-400 py-4">No gate security incidents logged.</div>';
}

function openGateModal(id = 0) {
    const g = gatesCache.find(x => Number(x.id) === Number(id));
    document.getElementById('edit_gate_id').value = g ? g.id : 0;
    document.getElementById('gate-modal-title').textContent = g ? 'Edit Gate: ' + g.gate_name : 'Add New Gate';
    document.getElementById('g_name').value = g ? g.gate_name : '';
    document.getElementById('g_type').value = g ? g.gate_type : 'Main';
    document.getElementById('g_building').value = g && g.building_id ? g.building_id : '';
    document.getElementById('g_open').value = g ? g.open_time.slice(0,5) : '00:00';
    document.getElementById('g_close').value = g ? g.close_time.slice(0,5) : '23:59';
    document.getElementById('g_active').checked = g ? Number(g.is_active) === 1 : true;
    document.getElementById('gate-modal').classList.remove('hidden');
}

async function saveGate() {
    const res = await fetch(appPath('/api/admin_manage_gates.php'), {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'save_gate', id: document.getElementById('edit_gate_id').value,
            gate_name: document.getElementById('g_name').value, gate_type: document.getElementById('g_type').value,
            building_id: document.getElementById('g_building').value || null,
            open_time: document.getElementById('g_open').value + ':00', close_time: document.getElementById('g_close').value + ':59',
            is_active: document.getElementById('g_active').checked, csrf_token: csrf
        })
    });
    const j = await res.json();
    showToast(j.message || 'Saved', res.ok ? 'success' : 'error');
    if (res.ok) { document.getElementById('gate-modal').classList.add('hidden'); loadGatesData(); }
}

async function assignGuard() {
    const res = await fetch(appPath('/api/admin_manage_gates.php'), {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'assign_guard', gate_id: document.getElementById('assign_gate_id').value, guard_id: document.getElementById('assign_guard_id').value, shift_name: document.getElementById('assign_shift').value, csrf_token: csrf })
    });
    const j = await res.json();
    showToast(j.message || 'Done', res.ok ? 'success' : 'error');
    if (res.ok) loadGatesData();
}

async function removeAssignment(id) {
    const res = await fetch(appPath('/api/admin_manage_gates.php'), {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'remove_assignment', assignment_id: id, csrf_token: csrf })
    });
    if (res.ok) loadGatesData();
}

async function resolveIncident(id) {
    const notes = await showPromptDialog({
        title: 'Resolve Security Incident',
        message: 'Enter a brief summary of the resolution or corrective action taken:',
        placeholder: 'e.g., Boom barrier repaired / False alarm verified...',
        confirmText: 'Mark Resolved'
    });
    if (notes === null) return;
    const res = await fetch(appPath('/api/admin_manage_gates.php'), {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'resolve_incident', incident_id: id, resolution_notes: notes, csrf_token: csrf })
    });
    const j = await res.json();
    showToast(j.message || 'Resolved', res.ok ? 'success' : 'error');
    if (res.ok) loadGatesData();
}

async function createEmergencyHandover() {
    const res = await fetch(appPath('/api/admin_manage_gates.php'), {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'create_emergency_handover', gate_id: document.getElementById('eh_gate_id').value, shift_name: document.getElementById('eh_shift').value, instructions: document.getElementById('eh_notes').value, csrf_token: csrf })
    });
    const j = await res.json();
    showToast(j.message || 'Created', res.ok ? 'success' : 'error');
    if (res.ok) { document.getElementById('eh_notes').value = ''; loadGatesData(); }
}
</script>
</body>
</html>
