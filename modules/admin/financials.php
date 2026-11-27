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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
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

        <div class="mb-6 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 font-serif">Financial Management &amp; Accounting</h1>
                <p class="text-sm text-slate-500 mt-1">Auto-billing rules, late penalties, partial/advance payments, income/expenses, budgets &amp; PDF reports.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" onclick="triggerFinV2('run_auto_billing')" class="px-3.5 py-2 text-xs font-semibold text-white bg-brand-900 hover:bg-brand-800 rounded-lg shadow-2xs transition cursor-pointer">⚡ Run Monthly Auto-Billing</button>
                <button type="button" onclick="triggerFinV2('apply_late_penalties')" class="px-3.5 py-2 text-xs font-semibold text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-200 rounded-lg transition cursor-pointer">⏱ Apply Late Penalties</button>
                <button type="button" onclick="triggerFinV2('send_whatsapp_reminders')" class="px-3.5 py-2 text-xs font-semibold text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-lg transition cursor-pointer">📲 WhatsApp Reminders</button>
                <a href="<?php echo $base; ?>/api/admin_financial_v2.php?export=dues_csv" class="px-3.5 py-2 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-100 border border-slate-300 rounded-lg transition">⬇ Dues CSV</a>
                <a href="<?php echo $base; ?>/api/admin_financial_v2.php?export=income_expense_pdf" target="_blank" class="px-3.5 py-2 text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 rounded-lg transition">📄 Income/Expense PDF</a>
                <a href="<?php echo $base; ?>/admin/vendors" class="px-3.5 py-2 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition">🏗 Vendors &amp; Payouts</a>
            </div>
        </div>

        <!-- Financial KPI Summary Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <div class="bg-white p-4 sm:p-5 rounded-xl border border-slate-200 shadow-2xs">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Total Invoiced</span>
                <div class="text-2xl font-black text-slate-900 mt-1" id="stat-total-invoiced">₹0.00</div>
                <span class="text-xs text-slate-500 font-medium">Includes penalties</span>
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

        <!-- Financial Module Tabs -->
        <div class="flex flex-wrap gap-2 border-b border-slate-200 pb-3 mb-6">
            <button type="button" onclick="switchFinTab('invoices')" id="tab-btn-invoices" class="px-4 py-2 text-sm font-semibold rounded-lg bg-brand-900 text-white cursor-pointer">Invoices &amp; Quick Bill</button>
            <button type="button" onclick="switchFinTab('rules')" id="tab-btn-rules" class="px-4 py-2 text-sm font-semibold rounded-lg bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 cursor-pointer">Auto-Billing &amp; Penalty Rules</button>
            <button type="button" onclick="switchFinTab('payments')" id="tab-btn-payments" class="px-4 py-2 text-sm font-semibold rounded-lg bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 cursor-pointer">Payments &amp; Unit Dues</button>
            <button type="button" onclick="switchFinTab('accounting')" id="tab-btn-accounting" class="px-4 py-2 text-sm font-semibold rounded-lg bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 cursor-pointer">Income, Expenses &amp; Budgets</button>
        </div>

        <div id="fin-tab-invoices" class="grid grid-cols-1 lg:grid-cols-3 gap-8">

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
                                <option value="Partially_Paid">Partially Paid</option>
                                <option value="Unpaid">Unpaid</option>
                                <option value="Overdue">Overdue</option>
                                <option value="Refunded">Refunded</option>
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

        <!-- TAB 2: Auto-Billing & Penalty Rules -->
        <div id="fin-tab-rules" class="hidden grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-xl border border-slate-200 p-6">
                <h2 class="text-lg font-bold text-slate-900 font-serif mb-4">Configure Maintenance Rule</h2>
                <form onsubmit="event.preventDefault(); saveMaintenanceRule();" class="space-y-3 text-sm">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Rule Name</label>
                        <input type="text" id="rule-name" required placeholder="e.g., Monthly 3BHK Maintenance" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Flat Type (Optional)</label>
                            <select id="rule-flat-type" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
                                <option value="">All / Default</option>
                                <option value="1BHK">1BHK</option>
                                <option value="2BHK">2BHK</option>
                                <option value="3BHK">3BHK</option>
                                <option value="Penthouse">Penthouse</option>
                                <option value="Shop">Shop</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Base Amount (₹)</label>
                            <input type="number" id="rule-amount" step="0.01" min="1" required class="w-full px-3 py-2 border border-slate-300 rounded-lg" placeholder="2500">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Due Days</label>
                            <input type="number" id="rule-due-days" min="1" value="10" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Grace Period (Days)</label>
                            <input type="number" id="rule-grace" min="0" value="5" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Penalty Type</label>
                            <select id="rule-pen-type" class="w-full px-2 py-2 border border-slate-300 rounded-lg bg-white">
                                <option value="Fixed">Fixed ₹</option>
                                <option value="Percentage">%</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Frequency</label>
                            <select id="rule-pen-freq" class="w-full px-2 py-2 border border-slate-300 rounded-lg bg-white">
                                <option value="One_Time">One Time</option>
                                <option value="Monthly">Monthly</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Value</label>
                            <input type="number" id="rule-pen-val" min="0" step="0.01" value="250" class="w-full px-2 py-2 border border-slate-300 rounded-lg">
                        </div>
                    </div>
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Maximum Penalty Cap (₹)</label>
                        <input type="number" id="rule-max-pen" min="0" step="0.01" value="1500" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    </div>
                    <button type="submit" class="w-full py-2.5 bg-brand-900 hover:bg-brand-800 text-white font-semibold rounded-lg cursor-pointer">Save Billing Rule</button>
                </form>
            </div>
            <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 p-6">
                <h2 class="text-lg font-bold text-slate-900 font-serif mb-4">Active Billing &amp; Late Fee Policies</h2>
                <div id="rules-list" class="space-y-3 text-sm text-slate-600">Loading rules...</div>
            </div>
        </div>

        <!-- TAB 3: Record Payments, Refunds & Unit Dues -->
        <div id="fin-tab-payments" class="hidden space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-white rounded-xl border border-slate-200 p-6">
                    <h2 class="text-lg font-bold text-slate-900 font-serif mb-4">Record Payment / Advance / Refund</h2>
                    <form onsubmit="event.preventDefault(); recordAdminPayment();" class="space-y-3 text-sm">
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Resident</label>
                            <select id="pay-resident-id" required class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
                                <option value="">Choose resident...</option>
                                <?php foreach ($residents as $res): ?>
                                    <option value="<?php echo (int)$res['id']; ?>">Unit <?php echo htmlspecialchars((string)($res['unit_number'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars($res['first_name'] . ' ' . $res['last_name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 mb-1">Invoice ID (Blank = Advance)</label>
                            <input type="number" id="pay-invoice-id" placeholder="Optional Invoice #" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-medium text-slate-700 mb-1">Amount (₹)</label>
                                <input type="number" id="pay-amount" step="0.01" min="0.01" required class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block font-medium text-slate-700 mb-1">Method</label>
                                <select id="pay-method" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
                                    <option value="UPI">UPI</option>
                                    <option value="Bank_Transfer">Bank Transfer</option>
                                    <option value="Cheque">Cheque</option>
                                    <option value="Cash">Cash</option>
                                    <option value="Gateway">Gateway</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex gap-4 pt-1">
                            <label class="inline-flex items-center gap-1.5"><input type="checkbox" id="pay-is-advance"> Advance Credit</label>
                            <label class="inline-flex items-center gap-1.5 text-rose-700"><input type="checkbox" id="pay-is-refund"> Issue Refund</label>
                        </div>
                        <input type="text" id="pay-notes" placeholder="Transaction Ref / Notes" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                        <button type="submit" class="w-full py-2.5 bg-emerald-700 hover:bg-emerald-800 text-white font-semibold rounded-lg cursor-pointer">Reconcile Transaction</button>
                    </form>
                </div>
                <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 p-6">
                    <h2 class="text-lg font-bold text-slate-900 font-serif mb-4">Outstanding Unit-Wise Balances</h2>
                    <div id="unit-dues-list" class="overflow-x-auto text-sm text-slate-600 mb-6">Loading dues...</div>
                    <h3 class="font-bold text-slate-900 mb-3">Recent Reconciled Payments</h3>
                    <div id="payments-history-list" class="overflow-x-auto text-sm text-slate-600">Loading payments...</div>
                </div>
            </div>
        </div>

        <!-- TAB 4: Society Income, Expenses & Budgets -->
        <div id="fin-tab-accounting" class="hidden space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-white rounded-xl border border-slate-200 p-5">
                    <h3 class="font-bold text-slate-900 mb-3">Record Society Income</h3>
                    <form onsubmit="event.preventDefault(); submitAccounting('add_income');" class="space-y-3 text-sm">
                        <input type="text" id="inc-category" value="Facility Rentals" required class="w-full px-3 py-2 border border-slate-300 rounded-lg" placeholder="Category">
                        <input type="text" id="inc-title" required class="w-full px-3 py-2 border border-slate-300 rounded-lg" placeholder="Description (e.g., Clubhouse Booking)">
                        <div class="grid grid-cols-2 gap-2">
                            <input type="number" id="inc-amount" step="0.01" min="1" required class="w-full px-3 py-2 border border-slate-300 rounded-lg" placeholder="Amount ₹">
                            <input type="date" id="inc-date" value="<?php echo date('Y-m-d'); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                        </div>
                        <button type="submit" class="w-full py-2 bg-emerald-700 text-white font-semibold rounded-lg cursor-pointer">+ Add Income</button>
                    </form>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-5">
                    <h3 class="font-bold text-slate-900 mb-3">Record Society Expense</h3>
                    <form onsubmit="event.preventDefault(); submitAccounting('add_expense');" class="space-y-3 text-sm">
                        <select id="exp-category" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
                            <option value="Security Salaries">Security Salaries</option>
                            <option value="Housekeeping">Housekeeping</option>
                            <option value="Electricity & Water">Electricity &amp; Water</option>
                            <option value="Lift AMC">Lift AMC</option>
                            <option value="Repairs & Maintenance">Repairs &amp; Maintenance</option>
                            <option value="Event Expenses">Event Expenses</option>
                        </select>
                        <input type="text" id="exp-title" required class="w-full px-3 py-2 border border-slate-300 rounded-lg" placeholder="Expense Title">
                        <div class="grid grid-cols-2 gap-2">
                            <input type="number" id="exp-amount" step="0.01" min="1" required class="w-full px-3 py-2 border border-slate-300 rounded-lg" placeholder="Amount ₹">
                            <input type="date" id="exp-date" value="<?php echo date('Y-m-d'); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                        </div>
                        <select id="exp-vendor-id" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white">
                            <option value="">Optional Linked Vendor</option>
                        </select>
                        <button type="submit" class="w-full py-2 bg-rose-700 text-white font-semibold rounded-lg cursor-pointer">+ Record Expense</button>
                    </form>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-5">
                    <h3 class="font-bold text-slate-900 mb-3">Set Category Budget</h3>
                    <form onsubmit="event.preventDefault(); submitAccounting('save_budget');" class="space-y-3 text-sm">
                        <input type="text" id="bud-fy" value="2026-2027" required class="w-full px-3 py-2 border border-slate-300 rounded-lg" placeholder="Fiscal Year">
                        <input type="text" id="bud-category" value="Repairs & Maintenance" required class="w-full px-3 py-2 border border-slate-300 rounded-lg" placeholder="Category">
                        <div class="grid grid-cols-2 gap-2">
                            <input type="number" id="bud-monthly" min="0" step="0.01" required class="w-full px-3 py-2 border border-slate-300 rounded-lg" placeholder="Monthly Limit ₹">
                            <input type="number" id="bud-annual" min="0" step="0.01" required class="w-full px-3 py-2 border border-slate-300 rounded-lg" placeholder="Annual Limit ₹">
                        </div>
                        <button type="submit" class="w-full py-2 bg-brand-900 text-white font-semibold rounded-lg cursor-pointer">Save Budget Allocation</button>
                    </form>
                </div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-xl border border-slate-200 p-5">
                    <h3 class="font-bold text-slate-900 mb-3">Annual Budget vs. Actual Spend</h3>
                    <div id="budgets-list" class="space-y-3 text-sm">Loading budgets...</div>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-5">
                    <h3 class="font-bold text-slate-900 mb-3">Recent Income &amp; Expense Entries</h3>
                    <div id="accounting-entries" class="space-y-2 text-sm max-h-80 overflow-y-auto">Loading...</div>
                </div>
            </div>
        </div>
    </main>

    <script>
        const csrfToken = document.getElementById('csrf_token').value;
        let allInvoices = [];
        let advLoaded = false;
        let accLoaded = false;

        document.addEventListener('DOMContentLoaded', () => {
            fetchInvoices();
            loadAdvancedFinance();
        });

        function switchFinTab(tab) {
            ['invoices', 'rules', 'payments', 'accounting'].forEach(t => {
                document.getElementById('fin-tab-' + t).classList.toggle('hidden', t !== tab);
                const btn = document.getElementById('tab-btn-' + t);
                btn.className = t === tab
                    ? 'px-4 py-2 text-sm font-semibold rounded-lg bg-brand-900 text-white cursor-pointer'
                    : 'px-4 py-2 text-sm font-semibold rounded-lg bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 cursor-pointer';
            });
            if ((tab === 'rules' || tab === 'payments') && !advLoaded) loadAdvancedFinance();
            if (tab === 'accounting' && !accLoaded) loadAccounting();
        }

        async function loadAdvancedFinance() {
            try {
                const res = await fetch(appPath('/api/admin_financial_v2.php'));
                const r = await res.json();
                if (!res.ok || r.status !== 'success') return;
                advLoaded = true;
                const fd = r.data || r;
                const k = fd.kpis || {};
                if (k.total_billed !== undefined) {
                    document.getElementById('stat-total-invoiced').innerText = `₹${parseFloat(k.total_billed || 0).toFixed(2)}`;
                    document.getElementById('stat-total-collected').innerText = `₹${parseFloat(k.total_collected || 0).toFixed(2)}`;
                    document.getElementById('stat-total-pending').innerText = `₹${parseFloat(k.total_outstanding || 0).toFixed(2)}`;
                    document.getElementById('stat-collection-rate').innerText = `${k.collection_pct || 0}%`;
                }
                const rules = fd.rules || [];
                document.getElementById('rules-list').innerHTML = rules.length ? rules.map(rl => `
                    <div class="p-3 border border-slate-200 rounded-lg flex justify-between items-center bg-slate-50">
                        <div>
                            <div class="font-bold text-slate-900">${escapeHtml(rl.rule_name)} <span class="text-xs px-2 py-0.5 rounded bg-indigo-50 text-indigo-700">${escapeHtml(rl.flat_type || 'All Units')}</span></div>
                            <div class="text-xs text-slate-500 mt-0.5">Base: ₹${parseFloat(rl.base_amount).toFixed(2)} • Due ${rl.due_days}d • Grace ${rl.grace_days}d • Late Fee: ${escapeHtml(rl.penalty_type)} (${rl.penalty_value}, ${escapeHtml(rl.penalty_frequency)}, Max ₹${rl.max_penalty})</div>
                        </div>
                        <span class="text-xs font-semibold px-2 py-1 rounded ${Number(rl.is_active) ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-200 text-slate-600'}">${Number(rl.is_active) ? 'Active' : 'Inactive'}</span>
                    </div>
                `).join('') : '<p class="text-slate-400">No billing rules configured yet.</p>';

                const dues = fd.unit_dues || [];
                document.getElementById('unit-dues-list').innerHTML = dues.length ? `
                    <table class="w-full text-left border border-slate-200 rounded-lg overflow-hidden">
                        <thead class="bg-slate-50"><tr><th class="px-3 py-2">Unit</th><th class="px-3 py-2">Resident</th><th class="px-3 py-2">Open Bills</th><th class="px-3 py-2 text-right">Outstanding</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">${dues.map(d => `<tr><td class="px-3 py-2 font-semibold">${escapeHtml(d.unit_number || 'N/A')}</td><td class="px-3 py-2">${escapeHtml(d.resident_name)}</td><td class="px-3 py-2">${d.open_invoices}</td><td class="px-3 py-2 text-right font-bold text-rose-700">₹${parseFloat(d.outstanding_balance).toFixed(2)}</td></tr>`).join('')}</tbody>
                    </table>` : '<p class="text-emerald-700 font-medium">All unit dues cleared!</p>';

                const pays = fd.payments || [];
                document.getElementById('payments-history-list').innerHTML = pays.length ? `
                    <table class="w-full text-left border border-slate-200 rounded-lg overflow-hidden">
                        <thead class="bg-slate-50"><tr><th class="px-3 py-2">Receipt</th><th class="px-3 py-2">Resident</th><th class="px-3 py-2">Method</th><th class="px-3 py-2 text-right">Amount</th><th class="px-3 py-2 text-right">PDF</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">${pays.map(p => `<tr><td class="px-3 py-2 font-mono text-xs">${escapeHtml(p.receipt_number)}</td><td class="px-3 py-2">${escapeHtml(p.resident_name)}</td><td class="px-3 py-2">${escapeHtml(p.payment_method)} ${Number(p.is_refund) ? '<span class="text-rose-600 font-bold">(Refund)</span>' : (Number(p.is_advance) ? '<span class="text-indigo-600">(Adv)</span>' : '')}</td><td class="px-3 py-2 text-right font-bold">₹${parseFloat(p.amount).toFixed(2)}</td><td class="px-3 py-2 text-right"><a target="_blank" href="${appPath('/api/resident_payments.php?action=receipt_pdf&payment_id=' + p.id)}" class="text-xs text-indigo-700 font-semibold hover:underline">PDF</a></td></tr>`).join('')}</tbody>
                    </table>` : '<p class="text-slate-400">No reconciled payment records yet.</p>';
            } catch (e) {}
        }

        async function saveMaintenanceRule() {
            const res = await fetch(appPath('/api/admin_financial_v2.php'), {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    csrf_token: csrfToken, action: 'save_rule',
                    rule_name: document.getElementById('rule-name').value.trim(),
                    flat_type: document.getElementById('rule-flat-type').value,
                    base_amount: document.getElementById('rule-amount').value,
                    due_days: document.getElementById('rule-due-days').value,
                    grace_days: document.getElementById('rule-grace').value,
                    penalty_type: document.getElementById('rule-pen-type').value,
                    penalty_frequency: document.getElementById('rule-pen-freq').value,
                    penalty_value: document.getElementById('rule-pen-val').value,
                    max_penalty: document.getElementById('rule-max-pen').value
                })
            });
            const r = await res.json();
            showToast(r.message || 'Done', res.ok ? 'success' : 'error');
            if (res.ok) loadAdvancedFinance();
        }

        async function triggerFinV2(action) {
            const res = await fetch(appPath('/api/admin_financial_v2.php'), {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ csrf_token: csrfToken, action })
            });
            const r = await res.json();
            showToast(r.message || 'Action completed', res.ok ? 'success' : 'error');
            if (res.ok) { fetchInvoices(); loadAdvancedFinance(); }
        }

        async function recordAdminPayment() {
            const res = await fetch(appPath('/api/admin_financial_v2.php'), {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    csrf_token: csrfToken, action: 'record_payment',
                    resident_id: document.getElementById('pay-resident-id').value,
                    invoice_id: document.getElementById('pay-invoice-id').value || null,
                    amount: document.getElementById('pay-amount').value,
                    payment_method: document.getElementById('pay-method').value,
                    is_advance: document.getElementById('pay-is-advance').checked,
                    is_refund: document.getElementById('pay-is-refund').checked,
                    notes: document.getElementById('pay-notes').value
                })
            });
            const r = await res.json();
            showToast(r.message || 'Updated', res.ok ? 'success' : 'error');
            if (res.ok) { fetchInvoices(); loadAdvancedFinance(); }
        }

        async function loadAccounting() {
            const res = await fetch(appPath('/api/admin_accounting.php'));
            const r = await res.json();
            if (!res.ok || r.status !== 'success') return;
            accLoaded = true;
            const ad = r.data || r;
            const vSel = document.getElementById('exp-vendor-id');
            if (vSel) {
                vSel.innerHTML = '<option value="">Optional Linked Vendor</option>' + (ad.vendors || []).map(v => `<option value="${v.id}">${escapeHtml(v.vendor_name)} (${escapeHtml(v.service_category)})</option>`).join('');
            }
            const buds = ad.budgets || [];
            document.getElementById('budgets-list').innerHTML = buds.length ? buds.map(b => {
                const lim = parseFloat(b.annual_limit) || 1;
                const spent = parseFloat(b.actual_spent) || 0;
                const pct = Math.min(100, Math.round((spent / lim) * 100));
                return `<div><div class="flex justify-between font-medium"><span>${escapeHtml(b.category)} (${escapeHtml(b.fiscal_year)})</span><span>₹${spent.toFixed(0)} / ₹${lim.toFixed(0)} (${pct}%)</span></div><div class="w-full h-2 bg-slate-100 rounded-full mt-1 overflow-hidden"><div class="h-full ${pct > 90 ? 'bg-rose-600' : 'bg-brand-900'}" style="width:${pct}%"></div></div></div>`;
            }).join('') : '<p class="text-slate-400">No budgets set.</p>';

            const inc = (ad.income || []).map(x => ({ type: 'IN', title: x.title, cat: x.category, amt: x.amount, dt: x.income_date }));
            const exp = (ad.expenses || []).map(x => ({ type: 'OUT', title: x.title, cat: x.category, amt: x.amount, dt: x.expense_date }));
            const merged = [...inc, ...exp].sort((a, b) => (b.dt || '').localeCompare(a.dt || ''));
            document.getElementById('accounting-entries').innerHTML = merged.length ? merged.map(m => `
                <div class="flex justify-between items-center p-2 border border-slate-100 rounded-lg">
                    <div><span class="font-semibold text-slate-800">${escapeHtml(m.title)}</span> <span class="text-xs text-slate-400">(${escapeHtml(m.cat)} • ${escapeHtml(m.dt)})</span></div>
                    <span class="font-bold ${m.type === 'IN' ? 'text-emerald-700' : 'text-rose-700'}">${m.type === 'IN' ? '+' : '-'}₹${parseFloat(m.amt).toFixed(2)}</span>
                </div>`).join('') : '<p class="text-slate-400">No accounting entries recorded.</p>';
        }

        async function submitAccounting(action) {
            let payload = { csrf_token: csrfToken, action };
            if (action === 'add_income') {
                payload = { ...payload, category: document.getElementById('inc-category').value, title: document.getElementById('inc-title').value, amount: document.getElementById('inc-amount').value, income_date: document.getElementById('inc-date').value };
            } else if (action === 'add_expense') {
                payload = { ...payload, category: document.getElementById('exp-category').value, title: document.getElementById('exp-title').value, amount: document.getElementById('exp-amount').value, expense_date: document.getElementById('exp-date').value, vendor_id: document.getElementById('exp-vendor-id').value || null };
            } else if (action === 'save_budget') {
                payload = { ...payload, fiscal_year: document.getElementById('bud-fy').value, category: document.getElementById('bud-category').value, monthly_limit: document.getElementById('bud-monthly').value, annual_limit: document.getElementById('bud-annual').value };
            }
            const res = await fetch(appPath('/api/admin_accounting.php'), { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) });
            const r = await res.json();
            showToast(r.message || 'Saved', res.ok ? 'success' : 'error');
            if (res.ok) loadAccounting();
        }

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
