<?php
// /society_app/modules/admin/financials.php

require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['logged_in']) || !hasRole([1, 2])) {
    header('Location: ' . appBasePath() . '/login');
    exit();
}
$csrfToken = generateCsrfToken();
$base = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');

$residents = [];
try {
    $resStmt = $conn->prepare(
        "SELECT u.id, uu.unit_number, p.first_name, p.last_name
         FROM sms_users u
         INNER JOIN sms_user_profiles p ON u.id = p.user_id
         LEFT JOIN sms_user_units uu ON u.id = uu.user_id AND uu.is_current_occupant = 1
         WHERE u.society_id = :society_id AND u.role_id = 3 AND u.status = 'active'
         ORDER BY uu.unit_number ASC"
    );
    $resStmt->execute([':society_id' => (int)$_SESSION['society_id']]);
    $residents = $resStmt->fetchAll();
} catch (PDOException $e) {
    error_log('Admin financials resident load error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="app-base" content="<?php echo $base; ?>">
    <title>Financials - Admin Portal</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script>
    <link rel="stylesheet" href="<?php echo $base; ?>/assets/css/style.css">
    <script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans flex flex-col min-h-screen">

    <?php
    $activeTab = 'financials';
    require_once __DIR__ . '/navbar.php';
    ?>

    <main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8">

        <div class="mb-8">
            <h1 class="text-2xl font-bold text-slate-900 font-serif">Maintenance & Dues</h1>
            <p class="text-sm text-slate-500 mt-1">Issue bills, track payments, and monitor society revenue.</p>
        </div>

        <!-- Financial KPI Summary Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
            <div class="bg-white p-4 sm:p-5 rounded-xl border border-slate-200 shadow-2xs">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Total Invoiced</span>
                <div class="text-2xl font-black text-slate-900 mt-1" id="stat-total-invoiced">₹0.00</div>
                <span class="text-xs text-slate-500 font-medium">Billed to units</span>
            </div>
            <div class="bg-white p-4 sm:p-5 rounded-xl border border-slate-200 shadow-2xs">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Total Collected</span>
                <div class="text-2xl font-black text-emerald-700 mt-1" id="stat-total-collected">₹0.00</div>
                <span class="text-xs text-emerald-600 font-medium">Verified payments</span>
            </div>
            <div class="bg-white p-4 sm:p-5 rounded-xl border border-slate-200 shadow-2xs">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Pending / Overdue</span>
                <div class="text-2xl font-black text-rose-700 mt-1" id="stat-total-pending">₹0.00</div>
                <span class="text-xs text-rose-600 font-medium">Outstanding dues</span>
            </div>
            <div class="bg-white p-4 sm:p-5 rounded-xl border border-slate-200 shadow-2xs">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Collection Efficiency</span>
                <div class="text-2xl font-black text-brand-900 mt-1" id="stat-collection-rate">0%</div>
                <span class="text-xs text-brand-700 font-medium">Recovery percentage</span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 sticky top-24">
                    <h2 class="text-lg font-bold text-slate-900 font-serif mb-4">Issue New Bill</h2>
                    <form id="invoice-form" onsubmit="event.preventDefault(); createInvoice();">
                        <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="space-y-4">
                            <div>
                                <label for="resident_id" class="block text-sm font-medium text-slate-700 mb-1">Select Resident</label>
                                <select id="resident_id" required class="w-full px-4 py-2 bg-white rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:border-brand-900">
                                    <option value="">Choose a unit...</option>
                                    <option value="ALL" class="font-bold text-brand-900 bg-brand-50">✦ All Active Units (Bulk Generate) ✦</option>

                                    <?php foreach ($residents as $res): ?>
                                        <option value="<?php echo (int)$res['id']; ?>">
                                            Unit <?php echo htmlspecialchars((string)($res['unit_number'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars($res['first_name'] . ' ' . $res['last_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label for="title" class="block text-sm font-medium text-slate-700 mb-1">Bill Title</label>
                                <input type="text" id="title" required class="w-full px-4 py-2 bg-white rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:border-brand-900" placeholder="e.g., August 2026 Maintenance">
                            </div>

                            <div>
                                <label for="amount" class="block text-sm font-medium text-slate-700 mb-1">Amount (₹)</label>
                                <input type="number" id="amount" step="0.01" min="0.01" required class="w-full px-4 py-2 bg-white rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:border-brand-900" placeholder="2500.00">
                            </div>

                            <div>
                                <label for="due_date" class="block text-sm font-medium text-slate-700 mb-1">Due Date</label>
                                <input type="date" id="due_date" required class="w-full px-4 py-2 bg-white rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:border-brand-900">
                            </div>

                            <button type="submit" id="btn-submit" class="w-full px-6 py-2.5 text-sm font-medium text-white bg-brand-900 hover:bg-brand-800 rounded-lg shadow-sm transition-all mt-2">
                                Generate Bill
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">

                    <div class="p-4 sm:p-6 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <h2 class="text-lg font-bold text-slate-900 font-serif">Financial Ledger</h2>

                        <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                            <select id="status-filter" onchange="applyFilter()" aria-label="Filter invoices by status" class="px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm text-slate-700 focus:outline-none focus:ring-2 focus:border-brand-900">
                                <option value="All">All Statuses</option>
                                <option value="Paid">Paid</option>
                                <option value="Unpaid">Unpaid</option>
                                <option value="Overdue">Overdue</option>
                            </select>

                            <button type="button" onclick="exportToCSV()" class="px-4 py-2 flex items-center gap-2 text-sm font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-lg transition-all">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                Export CSV
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm whitespace-nowrap">
                            <thead class="bg-slate-50 text-slate-600 font-medium border-b border-slate-200">
                                <tr>
                                    <th class="px-6 py-4">Resident & Unit</th>
                                    <th class="px-6 py-4">Bill Details</th>
                                    <th class="px-6 py-4">Amount</th>
                                    <th class="px-6 py-4">Status</th>
                                </tr>
                            </thead>
                            <tbody id="invoices-tbody" class="divide-y divide-slate-100 text-slate-700">
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center text-slate-500">
                                        <svg class="animate-spin h-6 w-6 mx-auto text-brand-900 mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        Loading financials...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script>
        const csrfToken = document.getElementById('csrf_token').value;
        let allInvoices = [];

        document.addEventListener('DOMContentLoaded', fetchInvoices);

        async function fetchInvoices() {
            const tbody = document.getElementById('invoices-tbody');
            try {
                const response = await fetch(appPath('/api/admin_get_invoices.php'));
                const result = await response.json();

                if (response.ok && result.status === 'success') {
                    allInvoices = result.data;
                    updateFinancialKPIs(allInvoices);
                    applyFilter();
                } else {
                    tbody.innerHTML = `<tr><td colspan="4" class="px-6 py-12 text-center text-red-600 font-bold whitespace-normal">API Error: ${escapeHtml(result.message || 'Unable to load invoices.')}</td></tr>`;
                }
            } catch (error) {
                tbody.innerHTML = `<tr><td colspan="4" class="px-6 py-12 text-center text-red-500 font-bold">Critical error connecting to API.</td></tr>`;
            }
        }

        function updateFinancialKPIs(invoices) {
            let total = 0;
            let collected = 0;
            let pending = 0;

            invoices.forEach(inv => {
                const amt = parseFloat(inv.amount) || 0;
                total += amt;
                if (inv.status === 'Paid') {
                    collected += amt;
                } else {
                    pending += amt;
                }
            });

            const rate = total > 0 ? ((collected / total) * 100).toFixed(1) : 0;

            const statTotal = document.getElementById('stat-total-invoiced');
            const statCollected = document.getElementById('stat-total-collected');
            const statPending = document.getElementById('stat-total-pending');
            const statRate = document.getElementById('stat-collection-rate');

            if (statTotal) statTotal.innerText = `₹${total.toFixed(2)}`;
            if (statCollected) statCollected.innerText = `₹${collected.toFixed(2)}`;
            if (statPending) statPending.innerText = `₹${pending.toFixed(2)}`;
            if (statRate) statRate.innerText = `${rate}%`;
        }

        function applyFilter() {
            const filterValue = document.getElementById('status-filter').value;
            let filteredData = allInvoices;

            if (filterValue !== 'All') {
                filteredData = allInvoices.filter(inv => inv.status === filterValue);
            }
            renderTable(filteredData);
        }

        function renderTable(data) {
            const tbody = document.getElementById('invoices-tbody');

            if (data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="4" class="px-6 py-12 text-center text-slate-500 font-medium">No invoices found.</td></tr>`;
                return;
            }

            tbody.innerHTML = data.map(inv => {
                let statusBadge = '';
                if (inv.status === 'Unpaid') statusBadge = `<span class="bg-amber-50 text-amber-700 px-2.5 py-1 rounded-full text-xs font-semibold border border-amber-200">Unpaid</span>`;
                else if (inv.status === 'Paid') statusBadge = `<span class="bg-emerald-50 text-emerald-700 px-2.5 py-1 rounded-full text-xs font-semibold border border-emerald-200">Paid</span>`;
                else statusBadge = `<span class="bg-red-50 text-red-700 px-2.5 py-1 rounded-full text-xs font-semibold border border-red-200">${escapeHtml(inv.status)}</span>`;

                return `
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="font-semibold text-slate-900">${escapeHtml(inv.first_name)} ${escapeHtml(inv.last_name)}</div>
                            <div class="text-xs text-slate-500 mt-0.5">Unit ${escapeHtml(inv.unit_number || 'N/A')}</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-slate-800">${escapeHtml(inv.title)}</div>
                            <div class="text-xs text-slate-500 mt-0.5">Due: ${escapeHtml(formatDisplayDate(inv.due_date))}</div>
                        </td>
                        <td class="px-6 py-4 font-medium text-slate-900">
                            ₹${parseFloat(inv.amount).toFixed(2)}
                        </td>
                        <td class="px-6 py-4">
                            ${statusBadge}
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function csvSafeCell(val) {
            let str = String(val ?? '');
            if (/^[=+\-@\t\r]/.test(str)) {
                str = "'" + str;
            }
            return `"${str.replace(/"/g, '""')}"`;
        }

        function exportToCSV() {
            const filterValue = document.getElementById('status-filter').value;
            let dataToExport = allInvoices;

            if (filterValue !== 'All') {
                dataToExport = allInvoices.filter(inv => inv.status === filterValue);
            }

            if (dataToExport.length === 0) {
                showToast('No data available to export.', 'warning');
                return;
            }

            let csvContent = 'Resident Name,Unit Number,Bill Title,Amount (INR),Due Date,Status\n';

            dataToExport.forEach(inv => {
                const name = csvSafeCell(`${inv.first_name || ''} ${inv.last_name || ''}`.trim());
                const unit = csvSafeCell(inv.unit_number || 'N/A');
                const title = csvSafeCell(inv.title);
                const amount = parseFloat(inv.amount).toFixed(2);
                const dueDate = csvSafeCell(formatDisplayDate(inv.due_date));
                const status = csvSafeCell(inv.status);

                csvContent += `${name},${unit},${title},${amount},${dueDate},${status}\n`;
            });

            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);

            link.setAttribute('href', url);
            link.setAttribute('download', `Society_Ledger_${new Date().toISOString().slice(0, 10)}.csv`);
            link.style.visibility = 'hidden';

            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }

        async function createInvoice() {
            const btn = document.getElementById('btn-submit');
            btn.innerText = 'Generating...';
            btn.disabled = true;

            const payload = {
                resident_id: document.getElementById('resident_id').value,
                title: document.getElementById('title').value.trim(),
                amount: document.getElementById('amount').value,
                due_date: document.getElementById('due_date').value,
                csrf_token: csrfToken
            };

            try {
                const response = await fetch(appPath('/api/admin_create_invoice.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();

                if (response.ok) {
                    document.getElementById('invoice-form').reset();
                    fetchInvoices();
                    showToast(result.message || 'Bill generated.', 'success');
                } else {
                    showToast(result.message || 'Failed to generate bill.', 'error');
                }
            } catch (error) {
                showToast('An error occurred while generating the bill.', 'error');
            } finally {
                btn.innerText = 'Generate Bill';
                btn.disabled = false;
            }
        }
    </script>
</body>
</html>
