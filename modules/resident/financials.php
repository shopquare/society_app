<?php
// /society_app/modules/resident/financials.php

if (!isset($_SESSION['logged_in']) || !hasRole(3)) {
    header('Location: ' . appBasePath() . '/login');
    exit();
}
require_once __DIR__ . '/../../includes/csrf.php';
$csrfToken = generateCsrfToken();
$base = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="app-base" content="<?php echo $base; ?>">
    <title>My Dues & Receipts - Resident Portal</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script>
    <script src="<?php echo $base; ?>/assets/js/app.js"></script>
    <style>
        @media print {
            body * { visibility: hidden; }
            #receipt-printable-area, #receipt-printable-area * { visibility: visible; }
            #receipt-printable-area { position: absolute; left: 0; top: 0; width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans flex flex-col min-h-screen">

    <nav class="bg-brand-900 shadow-md border-b border-brand-800 sticky top-0 z-50 no-print" style="padding-top: env(safe-area-inset-top, 0px);">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between h-16 items-center">
            <div class="flex items-center gap-4">
                <a href="<?php echo $base; ?>/resident/dashboard" aria-label="Back to Resident Dashboard" class="text-white/80 hover:text-white transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <span class="font-serif text-xl font-semibold text-white tracking-wide">My Dues & Receipts</span>
            </div>
            <div class="flex items-center gap-3">
                <a href="<?php echo $base; ?>/resident/dashboard" class="text-xs font-semibold text-brand-100 hover:text-white bg-white/10 hover:bg-white/20 px-3 py-1.5 rounded-lg transition">Dashboard</a>
                <form action="<?php echo $base; ?>/api/logout.php" method="POST" class="m-0">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" aria-label="Log Out" title="Log Out" class="p-2 text-brand-50 hover:text-white hover:bg-white/10 rounded-lg transition-colors cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <main class="flex-grow max-w-5xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8">

        <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

        <!-- Header & Summary Cards -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-slate-900 font-serif">Maintenance & Society Dues</h1>
            <p class="text-sm text-slate-500 mt-1">Pay society dues instantly via UPI and download official verified tax receipts.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Pending Dues</span>
                <div class="text-2xl font-black text-rose-700 mt-1" id="stat-pending-amount">₹0.00</div>
                <span class="text-xs text-rose-500 font-medium" id="stat-pending-count">0 unpaid bill(s)</span>
            </div>
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Paid (This Year)</span>
                <div class="text-2xl font-black text-emerald-700 mt-1" id="stat-paid-amount">₹0.00</div>
                <span class="text-xs text-emerald-600 font-medium" id="stat-paid-count">0 bill(s) completed</span>
            </div>
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Accepted Payment Modes</span>
                <div class="flex items-center gap-2 mt-2">
                    <span class="px-2 py-0.5 bg-indigo-50 text-indigo-700 text-xs font-bold rounded border border-indigo-200">UPI / QR</span>
                    <span class="px-2 py-0.5 bg-emerald-50 text-emerald-700 text-xs font-bold rounded border border-emerald-200">GPay</span>
                    <span class="px-2 py-0.5 bg-amber-50 text-amber-700 text-xs font-bold rounded border border-amber-200">PhonePe</span>
                </div>
                <span class="text-xs text-slate-400 block mt-1">Instant digital receipt issued</span>
            </div>
        </div>

        <!-- Invoices Ledger -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 font-serif">Billing History</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Click "Receipt" to print or download PDF proof of payment.</p>
                </div>
                <button type="button" onclick="fetchMyInvoices()" class="text-xs font-semibold text-brand-900 hover:text-brand-800 inline-flex items-center gap-1 cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    Refresh
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="bg-slate-50 text-slate-600 font-medium border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-4">Bill Title</th>
                            <th class="px-6 py-4">Due Date</th>
                            <th class="px-6 py-4">Amount</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="resident-invoices" class="divide-y divide-slate-100 text-slate-700">
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-slate-500">Loading your bills...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- UPI Payment QR Modal -->
    <div id="upi-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden no-print">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-100 text-center relative animate-in fade-in zoom-in-95 duration-150">
            <button type="button" onclick="closeUpiModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 p-1 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>

            <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
            </div>

            <h3 class="text-xl font-bold text-slate-900 font-serif">Instant UPI Payment</h3>
            <p class="text-xs text-slate-500 mt-1" id="upi-modal-title">Society Maintenance Charges</p>

            <div class="my-5 p-4 bg-slate-50 rounded-xl border border-slate-200 inline-block">
                <img id="upi-qr-image" src="" alt="UPI Payment QR Code" class="w-48 h-48 mx-auto rounded-lg shadow-xs bg-white p-2">
                <div class="text-xs font-mono text-slate-500 mt-2 font-medium">societyhub@upi</div>
            </div>

            <div class="mb-5">
                <div class="text-xs text-slate-400 uppercase font-bold tracking-wider">Amount Payable</div>
                <div class="text-3xl font-black text-slate-900 mt-0.5" id="upi-modal-amount">₹0.00</div>
            </div>

            <p class="text-xs text-slate-500 mb-6 px-4">Scan with any UPI App (Google Pay, PhonePe, Paytm, BHIM) to complete payment instantly.</p>

            <div class="flex flex-col gap-2">
                <button type="button" id="btn-upi-confirm" onclick="confirmUpiPayment()" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm rounded-xl shadow transition cursor-pointer flex items-center justify-center gap-2">
                    <span>✓</span> I Have Completed Payment
                </button>
                <button type="button" onclick="closeUpiModal()" class="w-full py-2.5 text-xs text-slate-500 hover:text-slate-700 font-medium transition">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Official Maintenance Receipt Modal -->
    <div id="receipt-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-2xl max-w-xl w-full p-6 sm:p-8 shadow-2xl border border-slate-100 relative animate-in fade-in zoom-in-95 duration-150 max-h-[90vh] overflow-y-auto">
            <div class="no-print flex justify-between items-center pb-4 mb-4 border-b border-slate-100">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Official Payment Proof</span>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="window.print()" class="px-3 py-1.5 bg-brand-900 hover:bg-brand-800 text-white text-xs font-bold rounded-lg shadow transition cursor-pointer flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        Print Receipt
                    </button>
                    <button type="button" onclick="closeReceiptModal()" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
            </div>

            <div id="receipt-printable-area" class="p-6 bg-slate-50/50 rounded-xl border border-slate-200">
                <!-- Receipt Header -->
                <div class="flex justify-between items-start border-b border-slate-200 pb-5">
                    <div>
                        <div class="text-xs font-black text-brand-900 uppercase tracking-widest">SOCIETY MAINTENANCE RECEIPT</div>
                        <h2 class="text-2xl font-bold text-slate-900 font-serif mt-1" id="receipt-society-name">Sunshine Residency</h2>
                        <p class="text-xs text-slate-500 mt-0.5" id="receipt-society-address">100 Palm Avenue, Pune</p>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-slate-400 font-mono">RECEIPT NO.</div>
                        <div class="text-sm font-black text-slate-800 font-mono" id="receipt-number">REC-0000</div>
                        <div class="text-xs text-slate-500 mt-1" id="receipt-date">Date: 25/11/2026</div>
                    </div>
                </div>

                <!-- Resident Details -->
                <div class="grid grid-cols-2 gap-4 my-5 text-xs">
                    <div>
                        <span class="text-slate-400 block font-medium">Billed To (Resident):</span>
                        <span class="text-slate-900 font-bold text-sm block mt-0.5" id="receipt-resident-name">John Resident</span>
                        <span class="text-slate-600 font-semibold" id="receipt-unit">Flat 101</span>
                    </div>
                    <div class="text-right">
                        <span class="text-slate-400 block font-medium">Payment Mode:</span>
                        <span class="text-slate-900 font-bold text-sm block mt-0.5">Online Digital UPI</span>
                        <span class="text-emerald-700 font-semibold font-mono text-xs" id="receipt-txnid">TXN-ONLINE-VERIFIED</span>
                    </div>
                </div>

                <!-- Line Item Details -->
                <table class="w-full text-xs text-left mb-6 border border-slate-200 rounded-lg overflow-hidden">
                    <thead class="bg-slate-100 text-slate-700 font-bold border-b border-slate-200">
                        <tr>
                            <th class="p-3">Description</th>
                            <th class="p-3 text-right">Amount (INR)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        <tr>
                            <td class="p-3 font-medium text-slate-800" id="receipt-bill-title">Maintenance Charges</td>
                            <td class="p-3 text-right font-bold text-slate-900" id="receipt-amount-row">₹2,500.00</td>
                        </tr>
                        <tr class="bg-slate-50/50 font-bold text-slate-900">
                            <td class="p-3 uppercase">Total Amount Paid</td>
                            <td class="p-3 text-right text-base text-brand-900" id="receipt-total-amount">₹2,500.00</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Stamp and Signature -->
                <div class="flex justify-between items-end pt-4 border-t border-slate-200">
                    <div class="inline-flex items-center gap-2 border-2 border-emerald-600 px-3.5 py-1.5 rounded-lg text-emerald-700 font-black uppercase text-xs tracking-wider transform -rotate-3">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                        PAID & VERIFIED
                    </div>
                    <div class="text-right text-xs text-slate-400">
                        <div class="border-t border-slate-300 w-32 ml-auto mb-1"></div>
                        <span>Authorized Signatory</span>
                        <div class="font-bold text-slate-700">Society Management</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = document.getElementById('csrf_token').value;
        let currentInvoices = [];
        let activeUpiInvoiceId = null;

        document.addEventListener('DOMContentLoaded', fetchMyInvoices);

        async function fetchMyInvoices() {
            const tbody = document.getElementById('resident-invoices');
            try {
                const response = await fetch(appPath('/api/resident_get_invoices.php'));
                const result = await response.json();

                if (response.ok && result.status === 'success') {
                    currentInvoices = result.data || [];
                    updateFinancialStats(currentInvoices);

                    if (currentInvoices.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-12 text-center text-slate-500">You have no maintenance bills at this time.</td></tr>`;
                        return;
                    }

                    tbody.innerHTML = currentInvoices.map(inv => {
                        let statusBadge = '';
                        let actionBtn = '';

                        if (inv.status === 'Unpaid' || inv.status === 'Overdue') {
                            const isOverdue = inv.status === 'Overdue';
                            statusBadge = `<span class="${isOverdue ? 'bg-red-50 text-red-700 border-red-200' : 'bg-amber-50 text-amber-700 border-amber-200'} px-2.5 py-1 rounded-full text-xs font-semibold border">${escapeHtml(inv.status)}</span>`;
                            actionBtn = `
                                <div class="flex items-center justify-end gap-1.5">
                                    <button type="button" onclick="openUpiModal(${inv.id})" class="px-3 py-1.5 text-xs font-bold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 rounded-lg shadow-2xs transition cursor-pointer">
                                        UPI QR
                                    </button>
                                    <button type="button" onclick="processPayment(${Number(inv.id)}, this)" class="px-3.5 py-1.5 text-xs font-bold text-white bg-brand-900 hover:bg-brand-800 rounded-lg shadow-sm transition cursor-pointer">
                                        Pay Now
                                    </button>
                                </div>
                            `;
                        } else {
                            statusBadge = `<span class="bg-emerald-50 text-emerald-700 px-2.5 py-1 rounded-full text-xs font-semibold border border-emerald-200">Paid</span>`;
                            actionBtn = `
                                <button type="button" onclick="viewReceipt(${inv.id})" class="px-3 py-1.5 text-xs font-bold text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 rounded-lg shadow-2xs inline-flex items-center gap-1.5 transition cursor-pointer">
                                    <span>🧾</span> Receipt
                                </button>
                            `;
                        }

                        return `
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4 font-medium text-slate-800">
                                    <div class="font-bold text-slate-900">${escapeHtml(inv.title)}</div>
                                    <div class="text-xs text-slate-400 mt-0.5">Inv #${inv.id}</div>
                                </td>
                                <td class="px-6 py-4 text-slate-500">${escapeHtml(formatDisplayDate(inv.due_date))}</td>
                                <td class="px-6 py-4 font-black text-slate-900 text-base">₹${parseFloat(inv.amount).toFixed(2)}</td>
                                <td class="px-6 py-4">${statusBadge}</td>
                                <td class="px-6 py-4 text-right">${actionBtn}</td>
                            </tr>
                        `;
                    }).join('');
                } else {
                    tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-12 text-center text-red-500">${escapeHtml(result.message || 'Error loading data.')}</td></tr>`;
                }
            } catch (error) {
                tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-12 text-center text-red-500">Error loading data.</td></tr>`;
            }
        }

        function updateFinancialStats(invoices) {
            let pendingAmount = 0;
            let pendingCount = 0;
            let paidAmount = 0;
            let paidCount = 0;

            invoices.forEach(inv => {
                const amt = parseFloat(inv.amount) || 0;
                if (inv.status === 'Paid') {
                    paidAmount += amt;
                    paidCount++;
                } else {
                    pendingAmount += amt;
                    pendingCount++;
                }
            });

            document.getElementById('stat-pending-amount').innerText = `₹${pendingAmount.toFixed(2)}`;
            document.getElementById('stat-pending-count').innerText = `${pendingCount} unpaid bill(s)`;
            document.getElementById('stat-paid-amount').innerText = `₹${paidAmount.toFixed(2)}`;
            document.getElementById('stat-paid-count').innerText = `${paidCount} bill(s) completed`;
        }

        function openUpiModal(invoiceId) {
            const inv = currentInvoices.find(i => Number(i.id) === Number(invoiceId));
            if (!inv) return;
            activeUpiInvoiceId = inv.id;

            const amt = parseFloat(inv.amount).toFixed(2);
            document.getElementById('upi-modal-title').innerText = `${inv.title} (Bill #${inv.id})`;
            document.getElementById('upi-modal-amount').innerText = `₹${amt}`;

            // Generate UPI Intent URL
            const upiUrl = `upi://pay?pa=societyhub@upi&pn=${encodeURIComponent(inv.society_name || 'SocietyHub')}&am=${amt}&cu=INR&tn=${encodeURIComponent('Inv ' + inv.id)}`;
            const qrApi = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(upiUrl)}`;
            document.getElementById('upi-qr-image').src = qrApi;

            document.getElementById('upi-modal').classList.remove('hidden');
        }

        function closeUpiModal() {
            document.getElementById('upi-modal').classList.add('hidden');
            activeUpiInvoiceId = null;
        }

        async function confirmUpiPayment() {
            if (!activeUpiInvoiceId) return;
            const btn = document.getElementById('btn-upi-confirm');
            btn.innerText = 'Verifying Payment...';
            btn.disabled = true;

            await processPayment(activeUpiInvoiceId, btn);
            closeUpiModal();
            btn.innerText = '✓ I Have Completed Payment';
            btn.disabled = false;
        }

        function viewReceipt(invoiceId) {
            const inv = currentInvoices.find(i => Number(i.id) === Number(invoiceId));
            if (!inv) return;

            const amtFormatted = `₹${parseFloat(inv.amount).toFixed(2)}`;
            document.getElementById('receipt-society-name').innerText = inv.society_name || 'Sunshine Residency';
            document.getElementById('receipt-society-address').innerText = inv.society_address || 'Pune, Maharashtra';
            document.getElementById('receipt-number').innerText = `REC-${new Date().getFullYear()}-${String(inv.id).padStart(4, '0')}`;
            document.getElementById('receipt-date').innerText = `Date: ${new Date().toLocaleDateString('en-GB')}`;
            document.getElementById('receipt-resident-name').innerText = inv.resident_name || 'Resident';
            document.getElementById('receipt-unit').innerText = `Unit ${inv.unit_number || '101'}`;
            document.getElementById('receipt-txnid').innerText = `TXN-UPI-${inv.id}-${Date.now().toString().slice(-6)}`;
            document.getElementById('receipt-bill-title').innerText = inv.title;
            document.getElementById('receipt-amount-row').innerText = amtFormatted;
            document.getElementById('receipt-total-amount').innerText = amtFormatted;

            document.getElementById('receipt-modal').classList.remove('hidden');
        }

        function closeReceiptModal() {
            document.getElementById('receipt-modal').classList.add('hidden');
        }

        async function processPayment(invoiceId, btnElement) {
            if (btnElement) {
                btnElement.innerText = 'Processing...';
                btnElement.disabled = true;
                btnElement.classList.add('opacity-50');
            }

            try {
                const response = await fetch(appPath('/api/resident_pay_invoice.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ invoice_id: invoiceId, csrf_token: csrfToken })
                });

                const result = await response.json();

                if (response.ok) {
                    showToast(result.message || 'Payment successful! Receipt generated.', 'success');
                    fetchMyInvoices();
                } else {
                    showToast(result.message || 'Payment failed.', 'error');
                    if (btnElement) {
                        btnElement.innerText = 'Pay Now';
                        btnElement.disabled = false;
                        btnElement.classList.remove('opacity-50');
                    }
                }
            } catch (error) {
                showToast('Payment error.', 'error');
                if (btnElement) {
                    btnElement.innerText = 'Pay Now';
                    btnElement.disabled = false;
                    btnElement.classList.remove('opacity-50');
                }
            }
        }
    </script>
</body>
</html>