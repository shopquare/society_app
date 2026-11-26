<?php
// modules/resident/documents.php
require_once __DIR__ . '/../../config/db.php';
requireLogin();
if (!hasRole([1, 2, 3])) {
    header('Location: ' . appBasePath() . '/login');
    exit();
}
$csrfToken = generateCsrfToken();
$base = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="app-base" content="<?php echo $base; ?>">
    <title>Digital Document Vault &amp; NOC Requests - SecureGate</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script>
    <script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 font-sans min-h-screen flex flex-col">
    <nav class="bg-brand-900 shadow-md border-b border-brand-800 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between h-16 items-center">
            <div class="flex items-center gap-3">
                <a href="<?php echo $base; ?>/resident/dashboard" class="text-white/80 hover:text-white">← Back</a>
                <span class="font-serif text-xl font-semibold text-white">Digital Society Office &amp; Vault</span>
            </div>
        </div>
    </nav>

    <main class="flex-grow max-w-6xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 grid grid-cols-1 lg:grid-cols-3 gap-6">
        <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="space-y-6">
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h2 class="text-base font-bold text-slate-900 font-serif mb-3">Request NOC / Society Certificate</h2>
                <form onsubmit="event.preventDefault(); submitOfficeRequest();" class="space-y-3 text-sm">
                    <select id="req-type" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
                        <option value="NOC">NOC (Sale / Rent / Passport)</option>
                        <option value="Address_Proof">Society Address Proof</option>
                        <option value="Tenant_Verification">Tenant Police Verification</option>
                        <option value="Move_In_Out_Approval">Move-In / Move-Out Clearance</option>
                        <option value="Parking_Allotment">Parking Slot Allotment</option>
                        <option value="Renovation_Permission">Interior Renovation Permission</option>
                    </select>
                    <input type="text" id="req-subject" required placeholder="Subject" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    <textarea id="req-details" required rows="3" placeholder="Provide required dates or recipient details..." class="w-full px-3 py-2 border border-slate-300 rounded-lg"></textarea>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Optional Supporting KYC/Lease Scan (PDF/Image)</label>
                        <input type="file" id="req-file" accept=".pdf,image/png,image/jpeg,image/webp" class="w-full text-xs">
                    </div>
                    <button type="submit" class="w-full py-2.5 bg-brand-900 text-white font-semibold rounded-lg cursor-pointer">Submit Request</button>
                </form>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h2 class="text-base font-bold text-slate-900 font-serif mb-3">Upload Private Flat KYC</h2>
                <form onsubmit="event.preventDefault(); uploadPrivateDoc();" class="space-y-3 text-sm">
                    <input type="text" id="pdoc-title" required placeholder="e.g., Registered Rent Agreement" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    <input type="file" id="pdoc-file" accept=".pdf,image/png,image/jpeg,image/webp" required class="w-full text-xs">
                    <button type="submit" class="w-full py-2 bg-emerald-700 text-white font-semibold rounded-lg cursor-pointer">🔒 Store Privately</button>
                </form>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h2 class="text-base font-bold text-slate-900 font-serif mb-3">My Certificate &amp; Approval Requests</h2>
                <div id="res-requests-list" class="space-y-3 text-sm">Loading requests...</div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h2 class="text-base font-bold text-slate-900 font-serif mb-3">Society Bylaws, Circulars &amp; My Private Vault</h2>
                <div id="res-docs-list" class="space-y-2 text-sm">Loading documents...</div>
            </div>
        </div>
    </main>

    <script>
        const csrfToken = document.getElementById('csrf_token').value;
        document.addEventListener('DOMContentLoaded', loadResidentOffice);

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

        async function loadResidentOffice() {
            const res = await fetch(appPath('/api/documents_office.php'));
            const r = await res.json();
            if (!res.ok || r.status !== 'success') return;

            const reqs = r.data.requests || [];
            document.getElementById('res-requests-list').innerHTML = reqs.length ? reqs.map(q => `
                <div class="p-3.5 border border-slate-200 rounded-xl bg-slate-50">
                    <div class="flex justify-between items-center">
                        <span class="font-bold text-slate-900">${escapeHtml(q.request_type.replace(/_/g, ' '))}: ${escapeHtml(q.subject)}</span>
                        <span class="text-xs font-bold px-2.5 py-0.5 rounded-full ${q.status === 'Issued' || q.status === 'Approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}">${escapeHtml(q.status)}</span>
                    </div>
                    <p class="text-xs text-slate-600 mt-1">${escapeHtml(q.details)}</p>
                    ${q.resident_remarks ? `<div class="text-xs text-indigo-800 mt-1">Office Note: ${escapeHtml(q.resident_remarks)}</div>` : ''}
                    ${q.response_url ? `<a href="${escapeHtml(q.response_url)}" target="_blank" class="inline-block mt-2 px-3 py-1 text-xs font-bold bg-emerald-700 text-white rounded-lg">⬇ Download Official Certificate PDF</a>` : ''}
                </div>`).join('') : '<p class="text-slate-400">No requests submitted yet.</p>';

            const docs = r.data.documents || [];
            document.getElementById('res-docs-list').innerHTML = docs.length ? docs.map(d => `
                <div class="p-3 border border-slate-100 rounded-lg flex justify-between items-center">
                    <div><div class="font-bold text-slate-900">${escapeHtml(d.title)}</div><div class="text-xs text-slate-500">${escapeHtml(d.category.replace(/_/g, ' '))} • ${escapeHtml(d.visibility === 'Resident_Private' ? 'Private Flat Document' : 'Society Document')}</div></div>
                    <a href="${escapeHtml(d.stream_url)}" target="_blank" class="px-3 py-1 text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-lg">View PDF / File</a>
                </div>`).join('') : '<p class="text-slate-400">No shared society documents available.</p>';
        }

        async function submitOfficeRequest() {
            const b64 = await readFileBase64(document.getElementById('req-file'));
            const res = await fetch(appPath('/api/documents_office.php'), {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ csrf_token: csrfToken, action: 'submit_request', request_type: document.getElementById('req-type').value, subject: document.getElementById('req-subject').value.trim(), details: document.getElementById('req-details').value.trim(), file_base64: b64 })
            });
            const r = await res.json();
            showToast(r.message || 'Submitted', res.ok ? 'success' : 'error');
            if (res.ok) { document.getElementById('req-subject').value = ''; document.getElementById('req-details').value = ''; loadResidentOffice(); }
        }

        async function uploadPrivateDoc() {
            const b64 = await readFileBase64(document.getElementById('pdoc-file'));
            const res = await fetch(appPath('/api/documents_office.php'), {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ csrf_token: csrfToken, action: 'upload_document', title: document.getElementById('pdoc-title').value.trim(), category: 'Flat_KYC', file_base64: b64 })
            });
            const r = await res.json();
            showToast(r.message || 'Stored', res.ok ? 'success' : 'error');
            if (res.ok) { document.getElementById('pdoc-title').value = ''; document.getElementById('pdoc-file').value = ''; loadResidentOffice(); }
        }
    </script>
</body>
</html>
