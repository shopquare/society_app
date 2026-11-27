<?php
// modules/admin/notifications.php
require_once __DIR__ . '/../../config/db.php';
requirePermission('notifications.broadcast');
$csrfToken = generateCsrfToken();
$base = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="app-base" content="<?php echo $base; ?>">
    <title>Emergency Broadcast &amp; Notification Logs - SecureGate</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script>
    <link rel="stylesheet" href="<?php echo $base; ?>/assets/css/style.css">
    <script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 font-sans min-h-screen flex flex-col">
    <?php $activeTab = 'notifications'; require_once __DIR__ . '/navbar.php'; ?>
    <main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 font-serif">Emergency Broadcast &amp; Multi-Channel Delivery Queue</h1>
                <p class="text-sm text-slate-500 mt-1">Dispatch instant or scheduled society alerts across In-App, Web Push, Android, WhatsApp, SMS &amp; Email.</p>
            </div>
            <button type="button" onclick="runQueueNow()" class="px-4 py-2 text-xs font-bold bg-indigo-700 hover:bg-indigo-800 text-white rounded-lg cursor-pointer">⚡ Process Pending Queue Now</button>
        </div>

        <div id="deliv-kpis" class="grid grid-cols-2 sm:grid-cols-4 gap-4"></div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-xl border border-slate-200 p-6 h-fit">
                <h2 class="text-lg font-bold text-slate-900 font-serif mb-4">Broadcast Composer</h2>
                <form onsubmit="event.preventDefault(); sendBroadcast();" class="space-y-3 text-sm">
                    <label class="p-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-900 flex items-center gap-2 font-semibold cursor-pointer">
                        <input type="checkbox" id="bc-emergency"> 🚨 Critical Emergency Broadcast (Bypasses Opt-Outs)
                    </label>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Target Audience</label>
                        <select id="bc-audience" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
                            <option value="all">All Society Members (Residents, Guards, Admins)</option>
                            <option value="residents">Residents Only</option>
                            <option value="guards">Security Guards Only</option>
                            <option value="committee">Committee &amp; Admins</option>
                        </select>
                    </div>
                    <input type="text" id="bc-title" required placeholder="Broadcast Title" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    <textarea id="bc-msg" required rows="3" placeholder="Alert message content..." class="w-full px-3 py-2 border border-slate-300 rounded-lg"></textarea>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Optional Schedule Date/Time (Blank = Immediate)</label>
                        <input type="datetime-local" id="bc-schedule" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Delivery Channels</label>
                        <div class="grid grid-cols-2 gap-1.5 text-xs">
                            <label><input type="checkbox" class="bc-chan" value="in_app" checked> In-App Bell</label>
                            <label><input type="checkbox" class="bc-chan" value="web_push" checked> Web Push</label>
                            <label><input type="checkbox" class="bc-chan" value="android" checked> Android App</label>
                            <label><input type="checkbox" class="bc-chan" value="whatsapp"> WhatsApp</label>
                            <label><input type="checkbox" class="bc-chan" value="sms"> SMS</label>
                            <label><input type="checkbox" class="bc-chan" value="email"> Email</label>
                        </div>
                    </div>
                    <button type="submit" class="w-full py-2.5 bg-brand-900 hover:bg-brand-800 text-white font-semibold rounded-lg cursor-pointer">Send Society Broadcast</button>
                </form>
            </div>

            <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="p-5 border-b border-slate-100"><h2 class="text-lg font-bold text-slate-900 font-serif">Channel Delivery Status Log</h2></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs whitespace-nowrap">
                        <thead class="bg-slate-50 border-b border-slate-200 text-slate-600">
                            <tr>
                                <th class="px-4 py-3">Recipient &amp; Title</th>
                                <th class="px-4 py-3">Channel</th>
                                <th class="px-4 py-3">Status &amp; Response</th>
                                <th class="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody id="deliv-tbody" class="divide-y divide-slate-100"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        const csrfToken = document.getElementById('csrf_token').value;
        document.addEventListener('DOMContentLoaded', loadDeliveryLogs);

        async function loadDeliveryLogs() {
            const res = await fetch(appPath('/api/admin_broadcast.php'));
            const r = await res.json();
            if (!res.ok || r.status !== 'success') return;
            const d = r.data || r;
            const s = d.summary || {};
            document.getElementById('deliv-kpis').innerHTML = `
                <div class="bg-white p-4 rounded-xl border border-slate-200"><span class="text-xs text-slate-400 font-bold uppercase">Sent</span><div class="text-2xl font-black text-emerald-700 mt-1">${s.Sent || 0}</div></div>
                <div class="bg-white p-4 rounded-xl border border-slate-200"><span class="text-xs text-slate-400 font-bold uppercase">Pending Queue</span><div class="text-2xl font-black text-amber-600 mt-1">${s.Pending || 0}</div></div>
                <div class="bg-white p-4 rounded-xl border border-slate-200"><span class="text-xs text-slate-400 font-bold uppercase">Failed (Retry)</span><div class="text-2xl font-black text-rose-700 mt-1">${s.Failed || 0}</div></div>
                <div class="bg-white p-4 rounded-xl border border-slate-200"><span class="text-xs text-slate-400 font-bold uppercase">Skipped / Sandbox</span><div class="text-2xl font-black text-slate-600 mt-1">${s.Skipped || 0}</div></div>`;

            const rows = d.deliveries || [];
            document.getElementById('deliv-tbody').innerHTML = rows.length ? rows.map(d => `
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3"><div class="font-bold text-slate-900">${escapeHtml(d.recipient_name || ('User #' + d.user_id))}</div><div class="text-slate-500">${escapeHtml(d.title)}</div></td>
                    <td class="px-4 py-3 uppercase font-mono">${escapeHtml(d.channel)} <span class="text-[10px] text-slate-400">(${d.attempts}/${d.max_attempts})</span></td>
                    <td class="px-4 py-3"><span class="px-2 py-0.5 rounded font-semibold ${d.status === 'Sent' ? 'bg-emerald-50 text-emerald-700' : (d.status === 'Failed' ? 'bg-rose-50 text-rose-700' : (d.status === 'Pending' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600'))}">${escapeHtml(d.status)}</span> <span class="text-slate-400 ml-1">${escapeHtml((d.provider_response || '').slice(0, 50))}</span></td>
                    <td class="px-4 py-3 text-right">${(d.status === 'Failed' || d.status === 'Pending') ? `<button onclick="retryDeliv(${d.id})" class="px-2.5 py-1 bg-indigo-50 text-indigo-700 rounded font-semibold cursor-pointer">Retry</button>` : '—'}</td>
                </tr>`).join('') : '<tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">No delivery logs recorded yet.</td></tr>';
        }

        async function sendBroadcast() {
            const channels = Array.from(document.querySelectorAll('.bc-chan:checked')).map(c => c.value);
            const res = await fetch(appPath('/api/admin_broadcast.php'), {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    csrf_token: csrfToken, action: 'send_broadcast',
                    is_emergency: document.getElementById('bc-emergency').checked,
                    audience: document.getElementById('bc-audience').value,
                    title: document.getElementById('bc-title').value.trim(),
                    message: document.getElementById('bc-msg').value.trim(),
                    scheduled_at: document.getElementById('bc-schedule').value,
                    channels
                })
            });
            const r = await res.json();
            showToast(r.message || 'Broadcast sent', res.ok ? 'success' : 'error');
            if (res.ok) { document.getElementById('bc-title').value = ''; document.getElementById('bc-msg').value = ''; loadDeliveryLogs(); }
        }

        async function runQueueNow() {
            const res = await fetch(appPath('/api/admin_broadcast.php'), { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ csrf_token: csrfToken, action: 'run_queue_now' }) });
            const r = await res.json();
            showToast(r.message || 'Processed', res.ok ? 'success' : 'error');
            loadDeliveryLogs();
        }

        async function retryDeliv(id) {
            const res = await fetch(appPath('/api/admin_broadcast.php'), { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ csrf_token: csrfToken, action: 'retry_delivery', delivery_id: id }) });
            const r = await res.json();
            showToast(r.message || 'Retried', res.ok ? 'success' : 'error');
            loadDeliveryLogs();
        }
    </script>
</body>
</html>
