<?php
// modules/admin/documents.php
require_once __DIR__ . '/../../config/db.php';
requirePermission('documents.manage');
$csrfToken = generateCsrfToken();
$base = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="app-base" content="<?php echo $base; ?>">
    <title>Digital Society Office &amp; Document Vault - SecureGate</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script>
    <script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 font-sans min-h-screen flex flex-col">
    <?php $activeTab = 'documents'; require_once __DIR__ . '/navbar.php'; ?>
    <main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 font-serif">Digital Society Office &amp; Document Vault</h1>
            <p class="text-sm text-slate-500 mt-1">Manage protected society records, bylaws, AGM minutes, and issue verified NOC/Address Proof PDF certificates.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-xl border border-slate-200 p-6 h-fit">
                <h2 class="text-lg font-bold text-slate-900 font-serif mb-4">Upload Vault Document</h2>
                <form onsubmit="event.preventDefault(); uploadVaultDoc();" class="space-y-3 text-sm">
                    <input type="text" id="doc-title" required placeholder="Document Title (e.g., AGM Minutes 2026)" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    <select id="doc-cat" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
                        <option value="Society_Rules">Society Rules &amp; Bylaws</option>
                        <option value="AGM_Minutes">AGM Minutes</option>
                        <option value="Financial_Audit">Financial Audit Report</option>
                        <option value="General_Circular">General Circular</option>
                        <option value="Flat_KYC">Flat KYC Record</option>
                    </select>
                    <select id="doc-vis" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
                        <option value="Public_Members">Visible to All Society Members</option>
                        <option value="Committee_Only">Committee Only (Restricted)</option>
                    </select>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">PDF or Image Scan (Max 10 MB)</label>
                        <input type="file" id="doc-file" accept=".pdf,image/png,image/jpeg,image/webp" required class="w-full text-xs">
                    </div>
                    <button type="submit" class="w-full py-2.5 bg-brand-900 text-white font-semibold rounded-lg cursor-pointer">🔒 Upload to Private Vault</button>
                </form>
            </div>

            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-xl border border-slate-200 p-6">
                    <h2 class="text-lg font-bold text-slate-900 font-serif mb-4">Resident NOC &amp; Office Approval Queue</h2>
                    <div id="admin-doc-requests" class="space-y-3 text-sm">Loading requests...</div>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-6">
                    <h2 class="text-lg font-bold text-slate-900 font-serif mb-4">Protected Document Repository</h2>
                    <div id="admin-docs-list" class="space-y-2 text-sm">Loading vault...</div>
                </div>
            </div>
        </div>
    </main>

    <script>
        const csrfToken = document.getElementById('csrf_token').value;
        document.addEventListener('DOMContentLoaded', loadOffice);

        function readFileBase64(input) {
            return new Promise((resolve, reject) => {
                const file = input.files && input.files[0];
                if (!file) return resolve('');
                const rd = new FileReader();
                rd.onload = () => resolve(rd.result);
                rd.onerror = reject;
                rd.readAsDataURL(file);
            });
        }

        async function loadOffice() {
            const res = await fetch(appPath('/api/documents_office.php'));
            const r = await res.json();
            if (!res.ok || r.status !== 'success') return;

            const reqs = r.data.requests || [];
            document.getElementById('admin-doc-requests').innerHTML = reqs.length ? reqs.map(q => `
                <div class="p-4 border border-slate-200 rounded-xl bg-slate-50 space-y-2">
                    <div class="flex justify-between items-start gap-2">
                        <div>
                            <div class="font-bold text-slate-900">${escapeHtml(q.request_type.replace(/_/g, ' '))}: ${escapeHtml(q.subject)}</div>
                            <div class="text-xs text-slate-500">Unit ${escapeHtml(q.unit_number)} • ${escapeHtml(q.resident_name)}</div>
                        </div>
                        <span class="text-xs font-bold px-2.5 py-0.5 rounded-full ${q.status === 'Issued' || q.status === 'Approved' ? 'bg-emerald-100 text-emerald-800' : (q.status === 'Rejected' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800')}">${escapeHtml(q.status)}</span>
                    </div>
                    <p class="text-xs text-slate-700">${escapeHtml(q.details)}</p>
                    <div class="flex flex-wrap gap-2 text-xs">
                        ${q.supporting_url ? `<a href="${escapeHtml(q.supporting_url)}" target="_blank" class="text-indigo-700 font-semibold hover:underline">📎 View Attachment</a>` : ''}
                        ${q.response_url ? `<a href="${escapeHtml(q.response_url)}" target="_blank" class="text-emerald-700 font-semibold hover:underline">📄 Issued Certificate PDF</a>` : ''}
                    </div>
                    ${q.status === 'Pending' || q.status === 'Under_Review' ? `
                    <div class="flex flex-wrap gap-2 pt-2 border-t border-slate-200 items-center">
                        <input type="text" id="req-rem-${q.id}" placeholder="Resident-facing remark..." class="flex-1 px-2.5 py-1 text-xs border border-slate-300 rounded">
                        <button onclick="procReq(${q.id},'Issued',true)" class="px-3 py-1 text-xs font-bold bg-emerald-700 text-white rounded cursor-pointer">✓ Approve &amp; Issue PDF</button>
                        <button onclick="procReq(${q.id},'Rejected',false)" class="px-3 py-1 text-xs font-bold bg-rose-600 text-white rounded cursor-pointer">Reject</button>
                    </div>` : ''}
                </div>`).join('') : '<p class="text-slate-400">No resident document requests in queue.</p>';

            const docs = r.data.documents || [];
            document.getElementById('admin-docs-list').innerHTML = docs.length ? docs.map(d => `
                <div class="p-3 border border-slate-100 rounded-lg flex justify-between items-center">
                    <div><div class="font-bold text-slate-900">${escapeHtml(d.title)}</div><div class="text-xs text-slate-500">${escapeHtml(d.category)} • <span class="font-semibold">${escapeHtml(d.visibility)}</span></div></div>
                    <a href="${escapeHtml(d.stream_url)}" target="_blank" class="px-3 py-1 text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-lg">Open</a>
                </div>`).join('') : '<p class="text-slate-400">No documents in vault yet.</p>';
        }

        async function uploadVaultDoc() {
            const b64 = await readFileBase64(document.getElementById('doc-file'));
            const res = await fetch(appPath('/api/documents_office.php'), {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ csrf_token: csrfToken, action: 'upload_document', title: document.getElementById('doc-title').value.trim(), category: document.getElementById('doc-cat').value, visibility: document.getElementById('doc-vis').value, file_base64: b64 })
            });
            const r = await res.json();
            showToast(r.message || 'Uploaded', res.ok ? 'success' : 'error');
            if (res.ok) { document.getElementById('doc-title').value = ''; document.getElementById('doc-file').value = ''; loadOffice(); }
        }

        async function procReq(reqId, status, autoIssuePdf) {
            const rem = (document.getElementById('req-rem-' + reqId)?.value || '').trim();
            const res = await fetch(appPath('/api/documents_office.php'), {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ csrf_token: csrfToken, action: 'process_request', request_id: reqId, status, resident_remarks: rem, auto_issue_pdf: autoIssuePdf })
            });
            const r = await res.json();
            showToast(r.message || 'Processed', res.ok ? 'success' : 'error');
            if (res.ok) loadOffice();
        }
    </script>
</body>
</html>
