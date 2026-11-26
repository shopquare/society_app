<?php
// includes/notification_widget.php
// Reusable Notification Bell, Popover Feed & Preferences Modal for Admin, Resident & Guard Headers

$notifWidgetCsrf = generateCsrfToken();
?>
<div class="relative inline-block" id="notif-widget-root">
    <button type="button" id="notif-bell-btn" onclick="toggleNotifDrawer()" class="relative p-2 text-slate-300 hover:text-white hover:bg-white/10 rounded-lg transition cursor-pointer" aria-label="Notifications" title="Notifications">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        <span id="notif-unread-badge" class="hidden absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 bg-rose-600 text-white text-[10px] font-black rounded-full flex items-center justify-center shadow">0</span>
    </button>

    <!-- Dropdown Drawer -->
    <div id="notif-drawer" class="hidden absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-2xl shadow-2xl border border-slate-200 z-[120] overflow-hidden text-slate-800">
        <div class="p-3.5 bg-slate-900 text-white flex items-center justify-between">
            <div>
                <h3 class="font-bold text-sm font-serif">Notifications</h3>
                <p class="text-[11px] text-slate-300" id="notif-drawer-subtitle">Live society & security alerts</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="openNotifPreferencesModal()" class="text-[11px] text-slate-300 hover:text-white px-2 py-1 rounded bg-white/10 cursor-pointer" title="Notification Settings">⚙️</button>
                <button type="button" onclick="markAllNotifsRead()" class="text-[11px] text-amber-300 hover:text-amber-200 font-semibold cursor-pointer">Mark all read</button>
            </div>
        </div>
        <div id="notif-feed-list" class="max-h-96 overflow-y-auto divide-y divide-slate-100">
            <div class="p-6 text-center text-xs text-slate-400">Loading notifications...</div>
        </div>
    </div>
</div>

<!-- Notification Preferences Modal -->
<div id="notif-prefs-modal" class="hidden fixed inset-0 z-[150] bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-xl w-full max-h-[85vh] flex flex-col shadow-2xl overflow-hidden text-slate-800">
        <div class="p-4 bg-slate-900 text-white flex items-center justify-between">
            <div>
                <h3 class="font-bold text-base font-serif">Notification Preferences</h3>
                <p class="text-xs text-slate-300">Critical Security & SOS alerts remain active at all times.</p>
            </div>
            <button type="button" onclick="document.getElementById('notif-prefs-modal').classList.add('hidden')" class="p-1.5 text-slate-300 hover:text-white rounded-lg cursor-pointer">✕</button>
        </div>
        <div id="notif-prefs-body" class="p-4 overflow-y-auto space-y-2.5 text-xs">
            <div class="text-center py-6 text-slate-400">Loading preferences...</div>
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
                listEl.innerHTML = '<div class="p-6 text-center text-xs text-slate-400">No notifications yet.</div>';
                return;
            }

            const prioColors = {
                'Critical': 'border-l-4 border-l-rose-600 bg-rose-50/60',
                'High': 'border-l-4 border-l-amber-500 bg-amber-50/40',
                'Normal': 'border-l-4 border-l-blue-500',
                'Low': 'border-l-4 border-l-slate-300'
            };

            listEl.innerHTML = items.map(n => {
                const unreadCls = Number(n.is_read) === 0 ? 'font-semibold bg-slate-50/80' : 'opacity-75';
                const pCls = prioColors[n.priority] || prioColors.Normal;
                return `
                    <div onclick="handleNotifClick(${Number(n.id)}, '${escapeHtml(n.action_url || '')}')" class="p-3.5 hover:bg-slate-100 transition cursor-pointer ${pCls} ${unreadCls}">
                        <div class="flex items-start justify-between gap-2">
                            <span class="text-xs text-slate-900 font-bold">${escapeHtml(n.title)}</span>
                            <span class="text-[10px] text-slate-400 shrink-0">${escapeHtml(formatDisplayDateTime(n.created_at))}</span>
                        </div>
                        <p class="text-[11px] text-slate-600 mt-1 line-clamp-2">${escapeHtml(n.message)}</p>
                    </div>`;
            }).join('');
        } catch (_) {}
    }

    window.toggleNotifDrawer = function () {
        const d = document.getElementById('notif-drawer');
        if (!d) return;
        const opening = d.classList.contains('hidden');
        d.classList.toggle('hidden');
        if (opening) refreshNotifications();
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
        } catch (_) {}
    };

    window.openNotifPreferencesModal = async function () {
        document.getElementById('notif-drawer')?.classList.add('hidden');
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
                <div class="p-3 rounded-xl border border-slate-200 bg-slate-50 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <div class="font-bold text-slate-800">${escapeHtml(p.label)}</div>
                        ${p.locked ? '<span class="text-[10px] text-rose-600 font-semibold">Locked On for Safety</span>' : ''}
                    </div>
                    <div class="flex flex-wrap items-center gap-3 text-[11px]">
                        <label class="flex items-center gap-1 cursor-pointer"><input type="checkbox" ${p.in_app_enabled ? 'checked' : ''} ${p.locked ? 'disabled' : ''} onchange="saveNotifPref('${escapeHtml(p.category)}', this.closest('.p-3'))"> In-App</label>
                        <label class="flex items-center gap-1 cursor-pointer"><input type="checkbox" ${p.push_enabled ? 'checked' : ''} ${p.locked ? 'disabled' : ''} onchange="saveNotifPref('${escapeHtml(p.category)}', this.closest('.p-3'))"> Push</label>
                        <label class="flex items-center gap-1 cursor-pointer"><input type="checkbox" ${p.email_enabled ? 'checked' : ''} onchange="saveNotifPref('${escapeHtml(p.category)}', this.closest('.p-3'))"> Email</label>
                        <label class="flex items-center gap-1 cursor-pointer"><input type="checkbox" ${p.whatsapp_enabled ? 'checked' : ''} onchange="saveNotifPref('${escapeHtml(p.category)}', this.closest('.p-3'))"> WhatsApp</label>
                    </div>
                </div>
            `).join('');
        } catch (err) {
            body.innerHTML = `<div class="text-rose-600 text-center py-4">${escapeHtml(err.message)}</div>`;
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
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        refreshNotifications(true);
        setInterval(() => refreshNotifications(true), 25000);
    });
})();
</script>
