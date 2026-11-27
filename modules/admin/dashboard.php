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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="app-base" content="<?php echo $base; ?>">
    <title>Admin Dashboard - SecureGate</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
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

        <!-- Role-Aware Quick Access Modules -->
        <?php
        $quickShortcuts = array_filter([
            ['href' => '/admin/gates', 'icon' => '🛡️', 'title' => 'Gates & Handovers', 'desc' => 'Shift logs & incidents', 'perm' => 'gates.view'],
            ['href' => '/admin/financials', 'icon' => '💳', 'title' => 'Financials', 'desc' => 'Bills, ledger & budgets', 'perm' => 'finance.view'],
            ['href' => '/admin/facilities', 'icon' => '🏊', 'title' => 'Facilities', 'desc' => 'Slots & approvals', 'perm' => 'facilities.manage'],
            ['href' => '/admin/complaints', 'icon' => '🛠️', 'title' => 'Helpdesk', 'desc' => 'Maintenance tickets', 'perm' => 'community.moderate'],
            ['href' => '/admin/moderation', 'icon' => '⚖️', 'title' => 'Moderation & KYC', 'desc' => 'Reports & tenant leases', 'perm' => 'community.moderate'],
            ['href' => '/admin/notifications', 'icon' => '📣', 'title' => 'Broadcasts', 'desc' => 'Multi-channel alerts', 'perm' => 'notifications.broadcast'],
            ['href' => '/admin/documents', 'icon' => '📄', 'title' => 'Digital Office', 'desc' => 'NOC & protected vault', 'perm' => 'documents.manage'],
            ['href' => '/admin/reports', 'icon' => '📊', 'title' => 'Reports & Audit', 'desc' => 'CSV & PDF compliance', 'perm' => 'reports.view'],
        ], fn($sc) => function_exists('hasPermission') && hasPermission($sc['perm']));
        if (!empty($quickShortcuts)):
        ?>
        <section aria-label="Quick module shortcuts" class="mb-8">
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2.5">
                <?php foreach (array_slice($quickShortcuts, 0, 8) as $sc): ?>
                    <a href="<?php echo $base . $sc['href']; ?>" class="sg-kpi-card p-3 flex flex-col justify-between hover:border-brand-300 group transition-all">
                        <span class="text-lg mb-1" aria-hidden="true"><?php echo $sc['icon']; ?></span>
                        <div>
                            <div class="text-xs font-bold text-slate-900 group-hover:text-brand-900 truncate"><?php echo htmlspecialchars($sc['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="text-[11px] text-slate-500 truncate"><?php echo htmlspecialchars($sc['desc'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <div class="mb-6 flex flex-col sm:flex-row sm:justify-between sm:items-end gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 font-serif">Community Approvals</h1>
                <p class="text-sm text-slate-500 mt-1">Review and manage pending resident access requests.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2.5 w-full sm:w-auto">
                <input type="search" id="pending-search" oninput="filterPendingUsers()" placeholder="Search pending..." aria-label="Search pending resident requests" class="flex-1 sm:w-52 px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs focus:outline-none focus:ring-2 focus:border-brand-900">
                <div class="text-sm font-medium text-slate-500 bg-white px-3.5 py-2 rounded-lg border border-slate-200 shadow-2xs shrink-0">
                    Pending: <span id="pending-count" class="text-brand-900 font-bold">0</span>
                </div>
            </div>
        </div>

        <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-8">
            <div class="overflow-x-auto sg-scroll-thin">
                <table class="w-full text-left text-sm whitespace-nowrap">
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

        <!-- System Activity & Immutable Audit Trail -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mt-8">
            <div class="p-4 sm:p-6 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 font-serif flex items-center gap-2">
                        <span>🛡️</span> Society Operations Audit Trail
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Immutable compliance log of vehicle registrations, parcel lockups, gate actions, and security events.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="fetchAuditLogs()" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg transition cursor-pointer">
                        🔄 Refresh Log
                    </button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="bg-slate-50 text-slate-600 font-medium border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-4">Action / Event</th>
                            <th class="px-6 py-4">Actor</th>
                            <th class="px-6 py-4">Target Details</th>
                            <th class="px-6 py-4">IP Address</th>
                            <th class="px-6 py-4">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody id="audit-logs-tbody" class="divide-y divide-slate-100 text-slate-700">
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-slate-500">Loading audit trail...</td>
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
            fetchAuditLogs();
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

        let allPendingUsers = [];

        async function fetchPendingUsers() {
            const tbody = document.getElementById('pending-users-tbody');
            const countDisplay = document.getElementById('pending-count');

            try {
                const response = await fetch(appPath('/api/admin_get_pending.php'));
                const result = await response.json();

                if (response.ok && result.status === 'success') {
                    allPendingUsers = result.data || [];
                    pendingCount = allPendingUsers.length;
                    countDisplay.innerText = pendingCount;
                    filterPendingUsers();
                } else {
                    tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-12 text-center text-red-500 font-medium">${escapeHtml(result.message || 'Failed to load data.')}</td></tr>`;
                }
            } catch (error) {
                tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-12 text-center text-red-500 font-medium">Network error occurred.</td></tr>`;
            }
        }

        function filterPendingUsers() {
            const tbody = document.getElementById('pending-users-tbody');
            const q = (document.getElementById('pending-search')?.value || '').trim().toLowerCase();
            const users = q
                ? allPendingUsers.filter(u => `${u.first_name || ''} ${u.last_name || ''} ${u.email || ''} ${u.unit_number || ''}`.toLowerCase().includes(q))
                : allPendingUsers;

            if (users.length === 0) {
                showEmptyState(tbody, q !== '');
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
                        <button type="button" onclick="updateUserStatus(${uid}, 'active')" class="inline-flex items-center justify-center px-3 sm:px-4 py-2 text-xs font-medium text-white bg-brand-900 hover:bg-brand-800 rounded-lg shadow-sm transition-all active:scale-95 cursor-pointer">
                            Approve
                        </button>
                        <button type="button" onclick="updateUserStatus(${uid}, 'rejected')" class="inline-flex items-center justify-center px-3 sm:px-4 py-2 text-xs font-medium text-red-600 bg-white hover:bg-red-50 border border-red-200 rounded-lg transition-all active:scale-95 cursor-pointer">
                            Reject
                        </button>
                    </td>
                </tr>`;
            }).join('');
        }

        async function updateUserStatus(userId, status) {
            const actionText = status === 'active' ? 'approve' : 'reject';
            const confirmed = await showConfirmDialog({
                title: status === 'active' ? 'Approve Resident Access' : 'Reject Registration',
                message: `Are you sure you want to ${actionText} this resident account?`,
                confirmText: status === 'active' ? 'Approve' : 'Reject',
                danger: status !== 'active'
            });
            if (!confirmed) return;

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
                            allPendingUsers = allPendingUsers.filter(u => Number(u.id) !== Number(userId));
                            pendingCount = allPendingUsers.length;
                            document.getElementById('pending-count').innerText = pendingCount;
                            filterPendingUsers();
                        }, 280);
                    }
                    showToast(result.message || 'Status updated.', 'success');
                } else {
                    showToast(result.message || 'Unable to update status.', 'error');
                }
            } catch (error) {
                showToast('An error occurred while communicating with the server.', 'error');
            }
        }

        async function fetchAuditLogs() {
            const tbody = document.getElementById('audit-logs-tbody');
            if (!tbody) return;

            try {
                const response = await fetch(appPath('/api/admin_get_audit_logs.php?limit=25'));
                const result = await response.json();

                if (response.ok && result.status === 'success') {
                    const logs = result.logs || [];
                    if (logs.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-8 text-center text-slate-400 text-xs">No audit logs recorded yet.</td></tr>`;
                        return;
                    }

                    tbody.innerHTML = logs.map(l => {
                        let badgeColor = 'bg-slate-100 text-slate-700 border-slate-200';
                        if (l.action.includes('REGISTER')) badgeColor = 'bg-blue-50 text-blue-700 border-blue-200';
                        else if (l.action.includes('PARCEL')) badgeColor = 'bg-amber-50 text-amber-800 border-amber-200';
                        else if (l.action.includes('EMERGENCY') || l.action.includes('DENIED')) badgeColor = 'bg-rose-50 text-rose-700 border-rose-200';
                        else if (l.action.includes('APPROVE') || l.action.includes('COLLECTED') || l.action.includes('HIT')) badgeColor = 'bg-emerald-50 text-emerald-700 border-emerald-200';

                        return `
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold border ${badgeColor}">
                                        ${escapeHtml(l.action)}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-bold text-slate-900 text-xs">${escapeHtml(l.user_name || 'System / Guest')}</div>
                                    <div class="text-[11px] text-slate-400 capitalize">${escapeHtml(l.role_name || 'Automated')}</div>
                                </td>
                                <td class="px-6 py-4 text-xs text-slate-600 max-w-xs truncate" title="${escapeHtml(l.details || '')}">
                                    ${escapeHtml(l.details || '-')}
                                </td>
                                <td class="px-6 py-4 text-xs font-mono text-slate-500">
                                    ${escapeHtml(l.ip_address || '127.0.0.1')}
                                </td>
                                <td class="px-6 py-4 text-xs text-slate-500">
                                    ${formatDisplayDateTime(l.created_at)}
                                </td>
                            </tr>
                        `;
                    }).join('');
                } else {
                    tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-8 text-center text-rose-500 text-xs">Failed to load audit logs.</td></tr>`;
                }
            } catch (e) {
                tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-8 text-center text-rose-500 text-xs">Network error loading audit logs.</td></tr>`;
            }
        }

        function showEmptyState(tbody, isSearch = false) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="px-6 py-16 text-center">
                        <div class="mx-auto w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mb-3 border border-slate-100">
                            <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <p class="text-slate-600 font-semibold">${isSearch ? 'No matching requests' : 'All caught up!'}</p>
                        <p class="text-xs text-slate-500 mt-1">${isSearch ? 'Try clearing or adjusting your search query.' : 'There are no pending registrations to review.'}</p>
                    </td>
                </tr>`;
        }
    </script>
</body>
</html>
