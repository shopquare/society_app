<?php
// modules/admin/vendors.php
if (!isset($_SESSION['logged_in'])) { header('Location: ' . appBasePath() . '/login'); exit(); }
requirePermission('vendors.manage');
$csrfToken = generateCsrfToken();
$base = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="app-base" content="<?php echo $base; ?>">
    <title>Vendors & Payouts - Admin Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700&display=swap" rel="stylesheet">
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script><script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans flex flex-col min-h-screen">
<?php $activeTab = 'vendors'; require_once __DIR__ . '/navbar.php'; ?>
<main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 grid grid-cols-1 lg:grid-cols-3 gap-6 text-xs">
    <div class="space-y-6">
        <form onsubmit="event.preventDefault(); saveVendor();" class="bg-white rounded-xl border border-slate-200 p-5 shadow-xs space-y-3">
            <h2 class="text-base font-bold font-serif text-slate-900">Register Society Vendor</h2>
            <input type="text" id="v_name" required placeholder="Vendor / Agency Name *" class="w-full px-3 py-2 border rounded-lg">
            <div class="grid grid-cols-2 gap-2">
                <select id="v_cat" class="px-3 py-2 border rounded-lg bg-white"><option>Security Agency</option><option>Housekeeping</option><option>Lift Maintenance</option><option>Utility / Water</option><option>Plumbing / Electrical</option><option>Garden & Pest Control</option></select>
                <input type="text" id="v_phone" required placeholder="Contact Phone *" class="px-3 py-2 border rounded-lg">
            </div>
            <input type="text" id="v_gst" placeholder="GSTIN / PAN (Optional)" class="w-full px-3 py-2 border rounded-lg">
            <button type="submit" class="w-full py-2 bg-brand-900 text-white font-bold rounded-lg cursor-pointer">Save Vendor</button>
        </form>

        <form onsubmit="event.preventDefault(); recordVendorPayment();" class="bg-white rounded-xl border border-slate-200 p-5 shadow-xs space-y-3">
            <h2 class="text-base font-bold font-serif text-slate-900">Record Vendor Invoice / Payout</h2>
            <select id="vp_vendor" required class="w-full px-3 py-2 border rounded-lg bg-white"></select>
            <div class="grid grid-cols-2 gap-2">
                <input type="text" id="vp_inv" required placeholder="Bill / Inv No *" class="px-3 py-2 border rounded-lg">
                <input type="date" id="vp_due" required value="<?php echo date('Y-m-d'); ?>" class="px-3 py-2 border rounded-lg">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <input type="number" id="vp_amt" required step="0.01" placeholder="Invoice Amount *" class="px-3 py-2 border rounded-lg">
                <input type="number" id="vp_paid" step="0.01" placeholder="Paid Amount" class="px-3 py-2 border rounded-lg">
            </div>
            <select id="vp_method" class="w-full px-3 py-2 border rounded-lg bg-white"><option value="Bank_Transfer">Bank Transfer</option><option value="UPI">UPI</option><option value="Cheque">Cheque</option><option value="Cash">Cash</option></select>
            <input type="file" id="vp_doc" accept="image/*,.pdf" class="w-full">
            <button type="submit" class="w-full py-2 bg-emerald-700 text-white font-bold rounded-lg cursor-pointer">Log Vendor Payment</button>
        </form>
    </div>

    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="p-4 border-b border-slate-100 font-bold font-serif text-slate-900">Registered Vendors</div>
            <div id="vendors-list" class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-3"></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="p-4 border-b border-slate-100 font-bold font-serif text-slate-900">Vendor Invoice & Payment History</div>
            <div class="overflow-x-auto"><table class="w-full text-left"><thead class="bg-slate-50 border-b"><tr><th class="px-4 py-2">Vendor</th><th class="px-4 py-2">Bill #</th><th class="px-4 py-2">Amount / Paid</th><th class="px-4 py-2">Status</th><th class="px-4 py-2">Doc</th></tr></thead><tbody id="vpay-tbody" class="divide-y divide-slate-100"></tbody></table></div>
        </div>
    </div>
</main>
<script>
const csrf = <?php echo json_encode($csrfToken); ?>;
document.addEventListener('DOMContentLoaded', loadVendors);
function readB64(f){ return new Promise(r=>{ if(!f)return r(''); const fr=new FileReader(); fr.onload=()=>r(fr.result); fr.readAsDataURL(f); }); }
async function loadVendors() {
    const j = await (await fetch(appPath('/api/admin_accounting.php'))).json();
    const vs = j.vendors || [];
    document.getElementById('vp_vendor').innerHTML = vs.map(v=>`<option value="${Number(v.id)}">${escapeHtml(v.vendor_name)} (${escapeHtml(v.service_category)})</option>`).join('');
    document.getElementById('vendors-list').innerHTML = vs.map(v=>`<div class="p-3 bg-slate-50 border rounded-lg"><div class="font-bold text-slate-900">${escapeHtml(v.vendor_name)}</div><div class="text-slate-500">${escapeHtml(v.service_category)} • 📞 ${escapeHtml(v.phone_number)}</div></div>`).join('') || '<div class="text-slate-400">No vendors added yet.</div>';
    document.getElementById('vpay-tbody').innerHTML = (j.vendor_payments||[]).map(p=>`<tr><td class="px-4 py-2.5 font-bold">${escapeHtml(p.vendor_name)}</td><td class="px-4 py-2.5">${escapeHtml(p.invoice_number)}</td><td class="px-4 py-2.5">₹${Number(p.paid_amount)} / ₹${Number(p.amount)}</td><td class="px-4 py-2.5 font-bold">${escapeHtml(p.status)}</td><td class="px-4 py-2.5">${p.document_path?`<a href="${appPath('/uploads/'+escapeHtml(p.document_path))}" target="_blank" class="text-brand-900 underline font-bold">View</a>`:'—'}</td></tr>`).join('') || '<tr><td colspan="5" class="p-6 text-center text-slate-400">No vendor invoices logged.</td></tr>';
}
async function saveVendor() {
    const res = await fetch(appPath('/api/admin_accounting.php'), { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'save_vendor', vendor_name:document.getElementById('v_name').value, service_category:document.getElementById('v_cat').value, phone_number:document.getElementById('v_phone').value, gst_or_pan:document.getElementById('v_gst').value, csrf_token:csrf}) });
    showToast((await res.json()).message||'Saved', res.ok?'success':'error'); if (res.ok) loadVendors();
}
async function recordVendorPayment() {
    const doc = await readB64(document.getElementById('vp_doc').files[0]);
    const res = await fetch(appPath('/api/admin_accounting.php'), { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'record_vendor_payment', vendor_id:document.getElementById('vp_vendor').value, invoice_number:document.getElementById('vp_inv').value, due_date:document.getElementById('vp_due').value, amount:document.getElementById('vp_amt').value, paid_amount:document.getElementById('vp_paid').value||0, payment_method:document.getElementById('vp_method').value, document_base64:doc, csrf_token:csrf}) });
    showToast((await res.json()).message||'Logged', res.ok?'success':'error'); if (res.ok) loadVendors();
}
</script>
</body></html>
