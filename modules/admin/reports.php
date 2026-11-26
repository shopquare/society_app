<?php
// modules/admin/reports.php
require_once __DIR__ . '/../../config/db.php';
requirePermission('reports.view');
$base = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="app-base" content="<?php echo $base; ?>">
    <title>Compliance Reports &amp; Audit Explorer - SecureGate</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script>
    <script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 font-sans min-h-screen flex flex-col">
    <?php $activeTab = 'reports'; require_once __DIR__ . '/navbar.php'; ?>
    <main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 font-serif">Automated Society Reports &amp; Audit Logs</h1>
            <p class="text-sm text-slate-500 mt-1">Generate security, gate handover, financial, overdue dues, and staff attendance reports in CSV or PDF.</p>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5 flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Report Dataset</label>
                <select id="rpt-type" onchange="loadReport()" class="px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white">
                    <option value="security_activity">Security &amp; Visitor Activity</option>
                    <option value="gate_handovers">Gate Shift Handovers</option>
                    <option value="financial_collection">Financial Collections</option>
                    <option value="overdue_payments">Overdue Dues &amp; Aging</option>
                    <option value="staff_attendance">Guard &amp; Staff Attendance</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">From Date</label>
                <input type="date" id="rpt-from" value="<?php echo date('Y-m-01'); ?>" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">To Date</label>
                <input type="date" id="rpt-to" value="<?php echo date('Y-m-d'); ?>" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <button onclick="loadReport()" class="px-4 py-2 text-sm font-semibold bg-brand-900 text-white rounded-lg cursor-pointer">Preview</button>
            <button onclick="exportReport('csv')" class="px-4 py-2 text-sm font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-lg cursor-pointer">⬇ CSV</button>
            <button onclick="exportReport('pdf')" class="px-4 py-2 text-sm font-semibold bg-indigo-50 text-indigo-800 border border-indigo-200 rounded-lg cursor-pointer">📄 PDF</button>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="p-4 border-b border-slate-100"><h2 id="rpt-title" class="font-bold text-slate-900">Report Preview</h2></div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs whitespace-nowrap">
                    <thead id="rpt-thead" class="bg-slate-50 border-b border-slate-200 text-slate-600"></thead>
                    <tbody id="rpt-tbody" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="p-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="font-bold text-slate-900">Enterprise Audit Trail Explorer</h2>
                <button onclick="loadAuditLogs()" class="text-xs font-semibold text-brand-900 cursor-pointer">↻ Refresh</button>
            </div>
            <div id="audit-log-list" class="divide-y divide-slate-100 text-xs max-h-96 overflow-y-auto">Loading audit trail...</div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => { loadReport(); loadAuditLogs(); });

        function rptQuery() {
            return `report=${encodeURIComponent(document.getElementById('rpt-type').value)}&from=${encodeURIComponent(document.getElementById('rpt-from').value)}&to=${encodeURIComponent(document.getElementById('rpt-to').value)}`;
        }

        function exportReport(fmt) {
            window.open(appPath(`/api/admin_reports.php?${rptQuery()}&format=${fmt}`), '_blank');
        }

        async function loadReport() {
            const res = await fetch(appPath(`/api/admin_reports.php?${rptQuery()}&format=json`));
            const r = await res.json();
            if (!res.ok || r.status !== 'success') return;
            document.getElementById('rpt-title').innerText = r.data.title || 'Report';
            const hdrs = r.data.headers || [];
            const rows = r.data.rows || [];
            document.getElementById('rpt-thead').innerHTML = '<tr>' + hdrs.map(h => `<th class="px-4 py-3">${escapeHtml(h)}</th>`).join('') + '</tr>';
            document.getElementById('rpt-tbody').innerHTML = rows.length
                ? rows.map(row => `<tr class="hover:bg-slate-50">${row.map(c => `<td class="px-4 py-2.5">${escapeHtml(c)}</td>`).join('')}</tr>`).join('')
                : `<tr><td colspan="${hdrs.length || 1}" class="px-4 py-8 text-center text-slate-400">No matching records in selected range.</td></tr>`;
        }

        async function loadAuditLogs() {
            const res = await fetch(appPath('/api/admin_get_audit_logs.php?limit=50'));
            const r = await res.json();
            if (!res.ok || r.status !== 'success') return;
            const logs = r.data.logs || [];
            document.getElementById('audit-log-list').innerHTML = logs.length ? logs.map(l => `
                <div class="px-4 py-2.5 flex justify-between items-center gap-3">
                    <div><span class="font-bold text-slate-900">${escapeHtml(l.action)}</span> on <span class="font-mono text-indigo-700">${escapeHtml(l.entity_type)} #${l.entity_id || '-'}</span> by <strong>${escapeHtml(l.user_name || 'System')}</strong></div>
                    <span class="text-slate-400 shrink-0">${escapeHtml(l.created_at)}</span>
                </div>`).join('') : '<div class="p-6 text-center text-slate-400">No audit entries recorded yet.</div>';
        }
    </script>
</body>
</html>
