<?php
// modules/admin/buildings.php
if (!isset($_SESSION['logged_in'])) {
    header('Location: ' . appBasePath() . '/login');
    exit();
}
requirePermission('buildings.manage');
$csrfToken = generateCsrfToken();
$base = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="app-base" content="<?php echo $base; ?>">
    <title>Buildings & Flats - SecureGate</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700&display=swap" rel="stylesheet">
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script>
    <script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans flex flex-col min-h-screen">
<?php $activeTab = 'buildings'; require_once __DIR__ . '/navbar.php'; ?>

<main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 font-serif">Buildings, Towers & Flats Registry</h1>
        <p class="text-sm text-slate-500 mt-1">Manage towers and flat configurations (flat types power automated billing & targeted notices).</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="space-y-6">
            <form onsubmit="event.preventDefault(); saveBuilding();" class="bg-white rounded-xl border border-slate-200 p-5 shadow-xs space-y-3 text-xs">
                <h2 class="font-bold text-base font-serif text-slate-900">Add Building / Tower</h2>
                <input type="text" id="b_name" required placeholder="Building Name (e.g., Tower D)" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                <div class="grid grid-cols-2 gap-3">
                    <input type="text" id="b_code" required placeholder="Code (e.g., D)" class="px-3 py-2 border border-slate-300 rounded-lg">
                    <input type="number" id="b_floors" value="10" min="1" required placeholder="Floors" class="px-3 py-2 border border-slate-300 rounded-lg">
                </div>
                <button type="submit" class="w-full py-2 bg-brand-900 hover:bg-brand-800 text-white font-bold rounded-lg cursor-pointer">Save Building</button>
            </form>

            <form onsubmit="event.preventDefault(); saveUnit();" class="bg-white rounded-xl border border-slate-200 p-5 shadow-xs space-y-3 text-xs">
                <h2 class="font-bold text-base font-serif text-slate-900">Add Flat / Unit</h2>
                <select id="u_building" required class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white"></select>
                <div class="grid grid-cols-2 gap-3">
                    <input type="text" id="u_number" required placeholder="Unit No (e.g., D-402)" class="px-3 py-2 border border-slate-300 rounded-lg">
                    <input type="number" id="u_floor" value="1" min="0" required placeholder="Floor" class="px-3 py-2 border border-slate-300 rounded-lg">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <select id="u_type" class="px-3 py-2 border border-slate-300 rounded-lg bg-white">
                        <option>1BHK</option><option selected>2BHK</option><option>3BHK</option><option>4BHK</option><option>Penthouse</option><option>Studio</option><option>Villa</option><option>Commercial</option>
                    </select>
                    <input type="number" id="u_sqft" value="1200" min="100" required placeholder="Area SqFt" class="px-3 py-2 border border-slate-300 rounded-lg">
                </div>
                <button type="submit" class="w-full py-2 bg-emerald-700 hover:bg-emerald-800 text-white font-bold rounded-lg cursor-pointer">Save Flat / Unit</button>
            </form>
        </div>

        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="p-4 border-b border-slate-100 font-bold font-serif text-slate-900">Towers & Blocks</div>
                <div id="buildings-grid" class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-4 text-xs"></div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="p-4 border-b border-slate-100 font-bold font-serif text-slate-900">Registered Flats</div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 border-b border-slate-200 text-slate-600">
                            <tr><th class="px-4 py-3">Unit</th><th class="px-4 py-3">Tower</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Area</th><th class="px-4 py-3">Status</th></tr>
                        </thead>
                        <tbody id="units-tbody" class="divide-y divide-slate-100"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
const csrf = <?php echo json_encode($csrfToken); ?>;
document.addEventListener('DOMContentLoaded', loadBuildings);

async function loadBuildings() {
    const res = await fetch(appPath('/api/admin_manage_buildings.php'));
    const json = await res.json();
    if (!res.ok || json.status !== 'success') return;
    const bs = json.buildings || [];
    const us = json.units || [];
    document.getElementById('u_building').innerHTML = bs.map(b => `<option value="${Number(b.id)}">${escapeHtml(b.building_name)} (${escapeHtml(b.building_code)})</option>`).join('');
    document.getElementById('buildings-grid').innerHTML = bs.map(b => `
        <div class="p-3 bg-slate-50 rounded-lg border border-slate-200">
            <div class="font-bold text-slate-900">${escapeHtml(b.building_name)}</div>
            <div class="text-slate-500 mt-0.5">Code: ${escapeHtml(b.building_code)} • Floors: ${Number(b.total_floors)}</div>
            <div class="text-brand-900 font-bold mt-1">${Number(b.unit_count)} Flats</div>
        </div>
    `).join('');
    document.getElementById('units-tbody').innerHTML = us.map(u => `
        <tr>
            <td class="px-4 py-2.5 font-bold text-slate-900">${escapeHtml(u.unit_number)}</td>
            <td class="px-4 py-2.5">${escapeHtml(u.building_name)} (Flr ${Number(u.floor_number)})</td>
            <td class="px-4 py-2.5"><span class="px-2 py-0.5 bg-blue-50 text-blue-800 rounded font-bold">${escapeHtml(u.flat_type)}</span></td>
            <td class="px-4 py-2.5">${Number(u.area_sqft)} sq.ft</td>
            <td class="px-4 py-2.5">${escapeHtml(u.status)}</td>
        </tr>
    `).join('');
}

async function saveBuilding() {
    const res = await fetch(appPath('/api/admin_manage_buildings.php'), {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'save_building', building_name: document.getElementById('b_name').value, building_code: document.getElementById('b_code').value, total_floors: document.getElementById('b_floors').value, csrf_token: csrf })
    });
    const j = await res.json();
    showToast(j.message || 'Done', res.ok ? 'success' : 'error');
    if (res.ok) loadBuildings();
}

async function saveUnit() {
    const res = await fetch(appPath('/api/admin_manage_buildings.php'), {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'save_unit', building_id: document.getElementById('u_building').value,
            unit_number: document.getElementById('u_number').value, floor_number: document.getElementById('u_floor').value,
            flat_type: document.getElementById('u_type').value, area_sqft: document.getElementById('u_sqft').value, csrf_token: csrf
        })
    });
    const j = await res.json();
    showToast(j.message || 'Done', res.ok ? 'success' : 'error');
    if (res.ok) loadBuildings();
}
</script>
</body>
</html>
