<?php
// modules/admin/roles.php
if (!isset($_SESSION['logged_in'])) {
    header('Location: ' . appBasePath() . '/login');
    exit();
}
requirePermission('roles.manage');
$csrfToken = generateCsrfToken();
$base = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="app-base" content="<?php echo $base; ?>">
    <title>Admin Roles & Permissions - SecureGate</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700&display=swap" rel="stylesheet">
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script>
    <link rel="stylesheet" href="<?php echo $base; ?>/assets/css/style.css">
    <script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans flex flex-col min-h-screen">
<?php $activeTab = 'roles'; require_once __DIR__ . '/navbar.php'; ?>

<main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 font-serif">Roles, Permissions & Admin Accounts</h1>
            <p class="text-sm text-slate-500 mt-1">Configure custom society roles with granular permission checks enforced on all backend APIs.</p>
        </div>
        <div class="flex gap-2">
            <button type="button" onclick="openRoleModal()" class="px-4 py-2 bg-brand-900 hover:bg-brand-800 text-white text-xs font-bold rounded-lg shadow cursor-pointer">+ Custom Role</button>
            <button type="button" onclick="document.getElementById('create-admin-modal').classList.remove('hidden')" class="px-4 py-2 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold rounded-lg shadow cursor-pointer">+ Provision Admin</button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <section class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="p-4 border-b border-slate-100 bg-slate-50/70">
                <h2 class="font-bold text-slate-900 font-serif">Configured Roles</h2>
            </div>
            <div id="roles-list" class="divide-y divide-slate-100 p-4 space-y-3 text-sm">Loading roles...</div>
        </section>

        <section class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="p-4 border-b border-slate-100 bg-slate-50/70">
                <h2 class="font-bold text-slate-900 font-serif">Committee & Admin Accounts</h2>
            </div>
            <div id="admins-list" class="divide-y divide-slate-100 p-4 space-y-3 text-sm">Loading admins...</div>
        </section>
    </div>
</main>

