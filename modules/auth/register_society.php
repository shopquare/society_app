<?php
// modules/auth/register_society.php
require_once __DIR__ . '/../../config/db.php';
$csrfToken = generateCsrfToken();
$base = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="app-base" content="<?php echo $base; ?>">
    <title>Register New Society - SecureGate SaaS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script>
    <script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans min-h-screen flex items-center justify-center p-4">
    <div class="max-w-2xl w-full bg-white rounded-2xl shadow-xl border border-slate-200 p-6 sm:p-8 space-y-6">
        <div class="flex justify-between items-center border-b pb-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 font-serif">Onboard Your Housing Society</h1>
                <p class="text-xs text-slate-500 mt-1">Launch an isolated SecureGate workspace with a 14-day free trial.</p>
            </div>
            <a href="<?php echo $base; ?>/login" class="text-xs font-semibold text-brand-900 hover:underline">← Back to Login</a>
        </div>

        <form onsubmit="event.preventDefault(); submitSocietySignup();" class="space-y-4 text-sm">
            <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div><label class="block font-medium mb-1">Society Name</label><input type="text" id="rs-name" required placeholder="Greenfield Heights CHS" class="w-full px-3 py-2 border border-slate-300 rounded-lg"></div>
                <div><label class="block font-medium mb-1">Unique Society Code</label><input type="text" id="rs-code" required placeholder="GRN2026" class="w-full px-3 py-2 border border-slate-300 rounded-lg uppercase"></div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <input type="text" id="rs-city" value="Pune" required placeholder="City" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                <input type="text" id="rs-state" value="Maharashtra" required placeholder="State" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                <input type="text" id="rs-zip" value="411001" required placeholder="PIN Code" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
            </div>
            <div>
                <label class="block font-medium mb-1">Select Subscription Tier</label>
                <select id="rs-plan" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white"></select>
            </div>
            <hr>
            <h2 class="font-bold text-slate-900">Primary Society Administrator Account</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <input type="text" id="rs-fname" required placeholder="First Name" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                <input type="text" id="rs-lname" required placeholder="Last Name" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                <input type="email" id="rs-email" required placeholder="admin@yoursociety.com" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                <input type="text" id="rs-phone" required placeholder="10-Digit Mobile Number" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
            </div>
            <input type="password" id="rs-pass" required minlength="6" placeholder="Admin Password (min 6 chars)" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
            <button type="submit" id="rs-btn" class="w-full py-3 bg-brand-900 hover:bg-brand-800 text-white font-bold rounded-xl cursor-pointer">Launch Society Workspace →</button>
        </form>
        <div id="rs-msg" class="hidden p-4 rounded-xl text-sm"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            const res = await fetch(appPath('/api/register_society.php'));
            const r = await res.json();
            if (res.ok && r.status === 'success') {
                document.getElementById('rs-plan').innerHTML = (r.data.plans || []).map(p => `<option value="${p.id}">${escapeHtml(p.plan_name)} — ₹${parseFloat(p.monthly_price).toFixed(0)}/mo (Up to ${p.max_flats} flats, ${p.max_gates} gates)</option>`).join('');
            }
        });

        async function submitSocietySignup() {
            const btn = document.getElementById('rs-btn');
            btn.disabled = true; btn.innerText = 'Creating Workspace...';
            const res = await fetch(appPath('/api/register_society.php'), {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    csrf_token: document.getElementById('csrf_token').value,
                    society_name: document.getElementById('rs-name').value.trim(),
                    society_code: document.getElementById('rs-code').value.trim(),
                    city: document.getElementById('rs-city').value.trim(),
                    state: document.getElementById('rs-state').value.trim(),
                    zip_code: document.getElementById('rs-zip').value.trim(),
                    plan_id: document.getElementById('rs-plan').value,
                    first_name: document.getElementById('rs-fname').value.trim(),
                    last_name: document.getElementById('rs-lname').value.trim(),
                    email: document.getElementById('rs-email').value.trim(),
                    phone_number: document.getElementById('rs-phone').value.trim(),
                    password: document.getElementById('rs-pass').value
                })
            });
            const r = await res.json();
            const box = document.getElementById('rs-msg');
            box.classList.remove('hidden', 'bg-emerald-50', 'text-emerald-800', 'bg-rose-50', 'text-rose-800');
            if (res.ok) {
                box.classList.add('bg-emerald-50', 'text-emerald-800');
                box.innerText = r.message || 'Society onboarded! You can now sign in at /login.';
            } else {
                box.classList.add('bg-rose-50', 'text-rose-800');
                box.innerText = r.message || 'Registration failed.';
            }
            btn.disabled = false; btn.innerText = 'Launch Society Workspace →';
        }
    </script>
</body>
</html>
