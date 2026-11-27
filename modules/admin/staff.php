<?php
// modules/admin/staff.php
require_once __DIR__ . '/../../config/db.php';
requirePermission('guards.manage');
$csrfToken = generateCsrfToken();
$base = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="app-base" content="<?php echo $base; ?>">
    <title>Society Staff &amp; Shift Attendance - SecureGate</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script>
    <link rel="stylesheet" href="<?php echo $base; ?>/assets/css/style.css">
    <script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 font-sans min-h-screen flex flex-col">
    <?php $activeTab = 'staff'; require_once __DIR__ . '/navbar.php'; ?>
    <main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 font-serif">Society Staff, Daily Help &amp; Shift Attendance</h1>
            <p class="text-sm text-slate-500 mt-1">Manage housekeeping/garden/domestic staff passes and track guard shift check-ins.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-xl border border-slate-200 p-6 space-y-5 h-fit">
                <div>
                    <h2 class="font-bold text-slate-900 mb-3">Register Daily Help / Staff Pass</h2>
                    <form onsubmit="event.preventDefault(); addDailyStaff();" class="space-y-3 text-sm">
                        <input type="text" id="stf-name" required placeholder="Full Name" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                        <select id="stf-role" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
                            <option value="Housekeeping">Housekeeping</option>
                            <option value="Domestic Maid">Domestic Maid</option>
                            <option value="Gardener">Gardener</option>
                            <option value="Plumber / Electrician">In-House Technician</option>
                            <option value="Manager">Facility Manager</option>
                        </select>
                        <input type="text" id="stf-phone" required placeholder="10-Digit Phone" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                        <button type="submit" class="w-full py-2.5 bg-brand-900 text-white font-semibold rounded-lg cursor-pointer">+ Issue Staff Gate Pass</button>
                    </form>
                </div>
                <div class="border-t pt-4">
                    <h2 class="font-bold text-slate-900 mb-3">Log Guard / Staff Shift Check-In</h2>
                    <form onsubmit="event.preventDefault(); logAttendanceCheckIn();" class="space-y-3 text-sm">
                        <select id="att-type" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
                            <option value="Guard">Security Guard</option>
                            <option value="Daily_Staff">Daily Staff</option>
                            <option value="Society_Staff">Society Office Staff</option>
                        </select>
                        <input type="text" id="att-name" required placeholder="Staff Name" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                        <select id="att-shift" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
                            <option value="Morning Shift">Morning Shift</option>
                            <option value="Evening Shift">Evening Shift</option>
                            <option value="Night Shift">Night Shift</option>
                        </select>
                        <button type="submit" class="w-full py-2.5 bg-emerald-700 text-white font-semibold rounded-lg cursor-pointer">✓ Mark On Duty</button>
                    </form>
                </div>
            </div>

            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-xl border border-slate-200 p-6">
                    <h2 class="font-bold text-slate-900 mb-3">Active Daily Staff Passes</h2>
                    <div id="daily-staff-list" class="space-y-2 text-sm">Loading...</div>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-6">
                    <h2 class="font-bold text-slate-900 mb-3">Shift Attendance Log</h2>
                    <div id="attendance-list" class="space-y-2 text-sm">Loading...</div>
                </div>
            </div>
        </div>
    </main>

    <script>
        const csrfToken = document.getElementById('csrf_token').value;
        document.addEventListener('DOMContentLoaded', loadStaff);

        async function loadStaff() {
            const res = await fetch(appPath('/api/admin_manage_staff.php'));
            const r = await res.json();
            if (!res.ok || r.status !== 'success') return;
            const d = r.data || r;

            const ds = d.daily_staff || [];
            document.getElementById('daily-staff-list').innerHTML = ds.length ? ds.map(s => `
                <div class="p-3 border border-slate-100 rounded-lg flex justify-between items-center bg-slate-50">
                    <div><span class="font-bold text-slate-900">${escapeHtml(s.staff_name)}</span> • <span class="text-xs text-slate-600">${escapeHtml(s.role)} • Pass: <strong class="font-mono">${escapeHtml(s.staff_pass_code)}</strong></span></div>
                    <button onclick="toggleStaff(${s.id})" class="px-2.5 py-1 text-xs font-semibold rounded border cursor-pointer ${s.status === 'Active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-200 text-slate-600'}">${escapeHtml(s.status)}</button>
                </div>`).join('') : '<p class="text-slate-400">No daily staff passes issued yet.</p>';

            const att = d.attendance || [];
            document.getElementById('attendance-list').innerHTML = att.length ? att.map(a => `
                <div class="p-3 border border-slate-100 rounded-lg flex justify-between items-center">
                    <div><span class="font-bold">${escapeHtml(a.staff_name)}</span> <span class="text-xs px-2 py-0.5 rounded bg-slate-100">${escapeHtml(a.staff_type)}</span> • <span class="text-xs text-slate-500">${escapeHtml(a.shift_name)} (In: ${escapeHtml(a.check_in_at)})</span></div>
                    ${a.status === 'On_Duty' ? `<button onclick="checkOutAtt(${a.id})" class="px-2.5 py-1 text-xs font-semibold bg-amber-100 text-amber-800 rounded cursor-pointer">Check Out</button>` : `<span class="text-xs text-slate-400">Out: ${escapeHtml(a.check_out_at || '')}</span>`}
                </div>`).join('') : '<p class="text-slate-400">No attendance logged.</p>';
        }

        async function addDailyStaff() {
            const res = await fetch(appPath('/api/admin_manage_staff.php'), {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ csrf_token: csrfToken, action: 'add_daily_staff', staff_name: document.getElementById('stf-name').value.trim(), role: document.getElementById('stf-role').value, phone_number: document.getElementById('stf-phone').value.trim() })
            });
            const r = await res.json();
            showToast(r.message || 'Done', res.ok ? 'success' : 'error');
            if (res.ok) { document.getElementById('stf-name').value = ''; document.getElementById('stf-phone').value = ''; loadStaff(); }
        }

        async function toggleStaff(staffId) {
            const res = await fetch(appPath('/api/admin_manage_staff.php'), {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ csrf_token: csrfToken, action: 'toggle_staff_status', staff_id: staffId })
            });
            if (res.ok) loadStaff();
        }

        async function logAttendanceCheckIn() {
            const res = await fetch(appPath('/api/admin_manage_staff.php'), {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ csrf_token: csrfToken, action: 'log_attendance', mode: 'check_in', staff_type: document.getElementById('att-type').value, staff_name: document.getElementById('att-name').value.trim(), shift_name: document.getElementById('att-shift').value })
            });
            const r = await res.json();
            showToast(r.message || 'Logged', res.ok ? 'success' : 'error');
            if (res.ok) { document.getElementById('att-name').value = ''; loadStaff(); }
        }

        async function checkOutAtt(aid) {
            const res = await fetch(appPath('/api/admin_manage_staff.php'), {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ csrf_token: csrfToken, action: 'log_attendance', mode: 'check_out', attendance_id: aid })
            });
            if (res.ok) loadStaff();
        }
    </script>
</body>
</html>
