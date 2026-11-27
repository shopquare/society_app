<?php
// /society_app/modules/admin/users.php

if (!isset($_SESSION['logged_in']) || !hasRole([1, 2])) {
    header('Location: ' . appBasePath() . '/login');
    exit();
}
$base = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="app-base" content="<?php echo $base; ?>">
    <title>Resident Directory - Admin Portal</title>

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
    $activeTab = 'users';
    require_once __DIR__ . '/navbar.php';
    ?>

    <main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8">

        <div class="mb-8 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 font-serif">Processed Users</h1>
                <p class="text-sm text-slate-500 mt-1">A complete list of active residents and rejected applications.</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                <input type="search" id="user-search" oninput="filterUsers()" placeholder="Search name, email, or unit..." aria-label="Search residents" class="px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:border-brand-900">
                <select id="user-status-filter" onchange="filterUsers()" aria-label="Filter by status" class="px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:border-brand-900">
                    <option value="all">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="bg-slate-50 text-slate-600 font-medium border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-4">Resident Info</th>
                            <th class="px-6 py-4">Unit Details</th>
                            <th class="px-6 py-4">Occupancy</th>
                            <th class="px-6 py-4">Status</th>
                        </tr>
                    </thead>
                    <tbody id="users-tbody" class="divide-y divide-slate-100 text-slate-700">
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-slate-500">
                                <svg class="animate-spin h-6 w-6 mx-auto text-brand-900 mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Loading directory...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        let allUsers = [];
        document.addEventListener('DOMContentLoaded', fetchUsers);

        async function fetchUsers() {
            const tbody = document.getElementById('users-tbody');

            try {
                const response = await fetch(appPath('/api/admin_get_users.php'));
                const result = await response.json();

                if (response.ok && result.status === 'success') {
                    allUsers = result.data || [];
                    filterUsers();
                } else {
                    tbody.innerHTML = `<tr><td colspan="4" class="px-6 py-12 text-center text-red-500 font-medium">${escapeHtml(result.message || 'Failed to load data.')}</td></tr>`;
                }
            } catch (error) {
                tbody.innerHTML = `<tr><td colspan="4" class="px-6 py-12 text-center text-red-500 font-medium">Network error occurred.</td></tr>`;
            }
        }

        function filterUsers() {
            const query = (document.getElementById('user-search').value || '').trim().toLowerCase();
            const statusFilter = document.getElementById('user-status-filter').value;

            const filtered = allUsers.filter(user => {
                if (statusFilter !== 'all' && user.status !== statusFilter) return false;
                if (!query) return true;
                const haystack = `${user.first_name || ''} ${user.last_name || ''} ${user.email || ''} ${user.unit_number || ''}`.toLowerCase();
                return haystack.includes(query);
            });

            renderUsers(filtered);
        }

        function renderUsers(users) {
            const tbody = document.getElementById('users-tbody');

            if (users.length === 0) {
                tbody.innerHTML = `<tr><td colspan="4" class="px-6 py-12 text-center text-slate-500 font-medium">No users match your filter.</td></tr>`;
                return;
            }

            tbody.innerHTML = users.map(user => `
                <tr class="hover:bg-slate-50 transition-colors duration-200">
                    <td class="px-6 py-4">
                        <div class="font-semibold text-slate-900">${escapeHtml(user.first_name)} ${escapeHtml(user.last_name)}</div>
                        <div class="text-xs text-slate-500 mt-0.5">${escapeHtml(user.email)} • ${escapeHtml(user.phone_number)}</div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                            Unit ${escapeHtml(user.unit_number || 'N/A')}
                        </span>
                    </td>
                    <td class="px-6 py-4 capitalize">
                        <span class="text-slate-600 font-medium text-xs bg-slate-100 px-2.5 py-1 rounded-md border border-slate-200">${escapeHtml(user.occupancy_type || 'Resident')}</span>
                    </td>
                    <td class="px-6 py-4">
                        ${user.status === 'active'
                            ? `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                <div class="w-1.5 h-1.5 rounded-full bg-emerald-600"></div> Active
                               </span>`
                            : `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800 border border-red-200">
                                <div class="w-1.5 h-1.5 rounded-full bg-red-600"></div> ${escapeHtml(user.status)}
                               </span>`}
                    </td>
                </tr>
            `).join('');
        }
    </script>
</body>
</html>
