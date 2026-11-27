// /assets/js/app.js

/**
 * Resolves an application path relative to <meta name="app-base"> (empty at root, '/society_app' in subpath).
 */
function appPath(path) {
    const meta = document.querySelector('meta[name="app-base"]');
    const base = meta ? meta.getAttribute('content').replace(/\/+$/, '') : '';
    const clean = path.startsWith('/') ? path : '/' + path;
    return base + clean;
}

/**
 * Escapes HTML special characters to safely interpolate untrusted strings into templates.
 */
function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/**
 * Parses any MySQL DATETIME / DATE string and anchors it to Indian Standard Time (IST, UTC+05:30).
 */
function parseMySqlDate(dateString) {
    if (!dateString) return null;
    let s = String(dateString).trim();
    if (!s) return null;
    if (!s.includes('+') && !s.includes('Z') && !/-\d\d:\d\d$/.test(s)) {
        s = s.replace(' ', 'T') + '+05:30';
    } else {
        s = s.replace(' ', 'T');
    }
    const parsed = new Date(s);
    return Number.isNaN(parsed.getTime()) ? new Date(dateString) : parsed;
}

/**
 * Formats a date string strictly in Indian Standard Time (IST): e.g. "25 Nov 2026"
 */
function formatDisplayDate(dateString) {
    const dt = parseMySqlDate(dateString);
    if (!dt || Number.isNaN(dt.getTime())) return '—';
    return dt.toLocaleDateString('en-IN', {
        timeZone: 'Asia/Kolkata',
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    });
}

/**
 * Formats a time string strictly in Indian Standard Time (IST): e.g. "03:15 PM"
 */
function formatDisplayTime(dateString) {
    const dt = parseMySqlDate(dateString);
    if (!dt || Number.isNaN(dt.getTime())) return '—';
    return dt.toLocaleTimeString('en-IN', {
        timeZone: 'Asia/Kolkata',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    });
}

/**
 * Formats a full datetime string strictly in Indian Standard Time (IST): e.g. "25 Nov, 03:15 PM"
 */
function formatDisplayDateTime(dateString) {
    const dt = parseMySqlDate(dateString);
    if (!dt || Number.isNaN(dt.getTime())) return '—';
    return dt.toLocaleString('en-IN', {
        timeZone: 'Asia/Kolkata',
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    });
}

/**
 * Displays a non-blocking toast notification in the top-right corner.
 */
function showToast(message, type = 'info') {
    let container = document.getElementById('app-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'app-toast-container';
        container.setAttribute('aria-live', 'polite');
        container.className = 'fixed top-4 right-4 z-[220] flex flex-col gap-2 max-w-sm w-full px-4 sm:px-0 pointer-events-none';
        document.body.appendChild(container);
    }

    const colorMap = {
        success: 'bg-emerald-700 text-white border-emerald-800',
        error: 'bg-rose-700 text-white border-rose-800',
        warning: 'bg-amber-600 text-white border-amber-700',
        info: 'bg-slate-900 text-white border-slate-800',
    };
    const styleClass = colorMap[type] || colorMap.info;

    const toast = document.createElement('div');
    toast.setAttribute('role', 'status');
    toast.className = `pointer-events-auto rounded-xl shadow-xl border px-4 py-3 text-sm font-medium flex items-start justify-between gap-3 transition-all duration-300 animate-fade-in ${styleClass}`;

    const textSpan = document.createElement('span');
    textSpan.textContent = message;
    toast.appendChild(textSpan);

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.setAttribute('aria-label', 'Dismiss notification');
    closeBtn.className = 'sg-compact-btn text-white/80 hover:text-white font-bold leading-none p-1 -mr-1 rounded cursor-pointer';
    closeBtn.textContent = '×';
    closeBtn.onclick = () => toast.remove();
    toast.appendChild(closeBtn);

    container.appendChild(toast);
    setTimeout(() => {
        toast.classList.add('opacity-0');
        setTimeout(() => toast.remove(), 300);
    }, 4200);
}

/**
 * Accessible SaaS Confirmation Dialog replacing native browser confirm().
 * Returns Promise<boolean>.
 */
