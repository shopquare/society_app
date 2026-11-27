<?php
// includes/notification_widget.php
// Reusable Notification Bell, Popover Feed & Preferences Modal for Admin, Resident & Guard Headers

$notifWidgetCsrf = generateCsrfToken();
?>
<div class="relative inline-block" id="notif-widget-root">
    <button type="button" id="notif-bell-btn" onclick="toggleNotifDrawer()" aria-expanded="false" aria-controls="notif-drawer" aria-haspopup="true" class="relative p-2 text-slate-200 hover:text-white hover:bg-white/10 rounded-lg transition cursor-pointer" aria-label="Notifications" title="Notifications">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        <span id="notif-unread-badge" class="hidden absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 bg-rose-600 text-white text-[10px] font-black rounded-full flex items-center justify-center shadow ring-2 ring-slate-900">0</span>
    </button>

    <!-- Dropdown Drawer (mobile-safe full-width inset, right-aligned popover on sm+) -->
    <div id="notif-drawer" role="region" aria-label="Notifications feed" class="hidden fixed sm:absolute inset-x-3 sm:inset-x-auto sm:right-0 top-[4.25rem] sm:top-full sm:mt-2 sm:w-96 bg-white rounded-2xl shadow-2xl border border-slate-200 z-[120] overflow-hidden text-slate-800 sg-modal-panel">
        <div class="p-3.5 bg-slate-900 text-white flex items-center justify-between gap-2">
            <div class="min-w-0">
                <h3 class="font-bold text-sm font-serif">Notifications</h3>
                <p class="text-[11px] text-slate-300 truncate" id="notif-drawer-subtitle">Live society &amp; security alerts</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button type="button" onclick="openNotifPreferencesModal()" aria-label="Notification preferences" class="sg-compact-btn text-xs text-slate-200 hover:text-white px-2.5 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 cursor-pointer transition" title="Notification Settings">⚙️</button>
                <button type="button" onclick="markAllNotifsRead()" class="sg-compact-btn text-xs text-amber-300 hover:text-amber-200 font-semibold px-2 py-1 rounded hover:bg-white/10 cursor-pointer transition">Mark all read</button>
            </div>
        </div>
        <div id="notif-feed-list" class="max-h-80 sm:max-h-96 overflow-y-auto divide-y divide-slate-100 sg-scroll-thin">
            <div class="p-6 text-center text-xs text-slate-500">Loading notifications...</div>
        </div>
    </div>
</div>

<!-- Notification Preferences Modal -->
<div id="notif-prefs-modal" role="dialog" aria-modal="true" aria-labelledby="notif-prefs-title" onclick="if (event.target === this) closeNotifPreferencesModal()" class="hidden fixed inset-0 z-[150] bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl max-w-xl w-full max-h-full flex flex-col shadow-2xl overflow-hidden text-slate-800 sg-modal-panel my-auto">
        <div class="p-4 sm:px-5 bg-slate-900 text-white flex items-center justify-between gap-3">
            <div>
                <h3 id="notif-prefs-title" class="font-bold text-base font-serif">Notification Preferences</h3>
                <p class="text-xs text-slate-300">Critical Security &amp; SOS alerts remain active at all times.</p>
            </div>
            <button type="button" onclick="closeNotifPreferencesModal()" aria-label="Close preferences modal" class="p-2 text-slate-300 hover:text-white hover:bg-white/10 rounded-lg cursor-pointer transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div id="notif-prefs-body" class="p-4 sm:p-5 overflow-y-auto space-y-2.5 text-xs sg-scroll-thin">
            <div class="text-center py-6 text-slate-500">Loading preferences...</div>
        </div>
    </div>
</div>

