<?php
// /society_app/modules/admin/dashboard.php

if (!isset($_SESSION['logged_in']) || !hasRole([1, 2])) {
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
    <title>Admin Dashboard - Society Portal</title>

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
    $activeTab = 'pending';
    require_once __DIR__ . '/navbar.php';
    ?>

    <main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-6 sm:py-8">

        <!-- Executive Community Overview KPIs -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
            <div class="bg-white p-4 sm:p-5 rounded-xl border border-slate-200 shadow-2xs">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Active Residents</span>
                <div class="text-2xl font-black text-slate-900 mt-1" id="stat-active-residents">0</div>
                <span class="text-xs text-slate-500 font-medium">Flat Occupants</span>
            </div>
            <div class="bg-white p-4 sm:p-5 rounded-xl border border-slate-200 shadow-2xs">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Security Staff</span>
                <div class="text-2xl font-black text-emerald-700 mt-1" id="stat-active-guards">0</div>
                <span class="text-xs text-emerald-600 font-medium">Guards On Duty</span>
            </div>
            <div class="bg-white p-4 sm:p-5 rounded-xl border border-slate-200 shadow-2xs">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Pending Requests</span>
                <div class="text-2xl font-black text-amber-600 mt-1" id="stat-pending-approvals">0</div>
                <span class="text-xs text-amber-600 font-medium">Awaiting Review</span>
            </div>
            <div class="bg-white p-4 sm:p-5 rounded-xl border border-slate-200 shadow-2xs">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Open Complaints</span>
                <div class="text-2xl font-black text-indigo-700 mt-1" id="stat-open-complaints">0</div>
                <span class="text-xs text-indigo-600 font-medium">Active Tickets</span>
            </div>
            <div class="bg-white p-4 sm:p-5 rounded-xl border border-slate-200 shadow-2xs col-span-2 sm:col-span-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Emergency Alerts</span>
                <div class="text-2xl font-black text-rose-600 mt-1" id="stat-recent-emergencies">0</div>
                <span class="text-xs text-rose-600 font-medium">Last 7 Days</span>
            </div>
        </div>

        <div class="mb-6 sm:mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-end gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 font-serif">Community Approvals</h1>
                <p class="text-sm text-slate-500 mt-1">Review and manage pending resident access requests.</p>
            </div>
            <div class="self-start sm:self-auto text-sm font-medium text-slate-500 bg-white px-4 py-2 rounded-lg border border-slate-200 shadow-sm">
                Pending: <span id="pending-count" class="text-brand-900 font-bold">0</span>
            </div>
        </div>

        <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-8">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap min-w-[760px]">
                    <thead class="bg-slate-50 text-slate-600 font-medium border-b border-slate-200">
                        <tr>
                            <th class="px-4 sm:px-6 py-4">Resident Info</th>
                            <th class="px-4 sm:px-6 py-4">Unit Details</th>
                            <th class="px-4 sm:px-6 py-4">Occupancy</th>
                            <th class="px-4 sm:px-6 py-4">Request Date</th>
                            <th class="px-4 sm:px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="pending-users-tbody" class="divide-y divide-slate-100 text-slate-700">
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-slate-500">
                                <svg class="animate-spin h-6 w-6 mx-auto text-brand-900 mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Loading pending requests...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Emergency Incident Security Audit Log -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 sm:p-6 border-b border-slate-100 flex justify-between items-center">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 font-serif flex items-center gap-2">
                        <span>🚨</span> Emergency & Security Incidents
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Audit log of resident emergency siren dispatches and guard responses.</p>
                </div>
                <span class="text-xs bg-slate-100 text-slate-600 px-3 py-1 rounded-full font-semibold border border-slate-200">Security Audit</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="bg-slate-50 text-slate-600 font-medium border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-4">Incident Type</th>
                            <th class="px-6 py-4">Unit & Resident</th>
                            <th class="px-6 py-4">Raised Time</th>
                            <th class="px-6 py-4">Status / Notes</th>
                        </tr>
                    </thead>
                    <tbody id="emergency-logs-tbody" class="divide-y divide-slate-100 text-slate-700">
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-slate-500">Loading incident log...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <script>
        const csrfToken = document.getElementById('csrf_token').value;
        let pendingCount = 0;

        document.addEventListener('DOMContentLoaded', () => {
            fetchPendingUsers();
            fetchAdminStats();
        });

        async function fetchAdminStats() {
            try {
                const response = await fetch(appPath('/api/admin_get_stats.php'));
                const result = await response.json();
                if (response.ok && result.status === 'success') {
                    const stats = result.stats;
                    document.getElementById('stat-active-residents').innerText = stats.active_residents;
                    document.getElementById('stat-active-guards').innerText = stats.active_guards;
                    document.getElementById('stat-pending-approvals').innerText = stats.pending_approvals;
                    document.getElementById('stat-open-complaints').innerText = stats.open_complaints;
                    document.getElementById('stat-recent-emergencies').innerText = stats.recent_emergencies;

                    renderEmergencyLogs(result.emergency_logs || []);
                }
            } catch (e) {
                console.error('Stats load error', e);
            }
        }

        function renderEmergencyLogs(logs) {
            const tbody = document.getElementById('emergency-logs-tbody');
            if (!tbody) return;
            if (logs.length === 0) {
                tbody.innerHTML = `<tr><td colspan="4" class="px-6 py-8 text-center text-slate-400">No emergency incidents recorded in the society.</td></tr>`;
                return;
            }

            tbody.innerHTML = logs.map(log => {
                const isCrit = log.alert_type === 'Security Threat' || log.alert_type === 'Fire Alert';
                const badgeColor = isCrit ? 'bg-red-50 text-red-700 border-red-200' : 'bg-rose-50 text-rose-700 border-rose-200';
                const statusBadge = log.status === 'Active' 
                    ? '<span class="bg-rose-600 text-white font-bold text-xs px-2.5 py-0.5 rounded-full animate-pulse">ACTIVE NOW</span>'
                    : '<span class="bg-emerald-50 text-emerald-700 font-semibold text-xs px-2.5 py-0.5 rounded-full border border-emerald-200">Acknowledged by Guard</span>';

                return `
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-4 font-bold text-slate-800">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-bold border ${badgeColor}">
                                <span>🚨</span> ${escapeHtml(log.alert_type)}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-bold text-slate-900">Unit ${escapeHtml(log.unit_number)}</div>
                            <div class="text-xs text-slate-500">${escapeHtml(log.resident_name)} • ${escapeHtml(log.phone_number || '')}</div>
                        </td>
                        <td class="px-6 py-4 text-xs text-slate-500 font-mono">
                            ${escapeHtml(formatDisplayDate(log.created_at))}
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                ${statusBadge}
                                ${log.notes ? `<span class="text-xs text-slate-400 italic">"${escapeHtml(log.notes)}"</span>` : ''}
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        async function fetchPendingUsers() {
            const tbody = document.getElementById('pending-users-tbody');
            const countDisplay = document.getElementById('pending-count');

            try {
                const response = await fetch(appPath('/api/admin_get_pending.php'));
                const result = await response.json();

                if (response.ok && result.status === 'success') {
                    const users = result.data;
                    pendingCount = users.length;
                    countDisplay.innerText = pendingCount;

                    if (pendingCount === 0) {
                        showEmptyState(tbody);
                        return;
                    }

                    tbody.innerHTML = users.map(user => {
                        const uid = Number(user.id);
                        return `
                        <tr class="hover:bg-slate-50 transition-colors duration-200" id="row-${uid}">
                            <td class="px-4 sm:px-6 py-4">
                                <div class="font-semibold text-slate-900">${escapeHtml(user.first_name)} ${escapeHtml(user.last_name)}</div>
                                <div class="text-xs text-slate-500 mt-0.5">${escapeHtml(user.email)} <br class="sm:hidden" /><span class="hidden sm:inline">•</span> ${escapeHtml(user.phone_number)}</div>
                            </td>
                            <td class="px-4 sm:px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                    Unit ${escapeHtml(user.unit_number || 'N/A')}
                                </span>
                            </td>
                            <td class="px-4 sm:px-6 py-4 capitalize">
                                ${user.occupancy_type === 'tenant'
                                    ? `<span class="text-amber-700 font-medium text-xs bg-amber-50 px-2.5 py-1 rounded-md border border-amber-200">Tenant</span>`
                                    : `<span class="text-emerald-700 font-medium text-xs bg-emerald-50 px-2.5 py-1 rounded-md border border-emerald-200">Owner</span>`}
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-xs font-medium text-slate-500">
                                ${escapeHtml(formatDisplayDate(user.created_at))}
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-right space-x-1 sm:space-x-2">
                                <button type="button" onclick="updateUserStatus(${uid}, 'active')" class="inline-flex items-center justify-center px-3 sm:px-4 py-2 text-xs font-medium text-white bg-brand-900 hover:bg-brand-800 rounded-lg shadow-sm transition-all active:scale-95 mb-2 sm:mb-0">
                                    Approve
                                </button>
                                <button type="button" onclick="updateUserStatus(${uid}, 'rejected')" class="inline-flex items-center justify-center px-3 sm:px-4 py-2 text-xs font-medium text-red-600 bg-white hover:bg-red-50 border border-red-200 rounded-lg transition-all active:scale-95">
                                    Reject
                                </button>
                            </td>
                        </tr>`;
                    }).join('');
                } else {
                    tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-12 text-center text-red-500 font-medium">${escapeHtml(result.message || 'Failed to load data.')}</td></tr>`;
                }
            } catch (error) {
                tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-12 text-center text-red-500 font-medium">Network error occurred.</td></tr>`;
            }
        }

        async function updateUserStatus(userId, status) {
            const actionText = status === 'active' ? 'approve' : 'reject';
            if (!confirm(`Are you sure you want to ${actionText} this user?`)) return;

            try {
                const response = await fetch(appPath('/api/admin_update_status.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        user_id: userId,
                        status: status,
                        csrf_token: csrfToken
                    })
                });

                const result = await response.json();

                if (response.ok) {
                    const row = document.getElementById(`row-${userId}`);
                    if (row) {
                        row.classList.add('fade-out');
                        setTimeout(() => {
                            row.remove();
                            pendingCount = Math.max(0, pendingCount - 1);
                            document.getElementById('pending-count').innerText = pendingCount;
                            if (pendingCount === 0) showEmptyState(document.getElementById('pending-users-tbody'));
                        }, 300);
                    }
                    showToast(result.message || 'Status updated.', 'success');
                } else {
                    showToast(result.message || 'Unable to update status.', 'error');
                }
            } catch (error) {
                showToast('An error occurred while communicating with the server.', 'error');
            }
        }

        function showEmptyState(tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="px-6 py-16 text-center">
                        <div class="mx-auto w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mb-3 border border-slate-100">
                            <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <p class="text-slate-500 font-medium">All caught up!</p>
                        <p class="text-xs text-slate-400 mt-1">There are no pending registrations to review.</p>
                    </td>
                </tr>`;
        }
    </script>
</body>
</html>