function showConfirmDialog(options) {
    const cfg = typeof options === 'string' ? { message: options } : (options || {});
    const title = cfg.title || 'Confirm Action';
    const message = cfg.message || 'Are you sure you want to proceed?';
    const confirmText = cfg.confirmText || 'Confirm';
    const cancelText = cfg.cancelText || 'Cancel';
    const danger = Boolean(cfg.danger);

    return new Promise((resolve) => {
        const previousFocus = document.activeElement;
        const overlay = document.createElement('div');
        overlay.id = 'sg-confirm-dialog';
        overlay.setAttribute('role', 'alertdialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'sg-confirm-title');
        overlay.setAttribute('aria-describedby', 'sg-confirm-msg');
        overlay.className = 'fixed inset-0 z-[210] bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto';

        const btnColor = danger
            ? 'bg-rose-600 hover:bg-rose-700 text-white'
            : 'bg-brand-900 hover:bg-brand-800 text-white';

        overlay.innerHTML = `
            <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden sg-modal-panel my-auto">
                <div class="p-5 sm:p-6">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 ${danger ? 'bg-rose-50 text-rose-600' : 'bg-brand-50 text-brand-900'}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 id="sg-confirm-title" class="text-base font-bold text-slate-900 font-serif">${escapeHtml(title)}</h3>
                            <p id="sg-confirm-msg" class="text-sm text-slate-600 mt-1.5 leading-relaxed">${escapeHtml(message)}</p>
                        </div>
                    </div>
                </div>
                <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2.5">
                    <button type="button" id="sg-confirm-cancel" class="px-4 py-2 text-xs font-bold text-slate-700 bg-white hover:bg-slate-100 border border-slate-300 rounded-lg transition cursor-pointer">${escapeHtml(cancelText)}</button>
                    <button type="button" id="sg-confirm-ok" class="px-4 py-2 text-xs font-bold rounded-lg shadow-xs transition cursor-pointer ${btnColor}">${escapeHtml(confirmText)}</button>
                </div>
            </div>
        `;

        function cleanup(result) {
            document.removeEventListener('keydown', onKey);
            overlay.remove();
            if (previousFocus && typeof previousFocus.focus === 'function') {
                try { previousFocus.focus(); } catch (_) {}
            }
            resolve(result);
        }

        function onKey(e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                cleanup(false);
            }
        }

        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) cleanup(false);
        });
        overlay.querySelector('#sg-confirm-cancel').onclick = () => cleanup(false);
        overlay.querySelector('#sg-confirm-ok').onclick = () => cleanup(true);
        document.addEventListener('keydown', onKey);
        document.body.appendChild(overlay);
        overlay.querySelector('#sg-confirm-ok').focus();
    });
}

/**
 * Accessible SaaS Input Prompt Dialog replacing native browser prompt().
 * Returns Promise<string|null>.
 */
function showPromptDialog(options) {
    const cfg = typeof options === 'string' ? { title: options } : (options || {});
    const title = cfg.title || 'Provide Details';
    const message = cfg.message || '';
    const placeholder = cfg.placeholder || 'Enter details...';
    const defaultValue = cfg.defaultValue || '';
    const confirmText = cfg.confirmText || 'Submit';
    const cancelText = cfg.cancelText || 'Cancel';
    const required = cfg.required !== false;

    return new Promise((resolve) => {
        const previousFocus = document.activeElement;
        const overlay = document.createElement('div');
        overlay.id = 'sg-prompt-dialog';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'sg-prompt-title');
        overlay.className = 'fixed inset-0 z-[210] bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto';

        overlay.innerHTML = `
            <form id="sg-prompt-form" class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden sg-modal-panel my-auto">
                <div class="p-5 sm:p-6 space-y-3">
                    <h3 id="sg-prompt-title" class="text-base font-bold text-slate-900 font-serif">${escapeHtml(title)}</h3>
                    ${message ? `<p class="text-xs text-slate-600 leading-relaxed">${escapeHtml(message)}</p>` : ''}
                    <div>
                        <label for="sg-prompt-input" class="sr-only">${escapeHtml(title)}</label>
                        <textarea id="sg-prompt-input" rows="3" ${required ? 'required' : ''} placeholder="${escapeHtml(placeholder)}" class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-900/20 focus:border-brand-900">${escapeHtml(defaultValue)}</textarea>
                    </div>
                </div>
                <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2.5">
                    <button type="button" id="sg-prompt-cancel" class="px-4 py-2 text-xs font-bold text-slate-700 bg-white hover:bg-slate-100 border border-slate-300 rounded-lg transition cursor-pointer">${escapeHtml(cancelText)}</button>
                    <button type="submit" id="sg-prompt-ok" class="px-4 py-2 text-xs font-bold bg-brand-900 hover:bg-brand-800 text-white rounded-lg shadow-xs transition cursor-pointer">${escapeHtml(confirmText)}</button>
                </div>
            </form>
        `;

        function cleanup(result) {
            document.removeEventListener('keydown', onKey);
            overlay.remove();
            if (previousFocus && typeof previousFocus.focus === 'function') {
                try { previousFocus.focus(); } catch (_) {}
            }
            resolve(result);
        }

        function onKey(e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                cleanup(null);
            }
        }

        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) cleanup(null);
        });
        overlay.querySelector('#sg-prompt-cancel').onclick = () => cleanup(null);
        overlay.querySelector('#sg-prompt-form').onsubmit = (e) => {
            e.preventDefault();
            const val = overlay.querySelector('#sg-prompt-input').value.trim();
            if (required && !val) {
                overlay.querySelector('#sg-prompt-input').focus();
                return;
            }
            cleanup(val);
        };
        document.addEventListener('keydown', onKey);
        document.body.appendChild(overlay);
        const inp = overlay.querySelector('#sg-prompt-input');
        inp.focus();
    });
}

