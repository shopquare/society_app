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
 * Parses MySQL DATETIME / DATE strings safely across Chrome, Firefox, and Safari.
 */
function parseMySqlDate(dateString) {
    if (!dateString) return null;
    const normalized = String(dateString).trim().replace(' ', 'T');
    const parsed = new Date(normalized);
    return Number.isNaN(parsed.getTime()) ? new Date(dateString) : parsed;
}

function formatDisplayDate(dateString) {
    const dt = parseMySqlDate(dateString);
    if (!dt || Number.isNaN(dt.getTime())) return '-';
    return dt.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatDisplayTime(dateString) {
    const dt = parseMySqlDate(dateString);
    if (!dt || Number.isNaN(dt.getTime())) return '-';
    return dt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
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
