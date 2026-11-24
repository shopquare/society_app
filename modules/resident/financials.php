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
    <title>My Dues - Resident Portal</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script>
    <script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans flex flex-col min-h-screen">

    <nav class="bg-brand-900 shadow-md border-b border-brand-800 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between h-16 items-center">
            <div class="flex items-center gap-4">
                <a href="<?php echo $base; ?>/resident/dashboard" aria-label="Back to Resident Dashboard" class="text-white/80 hover:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <span class="font-serif text-xl font-semibold text-white tracking-wide">My Dues</span>
            </div>
            <form action="<?php echo $base; ?>/api/logout.php" method="POST" class="m-0">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" aria-label="Log Out" title="Log Out" class="p-2 text-brand-50 hover:text-white hover:bg-white/10 rounded-lg transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                </button>
            </form>
        </div>
    </nav>

    <main class="flex-grow max-w-5xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8">

        <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-6 border-b border-slate-100">
                <h1 class="text-lg font-bold text-slate-900 font-serif">Billing History</h1>
                <p class="text-sm text-slate-500 mt-1">View and pay your society maintenance bills.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="bg-slate-50 text-slate-600 font-medium border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-4">Bill Title</th>
                            <th class="px-6 py-4">Due Date</th>
                            <th class="px-6 py-4">Amount</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4 text-right">Action</th>
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

    <script>
        const csrfToken = document.getElementById('csrf_token').value;
        document.addEventListener('DOMContentLoaded', fetchMyInvoices);

        async function fetchMyInvoices() {
            const tbody = document.getElementById('resident-invoices');
            try {
                const response = await fetch(appPath('/api/resident_get_invoices.php'));
                const result = await response.json();

                if (response.ok && result.status === 'success') {
                    if (result.data.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-12 text-center text-slate-500">You have no maintenance bills at this time.</td></tr>`;
                        return;
                    }

                    tbody.innerHTML = result.data.map(inv => {
                        let statusBadge = '';
                        let actionBtn = '';

                        if (inv.status === 'Unpaid' || inv.status === 'Overdue') {
                            const isOverdue = inv.status === 'Overdue';
                            statusBadge = `<span class="${isOverdue ? 'bg-red-50 text-red-700 border-red-200' : 'bg-amber-50 text-amber-700 border-amber-200'} px-2.5 py-1 rounded-full text-xs font-semibold border">${escapeHtml(inv.status)}</span>`;
                            actionBtn = `<button type="button" onclick="processPayment(${Number(inv.id)}, this)" class="px-4 py-1.5 text-xs font-medium text-white bg-brand-900 hover:bg-brand-800 rounded shadow-sm transition-all">Pay Now</button>`;
                        } else {
                            statusBadge = `<span class="bg-emerald-50 text-emerald-700 px-2.5 py-1 rounded-full text-xs font-semibold border border-emerald-200">Paid</span>`;
                            actionBtn = `<span class="text-xs text-slate-400 font-medium px-4">Completed</span>`;
                        }

                        return `
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4 font-medium text-slate-800">${escapeHtml(inv.title)}</td>
                                <td class="px-6 py-4 text-slate-500">${escapeHtml(formatDisplayDate(inv.due_date))}</td>
                                <td class="px-6 py-4 font-bold text-slate-900">₹${parseFloat(inv.amount).toFixed(2)}</td>
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

        async function processPayment(invoiceId, btnElement) {
            btnElement.innerText = 'Processing...';
            btnElement.disabled = true;
            btnElement.classList.add('opacity-50');

            try {
                const response = await fetch(appPath('/api/resident_pay_invoice.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ invoice_id: invoiceId, csrf_token: csrfToken })
                });

                const result = await response.json();

                if (response.ok) {
                    showToast(result.message || 'Payment successful!', 'success');
                    fetchMyInvoices();
                } else {
                    showToast(result.message || 'Payment failed.', 'error');
                    btnElement.innerText = 'Pay Now';
                    btnElement.disabled = false;
                    btnElement.classList.remove('opacity-50');
                }
            } catch (error) {
                showToast('Payment error.', 'error');
                btnElement.innerText = 'Pay Now';
                btnElement.disabled = false;
                btnElement.classList.remove('opacity-50');
            }
        }
    </script>
</body>
</html>
