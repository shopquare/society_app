<?php
// /society_app/modules/admin/complaints.php

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
    <title>Helpdesk - Admin Portal</title>

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
    $activeTab = 'complaints';
    require_once __DIR__ . '/navbar.php';
    ?>

    <main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 overflow-x-hidden">

        <div class="mb-8">
            <h1 class="text-2xl font-bold text-slate-900 font-serif">Resident Helpdesk</h1>
            <p class="text-sm text-slate-500 mt-1">Manage, track, and resolve community maintenance tickets.</p>
        </div>

        <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

        <div id="complaints-error" class="hidden mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"></div>

        <div class="flex flex-col lg:grid lg:grid-cols-3 gap-6">

            <div class="bg-slate-100 rounded-xl p-4 min-h-[500px]">
                <div class="flex items-center justify-between mb-4 px-2">
                    <h2 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Open</h2>
                    <span id="count-open" class="bg-slate-200 text-slate-600 text-xs font-bold px-2 py-1 rounded-md">0</span>
                </div>
                <div id="col-open" class="space-y-4"></div>
            </div>

            <div class="bg-brand-50 rounded-xl p-4 min-h-[500px] border border-brand-100">
                <div class="flex items-center justify-between mb-4 px-2">
                    <h2 class="text-sm font-bold text-brand-900 uppercase tracking-wider">In Progress</h2>
                    <span id="count-inprogress" class="bg-brand-200 text-brand-900 text-xs font-bold px-2 py-1 rounded-md">0</span>
                </div>
                <div id="col-inprogress" class="space-y-4"></div>
            </div>

            <div class="bg-emerald-50 rounded-xl p-4 min-h-[500px] border border-emerald-100">
                <div class="flex items-center justify-between mb-4 px-2">
                    <h2 class="text-sm font-bold text-emerald-900 uppercase tracking-wider">Resolved</h2>
                    <span id="count-resolved" class="bg-emerald-200 text-emerald-900 text-xs font-bold px-2 py-1 rounded-md">0</span>
                </div>
                <div id="col-resolved" class="space-y-4"></div>
            </div>

        </div>
    </main>

    <script>
        const csrfToken = document.getElementById('csrf_token').value;
        document.addEventListener('DOMContentLoaded', fetchComplaints);

        async function fetchComplaints() {
            const errBox = document.getElementById('complaints-error');
            errBox.classList.add('hidden');

            try {
                const response = await fetch(appPath('/api/admin_get_complaints.php'));
                const result = await response.json();

                if (response.ok && result.status === 'success') {
                    renderKanban(result.data || []);
                } else {
                    errBox.textContent = result.message || 'Unable to load complaints.';
                    errBox.classList.remove('hidden');
                }
            } catch (error) {
                errBox.textContent = 'Network error fetching complaints.';
                errBox.classList.remove('hidden');
            }
        }

        function renderKanban(complaints) {
            const colOpen = document.getElementById('col-open');
            const colInProgress = document.getElementById('col-inprogress');
            const colResolved = document.getElementById('col-resolved');

            colOpen.innerHTML = '';
            colInProgress.innerHTML = '';
            colResolved.innerHTML = '';

            const counts = { 'Open': 0, 'In Progress': 0, 'Resolved': 0 };

            complaints.forEach(ticket => {
                const status = counts.hasOwnProperty(ticket.status) ? ticket.status : 'Open';
                counts[status]++;

                const initial = ticket.first_name ? ticket.first_name.charAt(0).toUpperCase() : '?';
                const card = `
                    <div class="bg-white p-4 rounded-lg shadow-sm border border-slate-200 animate-fade-in hover:shadow-md transition-shadow">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs font-semibold px-2 py-1 rounded bg-slate-100 text-slate-600 border border-slate-200">
                                ${escapeHtml(ticket.category)}
                            </span>
                            <span class="text-xs text-slate-400 font-medium">
                                #${Number(ticket.id)}
                            </span>
                        </div>
                        <p class="text-sm text-slate-800 mb-3 whitespace-pre-wrap break-words">${escapeHtml(ticket.description)}</p>

                        <div class="flex items-center gap-2 mb-4 pt-3 border-t border-slate-100">
                            <div class="w-6 h-6 rounded-full bg-slate-200 flex items-center justify-center text-xs font-bold text-slate-500">
                                ${escapeHtml(initial)}
                            </div>
                            <div class="text-xs text-slate-500">
                                <span class="font-semibold text-slate-700">${escapeHtml(ticket.first_name)} ${escapeHtml(ticket.last_name)}</span><br>
                                ${escapeHtml(formatDisplayDate(ticket.created_at))}
                            </div>
                        </div>

                        ${getActionButtons(Number(ticket.id), status)}
                    </div>
                `;

                if (status === 'Open') colOpen.innerHTML += card;
                else if (status === 'In Progress') colInProgress.innerHTML += card;
                else colResolved.innerHTML += card;
            });

            document.getElementById('count-open').innerText = counts['Open'];
            document.getElementById('count-inprogress').innerText = counts['In Progress'];
            document.getElementById('count-resolved').innerText = counts['Resolved'];

            if (counts['Open'] === 0) colOpen.innerHTML = `<div class="text-center text-sm text-slate-400 py-6">No open tickets</div>`;
            if (counts['In Progress'] === 0) colInProgress.innerHTML = `<div class="text-center text-sm text-brand-400 py-6">Nothing in progress</div>`;
            if (counts['Resolved'] === 0) colResolved.innerHTML = `<div class="text-center text-sm text-emerald-400 py-6">No resolved tickets</div>`;
        }

        function getActionButtons(id, status) {
            if (status === 'Open') {
                return `<button type="button" onclick="updateStatus(${id}, 'In Progress')" class="w-full py-1.5 text-xs font-medium text-brand-700 bg-brand-50 hover:bg-brand-100 border border-brand-200 rounded transition-colors">Start Work</button>`;
            }
            if (status === 'In Progress') {
                return `<button type="button" onclick="updateStatus(${id}, 'Resolved')" class="w-full py-1.5 text-xs font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded transition-colors">Mark as Resolved</button>`;
            }
            return `<button type="button" onclick="updateStatus(${id}, 'Open')" class="w-full py-1.5 text-xs font-medium text-slate-500 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded transition-colors">Reopen Ticket</button>`;
        }

        async function updateStatus(complaintId, newStatus) {
            try {
                const response = await fetch(appPath('/api/admin_update_complaint.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        complaint_id: complaintId,
                        status: newStatus,
                        csrf_token: csrfToken
                    })
                });

                const result = await response.json();
                if (response.ok) {
                    fetchComplaints();
                    showToast(result.message || 'Ticket updated.', 'success');
                } else {
                    showToast(result.message || 'Unable to update ticket.', 'error');
                }
            } catch (error) {
                showToast('An error occurred while updating the ticket.', 'error');
            }
        }
    </script>
</body>
</html>
