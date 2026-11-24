<?php
// /society_app/modules/admin/notices.php

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
    <title>Notice Board - Admin Portal</title>

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
    $activeTab = 'notices';
    require_once __DIR__ . '/navbar.php';
    ?>

    <main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 sticky top-24">
                    <h2 class="text-xl font-bold text-slate-900 font-serif mb-4">Publish Notice</h2>
                    <form id="notice-form" onsubmit="event.preventDefault(); submitNotice();">
                        <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="space-y-4">
                            <div>
                                <label for="title" class="block text-sm font-medium text-slate-700 mb-1">Notice Title</label>
                                <input type="text" id="title" required class="w-full px-4 py-2 bg-white rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:border-brand-900" placeholder="e.g., Scheduled Maintenance">
                            </div>
                            <div>
                                <label for="content" class="block text-sm font-medium text-slate-700 mb-1">Announcement Content</label>
                                <textarea id="content" required rows="5" class="w-full px-4 py-2 bg-white rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:border-brand-900 resize-none" placeholder="Write the details here..."></textarea>
                            </div>
                            <button type="submit" id="btn-publish" class="w-full px-6 py-2.5 text-sm font-medium text-white bg-brand-900 hover:bg-brand-800 rounded-lg shadow-sm transition-all">
                                Broadcast to Residents
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="lg:col-span-2">
                <div class="mb-4">
                    <h2 class="text-xl font-bold text-slate-900 font-serif">Recent Announcements</h2>
                    <p class="text-sm text-slate-500">Notices published to the society board.</p>
                </div>

                <div id="notices-feed" class="space-y-4">
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 text-center text-slate-500">
                        <svg class="animate-spin h-6 w-6 mx-auto text-brand-900 mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Loading announcements...
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', fetchNotices);
        const csrfToken = document.getElementById('csrf_token').value;

        async function fetchNotices() {
            const feed = document.getElementById('notices-feed');
            try {
                const response = await fetch(appPath('/api/get_notices.php'));
                const result = await response.json();

                if (response.ok && result.status === 'success') {
                    const notices = result.data;

                    if (notices.length === 0) {
                        feed.innerHTML = `
                            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 text-center">
                                <p class="text-slate-500 font-medium">No notices published yet.</p>
                            </div>`;
                        return;
                    }

                    feed.innerHTML = notices.map(notice => `
                        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 animate-fade-in">
                            <div class="flex justify-between items-start gap-2 mb-2">
                                <h3 class="text-lg font-bold text-slate-900">${escapeHtml(notice.title)}</h3>
                                <span class="text-xs font-medium text-slate-500 bg-slate-100 px-2.5 py-1 rounded-md shrink-0">
                                    ${escapeHtml(formatDisplayDate(notice.created_at))}
                                </span>
                            </div>
                            <p class="text-sm text-slate-600 whitespace-pre-wrap mb-4">${escapeHtml(notice.content)}</p>
                            <div class="text-xs text-slate-400 font-medium flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                Posted by ${escapeHtml(notice.first_name)} ${escapeHtml(notice.last_name)}
                            </div>
                        </div>
                    `).join('');
                } else {
                    feed.innerHTML = `<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 text-center text-red-500 font-medium">${escapeHtml(result.message || 'Failed to load notices.')}</div>`;
                }
            } catch (error) {
                feed.innerHTML = `<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 text-center text-red-500 font-medium">Network error occurred.</div>`;
            }
        }

        async function submitNotice() {
            const btn = document.getElementById('btn-publish');
            btn.innerText = 'Publishing...';
            btn.disabled = true;

            const payload = {
                title: document.getElementById('title').value.trim(),
                content: document.getElementById('content').value.trim(),
                csrf_token: csrfToken
            };

            try {
                const response = await fetch(appPath('/api/admin_create_notice.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();

                if (response.ok) {
                    document.getElementById('notice-form').reset();
                    fetchNotices();
                    showToast(result.message || 'Notice published.', 'success');
                } else {
                    showToast(result.message || 'Failed to publish notice.', 'error');
                }
            } catch (error) {
                showToast('An error occurred while publishing.', 'error');
            } finally {
                btn.innerText = 'Broadcast to Residents';
                btn.disabled = false;
            }
        }
    </script>
</body>
</html>