/**
 * Global Escape-key accessibility handler for modals & popovers across all SecureGate views.
 */
document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    const notifDrawer = document.getElementById('notif-drawer');
    if (notifDrawer && !notifDrawer.classList.contains('hidden')) {
        notifDrawer.classList.add('hidden');
        document.getElementById('notif-bell-btn')?.setAttribute('aria-expanded', 'false');
    }
    const openDialogs = Array.from(document.querySelectorAll('[role="dialog"]:not(.hidden), .fixed.inset-0:not(.hidden)'))
        .filter((el) => el.id && el.id !== 'sg-confirm-dialog' && el.id !== 'sg-prompt-dialog');
    if (openDialogs.length > 0) {
        const topModal = openDialogs[openDialogs.length - 1];
        if (topModal.id === 'visitor-photo-lightbox-modal') {
            closePhotoLightbox();
        } else if (topModal.id === 'resident-mobile-backdrop') {
            if (typeof toggleMobileSidebar === 'function') toggleMobileSidebar();
        } else {
            topModal.classList.add('hidden');
        }
    }
});

/**
 * Opens a fullscreen image lightbox for inspecting visitor photos.
 */
function openPhotoLightbox(url, name, meta) {
    if (!url) return;
    let modal = document.getElementById('visitor-photo-lightbox-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'visitor-photo-lightbox-modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-labelledby', 'lightbox-visitor-name');
        modal.className = 'fixed inset-0 z-50 bg-slate-950/85 backdrop-blur-md flex flex-col justify-center items-center p-4';
        modal.onclick = (e) => { if (e.target === modal) closePhotoLightbox(); };
        modal.innerHTML = `
            <div class="relative max-w-md w-full bg-slate-900 rounded-2xl overflow-hidden shadow-2xl border border-slate-700 p-5 text-center my-auto sg-modal-panel">
                <div class="flex items-center justify-between mb-4">
                    <div class="text-left">
                        <h3 id="lightbox-visitor-name" class="text-lg font-bold text-white font-serif tracking-wide">Visitor Photo</h3>
                        <p id="lightbox-photo-meta" class="text-xs text-amber-400 font-medium">Gate Snapshot</p>
                    </div>
                    <button type="button" onclick="closePhotoLightbox()" aria-label="Close photo preview" class="text-slate-400 hover:text-white p-2 rounded-xl bg-slate-800 hover:bg-slate-700 transition cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="relative max-h-80 sm:max-h-96 flex items-center justify-center bg-slate-950 rounded-xl overflow-hidden border border-slate-800 p-2">
                    <img id="lightbox-img" src="" alt="Visitor Photo" class="max-h-72 sm:max-h-96 w-auto max-w-full object-contain rounded-lg shadow-lg">
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" onclick="closePhotoLightbox()" class="px-5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-lg transition cursor-pointer">Done</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    const img = modal.querySelector('#lightbox-img');
    const nameEl = modal.querySelector('#lightbox-visitor-name');
    const metaEl = modal.querySelector('#lightbox-photo-meta');
    if (img) img.src = url;
    if (nameEl) nameEl.textContent = name || 'Visitor Photo';
    if (metaEl) metaEl.textContent = meta ? `${meta}` : 'Captured at Gate Terminal';
    modal.classList.remove('hidden');
}

function closePhotoLightbox() {
    const modal = document.getElementById('visitor-photo-lightbox-modal');
    if (modal) {
        modal.classList.add('hidden');
        const img = modal.querySelector('#lightbox-img');
        if (img) img.src = '';
    }
}