<!-- Role Editor Modal -->
<div id="role-modal" role="dialog" aria-modal="true" aria-labelledby="role-modal-title" onclick="if (event.target === this) this.classList.add('hidden')" class="hidden fixed inset-0 z-[130] bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl max-w-3xl w-full max-h-full flex flex-col shadow-2xl overflow-hidden sg-modal-panel my-auto">
        <div class="p-4 bg-slate-900 text-white flex justify-between items-center">
            <h3 class="font-bold font-serif" id="role-modal-title">Create Custom Role</h3>
            <button type="button" onclick="document.getElementById('role-modal').classList.add('hidden')" aria-label="Close role editor modal" class="text-slate-300 hover:text-white p-1.5 rounded-lg hover:bg-white/10 cursor-pointer">✕</button>
        </div>
        <form onsubmit="event.preventDefault(); saveRole();" class="p-5 overflow-y-auto space-y-4 text-xs">
            <input type="hidden" id="edit_role_id" value="0">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="role_name_input" class="block font-bold text-slate-700 mb-1">Role Name *</label>
                    <input type="text" id="role_name_input" required class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                </div>
                <div>
                    <label for="role_desc_input" class="block font-bold text-slate-700 mb-1">Description</label>
                    <input type="text" id="role_desc_input" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                </div>
            </div>
            <div>
                <div class="font-bold text-slate-700 mb-2">Granular Permissions</div>
                <div id="perm-checkbox-grid" class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-72 overflow-y-auto p-3 bg-slate-50 rounded-xl border border-slate-200"></div>
            </div>
            <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('role-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-100 rounded-lg font-bold text-slate-600 cursor-pointer">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-brand-900 text-white rounded-lg font-bold cursor-pointer">Save Role</button>
            </div>
        </form>
    </div>
</div>

<!-- Create Admin Modal -->
<div id="create-admin-modal" role="dialog" aria-modal="true" aria-labelledby="create-admin-title" onclick="if (event.target === this) this.classList.add('hidden')" class="hidden fixed inset-0 z-[130] bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl overflow-hidden sg-modal-panel my-auto">
        <div class="p-4 bg-slate-900 text-white flex justify-between items-center">
            <h3 id="create-admin-title" class="font-bold font-serif">Provision Admin Account</h3>
            <button type="button" onclick="document.getElementById('create-admin-modal').classList.add('hidden')" aria-label="Close provision admin modal" class="text-slate-300 hover:text-white p-1.5 rounded-lg hover:bg-white/10 cursor-pointer">✕</button>
        </div>
        <form onsubmit="event.preventDefault(); createAdminAccount();" class="p-5 space-y-3 text-xs">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <input type="text" id="adm_fname" aria-label="First Name" placeholder="First Name *" required class="px-3 py-2 border border-slate-300 rounded-lg">
                <input type="text" id="adm_lname" aria-label="Last Name" placeholder="Last Name *" required class="px-3 py-2 border border-slate-300 rounded-lg">
            </div>
            <input type="email" id="adm_email" placeholder="Official Email *" required class="w-full px-3 py-2 border border-slate-300 rounded-lg">
            <input type="text" id="adm_phone" placeholder="10-Digit Mobile *" required class="w-full px-3 py-2 border border-slate-300 rounded-lg">
            <select id="adm_role_select" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white"></select>
            <div id="adm-temp-pass-box" class="hidden p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-emerald-900 font-mono"></div>
            <button type="submit" class="w-full py-2.5 bg-emerald-700 text-white font-bold rounded-lg cursor-pointer">Provision Account</button>
        </form>
    </div>
</div>

<script>
const csrf = <?php echo json_encode($csrfToken); ?>;
let rbacData = { permissions: [], roles: [], admins: [] };

document.addEventListener('DOMContentLoaded', loadRbac);

async function loadRbac() {
    const res = await fetch(appPath('/api/admin_manage_roles.php'));
    const json = await res.json();
    if (!res.ok || json.status !== 'success') return showToast(json.message || 'Load failed', 'error');
    rbacData = json;
    renderRoles();
    renderAdmins();
}

function renderRoles() {
    const el = document.getElementById('roles-list');
    const sel = document.getElementById('adm_role_select');
    sel.innerHTML = rbacData.roles.filter(r => r.role_slug !== 'super_admin' && Number(r.is_active) === 1)
        .map(r => `<option value="${Number(r.id)}">${escapeHtml(r.role_name)}</option>`).join('');

    el.innerHTML = rbacData.roles.map(r => `
        <div class="pt-3 first:pt-0 flex items-start justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <span class="font-bold text-slate-900">${escapeHtml(r.role_name)}</span>
                    ${Number(r.is_system) === 1 ? '<span class="px-1.5 py-0.5 text-[10px] bg-slate-100 text-slate-600 rounded font-semibold">System</span>' : '<span class="px-1.5 py-0.5 text-[10px] bg-blue-50 text-blue-700 rounded font-semibold">Custom</span>'}
                    ${Number(r.is_active) === 0 ? '<span class="px-1.5 py-0.5 text-[10px] bg-rose-100 text-rose-700 rounded font-semibold">Disabled</span>' : ''}
                </div>
                <p class="text-xs text-slate-500 mt-0.5">${escapeHtml(r.description || '')}</p>
                <div class="text-[11px] text-slate-400 mt-1">${Number(r.perm_count)} permissions • ${Number(r.user_count)} users assigned</div>
            </div>
            ${r.role_slug !== 'super_admin' ? `
                <div class="flex gap-1.5 shrink-0">
                    <button type="button" onclick="openRoleModal(${Number(r.id)})" class="px-2.5 py-1 text-xs font-bold bg-slate-100 hover:bg-slate-200 rounded cursor-pointer">Edit</button>
                    <button type="button" onclick="toggleRole(${Number(r.id)})" class="px-2.5 py-1 text-xs font-bold ${Number(r.is_active) ? 'bg-amber-50 text-amber-800' : 'bg-emerald-50 text-emerald-800'} rounded cursor-pointer">${Number(r.is_active) ? 'Disable' : 'Enable'}</button>
                </div>` : ''}
        </div>
    `).join('');
}

function renderAdmins() {
    const el = document.getElementById('admins-list');
    const assignable = rbacData.roles.filter(r => r.role_slug !== 'super_admin' && Number(r.is_active) === 1);
    el.innerHTML = rbacData.admins.map(a => `
        <div class="pt-3 first:pt-0 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <div class="font-bold text-slate-900">${escapeHtml(a.first_name + ' ' + a.last_name)}</div>
                <div class="text-xs text-slate-500">${escapeHtml(a.email)} • ${escapeHtml(a.phone_number || '')}</div>
            </div>
            <div>
                ${Number(a.role_id) === 1 ? '<span class="px-2.5 py-1 bg-brand-900 text-white rounded text-xs font-bold">Super Admin</span>' : `
                <select onchange="assignAdminRole(${Number(a.id)}, this.value)" class="px-2.5 py-1.5 border border-slate-300 rounded-lg text-xs bg-white font-semibold">
                    ${assignable.map(r => `<option value="${Number(r.id)}" ${Number(a.admin_role_id) === Number(r.id) ? 'selected' : ''}>${escapeHtml(r.role_name)}</option>`).join('')}
                </select>`}
            </div>
        </div>
    `).join('');
}

function openRoleModal(roleId = 0) {
    const role = rbacData.roles.find(r => Number(r.id) === Number(roleId));
    document.getElementById('edit_role_id').value = role ? role.id : 0;
    document.getElementById('role-modal-title').textContent = role ? `Edit Role: ${role.role_name}` : 'Create Custom Role';
    document.getElementById('role_name_input').value = role ? role.role_name : '';
    document.getElementById('role_desc_input').value = role ? (role.description || '') : '';
    const activePerms = new Set(role ? (role.permissions || []) : []);
    document.getElementById('perm-checkbox-grid').innerHTML = rbacData.permissions.map(p => `
        <label class="flex items-start gap-2 p-2 bg-white rounded-lg border border-slate-200 cursor-pointer hover:border-brand-900">
            <input type="checkbox" name="role_perm" value="${escapeHtml(p.perm_key)}" ${activePerms.has(p.perm_key) ? 'checked' : ''} class="mt-0.5 rounded text-brand-900">
            <div>
                <div class="font-bold text-slate-800">${escapeHtml(p.perm_key)} <span class="text-[10px] text-slate-400 font-normal">(${escapeHtml(p.module_name)})</span></div>
                <div class="text-[10px] text-slate-500">${escapeHtml(p.description)}</div>
            </div>
        </label>
    `).join('');
    document.getElementById('role-modal').classList.remove('hidden');
}

async function saveRole() {
    const id = Number(document.getElementById('edit_role_id').value || 0);
    const perms = Array.from(document.querySelectorAll('input[name="role_perm"]:checked')).map(c => c.value);
    const res = await fetch(appPath('/api/admin_manage_roles.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: id > 0 ? 'update_role' : 'create_role',
            role_id: id,
            role_name: document.getElementById('role_name_input').value,
            description: document.getElementById('role_desc_input').value,
            permissions: perms,
            csrf_token: csrf
        })
    });
    const json = await res.json();
    if (res.ok && json.status === 'success') {
        showToast(json.message, 'success');
        document.getElementById('role-modal').classList.add('hidden');
        loadRbac();
    } else {
        showToast(json.message || 'Save failed', 'error');
    }
}