<script>
(function () {
    const widgetCsrf = <?php echo json_encode($notifWidgetCsrf); ?>;

    async function refreshNotifications(silent = false) {
        try {
            const res = await fetch(appPath('/api/notifications.php?limit=25'));
            const json = await res.json();
            if (!res.ok || json.status !== 'success') return;

            const badge = document.getElementById('notif-unread-badge');
            const count = Number(json.unread_count || 0);
            if (badge) {
                badge.textContent = count > 99 ? '99+' : String(count);
                badge.classList.toggle('hidden', count <= 0);
            }

            const listEl = document.getElementById('notif-feed-list');
            if (!listEl) return;
            const items = json.notifications || [];
            if (items.length === 0) {
                listEl.innerHTML = '<div class="p-8 text-center text-xs text-slate-500">No notifications yet. You\'re all caught up!</div>';
                return;
            }

            const prioColors = {
                'Critical': 'border-l-4 border-l-rose-600 bg-rose-50/60',
                'High': 'border-l-4 border-l-amber-500 bg-amber-50/40',
                'Normal': 'border-l-4 border-l-blue-500',
                'Low': 'border-l-4 border-l-slate-300'
            };

            listEl.innerHTML = items.map(n => {
                const unreadCls = Number(n.is_read) === 0 ? 'font-semibold bg-slate-50/80' : 'opacity-80';
                const pCls = prioColors[n.priority] || prioColors.Normal;
                return `
                    <button type="button" onclick="handleNotifClick(${Number(n.id)}, '${escapeHtml(n.action_url || '')}')" class="w-full text-left p-3.5 hover:bg-slate-100 transition cursor-pointer block ${pCls} ${unreadCls}">
                        <div class="flex items-start justify-between gap-2">
                            <span class="text-xs text-slate-900 font-bold">${escapeHtml(n.title)}</span>
                            <span class="text-[11px] text-slate-500 shrink-0">${escapeHtml(formatDisplayDateTime(n.created_at))}</span>
                        </div>
                        <p class="text-xs text-slate-600 mt-1 line-clamp-2">${escapeHtml(n.message)}</p>
                    </button>`;
            }).join('');
        } catch (_) {}
    }

    window.toggleNotifDrawer = function () {
        const d = document.getElementById('notif-drawer');
        const btn = document.getElementById('notif-bell-btn');
        if (!d) return;
        const opening = d.classList.contains('hidden');
        d.classList.toggle('hidden');
        btn?.setAttribute('aria-expanded', String(opening));
        if (opening) refreshNotifications();
    };

    window.closeNotifPreferencesModal = function () {
        document.getElementById('notif-prefs-modal')?.classList.add('hidden');
    };

    window.handleNotifClick = async function (id, actionUrl) {
        try {
            await fetch(appPath('/api/notifications.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'mark_read', id: id, csrf_token: widgetCsrf })
            });
            refreshNotifications(true);
            if (actionUrl) {
                window.location.href = appPath(actionUrl);
            }
        } catch (_) {}
    };

    window.markAllNotifsRead = async function () {
        try {
            await fetch(appPath('/api/notifications.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'mark_all_read', csrf_token: widgetCsrf })
            });
            refreshNotifications();
            showToast('All notifications marked as read.', 'success');
        } catch (_) {}
    };

    window.openNotifPreferencesModal = async function () {
        document.getElementById('notif-drawer')?.classList.add('hidden');
        document.getElementById('notif-bell-btn')?.setAttribute('aria-expanded', 'false');
        const modal = document.getElementById('notif-prefs-modal');
        const body = document.getElementById('notif-prefs-body');
        if (!modal || !body) return;
        modal.classList.remove('hidden');

        try {
            const res = await fetch(appPath('/api/notifications.php?action=preferences'));
            const json = await res.json();
            if (!res.ok || json.status !== 'success') throw new Error(json.message || 'Failed');
            const prefs = json.preferences || [];
            body.innerHTML = prefs.map(p => `
                <div class="p-3.5 rounded-xl border border-slate-200 bg-slate-50 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                    <div>
                        <div class="font-bold text-slate-800 text-xs">${escapeHtml(p.label)}</div>
                        ${p.locked ? '<span class="text-[11px] text-rose-700 font-semibold">Locked On for Safety</span>' : ''}
                    </div>
                    <div class="flex flex-wrap items-center gap-3 text-xs">
                        <label class="flex items-center gap-1.5 cursor-pointer"><input type="checkbox" class="rounded border-slate-300" ${p.in_app_enabled ? 'checked' : ''} ${p.locked ? 'disabled' : ''} onchange="saveNotifPref('${escapeHtml(p.category)}', this.closest('.rounded-xl'))"> In-App</label>
                        <label class="flex items-center gap-1.5 cursor-pointer"><input type="checkbox" class="rounded border-slate-300" ${p.push_enabled ? 'checked' : ''} ${p.locked ? 'disabled' : ''} onchange="saveNotifPref('${escapeHtml(p.category)}', this.closest('.rounded-xl'))"> Push</label>
                        <label class="flex items-center gap-1.5 cursor-pointer"><input type="checkbox" class="rounded border-slate-300" ${p.email_enabled ? 'checked' : ''} onchange="saveNotifPref('${escapeHtml(p.category)}', this.closest('.rounded-xl'))"> Email</label>
                        <label class="flex items-center gap-1.5 cursor-pointer"><input type="checkbox" class="rounded border-slate-300" ${p.whatsapp_enabled ? 'checked' : ''} onchange="saveNotifPref('${escapeHtml(p.category)}', this.closest('.rounded-xl'))"> WhatsApp</label>
                    </div>
                </div>
            `).join('');
        } catch (err) {
            body.innerHTML = `<div class="text-rose-700 text-center py-4 font-medium">${escapeHtml(err.message)}</div>`;
        }
    };

    window.saveNotifPref = async function (category, rowEl) {
        const boxes = rowEl.querySelectorAll('input[type="checkbox"]');
        try {
            await fetch(appPath('/api/notifications.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'save_preference',
                    category: category,
                    in_app_enabled: boxes[0]?.checked ? 1 : 0,
                    push_enabled: boxes[1]?.checked ? 1 : 0,
                    email_enabled: boxes[2]?.checked ? 1 : 0,
                    whatsapp_enabled: boxes[3]?.checked ? 1 : 0,
                    sms_enabled: 0,
                    csrf_token: widgetCsrf
                })
            });
            showToast('Preference saved', 'success');
        } catch (_) {}
    };

    document.addEventListener('click', function (e) {
        const root = document.getElementById('notif-widget-root');
        const drawer = document.getElementById('notif-drawer');
        if (root && drawer && !root.contains(e.target)) {
            drawer.classList.add('hidden');
            document.getElementById('notif-bell-btn')?.setAttribute('aria-expanded', 'false');
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        refreshNotifications(true);
        setInterval(() => refreshNotifications(true), 25000);
    });
})();
</script>
