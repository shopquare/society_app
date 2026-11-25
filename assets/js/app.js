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
    // If no timezone offset is provided, anchor to IST (+05:30)
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
        container.className = 'fixed top-4 right-4 z-[100] flex flex-col gap-2 max-w-sm w-full px-4 sm:px-0 pointer-events-none';
        document.body.appendChild(container);
    }

    const colorMap = {
        success: 'bg-emerald-700 text-white border-emerald-800',
        error: 'bg-red-700 text-white border-red-800',
        warning: 'bg-amber-600 text-white border-amber-700',
        info: 'bg-slate-900 text-white border-slate-800',
    };
    const styleClass = colorMap[type] || colorMap.info;

    const toast = document.createElement('div');
    toast.setAttribute('role', 'status');
    toast.className = `pointer-events-auto rounded-lg shadow-lg border px-4 py-3 text-sm font-medium flex items-start justify-between gap-3 transition-all duration-300 ${styleClass}`;

    const textSpan = document.createElement('span');
    textSpan.textContent = message;
    toast.appendChild(textSpan);

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.setAttribute('aria-label', 'Dismiss notification');
    closeBtn.className = 'text-white/80 hover:text-white font-bold leading-none';
    closeBtn.textContent = '×';
    closeBtn.onclick = () => toast.remove();
    toast.appendChild(closeBtn);

    container.appendChild(toast);
    setTimeout(() => {
        toast.classList.add('opacity-0');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

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
        modal.className = 'fixed inset-0 z-50 bg-slate-950/85 backdrop-blur-md flex flex-col justify-center items-center p-4';
        modal.onclick = (e) => { if (e.target === modal) closePhotoLightbox(); };
        modal.innerHTML = `
            <div class="relative max-w-md w-full bg-slate-900 rounded-2xl overflow-hidden shadow-2xl border border-slate-700 p-5 text-center my-auto">
                <div class="flex items-center justify-between mb-4">
                    <div class="text-left">
                        <h3 id="lightbox-visitor-name" class="text-lg font-bold text-white font-serif tracking-wide">Visitor Photo</h3>
                        <p id="lightbox-photo-meta" class="text-xs text-amber-400 font-medium">📸 Gate Snapshot</p>
                    </div>
                    <button type="button" onclick="closePhotoLightbox()" aria-label="Close" class="text-slate-400 hover:text-white p-2 rounded-xl bg-slate-800 hover:bg-slate-700 transition cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="relative max-h-[60vh] flex items-center justify-center bg-slate-950 rounded-xl overflow-hidden border border-slate-800 p-2">
                    <img id="lightbox-img" src="" alt="Visitor Photo" class="max-h-[55vh] w-auto max-w-full object-contain rounded-lg shadow-lg">
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
    if (metaEl) metaEl.textContent = meta ? `📸 ${meta}` : '📸 Captured at Gate Terminal';
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