async function toggleRole(roleId) {
    const res = await fetch(appPath('/api/admin_manage_roles.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'toggle_role', role_id: roleId, csrf_token: csrf })
    });
    const json = await res.json();
    if (res.ok && json.status === 'success') {
        showToast(json.message, 'success');
        loadRbac();
    } else showToast(json.message || 'Action failed', 'error');
}

async function assignAdminRole(userId, adminRoleId) {
    const res = await fetch(appPath('/api/admin_manage_roles.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'assign_user_role', user_id: userId, admin_role_id: adminRoleId, csrf_token: csrf })
    });
    const json = await res.json();
    showToast(json.message || (res.ok ? 'Assigned' : 'Error'), res.ok ? 'success' : 'error');
    if (res.ok) loadRbac();
}

async function createAdminAccount() {
    const res = await fetch(appPath('/api/admin_manage_roles.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'create_admin',
            first_name: document.getElementById('adm_fname').value,
            last_name: document.getElementById('adm_lname').value,
            email: document.getElementById('adm_email').value,
            phone_number: document.getElementById('adm_phone').value,
            admin_role_id: document.getElementById('adm_role_select').value,
            csrf_token: csrf
        })
    });
    const json = await res.json();
    if (res.ok && json.status === 'success') {
        const box = document.getElementById('adm-temp-pass-box');
        box.textContent = 'Temp Password: ' + json.temporary_password;
        box.classList.remove('hidden');
        showToast('Admin provisioned', 'success');
        loadRbac();
    } else {
        showToast(json.message || 'Creation failed', 'error');
    }
}
</script>
</body>
</html>
